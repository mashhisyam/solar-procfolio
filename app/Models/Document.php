<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    public const REFERENCE_TYPES = ['Surat Penugasan'];

    protected $fillable = ['procurement_id', 'doc_type', 'number', 'title', 'date', 'required', 'available', 'link', 'customer_id', 'note'];

    protected $casts = ['date' => 'date', 'required' => 'boolean', 'available' => 'boolean'];

    /** @return BelongsTo<Procurement, $this> */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }
}
