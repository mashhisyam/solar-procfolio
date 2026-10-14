<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Tahap pembayaran; setiap tahap memiliki satu alur Pasca Pengadaan. */
class PaymentTerm extends Model
{
    protected $fillable = ['procurement_id', 'seq', 'name', 'kind', 'percent', 'amount', 'planned_end', 'period', 'bucket_code', 'bucket_link', 'paid_at'];

    protected $casts = ['percent' => 'decimal:2', 'amount' => 'decimal:2', 'planned_end' => 'date', 'paid_at' => 'date'];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function beritaAcara(): BelongsToMany
    {
        return $this->belongsToMany(BeritaAcara::class, 'berita_acara_payment_term');
    }

    public function goodsReceipt(): HasOne
    {
        return $this->hasOne(GoodsReceipt::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(StageVisit::class, 'term_id')->orderBy('entered_at')->orderBy('id');
    }
}
