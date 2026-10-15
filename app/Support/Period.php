<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter periode pengadaan berdasarkan tanggal registrasi: 2 bulan ke belakang (bawaan), 6 bulan, 1 tahun, atau semua.
 * Pilihan terakhir disimpan di sesi sehingga terbawa saat berpindah menu.
 */
final class Period
{
    public const DEFAULT = '2b';

    /** kunci => [label, jumlah bulan; null = tanpa batas] */
    public const OPTIONS = [
        '2b' => ['2 bulan terakhir', 2],
        '6b' => ['6 bulan terakhir', 6],
        '1t' => ['1 tahun terakhir', 12],
        'semua' => ['Semua waktu', null],
    ];

    private const SESSION_KEY = 'procfolio.periode';

    public static function current(Request $request): string
    {
        $asked = (string) $request->query('periode', '');
        if (isset(self::OPTIONS[$asked])) {
            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $asked);
            }

            return $asked;
        }
        $saved = $request->hasSession() ? (string) $request->session()->get(self::SESSION_KEY, '') : '';

        return isset(self::OPTIONS[$saved]) ? $saved : self::DEFAULT;
    }

    /** Tanggal registrasi paling awal yang masuk periode; null bila semua waktu. */
    public static function since(string $key): ?string
    {
        $months = array_key_exists($key, self::OPTIONS) ? self::OPTIONS[$key][1] : self::OPTIONS[self::DEFAULT][1];
        if ($months === null) {
            return null;
        }

        return now()->subMonthsNoOverflow($months)->toDateString();
    }

    /**
     * Batasi query pengadaan (atau relasi ke pengadaan) menurut periode.
     *
     * @template T of Builder
     *
     * @param  T  $query
     * @return T
     */
    public static function apply(Builder $query, string $key, ?string $relation = null): Builder
    {
        $since = self::since($key);
        if ($since === null) {
            return $query;
        }
        if ($relation) {
            $query->whereHas($relation, fn ($q) => $q->whereDate('registered_at', '>=', $since));
        } else {
            $query->whereDate('registered_at', '>=', $since);
        }

        return $query;
    }

    /** @return array{key:string, since:?string, options:array<int, array{value:string, label:string}>} */
    public static function share(Request $request): array
    {
        $key = self::current($request);

        return [
            'key' => $key,
            'since' => self::since($key),
            'options' => collect(self::OPTIONS)->map(fn ($o, $k) => ['value' => $k, 'label' => $o[0]])->values()->all(),
        ];
    }
}
