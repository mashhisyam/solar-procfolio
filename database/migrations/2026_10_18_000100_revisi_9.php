<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 18 Okt 2026:
 * - Kontrak Pengadaan: kontrak di samping SP2K/PO, disimpan di vendor_contracts (is_primary = false)
 *   agar perlakuannya sama dengan SP2K/PO (koreksi data, amandemen, halaman kontrak).
 *   Kolom work_contract_* di procurements dipindahkan lalu dihapus.
 * - PA (Project Activation) bukan lagi perjanjian pelanggan; cukup nomor PA di pengadaan.
 * - PRK tanpa sub bidang diisi dari nama cost center (PLN 1 / PLN 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_contracts', function (Blueprint $t) {
            $t->boolean('is_primary')->default(true)->after('procurement_id');
        });
        Schema::table('vendor_contracts', function (Blueprint $t) {
            $t->dropUnique(['procurement_id']);
            $t->unique(['procurement_id', 'is_primary']);
        });

        // Pindahkan kontrak perjanjian lama ke vendor_contracts. Nilai, vendor dan jadwal disalin dari SP2K/PO.
        foreach (DB::table('procurements')->whereNotNull('work_contract_number')->get() as $p) {
            $main = DB::table('vendor_contracts')->where('procurement_id', $p->id)->where('is_primary', true)->first();
            if (! $main) {
                continue;
            }
            $number = $p->work_contract_number;
            if (DB::table('vendor_contracts')->where('number', $number)->exists()) {
                $number .= ' (KONTRAK)';
            }
            DB::table('vendor_contracts')->insert([
                'procurement_id' => $p->id, 'is_primary' => false, 'kind' => 'Kontrak Pengadaan', 'number' => $number,
                'title' => $main->title, 'vendor_id' => $main->vendor_id, 'date' => $p->work_contract_date ?? $main->date,
                'kickoff_date' => $main->kickoff_date, 'end_date' => $main->end_date, 'value_excl' => $main->value_excl,
                'value_incl' => $main->value_incl, 'scope' => $main->scope, 'link' => $p->work_contract_link,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('procurements', function (Blueprint $t) {
            $t->dropColumn(['work_contract_number', 'work_contract_date', 'work_contract_link']);
        });
        Schema::table('procurements', function (Blueprint $t) {
            $t->string('pa_number', 255)->nullable()->after('bak_date');
        });

        // PA lama: nomornya disalin ke pengadaan yang merujuknya, lalu dokumen PA dihapus dari perjanjian pelanggan.
        foreach (DB::table('customer_contracts')->where('kind', 'PA (Project Activation)')->get() as $pa) {
            foreach (DB::table('procurement_customer_contract')->where('customer_contract_id', $pa->id)->pluck('procurement_id') as $pid) {
                $current = DB::table('procurements')->where('id', $pid)->value('pa_number');
                DB::table('procurements')->where('id', $pid)->update(['pa_number' => $current ? "{$current}, {$pa->number}" : $pa->number]);
            }
            DB::table('procurement_customer_contract')->where('customer_contract_id', $pa->id)->delete();
            DB::table('customer_contracts')->where('id', $pa->id)->delete();
        }

        foreach (['PLN 1', 'PLN 2'] as $sub) {
            DB::table('budget_codes')->whereNull('sub_bidang')->where('cost_center', 'like', "%{$sub}%")->update(['sub_bidang' => $sub]);
        }
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $t) {
            $t->string('work_contract_number')->nullable()->after('vendor_id');
            $t->date('work_contract_date')->nullable()->after('work_contract_number');
            $t->string('work_contract_link', 1000)->nullable()->after('work_contract_date');
        });
        foreach (DB::table('vendor_contracts')->where('is_primary', false)->get() as $c) {
            DB::table('procurements')->where('id', $c->procurement_id)->update([
                'work_contract_number' => $c->number, 'work_contract_date' => $c->date, 'work_contract_link' => $c->link,
            ]);
        }
        // Amandemen Kontrak Pengadaan ikut terhapus (cascade). Nomor PA tidak dikembalikan menjadi perjanjian pelanggan.
        DB::table('vendor_contracts')->where('is_primary', false)->delete();
        Schema::table('vendor_contracts', function (Blueprint $t) {
            $t->dropUnique(['procurement_id', 'is_primary']);
            $t->unique('procurement_id');
        });
        Schema::table('vendor_contracts', fn (Blueprint $t) => $t->dropColumn('is_primary'));
        Schema::table('procurements', fn (Blueprint $t) => $t->dropColumn('pa_number'));
    }
};
