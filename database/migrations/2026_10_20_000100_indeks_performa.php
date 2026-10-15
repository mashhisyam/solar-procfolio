<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk filter dan relasi yang dipakai di setiap halaman. PostgreSQL tidak membuat indeks otomatis
 * untuk kolom foreign key, sehingga eager load "where procurement_id in (...)" memindai seluruh tabel.
 */
return new class extends Migration
{
    /** tabel => daftar indeks (tiap indeks = daftar kolom) */
    private const INDEXES = [
        'procurements' => [['registered_at'], ['is_draft', 'cancelled_at'], ['pic_pengaju_id'], ['pks_id']],
        'procurement_customer_contract' => [['customer_contract_id']],
        'budget_code_procurement' => [['budget_code_id']],
        'rab_items' => [['procurement_id']],
        'documents' => [['procurement_id']],
        'stage_visits' => [['amendment_id']],
        'stage_activities' => [['stage_visit_id'], ['procurement_id']],
        'contract_amendments' => [['vendor_contract_id', 'status']],
        'berita_acara' => [['procurement_id']],
        'ba_rounds' => [['berita_acara_id']],
        'ba_signatories' => [['ba_round_id']],
        'berita_acara_payment_term' => [['payment_term_id']],
        'pks_amendments' => [['pks_id']],
        'customer_contracts' => [['customer_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $cols) {
                if (! Schema::hasIndex($table, $cols)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($cols, $this->name($table, $cols)));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $cols) {
                if (Schema::hasIndex($table, $this->name($table, $cols))) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($this->name($table, $cols)));
                }
            }
        }
    }

    /** @param array<int, string> $cols */
    private function name(string $table, array $cols): string
    {
        return 'pf_'.$table.'_'.implode('_', $cols).'_idx';
    }
};
