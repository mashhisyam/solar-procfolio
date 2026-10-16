<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi 21 Okt 2026:
 * - Jenis pengadaan dihapus; digabung ke kategori: Barang (Non Lisensi), Barang (Lisensi), Jasa, Barang Jasa (Supply Erect), Sewa.
 * - Nomor/tanggal BAK SPP Kemitraan pindah ke Dokumen Referensi (dokumen BAK).
 * - Skema SPR: User Pengaju -> Manager Pengaju -> Manager Pengadaan -> SP2K (dapat diubah di Master & Konfigurasi).
 */
return new class extends Migration
{
    public const CATEGORIES = ['Barang (Non Lisensi)', 'Barang (Lisensi)', 'Jasa', 'Barang Jasa (Supply Erect)', 'Sewa'];

    public function up(): void
    {
        foreach (DB::table('procurements')->get(['id', 'proc_type', 'category']) as $p) {
            DB::table('procurements')->where('id', $p->id)->update(['category' => self::mapCategory($p->proc_type, $p->category)]);
        }

        // BAK: lengkapi atau buat dokumen BAK dari kolom lama.
        foreach (DB::table('procurements')->whereNotNull('bak_number')->get(['id', 'bak_number', 'bak_date']) as $p) {
            $doc = DB::table('documents')->where('procurement_id', $p->id)->where('doc_type', 'BAK')->first();
            if ($doc) {
                DB::table('documents')->where('id', $doc->id)->update([
                    'number' => $doc->number ?: $p->bak_number, 'date' => $doc->date ?: $p->bak_date, 'available' => true, 'required' => true,
                ]);
            } else {
                DB::table('documents')->insert([
                    'procurement_id' => $p->id, 'doc_type' => 'BAK', 'number' => $p->bak_number, 'title' => 'BAK', 'date' => $p->bak_date,
                    'required' => true, 'available' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::table('procurements', function (Blueprint $t) {
            $t->dropColumn(['proc_type', 'bak_number', 'bak_date']);
        });

        DB::table('master_items')->where('group', 'proc_type')->delete();
        DB::table('master_items')->where('group', 'proc_category')->delete();
        foreach (self::CATEGORIES as $i => $label) {
            DB::table('master_items')->insert(['group' => 'proc_category', 'label' => $label, 'sort' => $i, 'active' => true, 'locked' => false, 'created_at' => now(), 'updated_at' => now()]);
        }

        if (! DB::table('workflow_templates')->where('scheme', 'SPR')->exists()) {
            $review = ['Review Dokumen', 'Approval', 'Disposisi', 'Klarifikasi'];
            $stages = [
                ['pengaju_user', 'User Pengaju', 'Pengaju', ['Review Dokumen', 'Lainnya']],
                ['pengaju_mgr', 'Manager Pengaju', 'Pengaju', $review],
                ['pengadaan_mgr', 'Manager Pengadaan', 'Manager Pengadaan', ['Review Dokumen', 'Disposisi', 'Klarifikasi', 'SP2K Rilis']],
            ];
            foreach ($stages as $i => [$key, $name, $group, $acts]) {
                DB::table('workflow_templates')->insert([
                    'scheme' => 'SPR', 'key' => $key, 'name' => $name, 'group' => $group, 'enabled' => true, 'optional' => false, 'sort' => $i,
                    'default_activities' => json_encode($acts), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $t) {
            $t->string('proc_type', 40)->nullable()->after('method');
            $t->string('bak_number')->nullable()->after('pks_id');
            $t->date('bak_date')->nullable()->after('bak_number');
        });
        foreach (DB::table('procurements')->get(['id', 'category']) as $p) {
            [$type, $cat] = match ($p->category) {
                'Barang (Lisensi)' => ['Barang', 'Lisensi'],
                'Jasa' => ['Jasa', 'Non Lisensi'],
                'Barang Jasa (Supply Erect)' => ['Barang & Jasa', 'Non Lisensi'],
                'Sewa' => ['Sewa', 'Non Lisensi'],
                default => ['Barang', 'Non Lisensi'],
            };
            $bak = DB::table('documents')->where('procurement_id', $p->id)->where('doc_type', 'BAK')->first();
            DB::table('procurements')->where('id', $p->id)->update(['proc_type' => $type, 'category' => $cat, 'bak_number' => $bak?->number, 'bak_date' => $bak?->date]);
        }
        DB::table('workflow_templates')->where('scheme', 'SPR')->delete();
    }

    /** Kategori baru dari jenis + kategori lama. */
    public static function mapCategory(?string $type, ?string $category): ?string
    {
        if ($category === null && $type === null) {
            return null;
        }
        if ($category === 'Lisensi') {
            return 'Barang (Lisensi)';
        }

        return match ($type) {
            'Jasa' => 'Jasa',
            'Barang & Jasa' => 'Barang Jasa (Supply Erect)',
            'Sewa' => 'Sewa',
            default => 'Barang (Non Lisensi)',
        };
    }
};
