<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BudgetCode extends Model
{
    protected $fillable = ['coa', 'cost_center', 'sub_bidang', 'code', 'program', 'klasifikasi', 'budget_amount', 'year', 'active'];

    protected $casts = ['active' => 'boolean', 'budget_amount' => 'decimal:2'];

    /**
     * Anggaran terpakai per PRK: total RAB pengadaan aktif (bukan draft, tidak dibatalkan).
     *
     * @return array<int, float>
     */
    public static function usedAmounts(): array
    {
        return \Illuminate\Support\Facades\DB::table('budget_code_procurement as bp')
            ->join('procurements as p', 'p.id', '=', 'bp.procurement_id')
            ->where('p.is_draft', false)->whereNull('p.cancelled_at')
            ->groupBy('bp.budget_code_id')
            ->selectRaw('bp.budget_code_id as id, sum(p.rab_value) as used')
            ->pluck('used', 'id')->map(fn ($v) => (float) $v)->all();
    }

    public function procurements(): BelongsToMany
    {
        return $this->belongsToMany(Procurement::class);
    }
}
