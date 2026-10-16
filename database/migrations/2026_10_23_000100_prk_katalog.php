<?php

use App\Support\PrkCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Daftar PRK (PLN 1 dan PLN 2) dari database/data/prk.csv ikut dimuat saat migrasi,
 * sehingga tidak hilang setelah migrate:fresh. Menambah PRK baru: tambahkan baris di CSV lalu
 * jalankan `php artisan db:seed --class=PrkSeeder` (atau migrasi baru yang memanggil PrkCatalog::import()).
 */
return new class extends Migration
{
    public function up(): void
    {
        PrkCatalog::import();
    }

    public function down(): void
    {
        // Data PRK tidak dihapus saat rollback karena mungkin sudah dipakai pengadaan.
    }
};
