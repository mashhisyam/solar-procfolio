<?php

namespace App\Services;

use App\Enums\Track;
use App\Enums\VisitStatus;
use App\Models\AppSetting;
use App\Models\BeritaAcara;
use App\Models\PaymentTerm;
use App\Models\Procurement;
use App\Models\StageVisit;
use App\Models\WorkflowTemplate;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Support\Collection;

/**
 * Turunan data pengadaan (dihitung saat dibaca, tidak disimpan):
 * status, durasi tahap, alert, jadwal tahap pembayaran, dan baris tabel monitoring.
 */
class ProcurementInsight
{
    /** Relasi yang perlu dimuat agar perhitungan tidak memicu query berulang. */
    public const WITH = [
        'visits.pic', 'picPengaju', 'documents', 'customerContracts', 'contract.amendments', 'contract.vendor',
        'terms.beritaAcara.rounds.signatories', 'terms.goodsReceipt', 'terms.invoice.payment', 'beritaAcara.rounds.signatories',
    ];

    public function __construct(private Cal $cal, private WorkflowService $workflow) {}

    public function today(): string
    {
        return Cal::today();
    }

    public function durLevel(?int $days): ?string
    {
        if ($days === null) {
            return null;
        }

        return $days >= (int) AppSetting::get('dur_bad') ? 'bad' : ($days >= (int) AppSetting::get('dur_warn') ? 'warn' : 'ok');
    }

    public function visitDays(StageVisit $v): int
    {
        if ($v->status === VisitStatus::Skipped->value) {
            return 0;
        }
        $end = $v->status !== VisitStatus::Running->value && $v->completed_at ? $v->completed_at : $this->today();

        return $this->cal->workdaysBetween($v->entered_at, $end);
    }

    public function scheduled(?StageVisit $v): bool
    {
        return $v !== null && $v->stage_key === 'pelaksanaan' && Cal::iso($v->entered_at) > $this->today();
    }

    public function currentApproval(Procurement $p): ?StageVisit
    {
        return $p->visits->where('track', Track::Pengadaan->value)->where('status', VisitStatus::Running->value)->last();
    }

    /** @return Collection<int, array{t:PaymentTerm, visits:Collection, cur:?StageVisit, plan:?array, paid:bool, scheduled:bool}> */
    public function termFlows(Procurement $p): Collection
    {
        $plans = $this->workflow->termPlans($p);

        return $p->terms->sortBy('seq')->values()->map(function (PaymentTerm $t) use ($p, $plans) {
            $visits = $p->visits->where('term_id', $t->id)->values();
            $cur = $visits->where('status', VisitStatus::Running->value)->last();

            return ['t' => $t, 'visits' => $visits, 'cur' => $cur, 'plan' => $plans[$t->id] ?? null, 'paid' => (bool) $t->paid_at, 'scheduled' => $this->scheduled($cur)];
        });
    }

    public function focusTerm(Procurement $p): ?array
    {
        $open = $this->termFlows($p)->where('paid', false);

        return $open->first(fn ($f) => $f['cur'] && ! $f['scheduled']) ?? $open->first(fn ($f) => $f['cur']) ?? $open->first();
    }

    /** na | noterm | berjalan | selesai */
    public function postState(Procurement $p): string
    {
        if (! $p->contract) {
            return 'na';
        }
        if ($p->terms->isEmpty()) {
            return 'noterm';
        }

        return $p->terms->every(fn ($t) => $t->paid_at) ? 'selesai' : 'berjalan';
    }

    public function status(Procurement $p): string
    {
        if ($p->cancelled_at) {
            return 'Dibatalkan';
        }
        if ($p->is_draft) {
            return 'Draft';
        }
        if ($p->contract && $p->contract->amendments->contains('status', 'Dalam Proses')) {
            return 'Amandemen Berjalan';
        }
        if (! $p->contract) {
            $v = $this->currentApproval($p);

            return ! $v || str_starts_with($v->stage_key, 'pengaju') ? 'Persiapan Dokumen' : 'Dalam Approval';
        }

        return match ($this->postState($p)) {
            'noterm' => 'Kontrak Rilis',
            'selesai' => 'Selesai',
            default => 'Pasca Pengadaan',
        };
    }

    public function isActive(Procurement $p): bool
    {
        return ! in_array($this->status($p), ['Draft', 'Selesai', 'Dibatalkan'], true);
    }

    public function lastUpdate(Procurement $p): string
    {
        $dates = collect([Cal::iso($p->updated_at)]);
        foreach ($p->visits->where('status', VisitStatus::Running->value) as $v) {
            if (Cal::iso($v->entered_at) <= $this->today()) {
                $dates->push(Cal::iso($v->last_update_at));
            }
        }

        return (string) $dates->filter()->max();
    }

