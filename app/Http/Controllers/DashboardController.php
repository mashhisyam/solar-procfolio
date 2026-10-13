<?php

namespace App\Http\Controllers;

use App\Models\CustomerContract;
use App\Models\PaymentTerm;
use App\Models\Procurement;
use App\Services\ProcurementInsight;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ProcurementInsight $insight): Response
    {
        $procs = Procurement::query()->with(ProcurementInsight::WITH)->whereNull('cancelled_at')->where('is_draft', false)->get();
        $rows = $procs->filter(fn ($p) => $insight->isActive($p))->map(fn ($p) => $insight->row($p))->sortByDesc('score')->values();
        $unpaid = PaymentTerm::query()->whereNull('paid_at')->whereIn('procurement_id', $procs->pluck('id'))->get(['amount']);

        return Inertia::render('Dashboard', [
            'rows' => $rows,
            'kpi' => [
                'active' => $rows->count(),
                'spp' => $rows->where('method', 'SPP')->count(),
                'kemitraan' => $rows->where('method', 'Kemitraan')->count(),
                'urgent' => $rows->where('priority', 'Urgent')->count(),
                'attention' => $rows->filter(fn ($r) => count($r['alerts']) > 0)->count(),
                'unpaid_terms' => $unpaid->count(),
                'unpaid_value' => (float) $unpaid->sum('amount'),
            ],
            'filters' => [
                'pengaju' => $rows->pluck('pengaju_name')->unique()->sort()->values(),
                'customerContracts' => CustomerContract::query()->orderBy('number')->get(['id', 'number']),
            ],
        ]);
    }
}
