<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // contract_amendments & payment_terms dibuat di migrasi 000500; FK ditambahkan di sana.
        Schema::create('stage_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->string('track', 20)->default('pengadaan'); // pengadaan | pasca
            $table->unsignedBigInteger('term_id')->nullable();      // Progres 2: satu alur per tahap pembayaran
            $table->unsignedBigInteger('amendment_id')->nullable(); // Progres 1: alur amandemen
            $table->string('stage_key', 40);
            $table->string('stage_name');
            $table->unsignedSmallInteger('cycle')->default(1);
            $table->date('entered_at');
            $table->date('completed_at')->nullable();
            $table->string('status', 20); // Dalam Proses | Selesai | Dikembalikan | Dilewati
            $table->foreignId('pic_id')->nullable()->constrained('pics')->nullOnDelete();
            $table->string('activity')->nullable();
            $table->text('note')->nullable();
            $table->text('return_reason')->nullable();
            $table->date('last_update_at');
            $table->timestamps();
            $table->index(['procurement_id', 'track', 'status']);
            $table->index('term_id');
        });

        Schema::create('stage_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_visit_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('category');
            $table->foreignId('pic_id')->nullable()->constrained('pics')->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('kind', 20); // masuk | aktivitas | selesai | kembali | lewati
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_activities');
        Schema::dropIfExists('stage_visits');
    }
};
