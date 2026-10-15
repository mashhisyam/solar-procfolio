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
 *  3. Pasca Pengadaan: tahap 1 di BA Penutupan    (SPP, SP2K rilis + Kontrak Pengadaan, 3 tahap)
 *  4. Pasca Pengadaan: tahap 1 di Invoicing       (Kemitraan, PO rilis, GR terbit, bucket terisi)
 *  5. Selesai, seluruh tahap dibayar              (SPP, Pengadaan Langsung, merujuk BAKB dan nomor PA)
 * Pengadaan 2 hanya merujuk nomor PA (tanpa perjanjian pelanggan).
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
        // PA (Project Activation) bukan perjanjian pelanggan; cukup nomornya di pengadaan.
        $paNumber = 'PA/NDU/2026/004';
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
            'method' => 'SPP Pusat', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Pengadaan Langsung', 'priority' => 'Normal', 'sub_bidang' => 'PLN 1', 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(14), 'pa_number' => $paNumber],
            [], [$sln->id, $dap->id], $lic, [['ORACLE DATABASE ENTERPRISE SUPPORT PER CORE - ANNUALLY', 'OPEX', 16, 'Core', 61250000]],
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
        // Kontrak Pengadaan di samping SP2K: nilai dan jadwal sama, vendor mengikuti pemenang.
        $wf->saveWorkContract($p3, [
            'number' => 'KTR/ICP-TAN/2026/0051', 'title' => $p3->title, 'date' => $d(35), 'kickoff_date' => $d(34), 'end_date' => now()->addDays(105)->toDateString(),
            'value_excl' => 1250000000, 'value_incl' => 1387500000, 'scope' => 'ANALISIS, DESAIN, DEVELOPMENT, SIT, UAT DAN GO-LIVE.',
            'link' => 'https://contoh.sharepoint.com/sites/SolArch/Kontrak/KTR-ICP-TAN-2026-0051.pdf',
        ]);
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
            'method' => 'SPP Pusat', 'proc_type' => 'Barang', 'category' => 'Lisensi', 'proc_method' => 'Pengadaan Langsung', 'priority' => 'Normal', 'sub_bidang' => 'PLN 1', 'pic_pengaju_id' => $dewi->id, 'registered_at' => $d(88), 'pa_number' => $paNumber],
            [$cc1->id], [$sln->id], $lic, [['TABLEAU CREATOR - ANNUALLY', 'OPEX', 50, 'User', 7200000]],
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

        $this->recent($wf, $make, $advanceTo, $finish, $ba, $d, $c, $cc, $roleId, $teguh, $lina, $ndu, $bas);
    }

    /**
     * Pengadaan contoh 2 bulan terakhir agar Dashboard (periode bawaan 2 bulan) terisi: tahap Pelaksanaan Pekerjaan,
     * Submit GR, Invoicing, Verifikasi Invoice, berbagai posisi approval, serta pengadaan Gagal dan Dibatalkan.
     */
    private function recent(WorkflowService $wf, \Closure $make, \Closure $advanceTo, \Closure $finish, \Closure $ba, \Closure $d, \Closure $c, \Closure $cc, \Closure $roleId, Pic $teguh, Pic $lina, Company $ndu, Company $bas): void
    {
        $etn = $c('PT ENERGI TERPADU NUSANTARA', 'Pelanggan');
        $sln = $c('PT SOLUSI LISENSI NUSANTARA', 'Vendor');
        $tan = $c('PT TEKNOLOGI APLIKASI NUSANTARA', 'Vendor');
        $gpm = $c('PT GLOBAL PERANGKAT LUNAK MANDIRI', 'Vendor');
        $dap = $c('PT DATA ANALITIKA PRIMA', 'Vendor');
        $mip = $c('PT MITRA INTEGRASI PRIMA', 'Mitra');
        $kss = $c('PT KREASI SISTEM SOLUSINDO', 'Vendor');
        $jti = $c('PT JARINGAN TELEMATIKA INDONESIA', 'Vendor');
        $mdk = $c('PT MITRA DIGITAL KOLABORASI', 'Mitra');

        $cc5 = $cc('BAKB/2026/027', 'Berita Acara Kesepakatan Biaya', 'PLN 1', 4200000000, 'MODERNISASI LAYANAN DATA CENTER DAN KEAMANAN SIBER', $etn, 2);
        $cc6 = $cc('KTR/ETN/2026/009', 'Kontrak Perjanjian', 'PLN 2', 3600000000, 'IMPLEMENTASI SMART METERING DAN DASHBOARD OPERASIONAL', $etn, 2);
        $cc7 = $cc('SP/PLN1/2026/044', 'Surat Penugasan', 'PLN 1', 1500000000, 'PENUGASAN DUKUNGAN APLIKASI LAYANAN PELANGGAN', $bas, 1);
        $cc8 = $cc('BAKB/2026/032', 'Berita Acara Kesepakatan Biaya', 'PLN 2', 2200000000, 'PERLUASAN JARINGAN DAN PERANGKAT MODEM OPERATOR', $ndu, 1);

        $lic = BudgetCode::query()->where('code', 'IC02122025')->firstOrFail();
        $pen = BudgetCode::query()->where('code', 'IC00592026')->firstOrFail();
        $ecp = BudgetCode::query()->where('code', 'IC00782025')->firstOrFail();
        $modem = BudgetCode::query()->where('code', 'IC00852024')->firstOrFail();
        $maximo = BudgetCode::query()->where('code', 'IC01052023')->firstOrFail();
        $modem->update(['budget_amount' => 3000000000, 'year' => 2026]);
        $maximo->update(['budget_amount' => 1800000000, 'year' => 2026]);

        $spp = fn (string $no, string $title, string $method, string $cat, string $prio, string $sub, int $ago, array $extra = []) => [
            'spp_number' => $no, 'spp_date' => $d($ago + 1), 'title' => $title, 'summary' => null, 'method' => 'SPP Pusat', 'proc_type' => $cat === 'Lisensi' ? 'Barang' : 'Jasa',
            'category' => $cat, 'proc_method' => $method, 'priority' => $prio, 'sub_bidang' => $sub, 'registered_at' => $d($ago),
            'pic_pengaju_id' => Pic::query()->where('name', $sub === 'PLN 1' ? 'Dewi Anggraini' : 'Bagus Wicaksono')->value('id'), ...$extra,
        ];
        $mitra = fn (string $no, string $title, string $cat, string $prio, int $ago, Pks $pks, string $bak) => [
            'spp_number' => $no, 'spp_date' => $d($ago + 1), 'title' => $title, 'summary' => null, 'method' => 'SPP Kemitraan', 'proc_type' => 'Jasa', 'category' => $cat,
            'priority' => $prio, 'sub_bidang' => 'PLN 2', 'registered_at' => $d($ago), 'pic_pengaju_id' => Pic::query()->where('name', 'Bagus Wicaksono')->value('id'),
            'pks_id' => $pks->id, 'bak_number' => $bak, 'bak_date' => $d($ago + 1),
        ];
        $tor = [['TOR (Term of Reference)', null, null], ['BCR (Benefit Cost Ratio)', null, 'BCR 135%']];
        $bakDocs = fn (string $bak) => [['BAK', $bak, null], ['BCR (Benefit Cost Ratio)', null, 'BCR 128%']];
        $pks2 = Pks::query()->where('number', 'PKS/2026/004')->firstOrFail();
        $lot = 'LOT (SERVICE & MATERIAL)';
        // Selesaikan tahap terakhir Proses Pengadaan lalu rilis SP2K/PO.
        $release = function (Procurement $p, string $kind, string $number, Company $vendor, int $ago, int $kickAgo, float $excl, array $terms) use ($wf, $d) {
            $wf->updateProcess($p, ['track' => 'pengadaan', 'mode' => 'complete', 'activity' => "{$kind} Rilis", 'date' => $d($ago)]);
            $wf->releaseContract($p, [
                'kind' => $kind, 'number' => $number, 'title' => $p->title, 'vendor_id' => $vendor->id, 'date' => $d($ago), 'kickoff_date' => $d($kickAgo),
                'value_excl' => $excl, 'value_incl' => round($excl * 1.11), 'close_stage' => true,
                'link' => 'https://contoh.sharepoint.com/sites/SolArch/Kontrak/'.str_replace('/', '-', $number).'.pdf',
                'terms' => $terms,
            ]);

            return $p->terms()->orderBy('seq')->pluck('id')->all();
        };
        $future = fn (int $days) => now()->addDays($days)->toDateString();
        $grDocs = fn (string $no, float $amount) => ['gr_number' => $no, 'gr_type' => 'BAST', 'gr_amount' => $amount, 'gr_link' => "https://contoh.sharepoint.com/sites/SolArch/GR/{$no}.pdf"];

        // ===== 6) Pelaksanaan Pekerjaan tahap 1 (SPP, Tender Terbuka, SP2K + Kontrak Pengadaan) =====
        $p6 = $make($spp('SPP/2026/BJB/000047', 'MODERNISASI SECURITY OPERATION CENTER (SOC) DATA CENTER', 'Tender Terbuka', 'Non Lisensi', 'Urgent', 'PLN 1', 38),
            [$cc5->id], [$gpm->id, $kss->id, $tan->id], $pen, [['JASA IMPLEMENTASI SOC 24X7', 'OPEX', 1, $lot, 1650000000], ['PERANGKAT SIEM DAN SENSOR JARINGAN', 'CAPEX', 4, 'Unit', 210000000]], $tor);
        $advanceTo($p6, 'lakdan_staff', 37, 24);
        $t6 = $release($p6, 'SP2K', 'SP2K/ICP/2026/0231', $gpm, 23, 21, 2250000000, [
            ['name' => 'DESAIN & INSTALASI PERANGKAT (BAPS)', 'planned_end' => $future(18), 'percent' => 40],
            ['name' => 'OPERASIONAL SOC BULAN 1-3 (BA REKON)', 'planned_end' => $future(80), 'percent' => 60],
        ]);
        $wf->saveWorkContract($p6->fresh(), ['number' => 'KTR/ICP-GPM/2026/0063', 'title' => $p6->title, 'date' => $d(22), 'kickoff_date' => $d(21), 'end_date' => $future(80),
            'value_excl' => 2250000000, 'value_incl' => 2497500000, 'link' => 'https://contoh.sharepoint.com/sites/SolArch/Kontrak/KTR-ICP-GPM-2026-0063.pdf']);
        $wf->updateProcess($p6, ['track' => 'pasca', 'term_id' => $t6[0], 'mode' => 'update', 'activity' => 'INSTALASI SENSOR 60%', 'date' => $d(2), 'note' => 'INSTALASI SENSOR DI 3 DARI 5 LOKASI.']);

        // ===== 7) Pelaksanaan Pekerjaan, melewati rencana selesai (Kemitraan, PO) =====
        $p7 = $make($mitra('PGJ/2026/BJB/000039', 'CHANGE REQUEST APLIKASI MAXIMO — MODUL PREVENTIVE MAINTENANCE', 'Non Lisensi', 'Normal', 30, $pks2, 'BAK/2026/038'),
            [$cc6->id], [$mip->id], $maximo, [['CHANGE REQUEST CR-038 MODUL PREVENTIVE MAINTENANCE', 'OPEX', 1, $lot, 540000000]], $bakDocs('BAK/2026/038'));
        $advanceTo($p7, 'partnership', 29, 20);
        $t7 = $release($p7, 'PO', 'PO/ICP/2026/4500233', $mip, 19, 17, 486486486, [
            ['name' => 'MODUL PREVENTIVE MAINTENANCE DITERIMA (BAST)', 'planned_end' => $d(3), 'percent' => 100],
        ]);
        $wf->updateProcess($p7, ['track' => 'pasca', 'term_id' => $t7[0], 'mode' => 'update', 'activity' => 'UAT MODUL', 'date' => $d(1), 'note' => 'UAT MUNDUR KARENA PERUBAHAN ALUR APPROVAL WORK ORDER.']);

        // ===== 8) Proses GR: GR sudah diajukan (Submit GR) =====
        $p8 = $make($spp('SPP/2026/BJB/000044', 'LISENSI ARCGIS ENTERPRISE ADVANCED 2 CORE', 'Pengadaan Langsung', 'Lisensi', 'Normal', 'PLN 1', 36),
            [$cc7->id], [$dap->id, $sln->id], $lic, [['ARCGIS ENTERPRISE ADVANCED MAINTENANCE - ANNUALLY', 'OPEX', 2, 'Core', 185000000]], $tor);
        $advanceTo($p8, 'lakdan_staff', 35, 24);
        $t8 = $release($p8, 'SP2K', 'SP2K/ICP/2026/0226', $dap, 23, 21, 333333333, [['name' => 'AKTIVASI LISENSI (BAST)', 'planned_end' => $d(10), 'percent' => 100]]);
        $finish($p8, $t8[0], $d(10), 'AKTIVASI LISENSI SELESAI');
        $ba($p8, [$t8[0]], 'BAST', '40981.BA/STI.01.03/IC010208/2026', 'SERAH TERIMA LISENSI ARCGIS ENTERPRISE', $d(9), [[$teguh, 'Selesai TTD', $d(8)], [$lina, 'Selesai TTD', $d(7)]]);
        $finish($p8, $t8[0], $d(7), 'BA Fully Sign');
        $wf->updateProcess($p8, ['track' => 'pasca', 'term_id' => $t8[0], 'mode' => 'update', 'activity' => 'Pengajuan GR', 'date' => $d(5), 'note' => 'GR DIAJUKAN KE TIM SAP, MENUNGGU POSTING.']);
        $p8->terms()->firstOrFail()->goodsReceipt()->create(['status' => 'Diajukan', 'gr_type' => 'BAST', 'amount' => 370000000, 'submitted_at' => $d(5), 'pic_id' => $roleId('Finance')]);

        // ===== 9) Proses GR tahap 1, Kemitraan (Submit GR) =====
        $p9 = $make($mitra('PGJ/2026/BJB/000041', 'PENGEMBANGAN DASHBOARD OPERASIONAL SMART METERING', 'Non Lisensi', 'Urgent', 33, $pks2, 'BAK/2026/040'),
            [$cc6->id], [$mip->id, $mdk->id], $ecp, [['PENGEMBANGAN DASHBOARD OPERASIONAL', 'OPEX', 1, $lot, 920000000]], $bakDocs('BAK/2026/040'));
        $advanceTo($p9, 'partnership', 32, 22);
        $t9 = $release($p9, 'PO', 'PO/ICP/2026/4500229', $mdk, 21, 20, 828828829, [
            ['name' => 'DASHBOARD TAHAP 1 (BAPS)', 'planned_end' => $d(8), 'percent' => 50], ['name' => 'GO-LIVE (BAST)', 'planned_end' => $future(30), 'percent' => 50],
        ]);
        $finish($p9, $t9[0], $d(8), 'DASHBOARD TAHAP 1 SELESAI');
        $finish($p9, $t9[0], $d(6), 'BA Fully Sign');
        $wf->updateProcess($p9, ['track' => 'pasca', 'term_id' => $t9[0], 'mode' => 'update', 'activity' => 'Pengajuan GR', 'date' => $d(4), 'note' => 'GR TAHAP 1 SUDAH DIAJUKAN.']);
        $p9->terms()->where('seq', 1)->firstOrFail()->goodsReceipt()->create(['status' => 'Diajukan', 'gr_type' => 'BAPS', 'amount' => 460000000, 'submitted_at' => $d(4), 'pic_id' => $roleId('Finance')]);

        // ===== 10) Invoicing (SPP, Seleksi Langsung) =====
        $p10 = $make($spp('SPP/2026/BJB/000042', 'PERANGKAT MODEM OPERATOR UNTUK GARDU INDUK TAHAP II', 'Seleksi Langsung', 'Non Lisensi', 'Normal', 'PLN 2', 40),
            [$cc8->id], [$jti->id, $kss->id], $modem, [['MODEM 4G INDUSTRIAL DUAL SIM', 'CAPEX', 350, 'Unit', 3250000], ['JASA INSTALASI DAN KONFIGURASI', 'OPEX', 1, $lot, 180000000]], $tor);
        $advanceTo($p10, 'lakdan_staff', 39, 29);
        $t10 = $release($p10, 'SP2K', 'SP2K/ICP/2026/0219', $jti, 28, 27, 1180000000, [
            ['name' => 'PENGIRIMAN 350 MODEM (BAPS)', 'planned_end' => $d(15), 'percent' => 60], ['name' => 'INSTALASI & KONFIGURASI (BAST)', 'planned_end' => $future(25), 'percent' => 40],
        ]);
        $finish($p10, $t10[0], $d(15), 'PENGIRIMAN SELESAI');
        $ba($p10, [$t10[0]], 'BAPS', '40902.BA/STI.01.03/IC010208/2026', 'PEMERIKSAAN PENGIRIMAN MODEM OPERATOR', $d(14), [[$teguh, 'Selesai TTD', $d(13)]]);
        $finish($p10, $t10[0], $d(12), 'BA Fully Sign');
        $finish($p10, $t10[0], $d(9), 'Posting GR', $grDocs('5000421190', 786000000));
        $wf->updateProcess($p10, ['track' => 'pasca', 'term_id' => $t10[0], 'mode' => 'update', 'activity' => 'Penerimaan Invoice', 'date' => $d(6), 'note' => 'INVOICE DITERIMA, MENUNGGU FAKTUR PAJAK.',
            'bucket_number' => '5100028804', 'bucket_code' => 'BKT-2026-1004']);
        $wf->saveWorkContract($p10->fresh(), ['number' => 'KTR/ICP-JTI/2026/0058', 'title' => $p10->title, 'date' => $d(27), 'kickoff_date' => $d(27), 'end_date' => $future(25),
            'value_excl' => 1180000000, 'value_incl' => 1309800000]);

        // ===== 11) Invoicing (Kemitraan, Urgent) =====
        $p11 = $make($mitra('PGJ/2026/BJB/000037', 'SUPPORT OPERASIONAL APLIKASI LAYANAN PELANGGAN 6 BULAN', 'Non Lisensi', 'Urgent', 37, $pks2, 'BAK/2026/036'),
            [$cc7->id], [$mip->id], $pen, [['SUPPORT OPERASIONAL APLIKASI - 6 BULAN', 'OPEX', 6, 'Bulan', 95000000]], $bakDocs('BAK/2026/036'));
        $advanceTo($p11, 'partnership', 36, 27);
        $t11 = $release($p11, 'PO', 'PO/ICP/2026/4500214', $mip, 26, 25, 513513514, [
            ['name' => 'SUPPORT BULAN 1-3 (BA REKON)', 'planned_end' => $d(12), 'percent' => 50], ['name' => 'SUPPORT BULAN 4-6 (BA REKON)', 'planned_end' => $future(60), 'percent' => 50],
        ]);
        $finish($p11, $t11[0], $d(12), 'SUPPORT BULAN 1-3 SELESAI');
        $finish($p11, $t11[0], $d(10), 'BA Fully Sign');
        $finish($p11, $t11[0], $d(7), 'Posting GR', $grDocs('5000421377', 285000000));
        $wf->updateProcess($p11, ['track' => 'pasca', 'term_id' => $t11[0], 'mode' => 'update', 'activity' => 'Kelengkapan Dokumen', 'date' => $d(3), 'note' => 'LAMPIRAN TIMESHEET BELUM LENGKAP.']);

        // ===== 12) Verifikasi Invoice (SPP, Pengadaan Langsung) =====
        $p12 = $make($spp('SPP/2026/BJB/000043', 'LISENSI POWER BI PRO 150 PENGGUNA', 'Pengadaan Langsung', 'Lisensi', 'Normal', 'PLN 1', 34),
            [$cc5->id], [$sln->id], $lic, [['POWER BI PRO - ANNUALLY', 'OPEX', 150, 'User', 2350000]], $tor);
        $advanceTo($p12, 'lakdan_staff', 33, 22);
        $t12 = $release($p12, 'SP2K', 'SP2K/ICP/2026/0229', $sln, 21, 20, 317567568, [['name' => 'AKTIVASI 150 LISENSI (BAST)', 'planned_end' => $d(14), 'percent' => 100]]);
        $finish($p12, $t12[0], $d(14), 'AKTIVASI SELESAI');
        $finish($p12, $t12[0], $d(12), 'BA Fully Sign');
        $finish($p12, $t12[0], $d(9), 'Posting GR', $grDocs('5000421254', 352500000));
        $finish($p12, $t12[0], $d(5), 'Penerimaan Invoice', ['invoice_number' => 'INV/SLN/2026/1022', 'invoice_date' => $d(5), 'invoice_amount' => 352500000]);

        // ===== 13-16) Posisi approval =====
        $p13 = $make($spp('SPP/2026/BJB/000049', 'PENGADAAN SERVER APLIKASI DAN STORAGE ALL-FLASH', 'Tender Terbatas', 'Non Lisensi', 'Urgent', 'PLN 1', 14),
            [$cc5->id], [$kss->id, $gpm->id], $pen, [['SERVER APLIKASI 2 SOCKET', 'CAPEX', 4, 'Unit', 285000000], ['STORAGE ALL-FLASH 100TB', 'CAPEX', 1, 'Unit', 760000000]], $tor);
        $advanceTo($p13, 'pengadaan_mgr', 13, 6);
        $p14 = $make($spp('SPP/2026/BJB/000048', 'JASA KONSULTAN ARSITEKTUR ENTERPRISE DATA PLATFORM', 'Seleksi Langsung', 'Non Lisensi', 'Normal', 'PLN 2', 18),
            [$cc6->id], [$tan->id, $dap->id], $ecp, [['JASA KONSULTAN ARSITEKTUR DATA - 4 BULAN', 'OPEX', 4, 'Bulan', 145000000]], $tor);
        $advanceTo($p14, 'rendan_staff', 17, 3);
        $p15 = $make($spp('SPP/2026/BJB/000051', 'PERPANJANGAN LISENSI ANTIVIRUS ENDPOINT 2.000 PERANGKAT', 'Pengadaan Langsung', 'Lisensi', 'Normal', 'PLN 1', 7),
            [$cc7->id], [$sln->id], $lic, [['ENDPOINT PROTECTION - ANNUALLY', 'OPEX', 2000, 'Endpoint', 285000]], $tor);
        $advanceTo($p15, 'anggaran_staff', 6, 2);
        $p16 = $make($mitra('PGJ/2026/BJB/000044', 'INTEGRASI APLIKASI WORK ORDER DENGAN SAP PM', 'Non Lisensi', 'Normal', 6, $pks2, 'BAK/2026/043'),
            [$cc6->id], [$mdk->id, $mip->id], $maximo, [['INTEGRASI WORK ORDER - SAP PM', 'OPEX', 1, $lot, 375000000]], $bakDocs('BAK/2026/043'));
        $advanceTo($p16, 'sales_vp', 5, 1);

        // ===== 17-18) Pengadaan Gagal: berhenti setelah masuk unit pengadaan =====
        $p17 = $make($spp('SPP/2026/BJB/000040', 'PENGADAAN PERANGKAT NEXT GENERATION FIREWALL', 'Tender Terbatas', 'Non Lisensi', 'Urgent', 'PLN 2', 32),
            [$cc8->id], [$jti->id, $kss->id], $modem, [['NEXT GENERATION FIREWALL HA PAIR', 'CAPEX', 2, 'Unit', 465000000]], $tor);
        $advanceTo($p17, 'lakdan_spv', 31, 12);
        $wf->stop($p17->fresh(), 'TIDAK ADA PENAWARAN YANG LULUS EVALUASI TEKNIS; TENDER DINYATAKAN GAGAL.', $d(10));
        $p18 = $make($spp('SPP/2026/BJB/000045', 'JASA MIGRASI APLIKASI KE CLOUD PRIVAT', 'Seleksi Langsung', 'Non Lisensi', 'Normal', 'PLN 1', 26),
            [$cc5->id], [$tan->id], $pen, [['JASA MIGRASI APLIKASI KE CLOUD PRIVAT', 'OPEX', 1, $lot, 690000000]], $tor);
        $advanceTo($p18, 'rendan_staff', 25, 10);
        $wf->stop($p18->fresh(), 'SPESIFIKASI TIDAK DAPAT DIPENUHI PENYEDIA; KEBUTUHAN AKAN DIAJUKAN ULANG.', $d(8));

        // ===== 19-20) Pengadaan Dibatalkan: berhenti sebelum unit pengadaan =====
        $p19 = $make($spp('SPP/2026/BJB/000046', 'LISENSI APLIKASI DESAIN GRAFIS 40 PENGGUNA', 'Pengadaan Langsung', 'Lisensi', 'Normal', 'PLN 1', 20),
            [$cc7->id], [$sln->id], $lic, [['LISENSI DESAIN GRAFIS - ANNUALLY', 'OPEX', 40, 'User', 9800000]], $tor);
        $advanceTo($p19, 'anggaran_mgr', 19, 13);
        $wf->stop($p19->fresh(), 'ANGGARAN DIALIHKAN KE PRIORITAS LAIN PADA REVISI RKAP.', $d(12));
        $p20 = $make($mitra('PGJ/2026/BJB/000040', 'CHANGE REQUEST PORTAL PELANGGAN — FITUR CHATBOT', 'Non Lisensi', 'Normal', 15, $pks2, 'BAK/2026/039'),
            [$cc6->id], [$mdk->id], $ecp, [['CHANGE REQUEST FITUR CHATBOT', 'OPEX', 1, $lot, 310000000]], $bakDocs('BAK/2026/039'));
        $advanceTo($p20, 'pengaju_vp', 14, 12);
        $wf->stop($p20->fresh(), 'PELANGGAN MENUNDA KEBUTUHAN SAMPAI TAHUN DEPAN.', $d(11));
    }
}
