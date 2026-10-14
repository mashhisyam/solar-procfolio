<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Dasar pelanggan: BAKB, MoM Kesepakatan Harga, atau Kontrak Pelanggan. */
class CustomerContract extends Model
{
    public const KINDS = ['BAKB', 'MoM Kesepakatan Harga', 'Kontrak Pelanggan'];

    protected $fillable = ['kind', 'number', 'title', 'customer_id', 'date', 'link'];

    protected $casts = ['date' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }

    public function procurements(): BelongsToMany
    {
        return $this->belongsToMany(Procurement::class, 'procurement_customer_contract');
    }
}
