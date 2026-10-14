<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40)->default('BAKB'); // BAKB | MoM Kesepakatan Harga | Kontrak Pelanggan
            $table->string('number')->unique();
            $table->string('title');
            $table->foreignId('customer_id')->constrained('companies');
            $table->date('date')->nullable();
            $table->string('link', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('pks', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('title');
            $table->foreignId('partner_id')->constrained('companies');
            $table->date('date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('scope')->nullable();
            $table->string('link', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('pks_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pks_id')->constrained('pks')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->string('number')->unique();
            $table->date('date');
            $table->date('effective_date');
            $table->json('changed_parts')->nullable();
            $table->text('description')->nullable();
            $table->date('new_end_date')->nullable();
            $table->text('new_scope')->nullable();
            $table->string('link', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pks_amendments');
        Schema::dropIfExists('pks');
        Schema::dropIfExists('customer_contracts');
    }
};
