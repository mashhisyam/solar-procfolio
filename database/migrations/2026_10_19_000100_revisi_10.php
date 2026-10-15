<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 19 Okt 2026: pengadaan yang berhenti dibedakan menjadi Dibatalkan (sebelum masuk unit pengadaan)
 * dan Gagal (setelah masuk unit pengadaan). Pengadaan tidak pernah dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $t) {
            $t->string('cancel_type', 10)->nullable()->after('cancelled_at'); // batal | gagal
        });
        DB::table('procurements')->whereNotNull('cancelled_at')->update(['cancel_type' => 'batal']);
    }

    public function down(): void
    {
        Schema::table('procurements', fn (Blueprint $t) => $t->dropColumn('cancel_type'));
    }
};
