<?php

use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Procurement;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->user = User::query()->where('email', 'admin@procfolio.local')->firstOrFail();
    $this->actingAs($this->user);
    $this->vendor = Company::query()->create(['name' => 'PT Vendor Uji', 'type' => 'Vendor', 'active' => true]);
    $this->customer = Company::query()->create(['name' => 'PT Pelanggan Uji', 'type' => 'Pelanggan', 'active' => true]);
    $this->bakb = CustomerContract::query()->create(['number' => 'BAKB/UJI/001', 'title' => 'Kontrak uji', 'customer_id' => $this->customer->id, 'date' => '2026-01-05']);
    $this->code = BudgetCode::query()->first();
});

function payload(array $over = []): array
{
    return array_merge([
        'draft' => false, 'method' => 'SPP', 'title' => 'Pengadaan Lisensi Uji', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Pengadaan Langsung',
        'priority' => 'Normal', 'sub_bidang' => 'PLN 1', 'registered_at' => '2026-01-05', 'spp_number' => 'SPP/2026/BJB/000001', 'spp_date' => '2026-01-02',
        'pic_pengaju_id' => App\Models\Pic::query()->value('id'), 'candidate_vendor_ids' => [test()->vendor->id], 'customer_contract_ids' => [test()->bakb->id],
        'budget_code_id' => test()->code->id,
        'rab_items' => [['description' => 'LISENSI UJI', 'budget_type' => 'OPEX', 'qty' => 10, 'unit' => 'User', 'price' => 1000000]],
        'documents' => [['doc_type' => 'TOR', 'required' => true, 'available' => true, 'number' => 'TOR/001'], ['doc_type' => 'BCR (Benefit Cost Ratio)', 'required' => true, 'available' => false]],
        'workflow' => [
            ['key' => 'pengaju', 'name' => 'Pengaju', 'enabled' => true], ['key' => 'anggaran', 'name' => 'Anggaran', 'enabled' => true],
            ['key' => 'rendan', 'name' => 'Perencana Pengadaan', 'enabled' => false], ['key' => 'lakdan', 'name' => 'Pelaksana Pengadaan', 'enabled' => true],
        ],
    ], $over);
}

it('menolak pengadaan tanpa perjanjian pelanggan', function () {
    $this->post('/pengadaan', payload(['customer_contract_ids' => []]))->assertSessionHasErrors('customer_contract_ids');
});

it('menolak SPP tanpa nomor SPP, termasuk draft', function () {
    $this->post('/pengadaan', payload(['spp_number' => '', 'draft' => true]))->assertSessionHasErrors('spp_number');
});

it('mendaftarkan pengadaan dan memulai tahap Pengaju', function () {
    $this->post('/pengadaan', payload())->assertSessionHasNoErrors()->assertRedirect();
    $p = Procurement::query()->firstOrFail();

    expect($p->rab_value)->toEqual('10000000.00')
        ->and($p->visits()->where('status', 'Dalam Proses')->value('stage_key'))->toBe('pengaju');
});

it('mengunci Proses Pengadaan setelah SP2K rilis dan membentuk alur per tahap pembayaran', function () {
    $this->post('/pengadaan', payload())->assertSessionHasNoErrors()->assertRedirect();
    $p = Procurement::query()->firstOrFail();
    $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'mode' => 'complete', 'date' => '2026-01-07', 'activity' => 'Review Dokumen']);
    $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'mode' => 'complete', 'date' => '2026-01-12', 'activity' => 'Approval']);

    $this->post("/pengadaan/{$p->code}/kontrak", [
        'kind' => 'SP2K', 'number' => 'SP2K/UJI/001', 'title' => 'Pengadaan Lisensi Uji', 'vendor_id' => $this->vendor->id, 'date' => '2026-01-15',
        'kickoff_date' => '2026-01-19', 'value_excl' => 9000000, 'value_incl' => 9900000, 'close_stage' => true,
        'terms' => [
            ['name' => 'Aktivasi lisensi (BAPS)', 'planned_end' => '2026-02-10', 'percent' => 60],
            ['name' => 'Serah terima akhir (BAST)', 'planned_end' => '2026-03-10', 'percent' => 40],
        ],
    ])->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->terms()->count())->toBe(2)
        ->and($p->contract->end_date->toDateString())->toBe('2026-03-10')
        ->and($p->visits()->where('track', 'pasca')->where('stage_key', 'pelaksanaan')->count())->toBe(2);

    // Tahap 1 mulai di kick off, tahap 2 sehari setelah rencana selesai tahap 1.
    $starts = $p->visits()->where('track', 'pasca')->orderBy('id')->pluck('entered_at')->map(fn ($d) => $d->toDateString());
    expect($starts->all())->toBe(['2026-01-19', '2026-02-11']);

    // Proses Pengadaan terkunci.
    $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'mode' => 'update', 'date' => '2026-01-20'])->assertSessionHasErrors('mode');
});

