<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAmendment extends Model
{
    public const CHANGE_TYPES = ['Nilai', 'Jangka Waktu', 'Ruang Lingkup', 'Termin', 'Lainnya'];

    protected $fillable = ['vendor_contract_id', 'seq', 'status', 'reason', 'requested_at', 'stages', 'change_types', 'proposal', 'number', 'date', 'description', 'before', 'after', 'link'];

    protected $casts = ['requested_at' => 'date', 'date' => 'date', 'stages' => 'array', 'change_types' => 'array', 'before' => 'array', 'after' => 'array'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(VendorContract::class, 'vendor_contract_id');
    }
}
