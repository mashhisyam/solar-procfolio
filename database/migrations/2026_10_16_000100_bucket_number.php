<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nomor bucket invoicing (opsional), melengkapi kode dan tautan bucket. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_terms', function (Blueprint $t) {
            $t->string('bucket_number', 60)->nullable()->after('bucket_code');
        });
    }

    public function down(): void
    {
        Schema::table('payment_terms', fn (Blueprint $t) => $t->dropColumn('bucket_number'));
    }
};