    public function processDays(Procurement $p): int
    {
        $end = $this->postState($p) === 'selesai' ? (Cal::iso($p->terms->max('paid_at')) ?? $this->today()) : $this->today();

        return $this->cal->workdaysBetween($p->registered_at, $end);
    }

    /** @return array{status:string, round:int, signed:int, total:int, days:int} */
    public function baInfo(BeritaAcara $ba): array
    {
        $round = $ba->rounds->last();
        $sig = $round?->signatories ?? collect();

        return [
            'status' => $ba->status(),
            'round' => (int) ($round?->seq ?? 0),
            'signed' => $sig->where('status', 'Selesai TTD')->count(),
            'total' => $sig->count(),
            'days' => $round ? $this->cal->workdaysBetween($round->sent_at, $this->today()) : 0,
        ];
    }

    /** @return array<int, string> */
    public function missingDocs(Procurement $p): array
    {
        return $p->documents->where('required', true)->where('available', false)->pluck('doc_type')->values()->all();
    }

    /** @return array<int, array{code:string, tone:string, label:string, target:string}> */
    public function alerts(Procurement $p): array
    {
        if (! $this->isActive($p)) {
            return [];
        }
        $out = [];
        $add = fn (string $code, string $tone, string $label, string $target) => $out[] = compact('code', 'tone', 'label', 'target');

        $v = $this->currentApproval($p);
        if ($v) {
            $days = $this->visitDays($v);
            $lvl = $this->durLevel($days);
            if ($lvl !== 'ok') {
                $add($lvl === 'bad' ? 'dur-bad' : 'dur-warn', $lvl, "{$v->stage_name} {$days} hari", 'proses');
            }
        }
        $hasBasis = $p->customerContracts->isNotEmpty();
        if (! $hasBasis) {
            $add('basis', 'warn', 'Belum ada perjanjian pelanggan', 'ringkasan');
        }
        if ($this->postState($p) === 'noterm') {
            $add('noterm', 'info', 'Tahapan pembayaran belum dibuat', 'pasca');
        }
        foreach ($this->termFlows($p) as $f) {
            if ($f['paid'] || ! $f['cur'] || $f['cur']->stage_key !== 'pelaksanaan' || ! $f['plan'] || ! $f['plan']['end'] || $f['scheduled']) {
                continue;
            }
            $late = Cal::daysBetween($f['plan']['end'], $this->today());
            if ($late > 0) {
                $add('late', 'bad', "Tahap {$f['t']->seq} melewati rencana selesai {$late} hari", 'pasca');
            } elseif ($late >= -5) {
                $add('soon', 'warn', 'Batas pekerjaan tahap '.$f['t']->seq.' '.($late === 0 ? 'hari ini' : (-$late).' hari lagi'), 'pasca');
            }
        }
        $lu = $this->cal->workdaysBetween($this->lastUpdate($p), $this->today());
        $inPost = ! $v && $this->termFlows($p)->contains(fn ($f) => $f['cur'] && ! $f['scheduled'] && ! $f['paid']);
        if ($lu >= (int) AppSetting::get('no_update_days') && ($v || $inPost)) {
            $add('no-update', 'warn', "Tidak diperbarui {$lu} hari kerja", $inPost ? 'pasca' : 'proses');
        }
        if ($missing = $this->missingDocs($p)) {
            $add('doc', 'warn', 'Dokumen wajib belum lengkap: '.implode(', ', $missing), 'dokumen');
        }
        foreach ($p->beritaAcara as $ba) {
            $bi = $this->baInfo($ba);
            if ($bi['status'] === 'Perlu Revisi') {
                $add('ba-revisi', 'warn', "{$ba->type} perlu revisi", 'pasca');
            } elseif (! in_array($bi['status'], ['TTD Selesai', 'Draft'], true) && $bi['days'] >= (int) AppSetting::get('ba_stale_days')) {
                $add('ba-stale', 'warn', "{$ba->type} proses TTD {$bi['days']} hari kerja", 'pasca');
            }
        }
        foreach ($p->terms as $t) {
            $inv = $t->invoice;
            if ($inv?->status === 'Dibayar') {
                continue;
            }
            $gr = $t->goodsReceipt;
            if ($gr?->status === 'Perlu Revisi') {
                $add('gr', 'warn', "GR tahap {$t->seq} perlu revisi", 'pasca');
            } elseif ($gr?->status === 'Diajukan' && ($d = $this->cal->workdaysBetween($gr->updated_at, $this->today())) >= (int) AppSetting::get('invoice_stale_days')) {
                $add('gr', 'warn', "GR tahap {$t->seq} belum terbit {$d} hari kerja", 'pasca');
            }
            if ($inv?->status === 'Perlu Revisi') {
                $add('inv', 'warn', "Invoice {$inv->number} perlu revisi", 'pasca');
            } elseif ($inv && ($d = $this->cal->workdaysBetween($inv->updated_at, $this->today())) >= (int) AppSetting::get('invoice_stale_days')) {
                $add('inv', 'warn', "Invoice {$inv->status} {$d} hari kerja", 'pasca');
            }
        }

        return $out;
    }

