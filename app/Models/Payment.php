<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['invoice_id', 'date', 'amount'];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];
}
