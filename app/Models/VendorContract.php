<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kontrak vendor. Satu pengadaan memiliki satu SP2K/PO (is_primary) dan boleh memiliki satu Kontrak Pengadaan.
 * Hanya SP2K/PO yang mengunci Proses Pengadaan dan menjadi dasar tahapan pembayaran serta Cost.
 */
class VendorContract extends Model
{
    public const WORK_KIND = 'Kontrak Pengadaan';

    protected $fillable = ['procurement_id', 'is_primary', 'kind', 'number', 'title', 'vendor_id', 'date', 'kickoff_date', 'end_date', 'value_excl', 'value_incl', 'scope', 'link'];

    protected $casts = ['is_primary' => 'boolean', 'date' => 'date', 'kickoff_date' => 'date', 'end_date' => 'date', 'value_excl' => 'decimal:2', 'value_incl' => 'decimal:2'];

    /** @return BelongsTo<Procurement, $this> */
    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'vendor_id');
    }

    /** @return HasMany<ContractAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class)->orderBy('seq');
    }
}
