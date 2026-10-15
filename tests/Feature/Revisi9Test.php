<?php

use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Pic;
use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementInsight;
use App\Services\WorkflowService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ReferenceSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->actingAs(User::query()->where('email', 'admin@procfolio.local')->firstOrFail());
    $this->vendor = Company::query()->create(['name' => 'PT Vendor R9', 'type' => 'Vendor', 'active' => true]);
    $customer = Company::query()->create(['name' => 'PT Pelanggan R9', 'type' => 'Pelanggan', 'active' => true]);
    $this->bakb = CustomerContract::query()->create(['kind' => 'Berita Acara Kesepakatan Biaya', 'number' => 'BAKB/R9/001', 'title' => 'Uji', 'customer_id' => $customer->id, 'date' => now()->subDays(10), 'revenue' => 20000000]);
});

function r9Payload(array $over = []): array
{
    return array_merge([
        'draft' => false, 'method' => 'SPP Pusat', 'title' => 'Pengadaan R9', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Pengadaan Langsung',
        'priority' => 'Normal', 'sub_bidang' => 'PLN 2', 'spp_number' => 'SPP/2026/R9/'.random_int(1000, 9999), 'spp_date' => now()->subDays(5)->toDateString(),
        'pic_pengaju_id' => Pic::query()->value('id'), 'candidate_vendor_ids' => [test()->vendor->id], 'customer_contract_ids' => [test()->bakb->id],
        'budget_code_id' => BudgetCode::query()->value('id'),
        'rab_items' => [['description' => 'UJI', 'budget_type' => 'OPEX', 'qty' => 1, 'unit' => 'Paket', 'price' => 10000000]],
        'documents' => [['doc_type' => 'TOR (Term of Reference)', 'required' => true, 'available' => true]],
        'workflow' => [['key' => 'pengaju', 'name' => 'Pengaju', 'group' => 'Pengaju', 'enabled' => true], ['key' => 'lakdan', 'name' => 'Pelaksana', 'group' => 'Pelaksana Pengadaan', 'enabled' => true]],
    ], $over);
}

function r9Release(Procurement $p, string $number = 'SP2K/R9/001'): void
{
    test()->post("/pengadaan/{$p->code}/kontrak", [
        'kind' => 'SP2K', 'number' => $number, 'title' => 'Uji', 'vendor_id' => test()->vendor->id, 'date' => now()->toDateString(),
        'kickoff_date' => now()->toDateString(), 'value_excl' => 9000000, 'value_incl' => 9990000, 'close_stage' => true,
        'terms' => [['name' => 'Pelunasan', 'planned_end' => now()->addDays(30)->toDateString(), 'percent' => 100]],
    ])->assertSessionHasNoErrors();
}

it('mengirim jenis perjanjian dan sub bidang PRK ke form (langkah 2 tidak kosong)', function () {
    $this->get('/pengadaan/baru')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Procurements/Form')
        ->has('options.ccKinds', 3)
        ->where('options.budgetCodes.0.sub_bidang', 'PLN 2'));
});

it('menerima nomor PA sebagai pengganti perjanjian pelanggan', function () {
    $this->post('/pengadaan', r9Payload(['customer_contract_ids' => [], 'pa_number' => 'pa/uji/001']))->assertSessionHasNoErrors();
    expect(Procurement::query()->value('pa_number'))->toBe('pa/uji/001');
    $this->post('/pengadaan', r9Payload(['customer_contract_ids' => [], 'pa_number' => '']))->assertSessionHasErrors('customer_contract_ids');
});

it('mencatat Kontrak Pengadaan setelah SP2K rilis dan mengamandemennya seperti SP2K', function () {
    $this->post('/pengadaan', r9Payload())->assertSessionHasNoErrors();
    $p = Procurement::query()->firstOrFail();
    $work = ['number' => 'KTR/R9/001', 'title' => 'Kontrak uji', 'date' => now()->toDateString(), 'kickoff_date' => now()->toDateString(), 'value_excl' => 9000000, 'value_incl' => 9990000];
    $this->post("/pengadaan/{$p->code}/kontrak-pengadaan", $work)->assertSessionHasErrors('number'); // SP2K belum rilis
    r9Release($p);
    $this->post("/pengadaan/{$p->code}/kontrak-pengadaan", $work)->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->workContract->kind)->toBe('Kontrak Pengadaan')
        ->and($p->workContract->vendor_id)->toBe($this->vendor->id)
        ->and($p->contract->number)->toBe('SP2K/R9/001')
        ->and($p->terms()->count())->toBe(1);

    $this->post("/pengadaan/{$p->code}/amandemen", ['reason' => 'Perubahan nilai kontrak', 'change_types' => ['Nilai'], 'stages' => ['pengaju'], 'date' => now()->toDateString(), 'contract_id' => $p->workContract->id])
        ->assertSessionHasNoErrors();
    $am = $p->workContract->amendments()->firstOrFail();
    // Tidak boleh ada amandemen kedua (SP2K) selama amandemen Kontrak Pengadaan berjalan.
    $this->post("/pengadaan/{$p->code}/amandemen", ['reason' => 'Amandemen lain', 'change_types' => ['Lainnya'], 'stages' => ['pengaju'], 'date' => now()->toDateString()])
        ->assertSessionHasErrors('reason');
    $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'mode' => 'complete', 'date' => now()->toDateString(), 'activity' => 'Approval'])->assertSessionHasNoErrors();
    $this->post("/pengadaan/{$p->code}/amandemen/{$am->id}/rilis", ['number' => 'AMD/KTR/R9/1', 'date' => now()->toDateString(), 'change_types' => ['Nilai'], 'description' => 'Nilai naik', 'value_excl' => 10000000, 'value_incl' => 11100000])
        ->assertSessionHasNoErrors();
    expect($p->workContract()->first()->value_incl)->toEqual('11100000.00')
        ->and($p->contract()->first()->value_incl)->toEqual('9990000.00');

    $this->get("/pengadaan/{$p->code}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('work_contract.kind', 'Kontrak Pengadaan')->where('contract.is_primary', true));
    $this->get('/kontrak')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows', 2));
});

