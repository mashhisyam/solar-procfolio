<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BudgetCode extends Model
{
    protected $fillable = ['coa', 'cost_center', 'code', 'program', 'klasifikasi', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function procurements(): BelongsToMany
    {
        return $this->belongsToMany(Procurement::class);
    }
}
