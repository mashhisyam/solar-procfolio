<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    public const CHECKLIST = ['invoice' => 'Invoice', 'faktur' => 'Faktur Pajak', 'ba' => 'BA terkait', 'gr' => 'Dokumen GR', 'laporan' => 'Laporan Pekerjaan'];

    protected $fillable = ['payment_term_id', 'number', 'date', 'amount', 'pic_id', 'status', 'checklist', 'note'];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2', 'checklist' => 'array'];

    public function term(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class, 'payment_term_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Pic::class);
    }
}
