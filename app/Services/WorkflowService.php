<?php

namespace App\Services;

use App\Enums\AmendmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Track;
use App\Enums\VisitStatus;
use App\Models\ContractAmendment;
use App\Models\PaymentTerm;
use App\Models\Payment;
use App\Models\Procurement;
use App\Models\StageActivity;
use App\Models\StageVisit;
use App\Models\TermPlanHistory;
use App\Models\VendorContract;
use App\Models\WorkflowTemplate;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin alur Procfolio.
 * - Progres 1 (track pengadaan): approval hingga SP2K/PO rilis. Setelah kontrak rilis, alur terkunci kecuali melalui amandemen.
 * - Progres 2 (track pasca): satu alur per tahap pembayaran: Pelaksanaan Pekerjaan → BA Penutupan → Proses GR → Invoicing → Verifikasi Invoice → Antri Bayar → Dibayar.
 * Semua perubahan status dicatat manual oleh pengguna; durasi dihitung otomatis.
 */
class WorkflowService
{
    public function __construct(private AuditLogger $audit) {}

    /** @return array<int, array<string, mixed>> */
    public function stages(Procurement $p, Track $track, ?int $amendmentId = null): array
    {
        if ($track === Track::Pasca) {
            return WorkflowTemplate::stagesFor('Pasca');
        }
        $list = $p->workflow ?? [];
        if ($amendmentId) {
            $keys = ContractAmendment::query()->find($amendmentId)?->stages ?? [];
            $list = array_values(array_filter($list, fn ($s) => in_array($s['key'], $keys, true)));
        }

        return $list;
    }

    /**
     * Rute default menurut metode pengadaan. Setelah Anggaran, semua pengadaan masuk ke Manager Pengadaan.
     * - Pengadaan Langsung: Manager Pengadaan -> SPV Lakdan -> Staff Lakdan.
     * - Metode lain: Manager Pengadaan -> SPV Rendan -> Staff Rendan -> SPV Lakdan -> Staff Lakdan.
     * Hanya tahap Rendan yang mengikuti metode; tahap lain tidak diubah.
     *
     * @param  array<int, array<string, mixed>>  $workflow
     * @return array<int, array<string, mixed>>
     */
    public static function applyRouting(array $workflow, ?string $procMethod): array
    {
        $direct = $procMethod === 'Pengadaan Langsung';

        return array_map(function ($s) use ($direct) {
            if (str_starts_with($s['key'], 'rendan_')) {
                $s['enabled'] = ! $direct;
            }

            return $s;
        }, $workflow);
    }

    /**
     * Pastikan tahap yang wajib selalu aktif (Manager Pengadaan), apa pun isian pengguna.
     *
     * @param  array<int, array<string, mixed>>  $workflow
     * @return array<int, array<string, mixed>>
     */
    public static function enforceRequired(array $workflow): array
    {
        return array_map(function ($s) {
            if ($s['key'] === 'pengadaan_mgr') {
                $s['enabled'] = true;
                $s['optional'] = false;
            }

            return $s;
        }, $workflow);
    }

    /** Kelompok tahap (Pengaju, Anggaran, ...); alur lama tanpa kelompok memakai kunci tahap. */
    public static function groupOf(array $stage): string
    {
        return $stage['group'] ?? match (true) {
            str_starts_with($stage['key'], 'pengaju') => 'Pengaju',
            str_starts_with($stage['key'], 'anggaran') => 'Anggaran',
            default => $stage['name'],
        };
    }

    private function hasDone(Procurement $p, string $key, ?int $termId, ?int $amendmentId): bool
    {
        return StageVisit::query()->where('procurement_id', $p->id)->where('stage_key', $key)
            ->where('term_id', $termId)->where('amendment_id', $amendmentId)
            ->where('status', VisitStatus::Done->value)->exists();
    }

    /** @param array<int, array<string, mixed>> $list */
    public static function index(array $list, string $key): int
    {
        foreach ($list as $i => $s) {
            if ($s['key'] === $key) {
                return $i;
            }
        }

        return -1;
    }

