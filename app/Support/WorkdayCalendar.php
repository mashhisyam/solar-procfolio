<?php

namespace App\Support;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Kalender hari kerja Indonesia: Sabtu/Minggu, libur nasional dan cuti bersama dikecualikan;
 * "Hari Kerja Pengganti" dihitung sebagai hari kerja.
 */
class WorkdayCalendar
{
    /** @var array<string, bool>|null */
    private ?array $off = null;

    /** @var array<string, bool>|null */
    private ?array $extraWork = null;

    private function load(): void
    {
        if ($this->off !== null) {
            return;
        }
        $this->off = [];
        $this->extraWork = [];
        foreach (Holiday::query()->get(['date', 'type']) as $h) {
            $key = $h->date->toDateString();
            if ($h->type === 'Hari Kerja Pengganti') {
                $this->extraWork[$key] = true;
            } else {
                $this->off[$key] = true;
            }
        }
    }

    public function flush(): void
    {
        $this->off = $this->extraWork = null;
    }

    public static function iso(DateTimeInterface|string|null $d): ?string
    {
        if ($d === null || $d === '') {
            return null;
        }

        return $d instanceof DateTimeInterface ? $d->format('Y-m-d') : substr($d, 0, 10);
    }

    public function isWorkday(string $iso): bool
    {
        $this->load();
        if (isset($this->extraWork[$iso])) {
            return true;
        }
        $dow = (int) CarbonImmutable::parse($iso)->dayOfWeek;
        if ($dow === 0 || $dow === 6) {
            return false;
        }

        return ! isset($this->off[$iso]);
    }

    /** Hari kerja terpakai pada rentang (dari, sampai]. Hari masuk tahap tidak dihitung. */
    public function workdaysBetween(DateTimeInterface|string|null $from, DateTimeInterface|string|null $to): int
    {
        $a = self::iso($from);
        $b = self::iso($to);
        if (! $a || ! $b || $b <= $a) {
            return 0;
        }
        $n = 0;
        $cur = CarbonImmutable::parse($a)->addDay();
        $end = CarbonImmutable::parse($b);
        $guard = 0;
        while ($cur->lte($end) && $guard < 4000) {
            if ($this->isWorkday($cur->toDateString())) {
                $n++;
            }
            $cur = $cur->addDay();
            $guard++;
        }

        return $n;
    }

    public static function addDays(DateTimeInterface|string $iso, int $n): string
    {
        return CarbonImmutable::parse(self::iso($iso))->addDays($n)->toDateString();
    }

    public static function daysBetween(DateTimeInterface|string $a, DateTimeInterface|string $b): int
    {
        return (int) CarbonImmutable::parse(self::iso($a))->diffInDays(CarbonImmutable::parse(self::iso($b)), false);
    }

    public static function today(): string
    {
        return now()->toDateString();
    }
}
