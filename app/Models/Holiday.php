<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    public const TYPES = ['Libur Nasional', 'Cuti Bersama', 'Hari Kerja Pengganti'];

    protected $fillable = ['date', 'name', 'type'];

    protected $casts = ['date' => 'date'];
}
