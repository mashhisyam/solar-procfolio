<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PksAmendment extends Model
{
    protected $fillable = ['pks_id', 'seq', 'number', 'date', 'effective_date', 'changed_parts', 'description', 'new_end_date', 'new_scope', 'link'];

    protected $casts = ['date' => 'date', 'effective_date' => 'date', 'new_end_date' => 'date', 'changed_parts' => 'array'];

    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class);
    }
}
