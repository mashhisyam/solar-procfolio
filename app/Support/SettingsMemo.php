<?php

namespace App\Support;

/**
 * Penampung pengaturan aplikasi selama satu permintaan.
 * Didaftarkan scoped di AppServiceProvider: dibuat baru untuk tiap permintaan (dan tiap tes), sehingga tidak basi.
 */
class SettingsMemo
{
    /** @var array<string, mixed>|null */
    public ?array $all = null;
}
