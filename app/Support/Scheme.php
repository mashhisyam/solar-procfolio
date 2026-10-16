<?php

namespace App\Support;

/**
 * Skema pengadaan:
 * - SPP Pusat: Pengaju -> Sales -> Anggaran -> Manager Pengadaan -> Rendan/Lakdan (menurut metode) -> SP2K.
 * - SPP Kemitraan: Pengaju -> Sales -> Anggaran -> Partnership -> PO.
 * - SPR: User Pengaju -> Manager Pengaju -> Manager Pengadaan -> SP2K, lalu langsung Pelaksanaan Pekerjaan.
 */
final class Scheme
{
    public const SPP = 'SPP Pusat';

    public const KEMITRAAN = 'SPP Kemitraan';

    public const SPR = 'SPR';

    public const ALL = [self::SPP, self::KEMITRAAN, self::SPR];

    /** Skema yang melewati unit pengadaan (Manager Pengadaan) dan berakhir di SP2K. */
    public static function viaProcurementUnit(?string $method): bool
    {
        return $method !== self::KEMITRAAN;
    }

    /** Hanya SPP Pusat yang memakai metode pengadaan untuk menentukan rute Rendan/Lakdan. */
    public static function usesProcMethod(?string $method): bool
    {
        return $method === self::SPP;
    }

    /** Skema dengan tahap Sales; nomor PA dapat dilengkapi saat approval Sales. */
    public static function hasSales(?string $method): bool
    {
        return $method !== self::SPR;
    }

    /** Dokumen yang menutup Proses Pengadaan. */
    public static function finalDoc(?string $method): string
    {
        return $method === self::KEMITRAAN ? 'PO' : 'SP2K';
    }

    /** Label nomor pengajuan di setiap skema. */
    public static function numberLabel(?string $method): string
    {
        return match ($method) {
            self::SPP => 'Nomor SPP',
            self::SPR => 'Nomor SPR',
            default => 'Nomor pengajuan',
        };
    }
}
