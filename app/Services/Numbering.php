<?php

namespace App\Services;

use App\Models\Procurement;

class Numbering
{
    /** ID monitoring berikutnya: PRC-{tahun}-{urut 4 digit}. */
    public function nextProcurementCode(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = config('procfolio.code_prefix', 'PRC')."-{$year}-";
        $last = Procurement::query()->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
