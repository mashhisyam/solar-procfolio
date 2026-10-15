<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 17 Okt 2026:
 * - Skema pengadaan: SPP Pusat / SPP Kemitraan (sebelumnya SPP / Kemitraan).
 * - Perjanjian pelanggan: jenis baru (BAKB, Kontrak Perjanjian, Surat Penugasan, PA) dan sub bidang.
 * - Kontrak perjanjian pelaksanaan pekerjaan berdampingan dengan SP2K/PO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $t) {
            $t->string('work_contract_number')->nullable()->after('vendor_id');
            $t->date('work_contract_date')->nullable()->after('work_contract_number');
            $t->string('work_contract_link', 1000)->nullable()->after('work_contract_date');
        });
        Schema::table('customer_contracts', function (Blueprint $t) {
            $t->string('sub_bidang', 20)->nullable()->after('kind');
        });

        // Nama skema lama -> baru
        foreach ([['SPP', 'SPP Pusat'], ['Kemitraan', 'SPP Kemitraan']] as [$old, $new]) {
            Schema::getConnection()->table('procurements')->where('method', $old)->update(['method' => $new]);
            Schema::getConnection()->table('workflow_templates')->where('scheme', $old)->update(['scheme' => $new]);
        }
    }

    public function down(): void
    {
        foreach ([['SPP Pusat', 'SPP'], ['SPP Kemitraan', 'Kemitraan']] as [$old, $new]) {
            Schema::getConnection()->table('procurements')->where('method', $old)->update(['method' => $new]);
            Schema::getConnection()->table('workflow_templates')->where('scheme', $old)->update(['scheme' => $new]);
        }
        Schema::table('customer_contracts', fn (Blueprint $t) => $t->dropColumn('sub_bidang'));
        Schema::table('procurements', fn (Blueprint $t) => $t->dropColumn(['work_contract_number', 'work_contract_date', 'work_contract_link']));
    }
};