it('menyaring pengadaan menurut periode registrasi', function () {
    $this->post('/pengadaan', r9Payload())->assertSessionHasNoErrors();
    $old = Procurement::query()->firstOrFail();
    $old->update(['registered_at' => now()->subMonths(4)]);
    $this->post('/pengadaan', r9Payload())->assertSessionHasNoErrors();

    $this->get('/pengadaan')->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('period.key', '2b'));
    $this->get('/pengadaan?periode=6b')->assertInertia(fn (Assert $page) => $page->has('rows', 2));
    // Pilihan tersimpan di sesi.
    $this->get('/pengadaan')->assertInertia(fn (Assert $page) => $page->has('rows', 2)->where('period.key', '6b'));
});

it('menampilkan dashboard dengan RAB usulan, realisasi, persebaran posisi dan Top 5', function () {
    $this->post('/pengadaan', r9Payload())->assertSessionHasNoErrors();
    $this->post('/pengadaan', r9Payload())->assertSessionHasNoErrors();
    r9Release(Procurement::query()->orderBy('id')->firstOrFail());

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('money.rab_proposed', 20000000)
        ->where('money.rab_realized', 9990000)
        ->where('money.rab_realized_count', 1)
        ->where('per_customer_contract.0.number', 'BAKB/R9/001')
        ->where('per_customer_contract.0.contract_value', 9990000)
        ->has('positions')
        ->has('top.time.slowest', 2)
        ->has('top.value.SP2K.largest', 1)
        ->where('top.vendor.by_count.0.vendor', 'PT Vendor R9')
        ->missing('rows'));
});

it('menjalankan DemoSeeder sampai tuntas', function () {
    $this->seed(DemoSeeder::class);
    expect(Procurement::query()->count())->toBe(22)
        ->and(Procurement::query()->where('cancel_type', 'gagal')->count())->toBe(2)
        ->and(Procurement::query()->where('cancel_type', 'batal')->count())->toBe(2)
        ->and(Procurement::query()->whereNotNull('pa_number')->count())->toBe(2)
        ->and(CustomerContract::query()->where('kind', 'PA (Project Activation)')->exists())->toBeFalse();
    $p3 = Procurement::query()->where('spp_number', 'SPP/2026/BJB/000024')->firstOrFail();
    expect($p3->workContract?->number)->toBe('KTR/ICP-TAN/2026/0051');
    $this->get('/?periode=semua')->assertOk();
    $this->get("/pengadaan/{$p3->code}")->assertOk();
});

it('mencatat alert pengadaan (sebelumnya selalu kosong)', function () {
    $this->post('/pengadaan', r9Payload(['customer_contract_ids' => [], 'pa_number' => '', 'draft' => true]))->assertSessionHasNoErrors();
    $p = Procurement::query()->firstOrFail();
    $p->update(['is_draft' => false]);
    app(WorkflowService::class)->register($p->fresh());
    $alerts = app(ProcurementInsight::class)->alerts($p->fresh()->load(ProcurementInsight::WITH));
    expect(collect($alerts)->pluck('code')->all())->toContain('basis');
});

it('menentukan Dibatalkan sebelum unit pengadaan dan Gagal setelahnya, tanpa menghapus data', function () {
    $this->post('/pengadaan', r9Payload())->assertSessionHasNoErrors();
    $p = Procurement::query()->firstOrFail();
    expect(WorkflowService::stopTypeFor($p))->toBe('batal');

    // Payload uji memakai tahap Pelaksana Pengadaan setelah Pengaju.
    $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'mode' => 'complete', 'date' => now()->toDateString(), 'activity' => 'Review Dokumen'])->assertSessionHasNoErrors();
    expect(WorkflowService::stopTypeFor($p->fresh()))->toBe('gagal');

    $this->post("/pengadaan/{$p->code}/batal", ['reason' => 'Tidak ada penawaran'])->assertSessionHasNoErrors();
    $p->refresh();
    expect($p->cancel_type)->toBe('gagal')
        ->and(app(ProcurementInsight::class)->status($p->load(ProcurementInsight::WITH)))->toBe('Gagal');
    $this->post("/pengadaan/{$p->code}/batal", ['reason' => 'Dua kali'])->assertSessionHasErrors('reason');

    $this->get('/?periode=semua')->assertInertia(fn (Assert $page) => $page->where('stopped.gagal', 1)->where('stopped.batal', 0)->where('kpi.active', 0));
    $this->get('/pengadaan')->assertInertia(fn (Assert $page) => $page->where('rows.0.cancel_type', 'gagal'));

    // Tidak ada rute untuk menghapus pengadaan.
    $this->delete("/pengadaan/{$p->code}")->assertStatus(405);
    expect(Procurement::query()->count())->toBe(1);

    $this->post("/pengadaan/{$p->code}/aktifkan")->assertSessionHasNoErrors();
    expect($p->fresh()->cancel_type)->toBeNull();
});

it('menolak pembatalan setelah SP2K/PO rilis', function () {
    $this->post('/pengadaan', r9Payload())->assertSessionHasNoErrors();
    $p = Procurement::query()->firstOrFail();
    r9Release($p);
    $this->post("/pengadaan/{$p->code}/batal", ['reason' => 'Coba batal'])->assertSessionHasErrors('reason');
});
