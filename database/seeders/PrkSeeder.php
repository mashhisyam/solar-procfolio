<?php

namespace Database\Seeders;

use App\Support\PrkCatalog;
use Illuminate\Database\Seeder;

/**
 * Impor daftar PRK dari database/data/prk.csv. Pemisah koma atau titik koma (format Excel Indonesia).
 * Kolom wajib: coa, kode_anggaran, program, klasifikasi, cost_center.
 * Kolom opsional: sub_bidang (bila kosong ditebak dari cost center), nilai_pengajuan, tahun.
 * PRK yang sudah ada diperbarui berdasarkan kode anggaran; nilai pengajuan hanya ditimpa bila kolomnya diisi.
 *
 * Jalankan: php artisan db:seed --class=PrkSeeder
 */
class PrkSeeder extends Seeder
{
    public const FILE = 'database/data/prk.csv';

    public function run(): void
    {
        $res = PrkCatalog::import(base_path(self::FILE));
        if ($res === null) {
            $this->command->warn('Berkas '.self::FILE.' tidak ditemukan; impor PRK dilewati.');

            return;
        }
        $this->command->info("PRK: {$res['created']} baru, {$res['updated']} diperbarui.");
    }

    /** Sub bidang ditebak dari nama cost center, mis. "I0325 - SUB BID SOL ARCH PLN 2" -> "PLN 2". */
    public static function subBidangOf(?string $costCenter): ?string
    {
        return PrkCatalog::subBidangOf($costCenter);
    }
}
