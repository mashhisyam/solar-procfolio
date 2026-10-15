<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu kunjungan tahap (siklus ulang dicatat sebagai kunjungan baru). */
class StageVisit extends Model
{
    protected $fillable = [
        'procurement_id', 'track', 'term_id', 'amendment_id', 'stage_key', 'stage_name', 'cycle', 'entered_at',
        'completed_at', 'status', 'pic_id', 'activity', 'note', 'return_reason', 'return_type', 'resume_key', 'last_update_at',
    ];

    protected $casts = ['entered_at' => 'date', 'completed_at' => 'date', 'last_update_at' => 'date'];

    /** @return BelongsTo<Procurement, $this> */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /** @return BelongsTo<Pic, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }

    /** @return BelongsTo<PaymentTerm, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'term_id');
    }

    /** @return BelongsTo<ContractAmendment, $this> */
    public function amendment(): BelongsTo
    {
        return $this->belongsTo(ContractAmendment::class, 'amendment_id');
    }

    /** @return HasMany<StageActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(StageActivity::class)->orderBy('date')->orderBy('id');
    }
}