    /** @param array<int, array<string, string>> $alerts */
    public function score(Procurement $p, array $alerts): int
    {
        $s = $p->priority === 'Urgent' ? 100 : 0;
        foreach ($alerts as $a) {
            $s += match ($a['code']) {
                'dur-bad', 'late' => 60, 'no-update' => 30, 'dur-warn' => 20, default => 10
            };
        }

        return $s;
    }

    /** Baris tabel monitoring (Dashboard dan Daftar Pengadaan). @return array<string, mixed> */
    public function row(Procurement $p): array
    {
        $approval = $this->currentApproval($p);
        $focus = $approval ? null : $this->focusTerm($p);
        $v = $approval ?? $focus['cur'] ?? null;
        $track = $approval || ! $p->contract ? 'pengadaan' : 'pasca';
        $scheduled = $this->scheduled($v);
        $days = $v && ! $scheduled ? $this->visitDays($v) : null;
        $stage = null;
        $position = null;
        if ($v) {
            $def = $focus ? null : collect($p->workflow)->firstWhere('key', $v->stage_key);
            $group = $def ? WorkflowService::groupOf($def) : null;
            $position = $focus ? $v->stage_name : ($group ?: $v->stage_name);
            $stage = ($focus ? "Tahap {$focus['t']->seq} · " : ($v->amendment_id ? 'Amandemen · ' : ''))
                .($group && $group !== $v->stage_name ? "{$group} · " : '').$v->stage_name;
        } elseif ($p->contract) {
            $stage = $this->postState($p) === 'selesai' ? 'Seluruh tahap dibayar' : ($this->postState($p) === 'noterm' ? 'Tahapan belum dibuat' : $p->contract->kind.' Rilis');
        }
        $late = 0;
        $planEnd = $focus['plan']['end'] ?? null;
        if ($planEnd && $v?->stage_key === 'pelaksanaan' && ! $scheduled) {
            $late = max(0, Cal::daysBetween($planEnd, $this->today()));
        }
        $alerts = $this->alerts($p);
        $lu = $this->lastUpdate($p);
        $approvalNodes = collect($p->workflow)->filter(fn ($s) => $s['enabled'] ?? true)->map(function ($s) use ($p) {
            $last = $p->visits->where('track', 'pengadaan')->whereNull('amendment_id')->where('stage_key', $s['key'])->last();

            return $last?->status;
        })->values();

        return [
            'code' => $p->code, 'spp_number' => $p->spp_number, 'title' => $p->title, 'rab_value' => (float) $p->rab_value,
            'method' => $p->method, 'proc_method' => $p->proc_method, 'priority' => $p->priority, 'pengaju_name' => $p->pengaju_name,
            'progress' => [
                'approval' => $approvalNodes, 'contract' => (bool) $p->contract,
                'terms' => $this->termFlows($p)->map(fn ($f) => ['paid' => $f['paid'], 'scheduled' => $f['scheduled'], 'stage' => $f['cur']?->stage_name])->values(),
            ],
            'track' => $track, 'amendment' => (bool) $v?->amendment_id,
            'stage' => $stage, 'cycle' => $v?->cycle ?? 1,
            'activity' => $scheduled ? 'Dijadwalkan mulai '.Cal::iso($v->entered_at) : $v?->activity,
            'pic' => $v?->pic?->name, 'days' => $days, 'level' => $track === 'pengadaan' ? $this->durLevel($days) : null,
            'late_days' => $late, 'status' => $this->status($p), 'last_update' => $lu,
            'last_update_days' => $this->cal->workdaysBetween($lu, $this->today()),
            'alerts' => $alerts, 'score' => $this->score($p, $alerts), 'registered_at' => Cal::iso($p->registered_at),
            'vendor' => $p->contract?->vendor?->name, 'sub_bidang' => $p->sub_bidang,
            'stage_key' => $v?->stage_key, 'pic_id' => $v?->pic_id,
            'position' => $scheduled ? 'Terjadwal' : $position, 'process_days' => $this->processDays($p),
            'cost' => (float) ($p->contract?->value_incl ?? $p->rab_value), 'category' => $p->category,
            'customer_contract_ids' => $p->customerContracts->pluck('id')->values(),
        ];
    }

