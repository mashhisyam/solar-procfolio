<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SP2K / PO. Satu pengadaan memiliki maksimal satu kontrak vendor utama. */
class VendorContract extends Model
{
    protected $fillable = ['procurement_id', 'kind', 'number', 'title', 'vendor_id', 'date', 'kickoff_date', 'end_date', 'value_excl', 'value_incl', 'scope', 'link'];

    protected $casts = ['date' => 'date', 'kickoff_date' => 'date', 'end_date' => 'date', 'value_excl' => 'decimal:2', 'value_incl' => 'decimal:2'];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'vendor_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class)->orderBy('seq');
    }
}
