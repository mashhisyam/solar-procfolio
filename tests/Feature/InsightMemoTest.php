<?php

use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementInsight;
use App\Services\WorkflowService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->actingAs(User::query()->where('email', 'superadmin@admin.com')->firstOrFail());
});

it('memo insight ikut basi saat relasi dimuat ulang', function () {
    $insight = app(ProcurementInsight::class);
    $p = Procurement::query()->where('spp_number', 'SPP/2026/BJB/000041')->with(ProcurementInsight::WITH)->firstOrFail();
    $before = $insight->row($p)['stage'];
    app(WorkflowService::class)->updateProcess($p, ['track' => 'pengadaan', 'mode' => 'complete', 'date' => now()->toDateString(), 'activity' => 'Approval']);
    $p->load(ProcurementInsight::WITH);
    expect($insight->row($p)['stage'])->not->toBe($before);
});

it('memuat Dashboard dan detail tanpa query berulang per pengadaan', function () {
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    $this->get('/?periode=semua')->assertOk();
    // Jumlah query tetap, tidak bertambah mengikuti jumlah pengadaan (22 di data demo).
    expect($count)->toBeLessThan(30);

    $p3 = Procurement::query()->where('spp_number', 'SPP/2026/BJB/000024')->firstOrFail();
    $this->get("/pengadaan/{$p3->code}")->assertOk()->assertInertia(fn (Assert $page) => $page->has('terms.0.ba_ids', 1)->where('terms.0.ba_ready', false));
});
