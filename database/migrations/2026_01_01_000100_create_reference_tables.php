<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20); // Internal | Pelanggan | Mitra | Vendor
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['type', 'active']);
        });

        Schema::create('pics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 40)->nullable(); // Solution Architect, Anggaran, Finance, Penandatangan, ...
            $table->string('sub_bidang', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pic_id')->nullable()->after('role')->constrained('pics')->nullOnDelete();
        });

        Schema::create('budget_codes', function (Blueprint $table) {
            $table->id();
            $table->string('coa', 30);
            $table->string('cost_center');
            $table->string('code', 40)->unique();
            $table->string('program');
            $table->string('klasifikasi', 60);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_items', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40); // proc_method, proc_type, activity, doc_type, ba_type, contract_type, change_part, rab_unit
            $table->string('label');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('locked')->default(false);
            $table->timestamps();
            $table->unique(['group', 'label']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->string('type', 20)->default('Libur Nasional'); // Libur Nasional | Cuti Bersama | Hari Kerja Pengganti
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('scheme', 20); // SPP | Kemitraan | Pasca
            $table->string('key', 40);
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->boolean('optional')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('default_activities')->nullable();
            $table->foreignId('pic_id')->nullable()->constrained('pics')->nullOnDelete();
            $table->timestamps();
            $table->unique(['scheme', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('pic_id'));
        Schema::dropIfExists('workflow_templates');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('master_items');
        Schema::dropIfExists('budget_codes');
        Schema::dropIfExists('pics');
        Schema::dropIfExists('companies');
    }
};
