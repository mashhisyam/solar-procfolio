<?php

namespace App\Services;

use App\Enums\Track;
use App\Enums\VisitStatus;
use App\Models\Procurement;
use App\Models\StageVisit;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Support\Collection;

/**
 * Angka Dashboard dihitung di server dari pengadaan yang sudah tersaring (periode, skema, prioritas, User Pengaju,
 * perjanjian pelanggan). Hanya turunan; tidak ada yang disimpan.
 */
class DashboardStats
{
    /** Urutan posisi dari pengajuan sampai pembayaran. Posisi tanpa pengadaan tetap tampil agar persebarannya utuh. */
    public const POSITIONS = [
        'pengadaan' => ['Pengaju', 'Sales', 'Anggaran', 'Manager Pengadaan', 'Perencana Pengadaan', 'Pelaksana Pengadaan', 'Partnership', 'Menunggu Rilis SP2K/PO'],
        'pasca' => ['Pelaksanaan Pekerjaan', 'BA Penutupan', 'Proses GR', 'Invoicing', 'Verifikasi Invoice', 'Antri Bayar', 'Terjadwal', 'Tahapan Belum Dibuat'],
    ];

    /** Kelompok approval yang dibandingkan di Top 5 Proses Approval. */
    public const APPROVAL_GROUPS = ['Pengaju', 'Sales', 'Anggaran', 'Perencana Pengadaan', 'Pelaksana Pengadaan'];

    public function __construct(private ProcurementInsight $insight, private Cal $cal) {}

    /**
     * @param  Collection<int, Procurement>  $procs  pengadaan terdaftar (bukan draft, tidak dibatalkan) yang lolos filter
     * @return array<string, mixed>
     */
    public function build(Collection $procs): array
    {
        $rows = $procs->mapWithKeys(fn (Procurement $p) => [$p->id => $this->insight->row($p)]);
        $active = $rows->filter(fn ($r) => $r['status'] !== 'Selesai');
        $unpaid = $procs->flatMap(fn (Procurement $p) => $p->terms->whereNull('paid_at'));
        $contracted = $procs->filter(fn (Procurement $p) => $p->contract !== null);

        return [
            'kpi' => [
                'active' => $active->count(),
                'spp' => $active->where('method', 'SPP Pusat')->count(),
                'kemitraan' => $active->where('method', 'SPP Kemitraan')->count(),
                'urgent' => $active->where('priority', 'Urgent')->count(),
                'attention' => $active->filter(fn ($r) => count($r['alerts']) > 0)->count(),
                'done' => $rows->where('status', 'Selesai')->count(),
                'unpaid_terms' => $unpaid->count(),
                'unpaid_value' => (float) $unpaid->sum('amount'),
            ],
            'money' => [
                // RAB Usulan: seluruh pengadaan terdaftar. RAB Realisasi: nilai SP2K/PO setelah PPN dari pengadaan yang sudah terkontrak.
                'rab_proposed' => (float) $procs->sum('rab_value'),
                'rab_proposed_count' => $procs->count(),
                'rab_realized' => (float) $contracted->sum(fn (Procurement $p) => (float) $p->contract->value_incl),
                'rab_realized_count' => $contracted->count(),
                'revenue' => (float) $procs->flatMap(fn (Procurement $p) => $p->customerContracts)->unique('id')->sum(fn ($c) => (float) $c->revenue),
            ],
            'per_customer_contract' => $this->perCustomerContract($procs),
            'positions' => $this->positions($procs->keyBy('id'), $active),
            'top' => [
                'time' => $this->topTime($procs),
                'value' => $this->topValue($contracted),
                'approval' => $this->topApproval($procs),
                'vendor' => $this->topVendor($contracted),
            ],
        ];
    }

    /**
     * Ringkasan pengadaan yang berhenti: jumlah dan RAB Gagal/Dibatalkan, serta posisi terakhir saat dihentikan.
     *
     * @param  Collection<int, Procurement>  $procs  pengadaan dengan cancelled_at terisi
     * @return array<string, mixed>
     */
    public function stopped(Collection $procs): array
    {
        $gagal = $procs->where('cancel_type', 'gagal');
        $batal = $procs->reject(fn (Procurement $p) => $p->cancel_type === 'gagal');
        $byPos = [];
        foreach ($procs as $p) {
            $pos = $this->insight->row($p)['position'] ?: 'Lainnya';
            $byPos[$pos] ??= ['name' => $pos, 'gagal' => 0, 'batal' => 0];
            $byPos[$pos][$p->cancel_type === 'gagal' ? 'gagal' : 'batal']++;
        }
        $order = array_merge(...array_values(self::POSITIONS));

        return [
            'gagal' => $gagal->count(), 'gagal_rab' => (float) $gagal->sum('rab_value'),
            'batal' => $batal->count(), 'batal_rab' => (float) $batal->sum('rab_value'),
            'by_position' => collect($byPos)->sortBy(fn ($r) => array_search($r['name'], $order, true) === false ? 99 : array_search($r['name'], $order, true))->values()->all(),
        ];
    }

