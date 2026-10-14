<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 14 Okt 2026:
 * - Tahap approval berjenjang dikelompokkan (Pengaju, Anggaran, Perencana, Pelaksana).
 * - PRK (dulu Kode Anggaran) memiliki pagu RKAP per tahun.
 * - GR menyimpan jenis dan nominal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->string('group', 60)->nullable()->after('name');
        });
        Schema::table('budget_codes', function (Blueprint $table) {
            $table->decimal('budget_amount', 18, 2)->default(0)->after('klasifikasi'); // pagu RKAP
            $table->unsignedSmallInteger('year')->nullable()->after('budget_amount');
        });
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('gr_type', 20)->nullable()->after('number'); // BAST | BAPS | BA Rekon | BAUK
            $table->decimal('amount', 18, 2)->nullable()->after('gr_type');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', fn (Blueprint $t) => $t->dropColumn(['gr_type', 'amount']));
        Schema::table('budget_codes', fn (Blueprint $t) => $t->dropColumn(['budget_amount', 'year']));
        Schema::table('workflow_templates', fn (Blueprint $t) => $t->dropColumn('group'));
    }
};
