<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageActivity extends Model
{
    protected $fillable = ['procurement_id', 'stage_visit_id', 'date', 'category', 'pic_id', 'note', 'kind', 'user_id'];

    protected $casts = ['date' => 'date'];

    /** @return BelongsTo<Pic, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }
}
