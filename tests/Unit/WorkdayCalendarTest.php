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

it('hasil aritmetika sama dengan hitungan harian pada banyak rentang', function () {
    Holiday::query()->create(['date' => '2026-03-19', 'name' => 'Nyepi', 'type' => 'Libur Nasional']);
    Holiday::query()->create(['date' => '2026-03-21', 'name' => 'Libur di hari Sabtu', 'type' => 'Libur Nasional']);
    Holiday::query()->create(['date' => '2026-02-07', 'name' => 'Pengganti Sabtu', 'type' => 'Hari Kerja Pengganti']);
    $cal = app(WorkdayCalendar::class);
    $manual = function (string $a, string $b) use ($cal): int {
        $n = 0;
        for ($d = strtotime($a.' UTC') + 86400; $d <= strtotime($b.' UTC'); $d += 86400) {
            $n += $cal->isWorkday(gmdate('Y-m-d', $d)) ? 1 : 0;
        }

        return $n;
    };
    foreach ([['2026-01-05', '2026-12-31'], ['2026-02-02', '2026-02-12'], ['2026-03-18', '2026-03-24'], ['2026-03-20', '2026-03-23'], ['2026-06-01', '2026-06-02']] as [$a, $b]) {
        expect($cal->workdaysBetween($a, $b))->toBe($manual($a, $b));
    }
});

it('menghitung selisih dan menambah hari tanpa Carbon', function () {
    expect(WorkdayCalendar::daysBetween('2026-01-05', '2026-01-12'))->toBe(7)
        ->and(WorkdayCalendar::daysBetween('2026-01-12', '2026-01-05'))->toBe(-7)
        ->and(WorkdayCalendar::addDays('2026-02-27', 2))->toBe('2026-03-01');
});