    /**
     * Revenue vs Cost per perjanjian pelanggan. RAB Pengadaan dan Nilai SP2K dijumlah dari pengadaan yang merujuknya;
     * pengadaan yang merujuk lebih dari satu perjanjian ikut dihitung di masing-masing perjanjian.
     *
     * @param  Collection<int, Procurement>  $procs
     * @return array<int, array<string, mixed>>
     */
    private function perCustomerContract(Collection $procs): array
    {
        $out = [];
        foreach ($procs as $p) {
            foreach ($p->customerContracts as $c) {
                $out[$c->id] ??= [
                    'id' => $c->id, 'kind' => $c->kind, 'number' => $c->number, 'title' => $c->title, 'customer' => $c->customer?->name,
                    'revenue' => (float) $c->revenue, 'rab' => 0.0, 'contract_value' => 0.0, 'count' => 0, 'contracted' => 0,
                ];
                $out[$c->id]['rab'] += (float) $p->rab_value;
                $out[$c->id]['count']++;
                if ($p->contract) {
                    $out[$c->id]['contract_value'] += (float) $p->contract->value_incl;
                    $out[$c->id]['contracted']++;
                }
            }
        }

        return collect($out)->sortByDesc(fn ($r) => max($r['revenue'], $r['rab']))->values()->all();
    }

    /**
     * Persebaran pengadaan aktif per posisi beserta rata-rata lama di tahapan dan lama proses (hari kerja).
     *
     * @param  Collection<int, Procurement>  $procs  dikunci dengan id pengadaan
     * @param  Collection<int, mixed>  $active
     * @return array<int, array<string, mixed>>
     */
    private function positions(Collection $procs, Collection $active): array
    {
        $byPos = [];
        foreach ($active as $id => $r) {
            $name = $r['position'] ?: ($procs[$id]->contract ? 'Tahapan Belum Dibuat' : 'Menunggu Rilis SP2K/PO');
            $byPos[$name][] = $r;
        }
        $order = [];
        foreach (self::POSITIONS as $track => $names) {
            foreach ($names as $n) {
                $order[$n] = $track;
            }
        }
        foreach (array_keys($byPos) as $n) {
            $order[$n] ??= $byPos[$n][0]['track'];
        }
        $avg = fn (array $xs) => $xs ? (int) round(array_sum($xs) / count($xs)) : null;

        return collect($order)->map(function (string $track, string $name) use ($byPos, $avg) {
            $list = collect($byPos[$name] ?? []);
            $days = $list->pluck('days')->filter(fn ($d) => $d !== null)->all();

            return [
                'name' => $name, 'track' => $track, 'count' => $list->count(),
                'spp' => $list->where('method', 'SPP Pusat')->count(), 'kemitraan' => $list->where('method', 'SPP Kemitraan')->count(),
                'stage_avg' => $avg($days), 'stage_max' => $days ? max($days) : null,
                'process_avg' => $avg($list->pluck('process_days')->all()),
            ];
        })->values()->all();
    }

    /**
     * Lama Proses Pengadaan: registrasi sampai SP2K/PO rilis (hari kerja). Pengadaan yang belum rilis dihitung sampai hari ini
     * dan hanya masuk daftar terlama; daftar tercepat hanya berisi yang sudah rilis.
     *
     * @param  Collection<int, Procurement>  $procs
     * @return array{slowest: array<int, array<string, mixed>>, fastest: array<int, array<string, mixed>>}
     */
    private function topTime(Collection $procs): array
    {
        $items = $procs->map(function (Procurement $p) {
            $end = $p->contract ? Cal::iso($p->contract->date) : Cal::today();

            return [
                ...$this->ident($p), 'days' => $this->cal->workdaysBetween($p->registered_at, $end), 'done' => (bool) $p->contract,
                'note' => $p->contract ? "{$p->contract->kind} rilis ".Cal::iso($p->contract->date) : 'Masih berjalan',
            ];
        });

        return [
            'slowest' => $items->sortByDesc('days')->take(5)->values()->all(),
            'fastest' => $items->where('done', true)->sortBy('days')->take(5)->values()->all(),
        ];
    }

