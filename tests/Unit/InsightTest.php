<?php

use App\Models\AppSetting;
use App\Services\ProcurementInsight;

/** Alert durasi: hijau 0–4, kuning 5–6, merah ≥ 7 hari kerja (ambang dapat diubah di Master). */
it('menentukan warna alert durasi sesuai ambang', function () {
    $i = app(ProcurementInsight::class);

    expect($i->durLevel(0))->toBe('ok')
        ->and($i->durLevel(4))->toBe('ok')
        ->and($i->durLevel(5))->toBe('warn')
        ->and($i->durLevel(6))->toBe('warn')
        ->and($i->durLevel(7))->toBe('bad');
});

it('mengikuti ambang yang diubah administrator', function () {
    AppSetting::put('dur_warn', 3);
    AppSetting::put('dur_bad', 5);
    $i = app(ProcurementInsight::class);

    expect($i->durLevel(2))->toBe('ok')->and($i->durLevel(3))->toBe('warn')->and($i->durLevel(5))->toBe('bad');
});
