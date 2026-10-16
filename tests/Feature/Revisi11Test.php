<?php

use App\Enums\Track;
use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Pic;
use App\Models\Pks;
use App\Models\Procurement;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\ProcurementInsight;
use App\Services\WorkflowService;
use App\Support\Scheme;
use Database\Seeders\ReferenceSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->actingAs(User::query()->where('email', 'superadmin@admin.com')->firstOrFail());
    $this->vendor = Company::query()->create(['name' => 'PT Vendor R11', 'type' => 'Vendor', 'active' => true]);
    $this->mitra = Company::query()->create(['name' => 'PT Mitra R11', 'type' => 'Mitra', 'active' => true]);
    $customer = Company::query()->create(['name' => 'PT Pelanggan R11', 'type' => 'Pelanggan', 'active' => true]);
    $this->cc = CustomerContract::query()->create(['kind' => 'Berita Acara Kesepakatan Biaya', 'number' => 'BAKB/R11/001', 'title' => 'Uji', 'customer_id' => $customer->id, 'date' => now()->subDays(10), 'revenue' => 10000000]);
});

/** Payload memakai template alur bawaan skema seperti yang dikirim form, agar rute Rendan/Lakdan ikut diuji. */
function r11Payload(array $over = []): array
{
    $d = array_merge([
        'draft' => false, 'method' => 'SPR', 'title' => 'Pengadaan R11', 'category' => 'Sewa', 'proc_method' => null,
        'priority' => 'Normal', 'sub_bidang' => 'PLN 2', 'spp_number' => 'SPR/2026/R11/'.random_int(1000, 9999), 'spp_date' => now()->subDays(3)->toDateString(),
        'pic_pengaju_id' => Pic::query()->value('id'), 'candidate_vendor_ids' => [test()->vendor->id], 'customer_contract_ids' => [test()->cc->id],
        'budget_code_id' => BudgetCode::query()->value('id'),
        'rab_items' => [['description' => 'SEWA', 'budget_type' => 'OPEX', 'qty' => 12, 'unit' => 'Bulan', 'price' => 1000000]],
        'documents' => [['doc_type' => 'TOR (Term of Reference)', 'required' => true, 'available' => true]],
    ], $over);
    $tpl = WorkflowTemplate::stagesFor($d['method']);
    $d['workflow'] ??= Scheme::usesProcMethod($d['method']) ? WorkflowService::applyRouting($tpl, $d['proc_method']) : $tpl;

    return $d;
}

it('mendaftarkan SPR: User Pengaju, Manager Pengaju, Manager Pengadaan, lalu SP2K', function () {
    $this->post('/pengadaan', r11Payload(['proc_method' => 'Tender Terbuka']))->assertSessionHasNoErrors();
    $p = Procurement::query()->firstOrFail();
    expect($p->method)->toBe('SPR')
        ->and(collect($p->workflow)->where('enabled', true)->pluck('key')->all())->toBe(['pengaju_user', 'pengaju_mgr', 'pengadaan_mgr'])
        ->and($p->proc_method)->toBeNull(); // metode pengadaan hanya SPP Pusat
    $nodes = app(ProcurementInsight::class)->stepper($p->load(ProcurementInsight::WITH), Track::Pengadaan);
    expect(collect($nodes)->last()['name'])->toBe('SP2K Rilis');
    $this->get("/pengadaan/{$p->code}")->assertOk();
    // Nomor SPR wajib.
    $this->post('/pengadaan', r11Payload(['spp_number' => '']))->assertSessionHasErrors('spp_number');
});

it('menolak kategori lama dan menerima kategori baru', function () {
    $this->post('/pengadaan', r11Payload(['category' => 'Lisensi']))->assertSessionHasErrors('category');
    foreach (['Barang (Non Lisensi)', 'Barang (Lisensi)', 'Jasa', 'Barang Jasa (Supply Erect)', 'Sewa'] as $c) {
        $this->post('/pengadaan', r11Payload(['category' => $c]))->assertSessionHasNoErrors();
    }
});

it('mencatat BAK SPP Kemitraan di Dokumen Referensi dan mewajibkan nomornya', function () {
    $pks = Pks::query()->create(['number' => 'PKS/R11/001', 'title' => 'PKS Uji', 'partner_id' => $this->mitra->id, 'start_date' => now()->subYear(), 'end_date' => now()->addYear()]);
    $kemitraan = fn (array $docs) => r11Payload(['method' => 'SPP Kemitraan', 'spp_number' => 'PGJ/R11/'.random_int(1000, 9999), 'proc_method' => null,
        'candidate_vendor_ids' => [$this->mitra->id], 'pks_id' => $pks->id, 'documents' => $docs]);
    $this->post('/pengadaan', $kemitraan([['doc_type' => 'BAK', 'required' => true, 'available' => false]]))->assertSessionHasErrors('documents');
    $this->post('/pengadaan', $kemitraan([['doc_type' => 'BAK', 'number' => 'BAK/R11/009', 'date' => now()->toDateString(), 'required' => true]]))->assertSessionHasNoErrors();
    $p = Procurement::query()->where('method', 'SPP Kemitraan')->firstOrFail();
    expect($p->documents()->where('doc_type', 'BAK')->value('available'))->toBeTrue();
    $this->get("/pengadaan/{$p->code}")->assertInertia(fn (Assert $page) => $page->where('procurement.bak_number', 'BAK/R11/009'));
});