    /** @param array<int, array<string, mixed>> $list */
    public static function next(array $list, string $key): ?array
    {
        foreach (array_slice($list, self::index($list, $key) + 1) as $s) {
            if ($s['enabled'] ?? true) {
                return $s;
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $list @return array<int, array<string, mixed>> */
    public static function later(array $list, string $key): array
    {
        return array_values(array_filter(array_slice($list, self::index($list, $key) + 1), fn ($s) => $s['enabled'] ?? true));
    }

    /** @param array<int, array<string, mixed>> $list @return array<int, array<string, mixed>> */
    public static function earlier(array $list, string $key): array
    {
        return array_values(array_filter(array_slice($list, 0, max(0, self::index($list, $key))), fn ($s) => $s['enabled'] ?? true));
    }

    public function startVisit(Procurement $p, string $key, string $date, ?int $picId, ?string $activity, string $note, Track $track, ?int $termId = null, ?int $amendmentId = null): StageVisit
    {
        $list = $this->stages($p, $track, $amendmentId);
        $def = $list[self::index($list, $key)] ?? null;
        if (! $def) {
            throw ValidationException::withMessages(['target' => "Tahap {$key} tidak dikenal."]);
        }
        $act = $activity ?: ($def['default_activities'][0] ?? 'Review Dokumen');
        $cycle = StageVisit::query()->where('procurement_id', $p->id)->where('stage_key', $key)
            ->where('term_id', $termId)->where('amendment_id', $amendmentId)
            ->where('status', '!=', VisitStatus::Skipped->value)->count() + 1;
        $visit = StageVisit::query()->create([
            'procurement_id' => $p->id, 'track' => $track->value, 'term_id' => $termId, 'amendment_id' => $amendmentId,
            'stage_key' => $key, 'stage_name' => $def['name'], 'cycle' => $cycle, 'entered_at' => $date,
            'status' => VisitStatus::Running->value, 'pic_id' => $picId ?: ($def['pic_id'] ?? null), 'activity' => $act,
            'note' => $note, 'last_update_at' => $date,
        ]);
        $this->activity($visit, $date, $act, $note ?: "Masuk tahap {$def['name']}".($cycle > 1 ? " (siklus {$cycle}, durasi dihitung ulang)" : '').'.', 'masuk');

        return $visit;
    }

    public function closeVisit(StageVisit $v, string $date, VisitStatus $status, ?string $activity = null, ?string $reason = null): void
    {
        $v->fill(['status' => $status->value, 'completed_at' => $date, 'last_update_at' => $date]);
        if ($activity) {
            $v->activity = $activity;
        }
        if ($reason) {
            $v->return_reason = $reason;
        }
        $v->save();
        $note = match ($status) {
            VisitStatus::Done => "Tahap {$v->stage_name} selesai.",
            VisitStatus::Returned => "Dikembalikan: {$reason}",
            default => "Tahap {$v->stage_name} dilewati".($reason ? ": {$reason}" : '').'.',
        };
        $kind = match ($status) { VisitStatus::Done => 'selesai', VisitStatus::Returned => 'kembali', default => 'lewati' };
        $this->activity($v, $date, $activity ?: (string) $v->activity, $note, $kind);
    }

    /** Catat tahap di antara dua tahap sebagai Dilewati. */
    public function markSkipped(Procurement $p, Track $track, string $fromKey, string $toKey, string $date, string $reason, ?int $termId = null, ?int $amendmentId = null): void
    {
        $list = $this->stages($p, $track, $amendmentId);
        $from = self::index($list, $fromKey);
        $to = self::index($list, $toKey);
        foreach (array_slice($list, $from + 1, max(0, $to - $from - 1)) as $s) {
            // Tahap nonaktif tidak dicatat; tahap yang pernah selesai (mis. Anggaran setelah dikembalikan) tetap Selesai.
            if (! ($s['enabled'] ?? true) || $this->hasDone($p, $s['key'], $termId, $amendmentId)) {
                continue;
            }
            $v = StageVisit::query()->create([
                'procurement_id' => $p->id, 'track' => $track->value, 'term_id' => $termId, 'amendment_id' => $amendmentId,
                'stage_key' => $s['key'], 'stage_name' => $s['name'], 'cycle' => 1, 'entered_at' => $date, 'completed_at' => $date,
                'status' => VisitStatus::Skipped->value, 'activity' => 'Lainnya',
                'note' => $reason, 'last_update_at' => $date,
            ]);
            $this->activity($v, $date, 'Lainnya', "Tahap {$s['name']} dilewati. {$v->note}", 'lewati');
        }
    }

    /** Registrasi selesai: tahap aktif pertama dimulai pada tanggal registrasi. */
    public function register(Procurement $p): void
    {
        if (StageVisit::query()->where('procurement_id', $p->id)->exists()) {
            return;
        }
        $first = collect($p->workflow)->first(fn ($s) => $s['enabled'] ?? true);
        if ($first) {
            $this->startVisit($p, $first['key'], Cal::iso($p->registered_at), $p->pic_pengaju_id, 'Review Dokumen', 'Registrasi pengadaan, monitoring Pengaju dimulai.', Track::Pengadaan);
        }
        $this->audit->log($p->id, 'Pengadaan', $p->code, 'Registrasi pengadaan', 'Status', 'Draft', 'Terdaftar');
    }

    /**
     * Update Proses (simpan update, selesaikan, kembalikan, pindah, lewati) untuk Progres 1 atau satu tahap Progres 2.
     *
     * @param  array<string, mixed>  $d
     * @return array{message:string, next:?string, amendment_id:?int}
     */
    public function updateProcess(Procurement $p, array $d): array
    {
        return DB::transaction(function () use ($p, $d) {
            $track = Track::from($d['track']);
            $mode = $d['mode'];
            $termId = $track === Track::Pasca ? (int) $d['term_id'] : null;
            $v = StageVisit::query()->where('procurement_id', $p->id)->where('track', $track->value)
                ->where('status', VisitStatus::Running->value)
                ->when($termId, fn ($q) => $q->where('term_id', $termId))
                ->latest('id')->first();
            if (! $v) {
                throw ValidationException::withMessages(['mode' => $track === Track::Pengadaan && $p->contract
                    ? 'Proses Pengadaan terkunci setelah SP2K/PO rilis. Perubahan hanya melalui amandemen.'
                    : 'Tidak ada tahap aktif.']);
            }
            $amendmentId = $v->amendment_id;
            $list = $this->stages($p, $track, $amendmentId);
            $date = $d['date'];
            $entered = $d['entered_at'] ?? null;
            $note = trim((string) ($d['note'] ?? ''));
            $activity = $d['activity'] ?? $v->activity;
            $target = $d['target'] ?? null;
            $targetPic = $d['target_pic_id'] ?? null;
            // Revisi Minor: setelah pengaju selesai, langsung kembali ke tahap yang mengembalikan.
            $resume = $v->resume_key ? ($list[self::index($list, $v->resume_key)] ?? null) : null;
            $next = $resume ?: self::next($list, $v->stage_key);
            $first = collect($list)->first(fn ($s) => $s['enabled'] ?? true);
            $term = $termId ? PaymentTerm::query()->find($termId) : null;
            $subject = $term ? "Tahap {$term->seq}" : ($amendmentId ? 'Amandemen' : 'Proses Pengadaan');

            $errors = [];
            $start = $entered ?: Cal::iso($v->entered_at);
            if ($date < $start && ! ($track === Track::Pasca && $v->stage_key === 'pelaksanaan')) {
                $errors['date'] = 'Tanggal kegiatan tidak boleh sebelum tanggal masuk tahap ('.Cal::iso($v->entered_at).').';
            }
            if ($track === Track::Pengadaan && in_array($mode, ['skip', 'move', 'return'], true)) {
                $errors['mode'] = 'Pada Proses Pengadaan gunakan Revisi Minor atau Revisi Mayor untuk mengembalikan; tahap tidak dapat dilewati.';
            }
            if (in_array($mode, ['rev_minor', 'rev_major'], true)) {
                if ($track !== Track::Pengadaan) {
                    $errors['mode'] = 'Revisi Minor/Mayor hanya untuk Proses Pengadaan.';
                } elseif (! $first || $first['key'] === $v->stage_key) {
                    $errors['mode'] = 'Proses sudah berada di tahap pengaju.';
                }
                if (mb_strlen($note) < 5) {
                    $errors['note'] = 'Alasan revisi wajib diisi.';
                }
            }
            if (in_array($mode, ['return', 'skip'], true) && mb_strlen($note) < 5) {
                $errors['note'] = $mode === 'return' ? 'Alasan pengembalian wajib diisi.' : 'Alasan melewati tahap wajib diisi.';
            }
            if (in_array($mode, ['return', 'move'], true) && ! $target) {
                $errors['target'] = 'Pilih tahap tujuan.';
            }
            $current = $list[self::index($list, $v->stage_key)] ?? ['key' => $v->stage_key, 'name' => $v->stage_name];
            if ($track === Track::Pengadaan && $mode === 'skip' && self::groupOf($current) === 'Anggaran') {
                $errors['mode'] = 'Tahap Anggaran tidak dapat dilewati.';
            }
            if ($track === Track::Pengadaan && $mode === 'move' && $target) {
                $between = array_slice($list, self::index($list, $v->stage_key) + 1, max(0, self::index($list, $target) - self::index($list, $v->stage_key) - 1));
                foreach ($between as $s) {
                    if (($s['enabled'] ?? true) && self::groupOf($s) === 'Anggaran' && ! $this->hasDone($p, $s['key'], null, $amendmentId)) {
                        $errors['target'] = "Tahap {$s['name']} belum pernah disetujui dan tidak dapat dilompati.";
                        break;
                    }
                }
            }
            if ($track === Track::Pasca && $v->stage_key === 'gr' && $mode === 'complete') {
                foreach (['gr_number' => 'Nomor GR', 'gr_type' => 'Jenis GR', 'gr_amount' => 'Nominal GR', 'gr_link' => 'Tautan dokumen GR'] as $k => $label) {
                    if (blank($d[$k] ?? null)) {
                        $errors[$k] = "{$label} wajib diisi untuk menyelesaikan Proses GR.";
                    }
                }
                if (! empty($d['gr_number']) && \App\Models\GoodsReceipt::query()->where('number', $d['gr_number'])->where('payment_term_id', '!=', $termId)->exists()) {
                    $errors['gr_number'] = 'Nomor GR sudah dipakai tahap lain.';
                }
            }
            if ($track === Track::Pasca && $v->stage_key === 'invoice' && $mode === 'complete' && ! empty($d['invoice_number'])
                && \App\Models\Invoice::query()->where('number', $d['invoice_number'])->where('payment_term_id', '!=', $termId)->exists()) {
                $errors['invoice_number'] = 'Nomor invoice sudah dipakai tahap lain.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $p->touch();
            if ($entered && $entered !== Cal::iso($v->entered_at)) {
                $this->audit->log($p->id, 'Tahap', $v->id, 'Koreksi tanggal masuk tahap', "Tanggal masuk {$v->stage_name}", Cal::iso($v->entered_at), $entered);
                $v->entered_at = $entered;
            }
            if (array_key_exists('pic_id', $d) && (int) ($d['pic_id'] ?? 0) !== (int) $v->pic_id) {
                $this->audit->log($p->id, 'Tahap', $v->id, 'Ganti PIC', "PIC {$v->stage_name}", $v->pic?->name, optional(\App\Models\Pic::query()->find($d['pic_id']))->name);
                $v->pic_id = $d['pic_id'] ?: null;
            }
            $v->save();

            if ($term && $v->stage_key === 'invoice' && (array_key_exists('bucket_code', $d) || array_key_exists('bucket_number', $d) || array_key_exists('bucket_link', $d))) {
                $bucket = ['bucket_code' => $d['bucket_code'] ?? null, 'bucket_number' => $d['bucket_number'] ?? null, 'bucket_link' => $d['bucket_link'] ?? null];
                if ($bucket['bucket_code'] !== $term->bucket_code || $bucket['bucket_number'] !== $term->bucket_number || $bucket['bucket_link'] !== $term->bucket_link) {
                    $this->audit->log($p->id, 'Tahap Pembayaran', $term->id, 'Bucket invoicing', "Bucket Tahap {$term->seq}", trim(($term->bucket_code ?? '').' '.($term->bucket_number ?? '')) ?: null, trim(($bucket['bucket_code'] ?? '').' '.($bucket['bucket_number'] ?? '')) ?: null);
                    $term->update($bucket);
                }
            }
            $msg = '';
            $after = null;
            switch ($mode) {
                case 'update':
                    if ($activity !== $v->activity) {
                        $this->audit->log($p->id, 'Tahap', $v->id, 'Update aktivitas', "Aktivitas {$subject}", $v->activity, $activity);
                    }
                    $v->fill(['activity' => $activity, 'last_update_at' => $date, 'note' => $note ?: $v->note])->save();
                    $this->activity($v, $date, $activity, $note ?: "Aktivitas diperbarui: {$activity}.", 'aktivitas');
                    $msg = "Update {$v->stage_name} disimpan.";
                    break;

                case 'complete':
                    if ($term && $v->stage_key === 'gr') {
                        $gr = $term->goodsReceipt()->updateOrCreate([], [
                            'status' => 'GR Terbit', 'number' => trim($d['gr_number']), 'gr_type' => $d['gr_type'], 'amount' => $d['gr_amount'],
                            'link' => trim($d['gr_link']), 'gr_date' => $date, 'submitted_at' => $term->goodsReceipt?->submitted_at ?? $date, 'pic_id' => $v->pic_id,
                        ]);
                        $gr->beritaAcara()->syncWithoutDetaching($term->beritaAcara()->pluck('berita_acara.id')->all());
                        $this->audit->log($p->id, 'Goods Receipt', $gr->id, 'GR terbit', "GR Tahap {$term->seq}", null, "{$gr->gr_type} {$gr->number} · ".number_format((float) $gr->amount, 0, ',', '.'));
                    }
                    if ($term && $v->stage_key === 'invoice' && ! empty($d['invoice_number'])) {
                        $inv = $term->invoice()->updateOrCreate([], [
                            'number' => trim($d['invoice_number']), 'date' => $d['invoice_date'] ?? $date,
                            'amount' => $d['invoice_amount'] ?? $term->amount, 'status' => 'Submit Invoice', 'pic_id' => $v->pic_id,
                        ]);
                        $this->audit->log($p->id, 'Invoice', $inv->id, 'Catat invoice', "Invoice Tahap {$term->seq}", null, $inv->number);
                    }
                    $this->closeVisit($v, $date, VisitStatus::Done, $activity);
                    $finalLabel = $track === Track::Pasca ? 'Dibayar' : ($amendmentId ? 'Amandemen Rilis' : ($p->method === 'SPP' ? 'SP2K Rilis' : 'PO Rilis'));
                    $this->audit->log($p->id, 'Tahap', $v->id, 'Selesaikan tahap', $subject, $v->stage_name, $next['name'] ?? $finalLabel, $note ?: null);
                    if ($next) {
                        $this->markSkipped($p, $track, $v->stage_key, $next['key'], $date, '', $termId, $amendmentId);
                        $this->startVisit($p, $next['key'], $date, $targetPic ?: ($next['pic_id'] ?? null), null, '', $track, $termId, $amendmentId);
                        if ($term) {
                            $this->syncTermInvoice($p, $term, $next['key'], $date);
                        }
                        $msg = "{$v->stage_name} selesai. {$next['name']} dimulai.";
                    } elseif ($term) {
                        $this->syncTermInvoice($p, $term, 'dibayar', $date);
                        $allPaid = PaymentTerm::query()->where('procurement_id', $p->id)->whereNull('paid_at')->doesntExist();
                        $msg = "Tahap {$term->seq} dibayar.".($allPaid ? ' Seluruh tahap dibayar; pengadaan Selesai.' : '');
                    } elseif ($amendmentId) {
                        $msg = 'Tahap amandemen selesai. Catat nomor dan isi amandemen.';
                        $after = 'amendment-release';
                    } else {
                        $msg = "{$v->stage_name} selesai. Lanjutkan rilis ".($p->method === 'SPP' ? 'SP2K' : 'PO').' beserta tahapan pembayaran.';
                        $after = 'contract';
                    }
                    break;

                case 'return':
                    $this->closeVisit($v, $date, VisitStatus::Returned, null, $note);
                    $this->startVisit($p, $target, $date, $targetPic, 'Klarifikasi', "Dikembalikan dari {$v->stage_name}: {$note}", $track, $termId, $amendmentId);
                    if ($term && $target === 'invoice') {
                        $this->syncTermInvoice($p, $term, 'revisi', $date);
                    }
                    $targetName = $list[self::index($list, $target)]['name'] ?? $target;
                    $this->audit->log($p->id, 'Tahap', $v->id, 'Kembalikan ke tahap', $subject, $v->stage_name, $targetName, $note);
                    $msg = "Dikembalikan ke {$targetName}. Siklus baru dibuat.";
                    break;

                case 'move':
                    $this->closeVisit($v, $date, VisitStatus::Done, $activity);
                    $this->markSkipped($p, $track, $v->stage_key, $target, $date, "Dilompati saat pindah tahap dari {$v->stage_name}.", $termId, $amendmentId);
                    $this->startVisit($p, $target, $date, $targetPic, null, '', $track, $termId, $amendmentId);
                    if ($term) {
                        $this->syncTermInvoice($p, $term, $target, $date);
                    }
                    $targetName = $list[self::index($list, $target)]['name'] ?? $target;
                    $this->audit->log($p->id, 'Tahap', $v->id, 'Pindah tahap', $subject, $v->stage_name, $targetName, $note ?: null);
                    $msg = "Pindah ke {$targetName}.";
                    break;

                case 'rev_minor':
                case 'rev_major':
                    $minor = $mode === 'rev_minor';
                    $label = $minor ? 'Revisi Minor' : 'Revisi Mayor';
                    $this->closeVisit($v, $date, VisitStatus::Returned, null, $note);
                    $v->update(['return_type' => $minor ? 'minor' : 'mayor']);
                    $pic = $targetPic ?: (str_starts_with($first['key'], 'pengaju') ? $p->pic_pengaju_id : ($first['pic_id'] ?? null));
                    $back = $this->startVisit($p, $first['key'], $date, $pic, 'Klarifikasi', "{$label} dari {$v->stage_name}: {$note}", $track, null, $amendmentId);
                    if ($minor) {
                        $back->update(['resume_key' => $v->stage_key]);
                    }
                    $cycle = StageVisit::query()->where('procurement_id', $p->id)->where('track', $track->value)
                        ->where('amendment_id', $amendmentId)->where('return_type', 'mayor')->count() + 1;
                    $this->audit->log($p->id, 'Tahap', $v->id, $label, $subject, $v->stage_name, $first['name'], $note);
                    $msg = $minor
                        ? "Revisi Minor: dikembalikan ke {$first['name']}. Setelah diperbaiki, proses langsung kembali ke {$v->stage_name}."
                        : "Revisi Mayor: dikembalikan ke {$first['name']}. Approval diulang dari awal (siklus {$cycle}).";
                    break;

                case 'skip':
                    $this->closeVisit($v, $date, VisitStatus::Skipped, null, $note);
                    $this->audit->log($p->id, 'Tahap', $v->id, 'Lewati tahap', $subject, $v->stage_name, $next['name'] ?? 'Selesai', $note);
                    if ($next) {
                        $this->startVisit($p, $next['key'], $date, $targetPic ?: ($next['pic_id'] ?? null), null, '', $track, $termId, $amendmentId);
                        if ($term) {
                            $this->syncTermInvoice($p, $term, $next['key'], $date);
                        }
                    } elseif ($term) {
                        $this->syncTermInvoice($p, $term, 'dibayar', $date);
                    }
                    $msg = "{$v->stage_name} dilewati".($next ? ", lanjut ke {$next['name']}" : '').'.';
                    break;

                default:
                    throw ValidationException::withMessages(['mode' => 'Jenis pembaruan tidak dikenal.']);
            }

            return ['message' => $msg, 'next' => $after, 'amendment_id' => $amendmentId];
        });
    }

    /** Buka kembali tahap Proses Pengadaan; hanya sebelum SP2K/PO rilis. */
    public function reopen(Procurement $p, string $key): void
    {
        if ($p->contract()->exists()) {
            throw ValidationException::withMessages(['target' => 'Proses Pengadaan terkunci setelah SP2K/PO rilis. Ajukan amandemen.']);
        }
        $this->startVisit($p, $key, Cal::today(), null, 'Klarifikasi', 'Tahap dibuka kembali.', Track::Pengadaan);
        $this->audit->log($p->id, 'Tahap', $p->code, 'Buka kembali tahap', null, null, $key);
    }

    /**
     * Jadwal tahap: mulai = kick off (tahap 1) atau sehari setelah rencana selesai tahap sebelumnya.
     *
     * @return array<int, array{start:string,end:?string,days:?int}>
     */
    public function termPlans(Procurement $p): array
    {
        $vc = $p->relationLoaded('contract') ? $p->contract : $p->contract()->first();
        if (! $vc?->kickoff_date) {
            return [];
        }
        $terms = $p->relationLoaded('terms') ? $p->terms : $p->terms()->get();
        $out = [];
        $start = Cal::iso($vc->kickoff_date);
        foreach ($terms->sortBy('seq') as $t) {
            $end = Cal::iso($t->planned_end);
            $out[$t->id] = ['start' => $start, 'end' => $end, 'days' => $end ? Cal::daysBetween($start, $end) + 1 : null];
            if ($end) {
                $start = Cal::addDays($end, 1);
            }
        }

        return $out;
    }

    /** Bentuk alur Progres 2 untuk setiap tahap pembayaran yang belum punya alur. */
    public function ensureTermFlows(Procurement $p): int
    {
        $p->unsetRelation('terms');
        $p->unsetRelation('contract');
        $plans = $this->termPlans($p);
        $made = 0;
        foreach ($p->terms()->get() as $t) {
            $plan = $plans[$t->id] ?? null;
            if (! $plan) {
                continue;
            }
            $existing = StageVisit::query()->where('term_id', $t->id);
            if ($t->paid_at || $existing->exists()) {
                // geser tanggal mulai alur yang masih terjadwal bila rencana berubah
                $sched = StageVisit::query()->where('term_id', $t->id)->where('stage_key', 'pelaksanaan')->where('status', VisitStatus::Running->value)
                    ->whereDate('entered_at', '>', Cal::today())->first();
                if ($sched && $plan['start'] > Cal::today() && $sched->activities()->count() <= 1) {
                    $sched->update(['entered_at' => $plan['start'], 'last_update_at' => $plan['start']]);
                }

                continue;
            }
            $this->startVisit($p, 'pelaksanaan', $plan['start'], $p->pic_pengaju_id, 'Pekerjaan Berjalan', "Tahap {$t->seq} dijadwalkan mulai {$plan['start']}, rencana selesai {$plan['end']}.", Track::Pasca, $t->id);
            $made++;
        }

        return $made;
    }

    /** Status invoice tahap mengikuti tahap Progres 2; 'dibayar' juga mencatat pembayaran. */
    public function syncTermInvoice(Procurement $p, PaymentTerm $t, string $stageOrFlag, string $date): void
    {
        if ($stageOrFlag === 'dibayar') {
            $t->update(['paid_at' => $date]);
        }
        $inv = $t->invoice()->first();
        if (! $inv || $inv->status === InvoiceStatus::Paid->value) {
            return;
        }
        $target = match ($stageOrFlag) {
            'dibayar' => InvoiceStatus::Paid,
            'revisi' => InvoiceStatus::Revision,
            default => InvoiceStatus::forStage($stageOrFlag),
        };
        if (! $target || $target->value === $inv->status) {
            return;
        }
        $this->audit->log($p->id, 'Invoice', $inv->id, 'Status invoice mengikuti Pasca Pengadaan', "Status invoice Tahap {$t->seq}", $inv->status, $target->value);
        $inv->update(['status' => $target->value]);
        if ($target === InvoiceStatus::Paid && ! $inv->payment()->exists()) {
            Payment::query()->create(['invoice_id' => $inv->id, 'date' => $date, 'amount' => $inv->amount]);
        }
    }

    /**
     * Rilis SP2K/PO: kontrak + tahapan pembayaran; Progres 1 terkunci, Progres 2 dibentuk per tahap.
     *
     * @param  array<string, mixed>  $d
     */
    public function releaseContract(Procurement $p, array $d): VendorContract
    {
        return DB::transaction(function () use ($p, $d) {
            $terms = collect($d['terms'])->values();
            $lastEnd = $terms->pluck('planned_end')->sort()->last();
            $vc = VendorContract::query()->create([
                'procurement_id' => $p->id, 'kind' => $d['kind'], 'number' => trim($d['number']), 'title' => $d['title'],
                'vendor_id' => $d['vendor_id'], 'date' => $d['date'], 'kickoff_date' => $d['kickoff_date'], 'end_date' => $lastEnd,
                'value_excl' => $d['value_excl'], 'value_incl' => $d['value_incl'], 'scope' => $d['scope'] ?? null, 'link' => $d['link'] ?? null,
            ]);
            $p->update(['vendor_id' => $d['vendor_id']]);
            $p->candidateVendors()->syncWithoutDetaching([$d['vendor_id']]);

            $running = StageVisit::query()->where('procurement_id', $p->id)->where('track', Track::Pengadaan->value)
                ->whereNull('amendment_id')->where('status', VisitStatus::Running->value)->latest('id')->first();
            if ($running && ($d['close_stage'] ?? true)) {
                $closeDate = $d['date'] >= Cal::iso($running->entered_at) ? $d['date'] : Cal::today();
                $this->closeVisit($running, $closeDate, VisitStatus::Done, $p->method === 'SPP' ? 'SP2K Rilis' : 'PO Rilis');
            }

            foreach ($terms as $i => $t) {
                PaymentTerm::query()->create([
                    'procurement_id' => $p->id, 'seq' => $i + 1, 'name' => trim($t['name']), 'kind' => 'Persentase',
                    'percent' => $t['percent'], 'amount' => round(((float) $d['value_incl']) * ((float) $t['percent']) / 100, 2),
                    'planned_end' => $t['planned_end'],
                ]);
            }
            $made = $this->ensureTermFlows($p);
            $this->audit->log($p->id, 'Kontrak Vendor', $vc->id, "{$vc->kind} dirilis", null, null,
                "{$vc->number} · nilai ".number_format((float) $vc->value_incl, 0, ',', '.').' · '.$terms->count().' tahap pembayaran',
                $made ? "{$made} alur Pasca Pengadaan dibentuk" : null);

            return $vc;
        });
    }

    /**
     * Ajukan amandemen: Progres 1 dibuka kembali khusus untuk alur amandemen.
     *
     * @param  array<string, mixed>  $d
     */
    public function startAmendment(Procurement $p, array $d): ContractAmendment
    {
        return DB::transaction(function () use ($p, $d) {
            $vc = $p->contract()->firstOrFail();
            if ($vc->amendments()->where('status', AmendmentStatus::Running->value)->exists()) {
                throw ValidationException::withMessages(['reason' => 'Masih ada amandemen yang sedang berjalan.']);
            }
            $order = array_column($p->workflow, 'key');
            $stages = array_values(array_filter($order, fn ($k) => in_array($k, $d['stages'], true)));
            $am = $vc->amendments()->create([
                'seq' => $vc->amendments()->count() + 1, 'status' => AmendmentStatus::Running->value, 'reason' => trim($d['reason']),
                'requested_at' => $d['date'], 'stages' => $stages, 'change_types' => $d['change_types'], 'proposal' => $d['proposal'] ?? null,
            ]);
            if ($stages) {
                $this->startVisit($p, $stages[0], $d['date'], str_starts_with($stages[0], 'pengaju') ? $p->pic_pengaju_id : null, 'Review Dokumen', "Amandemen {$am->seq} diajukan: {$am->reason}", Track::Pengadaan, null, $am->id);
            }
            $this->audit->log($p->id, 'Amandemen', $am->id, 'Ajukan amandemen', "{$vc->kind} {$vc->number}", null, "Amandemen {$am->seq} · ".implode(', ', $d['change_types']), $am->reason);
            $p->touch();

            return $am;
        });
    }

    /**
     * Rilis amandemen: catat nomor & perubahan, terapkan ke kontrak; Progres 1 kembali terkunci.
     *
     * @param  array<string, mixed>  $d
     */
    public function releaseAmendment(Procurement $p, ContractAmendment $am, array $d): void
    {
        DB::transaction(function () use ($p, $am, $d) {
            $vc = $am->contract;
            $types = $d['change_types'];
            $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
            $tgl = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('d M Y') : '-';
            // [kolom kontrak, label, nilai lama, nilai baru, format tampilan]
            $fields = [];
            if (in_array('Nilai', $types, true)) {
                $fields[] = ['value_excl', 'Nilai sebelum PPN', (float) $vc->value_excl, (float) $d['value_excl'], $rp];
                $fields[] = ['value_incl', 'Nilai setelah PPN', (float) $vc->value_incl, (float) $d['value_incl'], $rp];
            }
            if (in_array('Jangka Waktu', $types, true)) {
                $fields[] = ['end_date', 'Rencana selesai pekerjaan', Cal::iso($vc->end_date), $d['end_date'], $tgl];
            }
            if (in_array('Ruang Lingkup', $types, true)) {
                $fields[] = ['scope', 'Ruang lingkup', (string) $vc->scope, (string) $d['scope'], fn ($v) => $v ?: '-'];
            }
            $changed = array_values(array_filter($fields, fn ($f) => (string) $f[2] !== (string) $f[3]));
            $typesWithField = array_intersect($types, ['Nilai', 'Jangka Waktu', 'Ruang Lingkup']);
            if ($typesWithField && ! $changed) {
                throw ValidationException::withMessages(['change_types' => 'Belum ada nilai yang berubah. Isi nilai baru sesuai jenis perubahan, atau hapus jenis perubahan yang tidak dipakai.']);
            }

            $before = $after = [];
            foreach ($changed as [$col, $label, $old, $new, $fmt]) {
                $before[$label] = $fmt($old);
                $after[$label] = $fmt($new);
                $vc->{$col} = $new;
            }
            if (in_array('Termin', $types, true)) {
                TermPlanHistory::query()->create([
                    'procurement_id' => $p->id, 'contract_amendment_id' => $am->id,
                    'reason' => 'Snapshot sebelum perubahan tahapan oleh amandemen '.$d['number'],
                    'snapshot' => $p->terms()->get(['seq', 'name', 'kind', 'percent', 'amount', 'planned_end'])->toArray(),
                ]);
            }
            $am->update([
                'status' => AmendmentStatus::Released->value, 'number' => trim($d['number']), 'date' => $d['date'], 'change_types' => $types,
                'description' => $d['description'], 'before' => $before, 'after' => $after, 'link' => $d['link'] ?? null,
            ]);
            $vc->save();
            StageVisit::query()->where('amendment_id', $am->id)->where('status', VisitStatus::Running->value)->get()
                ->each(fn (StageVisit $v) => $this->closeVisit($v, $d['date'], VisitStatus::Done, 'Amandemen Rilis'));
            $this->audit->log($p->id, 'Amandemen', $am->id, "Amandemen {$am->seq} rilis", 'Nomor amandemen', null, $am->number, $d['description']);
            foreach ($changed as [, $label, $old, $new, $fmt]) {
                $this->audit->log($p->id, 'Amandemen', $am->id, "Perubahan kontrak (Amandemen {$am->seq})", $label, $fmt($old), $fmt($new), $am->number);
            }
            $this->ensureTermFlows($p);
            $p->touch();
        });
    }

    private function activity(StageVisit $v, string $date, string $category, string $note, string $kind): void
    {
        StageActivity::query()->create([
            'procurement_id' => $v->procurement_id, 'stage_visit_id' => $v->id, 'date' => $date, 'category' => $category,
            'pic_id' => $v->pic_id, 'note' => $note, 'kind' => $kind, 'user_id' => auth()->id(),
        ]);
    }
}
