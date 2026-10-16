<?php

namespace App\Support;

use App\Models\BudgetCode;

/**
 * Katalog PRK dari database/data/prk.csv (pemisah koma atau titik koma). Dipakai migrasi dan PrkSeeder sehingga
 * daftar PRK selalu terbawa setiap kali database dimigrasi atau diisi ulang.
 * Kolom wajib: coa, kode_anggaran, program, klasifikasi, cost_center. Opsional: sub_bidang, nilai_pengajuan, tahun.
 * PRK yang sudah ada diperbarui menurut kode anggaran; nilai pengajuan hanya ditimpa bila kolomnya diisi.
 */
final class PrkCatalog
{
    public const FILE = 'database/data/prk.csv';

    /** @return array{created:int, updated:int}|null null bila berkas tidak ada */
    public static function import(?string $path = null): ?array
    {
        $path ??= base_path(self::FILE);
        if (! is_file($path)) {
            return null;
        }
        $fh = fopen($path, 'r');
        $first = (string) fgets($fh);
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $head = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $h))), str_getcsv($first, $sep));
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
            $amount = (string) preg_replace('/[^\d]/', '', (string) ($r['nilai_pengajuan'] ?? ''));
            if ($amount !== '') {
                $values['budget_amount'] = (float) $amount;
            }
            if (! empty($r['tahun'])) {
                $values['year'] = (int) $r['tahun'];
            }
            $existing = BudgetCode::query()->where('code', $code)->first();
            if ($existing) {
                $existing->update($values);
                $updated++;
            } else {
                BudgetCode::query()->create(['code' => $code, 'budget_amount' => 0, ...$values]);
                $made++;
            }
        }
        fclose($fh);

        return ['created' => $made, 'updated' => $updated];
    }

    /** Sub bidang ditebak dari nama cost center, mis. "I0326 - SUB BID SOL ARCH PLN 1" -> "PLN 1". */
    public static function subBidangOf(?string $costCenter): ?string
    {
        return preg_match('/PLN\s*([12])\b/i', (string) $costCenter, $m) ? 'PLN '.$m[1] : null;
    }
}
