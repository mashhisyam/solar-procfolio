<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GoodsReceipt extends Model
{
    public const TYPES = ['BAST', 'BAPS', 'BA Rekon', 'BAUK'];

    protected $fillable = ['payment_term_id', 'status', 'number', 'gr_type', 'amount', 'submitted_at', 'gr_date', 'source_app', 'pic_id', 'link', 'note'];

    protected $casts = ['submitted_at' => 'date', 'gr_date' => 'date'];

    public function term(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function beritaAcara(): BelongsToMany
    {
        return $this->belongsToMany(BeritaAcara::class, 'berita_acara_goods_receipt');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }
}
