<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    public const TYPES = ['Internal', 'Pelanggan', 'Mitra', 'Vendor'];

    protected $fillable = ['name', 'type', 'active'];

    protected $casts = ['active' => 'boolean'];
}
