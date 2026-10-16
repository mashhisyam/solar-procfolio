<?php

namespace App\Http\Controllers;

use App\Models\CustomerContract;
use App\Models\Pic;
use App\Models\Procurement;
use App\Services\DashboardStats;
use App\Services\ProcurementInsight;
use App\Support\Period;
use App\Support\Scheme;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard: ringkasan angka dan grafik. Daftar pengadaan tidak ditampilkan di sini; kartu dan posisi
 * mengarah ke Daftar Pengadaan dengan filter yang sesuai.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $f = [
            'method' => in_array($request->query('skema'), Scheme::ALL, true) ? (string) $request->query('skema') : '',
            'priority' => in_array($request->query('prioritas'), ['Urgent', 'Normal'], true) ? (string) $request->query('prioritas') : '',
            'pengaju' => (int) $request->query('pengaju', 0),
            'cc' => (int) $request->query('perjanjian', 0),
        ];
        $procs = Period::apply(Procurement::query(), Period::current($request))
            ->where('is_draft', false)
            ->when($f['method'], fn ($q, $v) => $q->where('method', $v))
            ->when($f['priority'], fn ($q, $v) => $q->where('priority', $v))
            ->when($f['pengaju'], fn ($q, $v) => $q->where('pic_pengaju_id', $v))
            ->when($f['cc'], fn ($q, $v) => $q->whereHas('customerContracts', fn ($c) => $c->whereKey($v)))
            ->with([...ProcurementInsight::WITH, 'customerContracts.customer:id,name'])
            ->get();

        // Pengadaan Gagal/Dibatalkan tidak ikut angka utama, tetapi tetap dihitung tersendiri.
        [$stopped, $running] = $procs->partition(fn (Procurement $p) => $p->cancelled_at !== null);

        return Inertia::render('Dashboard', [
            ...$stats->build($running->values()),
            'stopped' => $stats->stopped($stopped->values()),
            'filters' => $f,
            'options' => [
                'pengaju' => Pic::query()->whereIn('id', Procurement::query()->whereNotNull('pic_pengaju_id')->select('pic_pengaju_id'))->orderBy('name')->get(['id', 'name']),
                'customerContracts' => CustomerContract::query()->orderBy('number')->get(['id', 'kind', 'number']),
            ],
        ]);
    }
}
