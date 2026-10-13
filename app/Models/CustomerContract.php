<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** BAKB / kontrak pelanggan. */
class CustomerContract extends Model
{
    protected $fillable = ['number', 'title', 'customer_id', 'date', 'link'];

    protected $casts = ['date' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'customer_id');
    }

    public function procurements(): BelongsToMany
    {
        return $this->belongsToMany(Procurement::class);
    }
}
