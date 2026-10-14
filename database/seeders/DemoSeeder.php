<?php

namespace Database\Seeders;

use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Pic;
use App\Models\Pks;
use App\Models\Procurement;
use App\Models\WorkflowTemplate;
use App\Services\Numbering;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk UAT. Seluruh perubahan status dibuat lewat WorkflowService
 * sehingga riwayat, durasi dan audit log konsisten dengan pemakaian nyata.
 */
class DemoSeeder extends Seeder
{
    public function run(WorkflowService $wf, Numbering $numbering): void
    {
        $c = fn (string $name, string $type) => Company::query()->firstOrCreate(['name' => $name], ['type' => $type, 'active' => true]);
        $icon = $c('PT Indonesia Comnets Plus', 'Internal');
        $ndu = $c('PT Nusantara Digital Utama', 'Pelanggan');
        $bas = $c('PT Bank Arunika Sejahtera', 'Pelanggan');
        $mip = $c('PT Mitra Integrasi Prima', 'Mitra');
        $sln = $c('PT Solusi Lisensi Nusantara', 'Vendor');
        $tan = $c('PT Teknologi Aplikasi Nusantara', 'Vendor');
        $gpm = $c('PT Global Perangkat Lunak Mandiri', 'Vendor');

        $pic = fn (string $name, string $title, string $role, Company $co, ?string $sub = null) => Pic::query()->firstOrCreate(['name' => $name], [
            'title' => $title, 'role' => $role, 'company_id' => $co->id, 'sub_bidang' => $sub, 'active' => true,
            'email' => strtolower(explode(' ', $name)[0]).'@contoh.co.id',
        ]);
        $dewi = $pic('Dewi Anggraini', 'Solution Architect', 'User Pengaju', $icon, 'PLN 1');
        $bagus = $pic('Bagus Wicaksono', 'Solution Architect', 'User Pengaju', $icon, 'PLN 2');
        $rina = $pic('Rina Kusuma', 'Analis Anggaran', 'Staff Anggaran', $icon);
        $arif = $pic('Arif Setiawan', 'Supervisor Pelaksana Pengadaan', 'SPV Lakdan', $icon);
        $pic('Sari Wulandari', 'Manager Sub Bidang PLN 1', 'Manager Pengaju', $icon, 'PLN 1');
        $pic('Yudi Pratama', 'Manager Anggaran', 'Manager Anggaran', $icon);
        $pic('Rudi Hartono', 'Manager Pengadaan', 'Manager Pengadaan', $icon);
        $hendra = $pic('Hendra Gunawan', 'Officer Partnership', 'Partnership', $icon);
        $novi = $pic('Novi Andriani', 'Verifikator Invoice', 'Finance', $icon);
        $teguh = $pic('Teguh Santoso', 'Manajer Sub Bidang PLN 1', 'Penandatangan', $icon, 'PLN 1');
        $lina = $pic('Lina Marlina', 'Project Manager', 'Penandatangan Vendor', $tan);

        $bakb = CustomerContract::query()->firstOrCreate(['number' => 'BAKB/2026/011'], ['kind' => 'BAKB', 'title' => 'Layanan Digital Workplace & Kolaborasi Kantor Cabang', 'customer_id' => $ndu->id, 'date' => now()->subMonths(3)]);
        $bakb2 = CustomerContract::query()->firstOrCreate(['number' => 'MoM/2026/017'], ['kind' => 'MoM Kesepakatan Harga', 'title' => 'MoM Kesepakatan Harga Pengembangan Aplikasi Perbankan', 'customer_id' => $bas->id, 'date' => now()->subMonths(4)]);
        $pks = Pks::query()->firstOrCreate(['number' => 'PKS/2025/009'], ['title' => 'Kerja Sama Pengembangan & Pemeliharaan Aplikasi', 'partner_id' => $mip->id, 'date' => now()->subYear(), 'start_date' => now()->subYear(), 'end_date' => now()->addYear(), 'scope' => 'Pengembangan aplikasi custom dan change request.']);
        $lic = BudgetCode::query()->where('code', 'IC02122025')->firstOrFail();
        $pen = BudgetCode::query()->where('code', 'IC00592026')->firstOrFail();
        // Pagu RKAP contoh; PRK penugasan sengaja dibuat terlampaui agar indikator minus terlihat.
        $lic->update(['budget_amount' => 5000000000, 'year' => 2026]);
        $pen->update(['budget_amount' => 2000000000, 'year' => 2026]);

        $d = fn (int $daysAgo) => now()->subWeekdays($daysAgo)->toDateString();
        // Selesaikan tahap Proses Pengadaan berurutan sampai tahap aktif = $key; tanggal maju satu hari kerja per tahap.
        $advanceTo = function (Procurement $p, string $key, int $from, int $to) use ($wf) {
            for ($i = 0, $n = $from; $i < 20; $i++, $n = max($to, $n - 1)) {
                $cur = $p->visits()->where('track', 'pengadaan')->where('status', 'Dalam Proses')->latest('id')->first();
                if (! $cur || $cur->stage_key === $key) {
                    return;
                }
                $wf->updateProcess($p, ['track' => 'pengadaan', 'mode' => 'complete', 'activity' => 'Approval', 'date' => now()->subWeekdays($n)->toDateString()]);
            }
        };
        $make = function (array $attr, array $ccIds, array $vendors, BudgetCode $code, string $desc, float $qty, string $unit, float $price) use ($numbering, $wf) {
            $p = Procurement::query()->create([
                ...$attr, 'code' => $numbering->nextProcurementCode((int) substr($attr['registered_at'], 0, 4)),
                'rab_value' => $qty * $price,
                'workflow' => $attr['method'] === 'SPP' ? WorkflowService::applyRouting(WorkflowTemplate::stagesFor('SPP'), $attr['proc_method'] ?? null) : WorkflowTemplate::stagesFor($attr['method']),
            ]);
            $p->customerContracts()->sync($ccIds);
            $p->candidateVendors()->sync($vendors);
            $p->budgetCodes()->sync([$code->id]);
            $p->rabItems()->create(['description' => $desc, 'budget_type' => 'OPEX', 'budget_code_id' => $code->id, 'qty' => $qty, 'unit' => $unit, 'price' => $price]);
            foreach ($attr['method'] === 'SPP' ? ['TOR', 'BCR (Benefit Cost Ratio)'] : ['BAK', 'BCR (Benefit Cost Ratio)'] as $t) {
                $p->documents()->create(['doc_type' => $t, 'title' => $t, 'number' => $t === 'TOR' ? 'TOR/2026/'.random_int(10, 99) : null, 'required' => true, 'available' => $t === 'TOR']);
            }
            $wf->register($p);

            return $p;
        };

        // 1) SPP Urgent, sedang di Pelaksana Pengadaan (durasi merah)
        $p1 = $make(['spp_number' => 'SPP/2026/BJB/000041', 'spp_date' => $d(18), 'title' => 'Pengadaan Lisensi Microsoft 365 E3 untuk 1.200 Pengguna', 'method' => 'SPP', 'proc_type' => 'Barang', 'proc_method' => 'Tender Terbatas', 'priority' => 'Urgent', 'sub_bidang' => 'PLN 1', 'pengaju_name' => $dewi->name, 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(16)],
            [$bakb->id], [$sln->id, $gpm->id], $lic, 'LISENSI MICROSOFT 365 E3 SUBSCRIPTION 12 BULAN', 1200, 'User', 1450000);
        $advanceTo($p1, 'lakdan_spv', 15, 3);
        $wf->updateProcess($p1, ['track' => 'pengadaan', 'mode' => 'update', 'activity' => 'Negosiasi', 'date' => $d(2), 'note' => 'Negosiasi harga putaran 1.']);

        // 2) SPP dengan SP2K rilis, tahap 1 di Pelaksanaan Pekerjaan
        $p2 = $make(['spp_number' => 'SPP/2026/BJB/000024', 'spp_date' => $d(45), 'title' => 'Pengembangan Aplikasi Customer Self-Service Portal', 'method' => 'SPP', 'proc_type' => 'Jasa', 'proc_method' => 'Tender Terbuka', 'priority' => 'Normal', 'sub_bidang' => 'PLN 1', 'pengaju_name' => $dewi->name, 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(43)],
            [$bakb2->id], [$tan->id, $gpm->id], $pen, 'JASA PENGEMBANGAN CUSTOMER SELF-SERVICE PORTAL', 1, 'LOT (SERVICE & MATERIAL)', 1450000000);
        $advanceTo($p2, 'lakdan_staff', 42, 31);
        $wf->updateProcess($p2, ['track' => 'pengadaan', 'mode' => 'complete', 'activity' => 'SP2K Rilis', 'date' => $d(30)]);
        $wf->releaseContract($p2, [
            'kind' => 'SP2K', 'number' => 'SP2K/ICP/2026/0188', 'title' => $p2->title, 'vendor_id' => $tan->id, 'date' => $d(30), 'kickoff_date' => $d(28),
            'value_excl' => 1250000000, 'value_incl' => 1387500000, 'scope' => 'Analisis, desain, development, SIT, UAT dan go-live.', 'close_stage' => true,
            'terms' => [
                ['name' => 'Dokumen analisis & desain disetujui (BAPS)', 'planned_end' => now()->addDays(10)->toDateString(), 'percent' => 30],
                ['name' => 'Development & SIT selesai (BA Rekon)', 'planned_end' => now()->addDays(80)->toDateString(), 'percent' => 50],
                ['name' => 'UAT & go-live (BAST)', 'planned_end' => now()->addDays(120)->toDateString(), 'percent' => 20],
            ],
        ]);
        $t1 = $p2->terms()->where('seq', 1)->first();
        $ba = $p2->beritaAcara()->create(['sub_bidang' => 'PLN 1', 'type' => 'BAPS', 'number' => '40712.BA/STI.01.03/IC010208/2026', 'contract_ref_type' => 'SP2K', 'contract_ref_number' => 'SP2K/ICP/2026/0188', 'title' => 'Pemeriksaan Dokumen Analisis & Desain Sistem', 'date' => $d(2), 'first_party' => 'PT Indonesia Comnets Plus', 'second_party' => $tan->name]);
        $ba->terms()->sync([$t1->id]);
        $round = $ba->rounds()->create(['seq' => 1, 'sent_at' => $d(2), 'context' => 'Sirkuler awal']);
        $round->signatories()->create(['pic_id' => $teguh->id, 'sent_at' => $d(2), 'status' => 'Selesai TTD', 'signed_at' => $d(1)]);
        $round->signatories()->create(['pic_id' => $lina->id, 'sent_at' => $d(2), 'status' => 'Proses TTD']);

        // 3) Kemitraan di Anggaran
        $p3 = $make(['spp_number' => 'PGJ/2026/BJB/000033', 'spp_date' => $d(8), 'title' => 'Change Request Aplikasi ERP — Modul e-Approval Pengadaan', 'method' => 'Kemitraan', 'proc_type' => 'Jasa', 'priority' => 'Normal', 'sub_bidang' => 'PLN 2', 'pengaju_name' => $bagus->name, 'pic_pengaju_id' => $bagus->id, 'registered_at' => $d(6), 'pks_id' => $pks->id, 'bak_number' => 'BAK/2026/031', 'bak_date' => $d(7)],
            [$bakb2->id], [$mip->id], $pen, 'CHANGE REQUEST CR-031 MODUL E-APPROVAL PENGADAAN', 1, 'LOT (SERVICE & MATERIAL)', 860000000);
        $advanceTo($p3, 'anggaran_mgr', 5, 3);
    }
}
