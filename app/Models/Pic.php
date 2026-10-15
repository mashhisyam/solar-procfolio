<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pic extends Model
{
    protected $fillable = ['name', 'title', 'company_id', 'role', 'sub_bidang', 'email', 'phone', 'active'];

    protected $casts = ['active' => 'boolean'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
