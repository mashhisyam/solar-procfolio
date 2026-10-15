<?php

namespace Database\Seeders;

use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Pic;
use App\Models\Pks;
use App\Models\Procurement;
use App\Models\StageVisit;
use App\Models\WorkflowTemplate;
use App\Services\Numbering;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk UAT. Semua perubahan status dibuat lewat WorkflowService (alur aplikasi yang sebenarnya),
 * sehingga riwayat tahap, durasi, BA, GR, invoice dan audit log konsisten.
 *
 * Posisi yang dibentuk:
 *  1. Approval di L1/VP Anggaran                  (SPP, Tender Terbatas)
 *  2. Approval di Staff Lakdan                    (SPP, Pengadaan Langsung)
 *  3. Pasca Pengadaan: tahap 1 di BA Penutupan    (SPP, SP2K rilis, 3 tahap)
 *  4. Pasca Pengadaan: tahap 1 di Invoicing       (Kemitraan, PO rilis, GR terbit, bucket terisi)
 *  5. Selesai, seluruh tahap dibayar              (SPP, Pengadaan Langsung)
 */
class DemoSeeder extends Seeder
{
    public function run(WorkflowService $wf, Numbering $numbering): void
    {
        // ---------- organisasi & PIC ----------
        $c = fn (string $name, string $type) => Company::query()->firstOrCreate(['name' => $name], ['type' => $type, 'active' => true]);
        $icon = $c('PT Indonesia Comnets Plus', 'Internal'); // sama dengan ReferenceSeeder
        $ndu = $c('PT NUSANTARA DIGITAL UTAMA', 'Pelanggan');
        $bas = $c('PT BANK ARUNIKA SEJAHTERA', 'Pelanggan');
        $mip = $c('PT MITRA INTEGRASI PRIMA', 'Mitra');
        $sln = $c('PT SOLUSI LISENSI NUSANTARA', 'Vendor');
        $tan = $c('PT TEKNOLOGI APLIKASI NUSANTARA', 'Vendor');
        $gpm = $c('PT GLOBAL PERANGKAT LUNAK MANDIRI', 'Vendor');
        $dap = $c('PT DATA ANALITIKA PRIMA', 'Vendor');

        $pic = fn (string $name, string $role, string $title, Company $co, ?string $sub = null) => Pic::query()->firstOrCreate(['name' => $name], [
            'title' => $title, 'role' => $role, 'company_id' => $co->id, 'sub_bidang' => $sub, 'active' => true,
            'email' => strtolower(explode(' ', $name)[0]).'@contoh.co.id',
        ]);
        $dewi = $pic('Dewi Anggraini', 'User Pengaju', 'Solution Architect', $icon, 'PLN 1');
        $bagus = $pic('Bagus Wicaksono', 'User Pengaju', 'Solution Architect', $icon, 'PLN 2');
        $pic('Sari Wulandari', 'Manager Pengaju', 'Manager Sub Bidang PLN 1', $icon, 'PLN 1');
        $pic('Hari Prasetyo', 'L1/VP Pengaju', 'VP Solution Architecture', $icon);
        $pic('Ratna Dewi', 'Manager Sales', 'Manager Account Management', $icon);
        $pic('Gilang Permana', 'L1/VP Sales', 'VP Sales & Marketing', $icon);
        $pic('Rina Kusuma', 'Staff Anggaran', 'Analis Anggaran', $icon);
        $pic('Yudi Pratama', 'Manager Anggaran', 'Manager Anggaran', $icon);
        $pic('Dimas Saputra', 'L1/VP Anggaran', 'VP Keuangan', $icon);
        $pic('Rudi Hartono', 'Manager Pengadaan', 'Manager Pengadaan', $icon);
        $pic('Wulan Sari', 'SPV Rendan', 'Supervisor Perencana Pengadaan', $icon);
        $pic('Fajar Nugroho', 'Staff Rendan', 'Staff Perencana Pengadaan', $icon);
        $pic('Arif Setiawan', 'SPV Lakdan', 'Supervisor Pelaksana Pengadaan', $icon);
        $pic('Indra Kurniawan', 'Staff Lakdan', 'Staff Pelaksana Pengadaan', $icon);
        $pic('Hendra Gunawan', 'Partnership', 'Officer Partnership', $icon);
        $novi = $pic('Novi Andriani', 'Finance', 'Verifikator Invoice', $icon);
        $teguh = $pic('Teguh Santoso', 'Penandatangan', 'Manager Sub Bidang PLN 1', $icon, 'PLN 1');
        $lina = $pic('Lina Marlina', 'Penandatangan Vendor', 'Project Manager', $tan);
        $roleId = fn (string $role) => Pic::query()->where('role', $role)->value('id');

        // ---------- perjanjian pelanggan, PKS, PRK ----------
        $cc = fn (string $number, string $kind, string $sub, float $revenue, string $title, Company $cust, int $monthsAgo) => CustomerContract::query()->firstOrCreate(['number' => $number],
            ['kind' => $kind, 'sub_bidang' => $sub, 'revenue' => $revenue, 'title' => $title, 'customer_id' => $cust->id, 'date' => now()->subMonths($monthsAgo),
                'link' => 'https://contoh.sharepoint.com/sites/SolArch/Perjanjian/'.str_replace('/', '-', $number).'.pdf']);
        $cc1 = $cc('BAKB/2026/011', 'Berita Acara Kesepakatan Biaya', 'PLN 1', 2600000000, 'LAYANAN DIGITAL WORKPLACE & KOLABORASI KANTOR CABANG', $ndu, 4);
        $cc2 = $cc('KTR/BAS/2026/017', 'Kontrak Perjanjian', 'PLN 1', 3100000000, 'PENGEMBANGAN & PEMELIHARAAN APLIKASI PERBANKAN', $bas, 5);
        $cc3 = $cc('PA/NDU/2026/004', 'PA (Project Activation)', 'PLN 1', 1200000000, 'AKTIVASI PROYEK LAYANAN ANALITIK DATA PELANGGAN', $ndu, 6);
        $cc4 = $cc('SP/PLN2/2026/031', 'Surat Penugasan', 'PLN 2', 1900000000, 'PENUGASAN LAYANAN APLIKASI FIELD SERVICE', $ndu, 3);
        $pks = Pks::query()->firstOrCreate(['number' => 'PKS/2025/009'], ['title' => 'KERJA SAMA PENGEMBANGAN & PEMELIHARAAN APLIKASI', 'partner_id' => $mip->id,
            'date' => now()->subMonths(16), 'start_date' => now()->subMonths(16), 'end_date' => now()->subMonths(4), 'scope' => 'PENGEMBANGAN APLIKASI CUSTOM DAN CHANGE REQUEST.',
            'link' => 'https://contoh.sharepoint.com/sites/SolArch/PKS/PKS-2025-009.pdf']);
        // Tiga amandemen: dua sudah efektif, satu baru berlaku bulan depan.
        $amd = [
            [1, 'AMD-PKS/2026/002', 10, 9, ['Masa Berlaku'], 'PERPANJANGAN MASA BERLAKU 6 BULAN.', now()->addMonths(2), null],
            [2, 'AMD-PKS/2026/014', 5, 4, ['Ruang Lingkup', 'Ketentuan Komersial'], 'PENAMBAHAN DUKUNGAN OPERASIONAL DAN PENYESUAIAN RATE MANDAYS.', null, 'PENGEMBANGAN APLIKASI CUSTOM, CHANGE REQUEST, DAN DUKUNGAN OPERASIONAL.'],
            [3, 'AMD-PKS/2026/021', 1, -1, ['Masa Berlaku', 'Para Pihak'], 'PERPANJANGAN SAMPAI 2028 DAN PERUBAHAN PEJABAT PENANDATANGAN.', now()->addYear()->addMonths(6), null],
        ];
        foreach ($amd as [$seq, $number, $dateAgo, $effAgo, $parts, $desc, $newEnd, $newScope]) {
            $pks->amendments()->firstOrCreate(['number' => $number], ['seq' => $seq, 'date' => now()->subMonths($dateAgo), 'effective_date' => now()->subMonths($effAgo),
                'changed_parts' => $parts, 'description' => $desc, 'new_end_date' => $newEnd, 'new_scope' => $newScope,
                'link' => 'https://contoh.sharepoint.com/sites/SolArch/PKS/'.str_replace('/', '-', $number).'.pdf']);
        }
        // PKS kedua tanpa amandemen, dipakai satu pengadaan.
        $pks2 = Pks::query()->firstOrCreate(['number' => 'PKS/2026/004'], ['title' => 'KERJA SAMA PENYEDIAAN LISENSI DAN DUKUNGAN TEKNIS', 'partner_id' => $mip->id,
            'date' => now()->subMonths(8), 'start_date' => now()->subMonths(8), 'end_date' => now()->addMonths(16), 'scope' => 'PENYEDIAAN LISENSI PERANGKAT LUNAK DAN DUKUNGAN TEKNIS.',
            'link' => 'https://contoh.sharepoint.com/sites/SolArch/PKS/PKS-2026-004.pdf']);
        $lic = BudgetCode::query()->where('code', 'IC02122025')->firstOrFail();
        $pen = BudgetCode::query()->where('code', 'IC00592026')->firstOrFail();
        $ecp = BudgetCode::query()->where('code', 'IC00782025')->firstOrFail();
        // Pagu contoh; PRK penugasan sengaja terlampaui agar indikator minus di RKAP terlihat.
        $lic->update(['budget_amount' => 5000000000, 'year' => 2026, 'sub_bidang' => 'PLN 1']);
        $pen->update(['budget_amount' => 1200000000, 'year' => 2026, 'sub_bidang' => 'PLN 1']);
        $ecp->update(['budget_amount' => 2500000000, 'year' => 2026, 'sub_bidang' => 'PLN 2']);

        // ---------- helper ----------
        $d = fn (int $weekdaysAgo) => now()->subWeekdays($weekdaysAgo)->toDateString();
        $make = function (array $attr, array $ccIds, array $vendors, BudgetCode $code, array $rab, array $docs) use ($numbering, $wf) {
            $flow = $attr['method'] === 'SPP Pusat'
                ? WorkflowService::applyRouting(WorkflowTemplate::stagesFor('SPP Pusat'), $attr['proc_method'] ?? null)
                : WorkflowTemplate::stagesFor('SPP Kemitraan');
            $p = Procurement::query()->create([
                ...$attr, 'code' => $numbering->nextProcurementCode((int) now()->format('Y')),
                'rab_value' => collect($rab)->sum(fn ($r) => $r[2] * $r[4]), 'workflow' => WorkflowService::enforceRequired($flow),
                'pengaju_name' => Pic::query()->whereKey($attr['pic_pengaju_id'])->value('name'),
            ]);
            $p->customerContracts()->sync($ccIds);
            $p->candidateVendors()->sync($vendors);
            $p->budgetCodes()->sync([$code->id]);
            foreach ($rab as $i => [$desc, $type, $qty, $unit, $price]) {
                $p->rabItems()->create(['description' => $desc, 'budget_type' => $type, 'budget_code_id' => $code->id, 'qty' => $qty, 'unit' => $unit, 'price' => $price, 'sort' => $i]);
            }
            foreach ($docs as [$type, $number, $note]) {
                $p->documents()->create(['doc_type' => $type, 'title' => $type, 'number' => $number, 'note' => $note, 'required' => true, 'available' => true,
                    'link' => 'https://contoh.sharepoint.com/sites/SolArch/Pengadaan/'.$p->code.'/'.str_replace(' ', '-', $type).'.pdf']);
            }
            $wf->register($p);

            return $p;
        };
        // Selesaikan tahap Proses Pengadaan berurutan sampai tahap aktif = $key; satu hari kerja per tahap; PIC tahap berikutnya sesuai peran.
        $advanceTo = function (Procurement $p, string $key, int $from, int $floor) use ($wf, $roleId) {
            for ($i = 0, $n = $from; $i < 25; $i++, $n = max($floor, $n - 1)) {
                $p->refresh();
                $cur = $p->visits()->where('track', 'pengadaan')->where('status', 'Dalam Proses')->latest('id')->first();
                if (! $cur || $cur->stage_key === $key) {
                    return;
                }
                $next = WorkflowService::next($p->workflow, $cur->stage_key);
                $wf->updateProcess($p, ['track' => 'pengadaan', 'mode' => 'complete', 'activity' => 'Approval', 'date' => now()->subWeekdays($n)->toDateString(),
                    'target_pic_id' => $next ? $roleId($next['name']) : null]);
            }
        };
        // Selesaikan tahap Pasca Pengadaan aktif pada satu tahap pembayaran.
        $finish = function (Procurement $p, int $termId, string $date, string $activity, array $extra = []) use ($wf, $novi) {
            $cur = StageVisit::query()->where('term_id', $termId)->where('status', 'Dalam Proses')->latest('id')->first();
            $nextPic = in_array($cur?->stage_key, ['gr', 'invoice', 'verifikasi'], true) ? $novi->id : $p->pic_pengaju_id;
            $wf->updateProcess($p, ['track' => 'pasca', 'term_id' => $termId, 'mode' => 'complete', 'date' => $date, 'activity' => $activity, 'target_pic_id' => $nextPic] + $extra);
        };
        $ba = function (Procurement $p, array $termIds, string $type, string $number, string $title, string $date, array $signers) {
            $vc = $p->contract()->first();
            $b = $p->beritaAcara()->create(['sub_bidang' => $p->sub_bidang, 'type' => $type, 'number' => $number, 'contract_ref_type' => $vc->kind, 'contract_ref_number' => $vc->number,
                'title' => $title, 'date' => $date, 'director_internal' => $p->pengaju_name, 'first_party' => 'PT INDONESIA COMNETS PLUS', 'second_party' => $vc->vendor?->name,
                'link' => 'https://contoh.sharepoint.com/sites/SolArch/BA/'.str_replace(['/', '.'], '-', $number).'.pdf']);
            $b->terms()->sync($termIds);
            $round = $b->rounds()->create(['seq' => 1, 'sent_at' => $date, 'context' => 'Sirkuler awal']);
            foreach ($signers as [$pic, $status, $signedAt]) {
                $round->signatories()->create(['pic_id' => $pic->id, 'sent_at' => $date, 'status' => $status, 'signed_at' => $signedAt]);
            }

            return $b;
        };

        // ===== 1) Approval di L1/VP Anggaran =====
        $p1 = $make(['spp_number' => 'SPP/2026/BJB/000041', 'spp_date' => $d(9), 'title' => 'PENGADAAN LISENSI MICROSOFT 365 E3 UNTUK 1.200 PENGGUNA', 'summary' => 'LISENSI KOLABORASI KANTOR CABANG PELANGGAN SELAMA 12 BULAN.',
            'method' => 'SPP Pusat', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Tender Terbatas', 'priority' => 'Urgent', 'sub_bidang' => 'PLN 1', 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(8)],
            [$cc1->id], [$sln->id, $gpm->id], $lic,
            [['LISENSI MICROSOFT 365 E3 SUBSCRIPTION 12 BULAN', 'OPEX', 1200, 'User', 1450000], ['MIGRASI MAILBOX DAN ONBOARDING PENGGUNA', 'OPEX', 1, 'LOT (SERVICE & MATERIAL)', 110000000]],
            [['TOR (Term of Reference)', null, null], ['BCR (Benefit Cost Ratio)', null, 'BCR 142%']]);
        $advanceTo($p1, 'anggaran_vp', 7, 3);

        // ===== 2) Approval di Staff Lakdan =====
        $p2 = $make(['spp_number' => 'SPP/2026/BJB/000038', 'spp_date' => $d(15), 'title' => 'LISENSI ORACLE DATABASE ENTERPRISE SUPPORT 16 CORE', 'summary' => 'PERPANJANGAN DUKUNGAN LISENSI DATABASE UNTUK SISTEM ANALITIK PELANGGAN.',
            'method' => 'SPP Pusat', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Pengadaan Langsung', 'priority' => 'Normal', 'sub_bidang' => 'PLN 1', 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(14)],
            [$cc3->id], [$sln->id, $dap->id], $lic, [['ORACLE DATABASE ENTERPRISE SUPPORT PER CORE - ANNUALLY', 'OPEX', 16, 'Core', 61250000]],
            [['TOR (Term of Reference)', null, null], ['BCR (Benefit Cost Ratio)', null, 'BCR 118%']]);
        $advanceTo($p2, 'lakdan_staff', 13, 6);

        // ===== 3) Pasca Pengadaan: tahap 1 di BA Penutupan =====
        $p3 = $make(['spp_number' => 'SPP/2026/BJB/000024', 'spp_date' => $d(52), 'title' => 'PENGEMBANGAN APLIKASI CUSTOMER SELF-SERVICE PORTAL', 'summary' => 'PORTAL LAYANAN MANDIRI PELANGGAN PERBANKAN: WEB DAN MOBILE.',
            'method' => 'SPP Pusat', 'proc_type' => 'Jasa', 'category' => 'Non Lisensi', 'proc_method' => 'Tender Terbuka', 'priority' => 'Normal', 'sub_bidang' => 'PLN 1', 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(50)],
            [$cc2->id], [$tan->id, $gpm->id], $pen, [['JASA PENGEMBANGAN CUSTOMER SELF-SERVICE PORTAL (WEB & MOBILE)', 'OPEX', 1, 'LOT (SERVICE & MATERIAL)', 1450000000]],
            [['TOR (Term of Reference)', null, null], ['BCR (Benefit Cost Ratio)', null, 'BCR 165%']]);
        $advanceTo($p3, 'lakdan_staff', 48, 37);
        $wf->updateProcess($p3, ['track' => 'pengadaan', 'mode' => 'complete', 'activity' => 'SP2K Rilis', 'date' => $d(36)]);
        $wf->releaseContract($p3, [
            'kind' => 'SP2K', 'number' => 'SP2K/ICP/2026/0188', 'title' => $p3->title, 'vendor_id' => $tan->id, 'date' => $d(36), 'kickoff_date' => $d(34),
            'value_excl' => 1250000000, 'value_incl' => 1387500000, 'scope' => 'ANALISIS, DESAIN, DEVELOPMENT, SIT, UAT DAN GO-LIVE.', 'close_stage' => true,
            'link' => 'https://contoh.sharepoint.com/sites/SolArch/Kontrak/SP2K-ICP-2026-0188.pdf',
            'terms' => [
                ['name' => 'DOKUMEN ANALISIS & DESAIN DISETUJUI (BAPS)', 'planned_end' => $d(8), 'percent' => 30],
                ['name' => 'DEVELOPMENT & SIT SELESAI (BA REKON)', 'planned_end' => now()->addDays(60)->toDateString(), 'percent' => 50],
                ['name' => 'UAT & GO-LIVE (BAST)', 'planned_end' => now()->addDays(105)->toDateString(), 'percent' => 20],
            ],
        ]);
        $p3->update(['work_contract_number' => 'KTR/ICP-TAN/2026/0051', 'work_contract_date' => $d(35),
            'work_contract_link' => 'https://contoh.sharepoint.com/sites/SolArch/Kontrak/KTR-ICP-TAN-2026-0051.pdf']);
        $t31 = $p3->terms()->where('seq', 1)->value('id');
        $finish($p3, $t31, $d(8), 'ANALISIS DAN DESAIN SELESAI 100%');
        $ba($p3, [$t31], 'BAPS', '40712.BA/STI.01.03/IC010208/2026', 'PEMERIKSAAN DOKUMEN ANALISIS & DESAIN SISTEM', $d(6), [[$teguh, 'Selesai TTD', $d(4)], [$lina, 'Proses TTD', null]]);
        $wf->updateProcess($p3, ['track' => 'pasca', 'term_id' => $t31, 'mode' => 'update', 'activity' => 'Sirkuler BA', 'date' => $d(4), 'note' => 'MENUNGGU TTD PIHAK VENDOR.']);

        // ===== 4) Pasca Pengadaan: tahap 1 di Invoicing =====
        $p4 = $make(['spp_number' => 'PGJ/2026/BJB/000025', 'spp_date' => $d(62), 'title' => 'CHANGE REQUEST APLIKASI FIELD SERVICE — MODUL ABSENSI TEKNISI', 'summary' => 'MODUL ABSENSI BERBASIS LOKASI UNTUK TEKNISI LAPANGAN.',
            'method' => 'SPP Kemitraan', 'proc_type' => 'Jasa', 'category' => 'Non Lisensi', 'priority' => 'Urgent', 'sub_bidang' => 'PLN 2', 'pic_pengaju_id' => $bagus->id, 'registered_at' => $d(60),
            'pks_id' => $pks->id, 'bak_number' => 'BAK/2026/022', 'bak_date' => $d(61)],
            [$cc4->id], [$mip->id], $ecp, [['CHANGE REQUEST CR-022 MODUL ABSENSI TEKNISI', 'OPEX', 1, 'LOT (SERVICE & MATERIAL)', 265000000]],
            [['BAK', 'BAK/2026/022', null], ['BCR (Benefit Cost Ratio)', null, 'BCR 122%']]);
        $advanceTo($p4, 'partnership', 59, 52);
        $wf->updateProcess($p4, ['track' => 'pengadaan', 'mode' => 'complete', 'activity' => 'PO Rilis', 'date' => $d(48)]);
        $wf->releaseContract($p4, [
            'kind' => 'PO', 'number' => 'PO/ICP/2026/4500198', 'title' => $p4->title, 'vendor_id' => $mip->id, 'date' => $d(48), 'kickoff_date' => $d(45),
            'value_excl' => 238738739, 'value_incl' => 265000000, 'scope' => 'MODUL ABSENSI TEKNISI DAN INTEGRASI APLIKASI FIELD SERVICE.', 'close_stage' => true,
            'link' => 'https://contoh.sharepoint.com/sites/SolArch/Kontrak/PO-ICP-2026-4500198.pdf',
            'terms' => [
                ['name' => 'MODUL ABSENSI TAHAP 1 DITERIMA (BAST)', 'planned_end' => $d(25), 'percent' => 50],
                ['name' => 'INTEGRASI & GO-LIVE (BAST)', 'planned_end' => now()->addDays(15)->toDateString(), 'percent' => 50],
            ],
        ]);
        $t41 = $p4->terms()->where('seq', 1)->value('id');
        $finish($p4, $t41, $d(25), 'MODUL ABSENSI TAHAP 1 SELESAI');
        $ba($p4, [$t41], 'BAST', '40655.BA/STI.01.03/IC010208/2026', 'SERAH TERIMA MODUL ABSENSI TEKNISI TAHAP 1', $d(24), [[$teguh, 'Selesai TTD', $d(23)], [$bagus, 'Selesai TTD', $d(22)]]);
        $finish($p4, $t41, $d(22), 'BA Fully Sign');
        $finish($p4, $t41, $d(18), 'Posting GR', ['gr_number' => '5000418871', 'gr_type' => 'BAST', 'gr_amount' => 132500000, 'gr_link' => 'https://contoh.sharepoint.com/sites/SolArch/GR/5000418871.pdf']);
        $wf->updateProcess($p4, ['track' => 'pasca', 'term_id' => $t41, 'mode' => 'update', 'activity' => 'Kelengkapan Dokumen', 'date' => $d(10), 'note' => 'FAKTUR PAJAK MASIH DITUNGGU DARI MITRA.',
            'bucket_number' => '5100027781', 'bucket_code' => 'BKT-2026-0915', 'bucket_link' => 'https://contoh.sharepoint.com/sites/Finance/Bucket/BKT-2026-0915']);

        // ===== 4b) Kemitraan kedua pada PKS yang sama: approval di Manager Sales =====
        $p4b = $make(['spp_number' => 'PGJ/2026/BJB/000033', 'spp_date' => $d(6), 'title' => 'CHANGE REQUEST APLIKASI ERP — MODUL E-APPROVAL PENGADAAN', 'summary' => 'PENAMBAHAN ALUR PERSETUJUAN ELEKTRONIK UNTUK PENGADAAN PELANGGAN.',
            'method' => 'SPP Kemitraan', 'proc_type' => 'Jasa', 'category' => 'Non Lisensi', 'priority' => 'Normal', 'sub_bidang' => 'PLN 2', 'pic_pengaju_id' => $bagus->id, 'registered_at' => $d(5),
            'pks_id' => $pks->id, 'bak_number' => 'BAK/2026/031', 'bak_date' => $d(6)],
            [$cc4->id], [$mip->id], $ecp, [['CHANGE REQUEST CR-031 MODUL E-APPROVAL PENGADAAN', 'OPEX', 1, 'LOT (SERVICE & MATERIAL)', 860000000]],
            [['BAK', 'BAK/2026/031', null], ['BCR (Benefit Cost Ratio)', null, 'BCR 130%']]);
        $advanceTo($p4b, 'sales_mgr', 4, 1);

        // ===== 4c) Kemitraan pada PKS kedua: approval di L1/VP Pengaju =====
        $p4c = $make(['spp_number' => 'PGJ/2026/BJB/000036', 'spp_date' => $d(4), 'title' => 'PERPANJANGAN LISENSI MONITORING INFRASTRUKTUR 24 BULAN', 'summary' => 'PERPANJANGAN LISENSI PEMANTAUAN INFRASTRUKTUR LAYANAN PELANGGAN.',
            'method' => 'SPP Kemitraan', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'priority' => 'Normal', 'sub_bidang' => 'PLN 2', 'pic_pengaju_id' => $bagus->id, 'registered_at' => $d(3),
            'pks_id' => $pks2->id, 'bak_number' => 'BAK/2026/035', 'bak_date' => $d(4)],
            [$cc4->id], [$mip->id], $ecp, [['LISENSI MONITORING INFRASTRUKTUR - 24 BULAN', 'OPEX', 1, 'LOT (SERVICE & MATERIAL)', 415000000]],
            [['BAK', 'BAK/2026/035', null], ['BCR (Benefit Cost Ratio)', null, 'BCR 124%']]);
        $advanceTo($p4c, 'pengaju_vp', 2, 1);

        // ===== 5) Selesai: seluruh tahap dibayar =====
        $p5 = $make(['spp_number' => 'SPP/2026/BJB/000019', 'spp_date' => $d(90), 'title' => 'LISENSI TABLEAU CREATOR 50 PENGGUNA', 'summary' => 'LISENSI VISUALISASI DATA UNTUK DASHBOARD PELANGGAN.',
            'method' => 'SPP Pusat', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Pengadaan Langsung', 'priority' => 'Normal', 'sub_bidang' => 'PLN 1', 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(88)],
            [$cc3->id], [$sln->id], $lic, [['TABLEAU CREATOR - ANNUALLY', 'OPEX', 50, 'User', 7200000]],
            [['TOR (Term of Reference)', null, null], ['BCR (Benefit Cost Ratio)', null, 'BCR 151%']]);
        $advanceTo($p5, 'lakdan_staff', 86, 78);
        $wf->updateProcess($p5, ['track' => 'pengadaan', 'mode' => 'complete', 'activity' => 'SP2K Rilis', 'date' => $d(76)]);
        $wf->releaseContract($p5, [
            'kind' => 'SP2K', 'number' => 'SP2K/ICP/2026/0142', 'title' => $p5->title, 'vendor_id' => $sln->id, 'date' => $d(76), 'kickoff_date' => $d(74),
            'value_excl' => 324324324, 'value_incl' => 360000000, 'close_stage' => true,
            'terms' => [['name' => 'AKTIVASI 50 LISENSI (BAST)', 'planned_end' => $d(60), 'percent' => 100]],
        ]);
        $t51 = $p5->terms()->where('seq', 1)->value('id');
        $finish($p5, $t51, $d(60), 'AKTIVASI LISENSI SELESAI');
        $ba($p5, [$t51], 'BAST', '40310.BA/STI.01.03/IC010208/2026', 'SERAH TERIMA LISENSI TABLEAU CREATOR', $d(59), [[$teguh, 'Selesai TTD', $d(58)]]);
        $finish($p5, $t51, $d(58), 'BA Fully Sign');
        $finish($p5, $t51, $d(55), 'Posting GR', ['gr_number' => '5000412233', 'gr_type' => 'BAST', 'gr_amount' => 360000000, 'gr_link' => 'https://contoh.sharepoint.com/sites/SolArch/GR/5000412233.pdf']);
        $finish($p5, $t51, $d(53), 'Penerimaan Invoice', ['invoice_number' => 'INV/SLN/2026/0811', 'invoice_date' => $d(53), 'invoice_amount' => 360000000, 'bucket_number' => '5100025510', 'bucket_code' => 'BKT-2026-0801']);
        $finish($p5, $t51, $d(48), 'Verifikasi Dokumen');
        $finish($p5, $t51, $d(42), 'Penjadwalan Pembayaran');
    }
}
