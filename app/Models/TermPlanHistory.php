<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermPlanHistory extends Model
{
    protected $fillable = ['procurement_id', 'contract_amendment_id', 'reason', 'snapshot'];

    protected $casts = ['snapshot' => 'array'];
}
