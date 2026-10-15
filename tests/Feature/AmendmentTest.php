<?php

use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Procurement;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->actingAs(User::query()->where('email', 'admin@procfolio.local')->firstOrFail());
    $vendor = Company::query()->create(['name' => 'PT Vendor Amandemen', 'type' => 'Vendor', 'active' => true]);
    $customer = Company::query()->create(['name' => 'PT Pelanggan Amandemen', 'type' => 'Pelanggan', 'active' => true]);
    $bakb = CustomerContract::query()->create(['number' => 'BAKB/AMD/001', 'title' => 'Kontrak', 'customer_id' => $customer->id, 'date' => '2026-01-05']);
    $code = BudgetCode::query()->first();
    $this->post('/pengadaan', [
        'draft' => false, 'method' => 'SPP Pusat', 'title' => 'Pengadaan Amandemen', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Pengadaan Langsung', 'priority' => 'Normal',
        'registered_at' => '2026-01-05', 'spp_number' => 'SPP/2026/BJB/000009', 'spp_date' => '2026-01-02', 'pic_pengaju_id' => App\Models\Pic::query()->value('id'),
        'candidate_vendor_ids' => [$vendor->id], 'customer_contract_ids' => [$bakb->id], 'budget_code_id' => $code->id,
        'rab_items' => [['description' => 'UJI', 'budget_type' => 'OPEX', 'qty' => 1, 'unit' => 'Paket', 'price' => 5000000]],
        'documents' => [['doc_type' => 'TOR (Term of Reference)', 'required' => true, 'available' => true], ['doc_type' => 'BCR (Benefit Cost Ratio)', 'required' => true, 'available' => true]],
        'workflow' => [['key' => 'pengaju', 'name' => 'Pengaju', 'group' => 'Pengaju', 'enabled' => true], ['key' => 'anggaran', 'name' => 'Anggaran', 'group' => 'Anggaran', 'enabled' => true], ['key' => 'pengadaan_mgr', 'name' => 'Manager Pengadaan', 'group' => 'Manager Pengadaan', 'enabled' => true], ['key' => 'lakdan', 'name' => 'Pelaksana Pengadaan', 'group' => 'Pelaksana Pengadaan', 'enabled' => true]],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->p = Procurement::query()->firstOrFail();
    $this->post("/pengadaan/{$this->p->code}/kontrak", [
        'kind' => 'SP2K', 'number' => 'SP2K/AMD/001', 'title' => 'Uji', 'vendor_id' => $vendor->id, 'date' => '2026-01-15', 'kickoff_date' => '2026-01-19',
        'value_excl' => 5000000, 'value_incl' => 5500000, 'close_stage' => true,
        'terms' => [['name' => 'Pelunasan', 'planned_end' => '2026-02-20', 'percent' => 100]],
    ])->assertSessionHasNoErrors();
});

it('membuka kembali Proses Pengadaan hanya melalui amandemen lalu menguncinya lagi', function () {
    $this->post("/pengadaan/{$this->p->code}/amandemen", [
        'reason' => 'Penambahan ruang lingkup pekerjaan', 'change_types' => ['Nilai'], 'stages' => ['pengaju', 'anggaran', 'lakdan'], 'date' => '2026-01-25',
    ])->assertSessionHasNoErrors();

    $am = $this->p->contract->amendments()->firstOrFail();
    expect($am->status)->toBe('Dalam Proses')
        ->and($this->p->visits()->whereNotNull('amendment_id')->where('status', 'Dalam Proses')->value('stage_key'))->toBe('pengaju');

    foreach (['2026-01-27', '2026-01-29'] as $date) {
        $this->post("/pengadaan/{$this->p->code}/proses", ['track' => 'pengadaan', 'mode' => 'complete', 'date' => $date, 'activity' => 'Review Dokumen']);
    }
    $this->post("/pengadaan/{$this->p->code}/proses", ['track' => 'pengadaan', 'mode' => 'complete', 'date' => '2026-02-02', 'activity' => 'Negosiasi']);

    $this->post("/pengadaan/{$this->p->code}/amandemen/{$am->id}/rilis", [
        'number' => 'AMD/SP2K/001', 'date' => '2026-02-03', 'change_types' => ['Nilai'], 'description' => 'Penambahan ruang lingkup',
        'value_excl' => 6000000, 'value_incl' => 6600000,
    ])->assertSessionHasNoErrors();

    $this->p->refresh();
    expect($am->fresh()->status)->toBe('Rilis')
        ->and($this->p->contract->fresh()->value_incl)->toEqual('6600000.00')
        ->and($this->p->visits()->where('status', 'Dalam Proses')->where('track', 'pengadaan')->count())->toBe(0);
});

it('menolak amandemen kedua saat satu amandemen masih berjalan', function () {
    $d = ['reason' => 'Perubahan jangka waktu', 'change_types' => ['Jangka Waktu'], 'stages' => ['pengaju'], 'date' => '2026-01-25'];
    $this->post("/pengadaan/{$this->p->code}/amandemen", $d)->assertSessionHasNoErrors();
    $this->post("/pengadaan/{$this->p->code}/amandemen", $d)->assertSessionHasErrors('reason');
});
