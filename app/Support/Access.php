<?php

namespace App\Support;

/**
 * RBAC Procfolio: dua peran (Super Admin, Admin) + hak akses per menu untuk setiap pengguna.
 * - Super Admin: seluruh menu tingkat Ubah, Kelola Pengguna, dan hapus pengadaan. Tidak dapat dibatasi.
 * - Admin: hak akses per menu diatur Super Admin (Tidak ada / Lihat / Ubah).
 * "Ubah" di Daftar Pengadaan mencakup semua aksi di halaman detail pengadaan (proses, kontrak, tahapan, GR, invoice, BA baru).
 */
final class Access
{
    public const NONE = 'none';

    public const VIEW = 'view';

    public const EDIT = 'edit';

    /** kunci => [label, URL, tingkat yang dapat dipilih] — urutan sama dengan menu samping. */
    public const MENUS = [
        'dashboard' => ['Dashboard', '/', [self::NONE, self::VIEW]],
        'pengadaan' => ['Daftar Pengadaan', '/pengadaan', [self::NONE, self::VIEW, self::EDIT]],
        'rkap' => ['RKAP', '/rkap', [self::NONE, self::VIEW, self::EDIT]],
        'bakb' => ['Perjanjian Pelanggan', '/bakb', [self::NONE, self::VIEW, self::EDIT]],
        'pks' => ['PKS & Amandemen', '/pks', [self::NONE, self::VIEW, self::EDIT]],
        'kontrak' => ['SP2K/PO & Kontrak', '/kontrak', [self::NONE, self::VIEW, self::EDIT]],
        'dokumen' => ['Daftar TOR, BCR, BAK', '/dokumen', [self::NONE, self::VIEW]],
        'ba' => ['Berita Acara Penyelesaian', '/ba', [self::NONE, self::VIEW, self::EDIT]],
        'tahapan' => ['Tahapan, GR & Invoice', '/tahapan', [self::NONE, self::VIEW]],
        'master' => ['Master & Konfigurasi', '/master', [self::NONE, self::VIEW, self::EDIT]],
    ];

    /** Hak bawaan Admin baru: operasional dapat diubah, RKAP dilihat, Master tidak. */
    public const ADMIN_DEFAULT = [
        'dashboard' => self::VIEW, 'pengadaan' => self::EDIT, 'rkap' => self::VIEW, 'bakb' => self::EDIT, 'pks' => self::EDIT,
        'kontrak' => self::EDIT, 'dokumen' => self::VIEW, 'ba' => self::EDIT, 'tahapan' => self::VIEW, 'master' => self::NONE,
    ];

    /** Hanya lihat seluruh menu operasional (dipakai untuk akun SSO baru dan peran lama "viewer"). */
    public const VIEW_ONLY = [
        'dashboard' => self::VIEW, 'pengadaan' => self::VIEW, 'rkap' => self::VIEW, 'bakb' => self::VIEW, 'pks' => self::VIEW,
        'kontrak' => self::VIEW, 'dokumen' => self::VIEW, 'ba' => self::VIEW, 'tahapan' => self::VIEW, 'master' => self::NONE,
    ];

    /** Seluruh menu tingkat tertinggi (dipakai untuk peran lama "admin"). */
    public static function full(): array
    {
        return collect(self::MENUS)->keys()->mapWithKeys(fn ($k) => [$k => self::top($k)])->all();
    }

    /** Tingkat tertinggi yang tersedia untuk satu menu. */
    public static function top(string $menu): string
    {
        $levels = self::MENUS[$menu][2];

        return $levels[count($levels) - 1];
    }

    public static function rank(string $level): int
    {
        return match ($level) {
            self::EDIT => 2, self::VIEW => 1, default => 0,
        };
    }

    /**
     * Rapikan isian hak akses: menu tak dikenal dibuang, tingkat di luar pilihan menu diturunkan ke tingkat tertinggi yang diizinkan.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalize(array $input): array
    {
        $out = [];
        foreach (self::MENUS as $key => [, , $levels]) {
            $want = (string) ($input[$key] ?? self::NONE);
            if (! in_array($want, $levels, true)) {
                $want = self::rank($want) > 0 ? $levels[count($levels) - 1] : self::NONE;
            }
            $out[$key] = $want;
        }

        return $out;
    }
}