it('menampilkan revisi mayor dan minor dengan jelas', function () {
    $this->post('/pengadaan', r11Payload(['method' => 'SPP Pusat', 'spp_number' => 'SPP/R11/REV', 'proc_method' => 'Pengadaan Langsung']))->assertSessionHasNoErrors();
    $p = Procurement::query()->firstOrFail();
    $step = fn (string $mode, array $extra = []) => $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'mode' => $mode, 'date' => now()->toDateString(), ...$extra])->assertSessionHasNoErrors();
    $step('complete', ['activity' => 'Approval']);
    $step('complete', ['activity' => 'Approval']);
    $step('rev_major', ['note' => 'RAB tidak sesuai']);

    $this->get("/pengadaan/{$p->code}")->assertInertia(fn (Assert $page) => $page
        ->where('revision.type', 'mayor')->where('revision.from_stage', 'L1/VP Pengaju')->where('revision.reason', 'RAB tidak sesuai')
        ->where('revision.cycle', 2)->where('alerts.0.code', 'revisi')->where('alerts.0.tone', 'bad'));
    $this->get('/?periode=semua')->assertInertia(fn (Assert $page) => $page->where('kpi.revision_major', 1)->where('kpi.revision_minor', 0));

    // Pengaju memperbaiki: tidak lagi dalam revisi.
    $step('complete', ['activity' => 'Review Dokumen']);
    $fresh = Procurement::query()->with(ProcurementInsight::WITH)->firstOrFail();
    expect(app(ProcurementInsight::class)->revision($fresh))->toBeNull();

    $step('rev_minor', ['note' => 'Lengkapi TOR']);
    $this->get("/pengadaan/{$p->code}")->assertInertia(fn (Assert $page) => $page
        ->where('revision.type', 'minor')->where('revision.resume_stage', 'Manager Pengaju')->where('alerts.0.tone', 'warn'));
    $this->get('/pengadaan')->assertInertia(fn (Assert $page) => $page->where('rows.0.revision.type', 'minor'));
});

it('menghitung pengadaan per kategori di Top 5 Dashboard', function () {
    $this->post('/pengadaan', r11Payload(['category' => 'Sewa']))->assertSessionHasNoErrors();
    $this->post('/pengadaan', r11Payload(['category' => 'Sewa']))->assertSessionHasNoErrors();
    $this->post('/pengadaan', r11Payload(['category' => 'Jasa']))->assertSessionHasNoErrors();
    $this->get('/?periode=semua')->assertInertia(fn (Assert $page) => $page
        ->has('top.category', 5)
        ->where('top.category', fn ($rows) => collect($rows)->firstWhere('name', 'Sewa')['count'] === 2 && collect($rows)->firstWhere('name', 'Jasa')['count'] === 1)
        ->where('kpi.spr', 3));
});

it('melengkapi nomor PA saat approval Sales bila belum diisi', function () {
    $this->post('/pengadaan', r11Payload(['method' => 'SPP Pusat', 'spp_number' => 'SPP/R11/PA', 'proc_method' => 'Pengadaan Langsung']))->assertSessionHasNoErrors();
    $p = Procurement::query()->firstOrFail();
    $step = fn (array $extra = []) => $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'mode' => 'complete', 'date' => now()->toDateString(), 'activity' => 'Approval', ...$extra]);
    // Belum di tahap Sales: ditolak.
    $step(['pa_number' => 'PA/X/1'])->assertSessionHasErrors('pa_number');
    $step()->assertSessionHasNoErrors();
    $step()->assertSessionHasNoErrors();
    $step()->assertSessionHasNoErrors(); // User -> Manager -> L1/VP Pengaju -> kini di Manager Sales
    $step(['pa_number' => 'PA/UJI/2026/010'])->assertSessionHasNoErrors();
    expect($p->fresh()->pa_number)->toBe('PA/UJI/2026/010');
    // Nomor yang sudah ada tidak ditimpa dari drawer.
    $step(['pa_number' => 'PA/LAIN'])->assertSessionHasNoErrors();
    expect($p->fresh()->pa_number)->toBe('PA/UJI/2026/010');
});

it('mempertahankan kelompok tahap saat template disimpan dari Master', function () {
    $stages = WorkflowTemplate::query()->where('scheme', 'SPR')->orderBy('sort')->get()
        ->map(fn ($t) => ['key' => $t->key, 'name' => $t->name, 'enabled' => $t->enabled, 'optional' => $t->optional, 'pic_id' => null, 'default_activities' => $t->default_activities ?? []])->all();
    $this->post('/master/workflow/SPR', ['stages' => $stages])->assertSessionHasNoErrors();
    expect(WorkflowTemplate::query()->where('scheme', 'SPR')->where('key', 'pengadaan_mgr')->value('group'))->toBe('Manager Pengadaan');
});
