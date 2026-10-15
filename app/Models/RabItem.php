<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RabItem extends Model
{
    protected $fillable = ['procurement_id', 'description', 'budget_type', 'budget_code_id', 'qty', 'unit', 'price', 'sort'];

    protected $casts = ['qty' => 'decimal:2', 'price' => 'decimal:2'];

    /** @return BelongsTo<BudgetCode, $this> */
    public function budgetCode(): BelongsTo
    {
        return $this->belongsTo(BudgetCode::class);
    }

    public function total(): float
    {
        return (float) $this->qty * (float) $this->price;
    }
}
