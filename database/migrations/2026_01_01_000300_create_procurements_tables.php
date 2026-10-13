<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // ID monitoring internal: PRC-2026-0001
            $table->string('spp_number')->nullable();      // Nomor SPP / nomor pengajuan dari aplikasi pengadaan
            $table->date('spp_date')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('method', 20);                  // SPP | Kemitraan
            $table->string('proc_type', 40)->nullable();   // Barang | Jasa | ...
            $table->string('proc_method', 60)->nullable(); // Metode pengadaan (SPP)
            $table->string('priority', 10)->default('Normal'); // Normal | Urgent
            $table->string('sub_bidang', 20)->nullable();
            $table->string('pengaju_name');                // User Pengaju di aplikasi pengadaan (tidak terikat akun)
            $table->foreignId('pic_pengaju_id')->nullable()->constrained('pics')->nullOnDelete();
            $table->date('registered_at');
            $table->decimal('rab_value', 18, 2)->default(0);
            $table->foreignId('pks_id')->nullable()->constrained('pks')->nullOnDelete();
            $table->string('bak_number')->nullable();
            $table->date('bak_date')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('companies')->nullOnDelete(); // pemenang (diisi saat rilis SP2K/PO)
            $table->json('workflow');                      // snapshot tahap Proses Pengadaan
            $table->boolean('is_draft')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('spp_number');
        });

        Schema::create('procurement_customer_contract', function (Blueprint $table) {
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_contract_id')->constrained()->cascadeOnDelete();
            $table->primary(['procurement_id', 'customer_contract_id']);
        });

        Schema::create('procurement_candidate_vendor', function (Blueprint $table) {
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->primary(['procurement_id', 'company_id']);
        });

        Schema::create('budget_code_procurement', function (Blueprint $table) {
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_code_id')->constrained()->cascadeOnDelete();
            $table->primary(['procurement_id', 'budget_code_id']);
        });

        Schema::create('rab_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('budget_type', 10); // OPEX | CAPEX
            $table->foreignId('budget_code_id')->constrained('budget_codes');
            $table->decimal('qty', 14, 2);
            $table->string('unit', 60);
            $table->decimal('price', 18, 2);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->string('doc_type', 60);
            $table->string('number')->nullable();
            $table->string('title')->nullable();
            $table->date('date')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('available')->default(false);
            $table->string('link', 1000)->nullable(); // tautan SharePoint
            $table->foreignId('customer_id')->nullable()->constrained('companies')->nullOnDelete(); // untuk MoM
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('rab_items');
        Schema::dropIfExists('budget_code_procurement');
        Schema::dropIfExists('procurement_candidate_vendor');
        Schema::dropIfExists('procurement_customer_contract');
        Schema::dropIfExists('procurements');
    }
};
