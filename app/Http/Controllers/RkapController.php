<?php

namespace App\Http\Controllers;

use App\Models\BudgetCode;
use App\Models\Procurement;
use App\Services\AuditLogger;
use App\Services\ProcurementInsight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RKAP: monitoring penetapan PRK per sub bidang.
 * Nilai Pengajuan = anggaran yang ditetapkan untuk PRK. Nilai Digunakan = total RAB pengadaan aktif yang memakainya.
 * Sisa Anggaran boleh minus; hanya diinformasikan dan tidak menahan pengadaan.
 */
class RkapController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(ProcurementInsight $insight): Response
    {
        $used = BudgetCode::usedAmounts();
        $procs = Procurement::query()->with(['budgetCodes:id', 'contract.amendments', 'visits', 'terms', 'documents', 'customerContracts'])
            ->where('is_draft', false)->whereNull('cancelled_at')->get();
        $byCode = [];
        foreach ($procs as $p) {
            foreach ($p->budgetCodes as $b) {
                $byCode[$b->id][] = ['code' => $p->code, 'spp_number' => $p->spp_number, 'title' => $p->title, 'rab_value' => (float) $p->rab_value, 'status' => $insight->status($p), 'registered_at' => $p->registered_at?->toDateString()];
            }
        }
        $rows = BudgetCode::query()->orderBy('code')->get()->map(function (BudgetCode $b) use ($used, $byCode) {
            $pagu = (float) $b->budget_amount;
            $pakai = $used[$b->id] ?? 0.0;

            return [
                ...$b->only(['id', 'coa', 'cost_center', 'sub_bidang', 'code', 'program', 'klasifikasi', 'year', 'active']),
                'budget_amount' => $pagu, 'used' => $pakai, 'remaining' => $pagu - $pakai,
                'percent' => $pagu > 0 ? round($pakai / $pagu * 100, 1) : null,
                'procurements' => $byCode[$b->id] ?? [],
            ];
        });

        return Inertia::render('Rkap/Index', ['rows' => $rows->map(fn ($r) => collect($r)->except('procurements')->all() + ['count' => count($r['procurements'])])->values()]);
    }

    /** Detail PRK: persentase terpakai dan daftar pengadaan yang memakai PRK ini. */
    public function show(BudgetCode $budgetCode, ProcurementInsight $insight): Response
    {
        $procs = $budgetCode->procurements()->with(ProcurementInsight::WITH)->where('is_draft', false)->whereNull('cancelled_at')->get();
        $pagu = (float) $budgetCode->budget_amount;
        $used = (float) $procs->sum('rab_value');

        return Inertia::render('Rkap/Show', [
            'prk' => [
                ...$budgetCode->only(['id', 'coa', 'cost_center', 'sub_bidang', 'code', 'program', 'klasifikasi', 'year', 'active']),
                'budget_amount' => $pagu, 'used' => $used, 'remaining' => $pagu - $used,
                'percent' => $pagu > 0 ? round($used / $pagu * 100, 1) : null,
            ],
            'rows' => $procs->map(fn ($p) => $insight->row($p))->sortByDesc('rab_value')->values(),
        ]);
    }

    public function save(Request $request, ?BudgetCode $budgetCode = null): RedirectResponse
    {
        $d = $request->validate([
            'coa' => ['required', 'string', 'max:30'],
            'cost_center' => ['required', 'string'],
            'sub_bidang' => ['nullable', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:40', Rule::unique('budget_codes', 'code')->ignore($budgetCode?->id)],
            'program' => ['required', 'string'],
            'klasifikasi' => ['required', 'string', 'max:60'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'active' => ['boolean'],
        ], [], ['code' => 'PRK', 'budget_amount' => 'nilai pengajuan']);
        $d = [...$d, 'code' => strtoupper(trim($d['code'])), 'program' => mb_strtoupper(trim($d['program'])), 'klasifikasi' => mb_strtoupper(trim($d['klasifikasi']))];
        if ($budgetCode && (float) $budgetCode->budget_amount !== (float) $d['budget_amount']) {
            $this->audit->log(null, 'RKAP', $budgetCode->code, 'Ubah nilai pengajuan PRK', 'Nilai pengajuan',
                'Rp'.number_format((float) $budgetCode->budget_amount, 0, ',', '.'), 'Rp'.number_format((float) $d['budget_amount'], 0, ',', '.'));
        }
        $budgetCode ? $budgetCode->update($d) : BudgetCode::query()->create($d);
        if (! $budgetCode) {
            $this->audit->log(null, 'RKAP', $d['code'], 'Tambah PRK', null, null, $d['program']);
        }

        return $this->done('PRK disimpan.');
    }
}
