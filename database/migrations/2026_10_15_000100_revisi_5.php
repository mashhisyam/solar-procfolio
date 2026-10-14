<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 15 Okt 2026:
 * - Kategori pengadaan (Lisensi / Non Lisensi, dari master).
 * - Sub bidang pada PRK.
 * - Revisi Minor / Revisi Mayor pada Proses Pengadaan.
 * - Revenue pada perjanjian pelanggan.
 * - Kode bucket invoicing per tahap pembayaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $t) {
            $t->string('category', 60)->nullable()->after('proc_type');
        });
        Schema::table('budget_codes', function (Blueprint $t) {
            $t->string('sub_bidang', 20)->nullable()->after('cost_center');
        });
        Schema::table('stage_visits', function (Blueprint $t) {
            $t->string('return_type', 10)->nullable()->after('return_reason'); // minor | mayor
            $t->string('resume_key', 40)->nullable()->after('return_type');    // Revisi Minor: tahap tujuan setelah pengaju selesai
        });
        Schema::table('customer_contracts', function (Blueprint $t) {
            $t->decimal('revenue', 18, 2)->nullable()->after('date');
        });
        Schema::table('payment_terms', function (Blueprint $t) {
            $t->string('bucket_code', 60)->nullable()->after('period');
            $t->string('bucket_link', 1000)->nullable()->after('bucket_code');
        });
    }

    public function down(): void
    {
        Schema::table('payment_terms', fn (Blueprint $t) => $t->dropColumn(['bucket_code', 'bucket_link']));
        Schema::table('customer_contracts', fn (Blueprint $t) => $t->dropColumn('revenue'));
        Schema::table('stage_visits', fn (Blueprint $t) => $t->dropColumn(['return_type', 'resume_key']));
        Schema::table('budget_codes', fn (Blueprint $t) => $t->dropColumn('sub_bidang'));
        Schema::table('procurements', fn (Blueprint $t) => $t->dropColumn('category'));
    }
};
