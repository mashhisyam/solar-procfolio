<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Submitted = 'Submit Invoice';
    case Verification = 'Verifikasi';
    case Revision = 'Perlu Revisi';
    case Queue = 'Antri Bayar';
    case Paid = 'Dibayar';

    /** Status invoice mengikuti tahap Progres 2. */
    public static function forStage(string $stageKey): ?self
    {
        return match ($stageKey) {
            'invoice' => self::Submitted,
            'verifikasi' => self::Verification,
            'antri' => self::Queue,
            default => null,
        };
    }
}