    /**
     * Node stepper untuk satu alur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stepper(Procurement $p, Track $track, ?int $termId = null, ?int $amendmentId = null): array
    {
        $list = $track === Track::Pasca ? WorkflowTemplate::stagesFor('Pasca') : $this->workflow->stages($p, $track, $amendmentId);
        $visits = $p->visits->where('track', $track->value)
            ->when($track === Track::Pasca, fn ($c) => $c->where('term_id', $termId))
            ->when($track === Track::Pengadaan, fn ($c) => $c->where('amendment_id', $amendmentId));
        $allVisits = $visits;
        if ($track === Track::Pengadaan && ($cut = $visits->where('return_type', 'mayor')->max('id'))) {
            $visits = $visits->filter(fn ($v) => $v->id > $cut); // siklus approval baru
        }
        $nodes = [];
        foreach ($list as $s) {
            if (! ($s['enabled'] ?? true)) {
                continue; // tahap nonaktif (rute metode pengadaan, Direktur opsional) tidak ditampilkan
            }
            $mine = $visits->where('stage_key', $s['key']);
            $last = $mine->last();
            $cyclesAll = $allVisits->where('stage_key', $s['key'])->where('status', '!=', 'Dilewati')->count();
            $state = match ($last?->status) {
                'Dalam Proses' => 'current', 'Selesai' => 'done', 'Dilewati' => 'skipped', 'Dikembalikan' => 'returned', default => 'pending',
            };
            if ($state === 'skipped' && $mine->contains('status', 'Selesai')) {
                $state = 'done';
            }
            $nodes[] = [
                'key' => $s['key'], 'name' => $s['name'], 'group' => $track === Track::Pasca ? null : WorkflowService::groupOf($s), 'state' => $state, 'cycles' => $cyclesAll,
                'date' => $state === 'done' ? Cal::iso($last->completed_at) : ($state === 'current' ? Cal::iso($last->entered_at) : null),
                'days' => $state === 'current' && ! $this->scheduled($last) ? $this->visitDays($last) : null,
                'scheduled' => $state === 'current' && $this->scheduled($last),
            ];
        }
        if ($track === Track::Pasca) {
            $term = $p->terms->firstWhere('id', $termId);
            $nodes[] = ['key' => 'final', 'group' => null, 'name' => 'Dibayar', 'state' => $term?->paid_at ? 'done' : 'pending', 'cycles' => 0, 'date' => Cal::iso($term?->paid_at), 'days' => null, 'scheduled' => false];
        } elseif ($amendmentId) {
            $am = $p->contract?->amendments->firstWhere('id', $amendmentId);
            $nodes[] = ['key' => 'final', 'group' => null, 'name' => 'Amandemen Rilis', 'state' => $am?->status === 'Rilis' ? 'done' : 'pending', 'cycles' => 0, 'date' => Cal::iso($am?->date), 'days' => null, 'scheduled' => false];
        } else {
            $nodes[] = ['key' => 'final', 'group' => null, 'name' => $p->method === 'SPP' ? 'SP2K Rilis' : 'PO Rilis', 'state' => $p->contract ? 'done' : 'pending', 'cycles' => 0, 'date' => Cal::iso($p->contract?->date), 'days' => null, 'scheduled' => false];
        }

        return $nodes;
    }

    /** Riwayat kunjungan tahap beserta aktivitasnya. @return array<int, array<string, mixed>> */
    public function history(Collection $visits): array
    {
        return $visits->map(fn (StageVisit $v) => [
            'id' => $v->id, 'stage' => $v->stage_name, 'cycle' => $v->cycle, 'status' => $v->status,
            'entered_at' => Cal::iso($v->entered_at), 'completed_at' => Cal::iso($v->completed_at),
            'days' => $this->scheduled($v) ? null : $this->visitDays($v), 'pic' => $v->pic?->name, 'activity' => $v->activity,
            'return_reason' => $v->return_reason, 'return_type' => $v->return_type, 'resume_key' => $v->resume_key, 'amendment_id' => $v->amendment_id,
            'activities' => $v->activities->map(fn ($a) => ['date' => Cal::iso($a->date), 'category' => $a->category, 'note' => $a->note, 'kind' => $a->kind])->values(),
        ])->values()->all();
    }

    /** Siklus approval Proses Pengadaan: 1 + jumlah Revisi Mayor pada alur yang sama. */
    public function approvalCycle(Procurement $p, ?int $amendmentId = null): int
    {
        return $p->visits->where('track', 'pengadaan')->where('amendment_id', $amendmentId)->where('return_type', 'mayor')->count() + 1;
    }
}
