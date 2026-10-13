<?php

use App\Models\Holiday;
use App\Support\WorkdayCalendar;

/** Hari masuk tahap tidak dihitung; akhir pekan, libur dan cuti bersama dikecualikan. */
it('menghitung hari kerja tanpa menghitung hari masuk tahap', function () {
    $cal = app(WorkdayCalendar::class);

    // Senin 5 Jan 2026 → Jumat 9 Jan 2026 = 4 hari kerja.
    expect($cal->workdaysBetween('2026-01-05', '2026-01-09'))->toBe(4)
        ->and($cal->workdaysBetween('2026-01-05', '2026-01-05'))->toBe(0)
        ->and($cal->workdaysBetween('2026-01-09', '2026-01-12'))->toBe(1); // lewat akhir pekan
});

it('mengecualikan libur nasional dan cuti bersama', function () {
    Holiday::query()->create(['date' => '2026-01-07', 'name' => 'Libur uji', 'type' => 'Libur Nasional']);
    $cal = app(WorkdayCalendar::class);

    expect($cal->workdaysBetween('2026-01-05', '2026-01-09'))->toBe(3);
});

it('menghitung hari kerja pengganti sebagai hari kerja', function () {
    Holiday::query()->create(['date' => '2026-01-10', 'name' => 'Pengganti uji', 'type' => 'Hari Kerja Pengganti']);
    $cal = app(WorkdayCalendar::class);

    expect($cal->isWorkday('2026-01-10'))->toBeTrue()
        ->and($cal->isWorkday('2026-01-11'))->toBeFalse();
});