    /**
     * Nilai SP2K/PO setelah PPN, dipisah per jenis dokumen.
     *
     * @param  Collection<int, Procurement>  $contracted
     * @return array<string, array{largest: array<int, array<string, mixed>>, smallest: array<int, array<string, mixed>>}>
     */
    private function topValue(Collection $contracted): array
    {
        $out = [];
        foreach (['SP2K', 'PO'] as $kind) {
            $items = $contracted->filter(fn (Procurement $p) => $p->contract->kind === $kind)->map(fn (Procurement $p) => [
                ...$this->ident($p), 'value' => (float) $p->contract->value_incl, 'note' => "{$p->contract->number} · ".($p->contract->vendor->name ?? '–'),
            ]);
            $out[$kind] = [
                'largest' => $items->sortByDesc('value')->take(5)->values()->all(),
                'smallest' => $items->sortBy('value')->take(5)->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Rata-rata lama tiap kelompok approval (hari kerja) dari pengadaan yang sudah menyelesaikan kelompok tersebut,
     * serta lima pengadaan tercepat/terlama per kelompok. Alur amandemen tidak dihitung; seluruh siklus revisi ikut dihitung.
     *
     * @param  Collection<int, Procurement>  $procs
     * @return array{groups: array<int, array<string, mixed>>, items: array<string, array<string, array<int, array<string, mixed>>>>}
     */
    private function topApproval(Collection $procs): array
    {
        $durations = array_fill_keys(self::APPROVAL_GROUPS, []);
        foreach ($procs as $p) {
            $keysByGroup = [];
            foreach ($p->workflow ?? [] as $s) {
                $keysByGroup[WorkflowService::groupOf($s)][] = $s['key'];
            }
            $visits = $p->visits->where('track', Track::Pengadaan->value)->whereNull('amendment_id');
            foreach (self::APPROVAL_GROUPS as $g) {
                $mine = $visits->whereIn('stage_key', $keysByGroup[$g] ?? [])->where('status', '!=', VisitStatus::Skipped->value);
                // Hanya kelompok yang sudah selesai agar rata-rata tidak terpotong oleh proses yang masih berjalan.
                if ($mine->isEmpty() || $mine->contains('status', VisitStatus::Running->value) || ! $mine->contains('status', VisitStatus::Done->value)) {
                    continue;
                }
                $durations[$g][] = [...$this->ident($p), 'days' => $mine->sum(fn (StageVisit $v) => $this->insight->visitDays($v)), 'note' => $g];
            }
        }
        $groups = [];
        $items = [];
        foreach ($durations as $g => $list) {
            $c = collect($list);
            $groups[] = ['name' => $g, 'avg' => $c->isEmpty() ? null : round($c->avg('days'), 1), 'count' => $c->count()];
            $items[$g] = [
                'fastest' => $c->sortBy('days')->take(5)->values()->all(),
                'slowest' => $c->sortByDesc('days')->take(5)->values()->all(),
            ];
        }

        return ['groups' => $groups, 'items' => $items];
    }

    /**
     * Vendor/mitra pemenang dari pengadaan yang sudah SP2K/PO.
     *
     * @param  Collection<int, Procurement>  $contracted
     * @return array{by_count: array<int, array<string, mixed>>, by_value: array<int, array<string, mixed>>}
     */
    private function topVendor(Collection $contracted): array
    {
        $all = $contracted->groupBy(fn (Procurement $p) => $p->contract->vendor_id)->map(fn (Collection $list) => [
            'vendor' => $list->first()->contract->vendor->name ?? '–',
            'count' => $list->count(),
            'value' => (float) $list->sum(fn (Procurement $p) => (float) $p->contract->value_incl),
            'sp2k' => $list->filter(fn (Procurement $p) => $p->contract->kind === 'SP2K')->count(),
            'po' => $list->filter(fn (Procurement $p) => $p->contract->kind === 'PO')->count(),
        ])->values();

        return [
            'by_count' => $all->sortBy([['count', 'desc'], ['value', 'desc']])->take(5)->values()->all(),
            'by_value' => $all->sortByDesc('value')->take(5)->values()->all(),
        ];
    }

    /** @return array{code:string, spp_number:?string, title:string, method:string} */
    private function ident(Procurement $p): array
    {
        return ['code' => $p->code, 'spp_number' => $p->spp_number, 'title' => $p->title, 'method' => $p->method];
    }
}
