<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaRound extends Model
{
    protected $fillable = ['berita_acara_id', 'seq', 'sent_at', 'context'];

    protected $casts = ['sent_at' => 'date'];

    public function beritaAcara(): BelongsTo
    {
        return $this->belongsTo(BeritaAcara::class);
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(BaSignatory::class)->orderBy('id');
    }
}
