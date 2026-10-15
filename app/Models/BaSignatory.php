<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaSignatory extends Model
{
    protected $fillable = ['ba_round_id', 'pic_id', 'sent_at', 'status', 'signed_at', 'note'];

    protected $casts = ['sent_at' => 'date', 'signed_at' => 'date'];

    /** @return BelongsTo<Pic, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }

    /** @return BelongsTo<BaRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(BaRound::class, 'ba_round_id');
    }
}
