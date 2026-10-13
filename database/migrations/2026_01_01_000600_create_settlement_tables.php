<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berita_acara', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->string('sub_bidang', 20)->nullable();
            $table->string('type', 20); // BAST | BAPS | BAUK | BA Rekon
            $table->string('number')->nullable()->unique();
            $table->string('contract_ref_type', 10)->nullable(); // SP2K | PO | PKS | BAK
            $table->string('contract_ref_number')->nullable();
            $table->string('title');
            $table->date('date')->nullable();
            $table->string('director_internal')->nullable();
            $table->string('director_external')->nullable();
            $table->string('first_party')->nullable();
            $table->string('second_party')->nullable();
            $table->string('link', 1000)->nullable();
            $table->string('manual_status', 20)->nullable(); // koreksi manual status BA
            $table->text('manual_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ba_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('berita_acara_id')->constrained('berita_acara')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->date('sent_at');
            $table->text('context')->nullable();
            $table->timestamps();
        });

        Schema::create('ba_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ba_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pic_id')->constrained('pics');
            $table->date('sent_at')->nullable();
            $table->string('status', 20)->default('Belum Diproses'); // Belum Diproses | Proses TTD | Selesai TTD | Perlu Revisi
            $table->date('signed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('berita_acara_payment_term', function (Blueprint $table) {
            $table->foreignId('berita_acara_id')->constrained('berita_acara')->cascadeOnDelete();
            $table->foreignId('payment_term_id')->constrained()->cascadeOnDelete();
            $table->primary(['berita_acara_id', 'payment_term_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_term_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('Belum Diajukan'); // Belum Diajukan | Diajukan | Perlu Revisi | GR Terbit
            $table->string('number')->nullable()->unique();
            $table->date('submitted_at')->nullable();
            $table->date('gr_date')->nullable();
            $table->string('source_app', 60)->nullable();
            $table->foreignId('pic_id')->nullable()->constrained('pics')->nullOnDelete();
            $table->string('link', 1000)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('berita_acara_goods_receipt', function (Blueprint $table) {
            $table->foreignId('berita_acara_id')->constrained('berita_acara')->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->primary(['berita_acara_id', 'goods_receipt_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_term_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('pic_id')->nullable()->constrained('pics')->nullOnDelete();
            $table->string('status', 20); // Submit Invoice | Verifikasi | Perlu Revisi | Antri Bayar | Dibayar
            $table->json('checklist')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('berita_acara_goods_receipt');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('berita_acara_payment_term');
        Schema::dropIfExists('ba_signatories');
        Schema::dropIfExists('ba_rounds');
        Schema::dropIfExists('berita_acara');
    }
};
