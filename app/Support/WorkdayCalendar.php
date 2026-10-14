<?php

namespace App\Support;

use App\Models\Holiday;
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

    /** Cap waktu UTC tengah malam untuk tanggal 'Y-m-d' (tanpa objek tanggal, jauh lebih murah dari Carbon). */
    private static function ts(string $iso): int
    {
        return gmmktime(0, 0, 0, (int) substr($iso, 5, 2), (int) substr($iso, 8, 2), (int) substr($iso, 0, 4));
    }

    private static function isWeekend(int $ts): bool
    {
        $w = (int) gmdate('w', $ts);

        return $w === 0 || $w === 6;
    }

    public function isWorkday(string $iso): bool
    {
        $this->load();
        if (isset($this->extraWork[$iso])) {
            return true;
        }
        if (self::isWeekend(self::ts($iso))) {
            return false;
        }

        return ! isset($this->off[$iso]);
    }

    /**
     * Hari kerja terpakai pada rentang (dari, sampai]. Hari masuk tahap tidak dihitung.
     * Dihitung dengan aritmetika (minggu penuh x 5, sisa hari, lalu koreksi libur), bukan iterasi harian.
     */
    public function workdaysBetween(DateTimeInterface|string|null $from, DateTimeInterface|string|null $to): int
    {
        $a = self::iso($from);
        $b = self::iso($to);
        if (! $a || ! $b || $b <= $a) {
            return 0;
        }
        $this->load();
        $ta = self::ts($a);
        $days = intdiv(self::ts($b) - $ta, 86400);
        $full = intdiv($days, 7);
        $n = $full * 5;
        // sisa hari setelah minggu penuh
        for ($k = 0, $d = $ta + ($full * 7 + 1) * 86400, $rem = $days % 7; $k < $rem; $k++, $d += 86400) {
            if (! self::isWeekend($d)) {
                $n++;
            }
        }
        // libur nasional/cuti bersama pada hari kerja mengurangi; hari kerja pengganti pada akhir pekan menambah
        foreach ($this->off as $h => $_) {
            if ($h > $a && $h <= $b && ! self::isWeekend(self::ts($h))) {
                $n--;
            }
        }
        foreach ($this->extraWork as $h => $_) {
            if ($h > $a && $h <= $b && self::isWeekend(self::ts($h))) {
                $n++;
            }
        }

        return $n;
    }

    public static function addDays(DateTimeInterface|string $iso, int $n): string
    {
        return gmdate('Y-m-d', self::ts((string) self::iso($iso)) + $n * 86400);
    }

    public static function daysBetween(DateTimeInterface|string $a, DateTimeInterface|string $b): int
    {
        return intdiv(self::ts((string) self::iso($b)) - self::ts((string) self::iso($a)), 86400);
    }

    public static function today(): string
    {
        return now()->toDateString();
    }
}