it('tidak menahan BA Penutupan dan mewajibkan detail GR saat Proses GR diselesaikan', function () {
    $this->post('/pengadaan', payload())->assertSessionHasNoErrors()->assertRedirect();
    $p = Procurement::query()->firstOrFail();
    $this->post("/pengadaan/{$p->code}/kontrak", [
        'kind' => 'SP2K', 'number' => 'SP2K/UJI/002', 'title' => 'Uji', 'vendor_id' => $this->vendor->id, 'date' => '2026-01-15',
        'kickoff_date' => '2026-01-19', 'value_excl' => 1000000, 'value_incl' => 1100000, 'close_stage' => true,
        'terms' => [['name' => 'Pelunasan (BAST)', 'planned_end' => '2026-02-10', 'percent' => 100]],
    ])->assertSessionHasNoErrors();
    $term = $p->terms()->firstOrFail();
    $base = ['track' => 'pasca', 'term_id' => $term->id, 'mode' => 'complete'];
    $this->post("/pengadaan/{$p->code}/proses", $base + ['date' => '2026-02-10', 'activity' => 'Pekerjaan selesai'])->assertSessionHasNoErrors();
    // BA Penutupan tidak lagi menunggu status TTD.
    $this->post("/pengadaan/{$p->code}/proses", $base + ['date' => '2026-02-11', 'activity' => 'BA Fully Sign'])->assertSessionHasNoErrors();
    // Proses GR wajib detail GR.
    $this->post("/pengadaan/{$p->code}/proses", $base + ['date' => '2026-02-12'])->assertSessionHasErrors(['gr_number', 'gr_type', 'gr_amount', 'gr_link']);
    $this->post("/pengadaan/{$p->code}/proses", $base + ['date' => '2026-02-12', 'gr_number' => 'GR-001', 'gr_type' => 'BAST', 'gr_amount' => 1100000, 'gr_link' => 'folder/gr-001'])
        ->assertSessionHasNoErrors();
    expect($term->goodsReceipt()->value('number'))->toBe('GR-001');
});

it('menandai tahap dibayar dan pengadaan selesai saat Antri Bayar ditutup', function () {
    $this->post('/pengadaan', payload())->assertSessionHasNoErrors()->assertRedirect();
    $p = Procurement::query()->firstOrFail();
    $this->post("/pengadaan/{$p->code}/kontrak", [
        'kind' => 'SP2K', 'number' => 'SP2K/UJI/003', 'title' => 'Uji', 'vendor_id' => $this->vendor->id, 'date' => '2026-01-15',
        'kickoff_date' => '2026-01-19', 'value_excl' => 1000000, 'value_incl' => 1100000, 'close_stage' => true,
        'terms' => [['name' => 'Pelunasan (BAST)', 'planned_end' => '2026-02-10', 'percent' => 100]],
    ])->assertSessionHasNoErrors();
    $term = $p->terms()->firstOrFail();
    $this->post("/pengadaan/{$p->code}/ba", ['type' => 'BAST', 'title' => 'Serah terima', 'number' => 'BA/UJI/001', 'date' => '2026-02-10', 'term_ids' => [$term->id]]);
    $ba = $p->beritaAcara()->firstOrFail();
    $this->post("/ba/{$ba->id}/koreksi", ['status' => 'TTD Selesai', 'reason' => 'Dokumen fisik lengkap']);

    foreach (['pelaksanaan', 'ba', 'gr', 'invoice', 'verifikasi', 'antri'] as $i => $stage) {
        $gr = $stage === 'gr' ? ['gr_number' => 'GR-UJI-9', 'gr_type' => 'BAST', 'gr_amount' => 1100000, 'gr_link' => 'https://contoh/gr'] : [];
        $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pasca', 'term_id' => $term->id, 'mode' => 'complete', 'date' => '2026-02-'.str_pad((string) (10 + $i), 2, '0', STR_PAD_LEFT)] + $gr)
            ->assertSessionHasNoErrors();
    }

    expect($term->fresh()->paid_at->toDateString())->toBe('2026-02-15')
        ->and(app(App\Services\ProcurementInsight::class)->status($p->fresh()->load(App\Services\ProcurementInsight::WITH)))->toBe('Selesai');
});

it('Revisi Minor kembali ke pengaju lalu langsung ke tahap asal tanpa mengulang approval', function () {
    $this->post('/pengadaan', payload())->assertSessionHasNoErrors()->assertRedirect();
    $p = Procurement::query()->firstOrFail();
    $go = fn (array $d) => $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'activity' => 'Review Dokumen'] + $d);
    $go(['mode' => 'complete', 'date' => '2026-01-06']);
    $go(['mode' => 'complete', 'date' => '2026-01-07']); // sekarang di lakdan
    $go(['mode' => 'skip', 'date' => '2026-01-08', 'note' => 'coba lewati'])->assertSessionHasErrors('mode');
    $go(['mode' => 'rev_minor', 'date' => '2026-01-08', 'note' => 'TOR perlu dilengkapi'])->assertSessionHasNoErrors();
    expect($p->visits()->where('status', 'Dalam Proses')->value('stage_key'))->toBe('pengaju');
    $go(['mode' => 'complete', 'date' => '2026-01-09'])->assertSessionHasNoErrors();
    expect($p->visits()->where('status', 'Dalam Proses')->value('stage_key'))->toBe('lakdan')
        ->and($p->visits()->where('stage_key', 'anggaran')->where('status', 'Dilewati')->exists())->toBeFalse();
});

it('Revisi Mayor mengulang approval dari awal dan menambah siklus', function () {
    $this->post('/pengadaan', payload())->assertSessionHasNoErrors()->assertRedirect();
    $p = Procurement::query()->firstOrFail();
    $go = fn (array $d) => $this->post("/pengadaan/{$p->code}/proses", ['track' => 'pengadaan', 'activity' => 'Review Dokumen'] + $d);
    $go(['mode' => 'complete', 'date' => '2026-01-06']);
    $go(['mode' => 'complete', 'date' => '2026-01-07']);
    $go(['mode' => 'rev_major', 'date' => '2026-01-08', 'note' => 'Spesifikasi berubah total'])->assertSessionHasNoErrors();
    $go(['mode' => 'complete', 'date' => '2026-01-09'])->assertSessionHasNoErrors();
    $p->refresh()->load(App\Services\ProcurementInsight::WITH);
    expect($p->visits()->where('status', 'Dalam Proses')->value('stage_key'))->toBe('anggaran')
        ->and(app(App\Services\ProcurementInsight::class)->approvalCycle($p))->toBe(2);
});
