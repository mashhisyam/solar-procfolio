<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\SettingsMemo;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    public const DEFAULTS = [
        'dur_warn' => 5,           // durasi tahap kuning mulai (hari kerja)
        'dur_bad' => 7,            // durasi tahap merah mulai (hari kerja)
        'no_update_days' => 3,     // batas tanpa pembaruan
        'ba_stale_days' => 5,      // batas BA di sirkuler
        'invoice_stale_days' => 5, // batas invoice tidak bergerak
        'deadline_warn_days' => 7, // attention batas pekerjaan kuning
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'json'];

    public static function get(string $key): mixed
    {
        // Dibaca dari cache sekali per permintaan; tanpa memo ini tiap pemanggilan memicu satu query ke tabel cache.
        $memo = app(SettingsMemo::class);
        $memo->all ??= Cache::remember('app_settings', 300, fn () => static::query()->pluck('value', 'key')->all());

        return $memo->all[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    /** @return array<string, mixed> */
    public static function allWithDefaults(): array
    {
        return array_merge(self::DEFAULTS, static::query()->pluck('value', 'key')->all());
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_settings');
        app(SettingsMemo::class)->all = null;
    }
}
