<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterItem extends Model
{
    /** Kelompok master daftar beserta label tampilannya. */
    public const GROUPS = [
        'proc_method' => 'Metode pengadaan (SPP)',
        'proc_type' => 'Jenis pengadaan',
        'proc_category' => 'Kategori pengadaan',
        'activity' => 'Kategori aktivitas',
        'doc_type' => 'Jenis dokumen',
        'ba_type' => 'Jenis BA',
        'contract_type' => 'Jenis kontrak',
        'change_part' => 'Bagian amandemen PKS',
        'rab_unit' => 'Satuan RAB',
    ];

    protected $fillable = ['group', 'label', 'sort', 'active', 'locked'];

    protected $casts = ['active' => 'boolean', 'locked' => 'boolean'];

    /** @return array<int, string> */
    public static function labels(string $group): array
    {
        return static::query()->where('group', $group)->where('active', true)->orderBy('sort')->orderBy('label')->pluck('label')->all();
    }
}
