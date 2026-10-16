<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTemplate extends Model
{
    public const SCHEMES = ['SPP Pusat', 'SPP Kemitraan', 'SPR', 'Pasca'];

    protected $fillable = ['scheme', 'key', 'name', 'group', 'enabled', 'optional', 'sort', 'default_activities', 'pic_id'];

    protected $casts = ['enabled' => 'boolean', 'optional' => 'boolean', 'default_activities' => 'array'];

    /**
     * Tahap sebagai array sederhana (snapshot yang disimpan di pengadaan).
     *
     * @return array<int, array{key:string,name:string,group:?string,enabled:bool,optional:bool,pic_id:?int,default_activities:array<int,string>}>
     */
    public static function stagesFor(string $scheme): array
    {
        return static::query()->where('scheme', $scheme)->orderBy('sort')->get()
            ->map(fn (self $t) => [
                'key' => $t->key, 'name' => $t->name, 'group' => $t->group, 'enabled' => $t->enabled, 'optional' => $t->optional,
                'pic_id' => $t->pic_id, 'default_activities' => $t->default_activities ?? [],
            ])->all();
    }
}
