<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // SP2K | PO | Kontrak
            $table->string('number')->unique();
            $table->string('title');
            $table->foreignId('vendor_id')->constrained('companies');
            $table->date('date');
            $table->date('kickoff_date');
            $table->date('end_date')->nullable();
            $table->decimal('value_excl', 18, 2);
            $table->decimal('value_incl', 18, 2);
            $table->text('scope')->nullable();
            $table->string('link', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->string('status', 20); // Dalam Proses | Rilis | Dibatalkan
            $table->text('reason');
            $table->date('requested_at');
            $table->json('stages');        // kunci tahap Proses Pengadaan yang dilalui amandemen
            $table->json('change_types');  // Nilai | Jangka Waktu | Ruang Lingkup | Termin | Lainnya
            $table->text('proposal')->nullable();
            $table->string('number')->nullable()->unique();
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('link', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->text('name');                 // Syarat penyerahan pekerjaan
            $table->string('kind', 20)->default('Persentase'); // Persentase | Nominal | Berkala
            $table->decimal('percent', 6, 2)->nullable();
            $table->decimal('amount', 18, 2);
            $table->date('planned_end')->nullable(); // Rencana tanggal selesai pekerjaan
            $table->string('period', 7)->nullable(); // YYYY-MM untuk termin berkala
            $table->date('paid_at')->nullable();
            $table->timestamps();
            $table->index(['procurement_id', 'seq']);
        });

        Schema::create('term_plan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_amendment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::table('stage_visits', function (Blueprint $table) {
            $table->foreign('term_id')->references('id')->on('payment_terms')->cascadeOnDelete();
            $table->foreign('amendment_id')->references('id')->on('contract_amendments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stage_visits', function (Blueprint $table) {
            $table->dropForeign(['term_id']);
            $table->dropForeign(['amendment_id']);
        });
        Schema::dropIfExists('term_plan_histories');
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('vendor_contracts');
    }
};
