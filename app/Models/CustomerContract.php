<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Perjanjian pelanggan: BAKB, Kontrak Perjanjian, atau Surat Penugasan. PA cukup dicatat sebagai nomor di pengadaan. */
class CustomerContract extends Model
{
    public const KINDS = ['Berita Acara Kesepakatan Biaya', 'Kontrak Perjanjian', 'Surat Penugasan'];

    protected $fillable = ['kind', 'sub_bidang', 'number', 'title', 'customer_id', 'date', 'revenue', 'link'];

    protected $casts = ['date' => 'date', 'revenue' => 'decimal:2'];

    /** @return BelongsTo<Company, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }

    /** @return BelongsToMany<Procurement, $this> */
    public function procurements(): BelongsToMany
    {
        return $this->belongsToMany(Procurement::class, 'procurement_customer_contract');
    }
}
