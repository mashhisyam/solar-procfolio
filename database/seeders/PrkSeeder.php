<?php

namespace Database\Seeders;

use App\Models\BudgetCode;
use Illuminate\Database\Seeder;

/**
 * Impor daftar PRK dari database/data/prk.csv. Pemisah koma atau titik koma (format Excel Indonesia).
 * Kolom wajib: coa, kode_anggaran, program, klasifikasi, cost_center.
 * Kolom opsional: sub_bidang (bila kosong ditebak dari cost center), nilai_pengajuan, tahun.
 * PRK yang sudah ada diperbarui berdasarkan kode anggaran; nilai pengajuan hanya ditimpa bila kolomnya diisi.
 *
 * Jalankan: php artisan db:seed --class=PrkSeeder
 */
class PrkSeeder extends Seeder
{
    public const FILE = 'database/data/prk.csv';

    public function run(): void
    {
        $path = base_path(self::FILE);
        if (! is_file($path)) {
            $this->command->warn('Berkas '.self::FILE.' tidak ditemukan; impor PRK dilewati.');

            return;
        }
        $fh = fopen($path, 'r');
        $first = (string) fgets($fh);
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $head = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), str_getcsv($first, $sep));
        $made = $updated = 0;
        while (($cols = fgetcsv($fh, 0, $sep)) !== false) {
            if (count(array_filter($cols, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $r = array_combine($head, array_pad(array_slice($cols, 0, count($head)), count($head), null));
            $code = strtoupper(trim((string) ($r['kode_anggaran'] ?? '')));
            if ($code === '') {
                continue;
            }
            $cc = trim((string) ($r['cost_center'] ?? ''));
            $values = [
                'coa' => trim((string) ($r['coa'] ?? '')),
                'program' => mb_strtoupper(trim((string) ($r['program'] ?? ''))),
                'klasifikasi' => mb_strtoupper(trim((string) ($r['klasifikasi'] ?? ''))),
                'cost_center' => $cc,
                'sub_bidang' => trim((string) ($r['sub_bidang'] ?? '')) ?: self::subBidangOf($cc),
                'active' => true,
            ];
            $amount = preg_replace('/[^\d]/', '', (string) ($r['nilai_pengajuan'] ?? ''));
            if ($amount !== '') {
                $values['budget_amount'] = (float) $amount;
            }
            if (! empty($r['tahun'])) {
                $values['year'] = (int) $r['tahun'];
            }
            $existing = BudgetCode::query()->where('code', $code)->first();
            $existing ? $existing->update($values) : BudgetCode::query()->create(['code' => $code, 'budget_amount' => 0, ...$values]);
            $existing ? $updated++ : $made++;
        }
        fclose($fh);
        $this->command->info("PRK: {$made} baru, {$updated} diperbarui.");
    }

    /** Sub bidang ditebak dari nama cost center, mis. "I0325 - SUB BID SOL ARCH PLN 2" -> "PLN 2". */
    public static function subBidangOf(?string $costCenter): ?string
    {
        return preg_match('/PLN\s*([12])\b/i', (string) $costCenter, $m) ? 'PLN '.$m[1] : null;
    }
}
