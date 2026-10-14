<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\MasterItem;
use App\Models\Pic;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;

/** Master data awal Procfolio: daftar pilihan, template alur, parameter alert, kalender 2026, kode anggaran, akun admin. */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'proc_method' => ['Pengadaan Langsung', 'Penunjukan Langsung', 'Tender Terbatas', 'Tender Terbuka', 'Seleksi Langsung'],
            'proc_type' => ['Barang', 'Jasa', 'Barang & Jasa', 'Sewa'],
            'activity' => ['Review Dokumen', 'Approval', 'Disposisi', 'Klarifikasi', 'Penyusunan RKS', 'Negosiasi', 'SP2K Rilis', 'PO Rilis',
                'Pekerjaan Berjalan', 'Penyusunan BA', 'Sirkuler TTD', 'Pengajuan GR', 'Posting GR', 'Penerimaan Invoice', 'Kelengkapan Dokumen',
                'Verifikasi Dokumen', 'Penjadwalan Pembayaran', 'Pembuatan BA', 'Sirkuler BA', 'Revisi BA', 'BA Fully Sign', 'Lainnya'],
            'doc_type' => ['TOR', 'BCR (Benefit Cost Ratio)', 'BAK', 'SPH', 'Form Justifikasi Anggaran', 'BAKB', 'Surat Penugasan', 'RKS', 'Lainnya'],
            'ba_type' => ['BAST', 'BAPS', 'BAUK', 'BA Rekon'],
            'contract_type' => ['SP2K', 'PO', 'Kontrak'],
            'change_part' => ['Ruang Lingkup', 'Masa Berlaku', 'Ketentuan Komersial', 'Para Pihak', 'Lainnya'],
            'rab_unit' => ['LOT (SERVICE & MATERIAL)', 'User', 'Lisensi', 'Core', 'Endpoint', 'Unit', 'Bulan', 'Paket'],
        ];
        $locked = ['activity' => ['SP2K Rilis', 'PO Rilis'], 'doc_type' => ['TOR', 'BCR (Benefit Cost Ratio)', 'BAK', 'Surat Penugasan'], 'contract_type' => ['SP2K', 'PO']];
        foreach ($lists as $group => $labels) {
            foreach ($labels as $i => $label) {
                MasterItem::query()->updateOrCreate(['group' => $group, 'label' => $label], ['sort' => $i, 'active' => true, 'locked' => in_array($label, $locked[$group] ?? [], true)]);
            }
        }

        // [kunci, nama tahap (= peran PIC yang disarankan), kelompok, opsional, aktif default, aktivitas]
        $review = ['Review Dokumen', 'Approval', 'Disposisi', 'Klarifikasi'];
        $pengaju = [
            ['pengaju_user', 'User Pengaju', 'Pengaju', false, true, ['Review Dokumen', 'Lainnya']],
            ['pengaju_mgr', 'Manager Pengaju', 'Pengaju', false, true, $review],
            ['pengaju_vp', 'L1/VP Pengaju', 'Pengaju', false, true, $review],
            ['pengaju_dir', 'Direktur Pengaju', 'Pengaju', true, false, $review],
        ];
        $anggaran = [
            ['anggaran_staff', 'Staff Anggaran', 'Anggaran', false, true, $review],
            ['anggaran_mgr', 'Manager Anggaran', 'Anggaran', false, true, $review],
            ['anggaran_vp', 'L1/VP Anggaran', 'Anggaran', false, true, $review],
            ['anggaran_dir', 'Direktur Anggaran', 'Anggaran', true, false, $review],
        ];
        $tpl = [
            // Setelah Anggaran, semua pengadaan masuk ke Manager Pengadaan. Selanjutnya (default menurut metode, lihat WorkflowService::applyRouting):
            // Pengadaan Langsung: Manager Pengadaan -> SPV Lakdan -> Staff Lakdan
            // Metode lain:        Manager Pengadaan -> SPV Rendan -> Staff Rendan -> SPV Lakdan -> Staff Lakdan
            'SPP' => [...$pengaju, ...$anggaran,
                ['pengadaan_mgr', 'Manager Pengadaan', 'Manager Pengadaan', false, true, ['Review Dokumen', 'Disposisi', 'Klarifikasi']],
                ['rendan_spv', 'SPV Rendan', 'Perencana Pengadaan', false, true, ['Review Dokumen', 'Penyusunan RKS', 'Klarifikasi']],
                ['rendan_staff', 'Staff Rendan', 'Perencana Pengadaan', false, true, ['Penyusunan RKS', 'Klarifikasi']],
                ['lakdan_spv', 'SPV Lakdan', 'Pelaksana Pengadaan', false, true, ['Review Dokumen', 'Klarifikasi', 'Negosiasi']],
                ['lakdan_staff', 'Staff Lakdan', 'Pelaksana Pengadaan', false, true, ['Klarifikasi', 'Negosiasi', 'SP2K Rilis']],
            ],
            'Kemitraan' => [...$pengaju, ...$anggaran,
                ['partnership', 'Partnership', 'Partnership', false, true, ['Review Dokumen', 'Klarifikasi', 'Negosiasi', 'PO Rilis']],
            ],
            'Pasca' => [
                ['pelaksanaan', 'Pelaksanaan Pekerjaan', null, false, true, []],
                ['ba', 'BA Penutupan', null, false, true, ['Pembuatan BA', 'Sirkuler BA', 'Revisi BA']],
                ['gr', 'Proses GR', null, false, true, ['Pengajuan GR', 'Posting GR', 'Klarifikasi']],
                ['invoice', 'Invoicing', null, false, true, ['Penerimaan Invoice', 'Kelengkapan Dokumen']],
                ['verifikasi', 'Verifikasi Invoice', null, false, true, ['Verifikasi Dokumen', 'Klarifikasi']],
                ['antri', 'Antri Bayar', null, false, true, ['Penjadwalan Pembayaran']],
            ],
        ];
        foreach ($tpl as $scheme => $stages) {
            // Template diganti utuh agar kunci lama tidak tertinggal. Pengadaan berjalan memakai salinan alurnya sendiri.
            WorkflowTemplate::query()->where('scheme', $scheme)->delete();
            foreach ($stages as $i => [$key, $name, $group, $optional, $enabled, $acts]) {
                WorkflowTemplate::query()->create(['scheme' => $scheme, 'key' => $key, 'name' => $name, 'group' => $group, 'optional' => $optional, 'enabled' => $enabled, 'sort' => $i, 'default_activities' => $acts]);
            }
        }

        foreach (AppSetting::DEFAULTS as $k => $v) {
            AppSetting::query()->firstOrCreate(['key' => $k], ['value' => $v]);
        }

        $holidays = [
            ['2026-01-01', 'Tahun Baru Masehi'], ['2026-01-16', 'Isra Mikraj'], ['2026-02-17', 'Tahun Baru Imlek'], ['2026-03-19', 'Hari Suci Nyepi'],
            ['2026-03-20', 'Idul Fitri'], ['2026-03-23', 'Cuti Bersama Idul Fitri', 'Cuti Bersama'], ['2026-03-24', 'Cuti Bersama Idul Fitri', 'Cuti Bersama'],
            ['2026-04-03', 'Wafat Yesus Kristus'], ['2026-05-01', 'Hari Buruh Internasional'], ['2026-05-14', 'Kenaikan Yesus Kristus'],
            ['2026-05-27', 'Idul Adha'], ['2026-06-01', 'Hari Lahir Pancasila'], ['2026-06-16', 'Tahun Baru Islam'], ['2026-08-17', 'Hari Kemerdekaan RI'],
            ['2026-08-25', 'Maulid Nabi Muhammad'], ['2026-12-24', 'Cuti Bersama Natal', 'Cuti Bersama'], ['2026-12-25', 'Hari Raya Natal'],
        ];
        foreach ($holidays as $h) {
            Holiday::query()->updateOrCreate(['date' => $h[0]], ['name' => $h[1], 'type' => $h[2] ?? 'Libur Nasional']);
        }

        $cc = 'I0325 - SUB BID SOL ARCH PLN 2';
        $codes = [
            ['5111110003', 'IC00852024', 'PENYEDIAAN PERANGKAT TELEKOMUNIKASI MODEM OPERATOR UNTUK PLN', 'PRODUKSI LANGSUNG'],
            ['5514110001', 'IC00472024', 'SOSIALISASI KE PELANGGAN', 'PEMASARAN'],
            ['5514110001', 'IC00482024', 'SUPPORT EVENT PLN DAN LAYANAN VVIP', 'PEMASARAN'],
            ['5211990003', 'IC02122025', 'LISENSI DAN PENDAMPINGAN RISET TI KETENAGALISTRIKAN', 'PRODUKSI LANGSUNG'],
            ['5111110003', 'IC00782025', 'PROJECT EMPLOYEE CONNECTED PROGRAM (ECP)', 'PRODUKSI LANGSUNG'],
            ['5211990003', 'IC01052023', 'MANAGE SERVICE APLIKASI MAXIMO PLN', 'PRODUKSI LANGSUNG'],
            ['5211990003', 'IC00592026', 'SUPPORT KEBUTUHAN PENUGASAN PLN', 'PRODUKSI LANGSUNG'],
        ];
        foreach ($codes as [$coa, $code, $program, $klas]) {
            BudgetCode::query()->updateOrCreate(['code' => $code], ['coa' => $coa, 'cost_center' => $cc, 'program' => $program, 'klasifikasi' => $klas, 'active' => true]);
        }

        $icon = Company::query()->firstOrCreate(['name' => 'PT Indonesia Comnets Plus'], ['type' => 'Internal', 'active' => true]);
        $admin = Pic::query()->firstOrCreate(['email' => 'admin@procfolio.local'], ['name' => 'Admin Procfolio', 'title' => 'Administrator', 'company_id' => $icon->id, 'role' => 'Solution Architect', 'active' => true]);

        // Akun admin awal untuk login lokal (ganti kata sandi atau nonaktifkan setelah SSO berjalan).
        User::query()->firstOrCreate(['email' => 'admin@procfolio.local'], [
            'name' => 'Admin Procfolio', 'password' => 'procfolio123', 'role' => 'admin', 'pic_id' => $admin->id, 'active' => true,
        ]);
    }
}
