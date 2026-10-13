<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only; tidak ada fitur ubah atau hapus. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['procurement_id', 'user_id', 'entity', 'entity_id', 'action', 'field', 'old_value', 'new_value', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
