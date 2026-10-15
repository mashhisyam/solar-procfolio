<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Pks extends Model
{
    protected $table = 'pks';

    protected $fillable = ['number', 'title', 'partner_id', 'date', 'start_date', 'end_date', 'scope', 'link'];

    protected $casts = ['date' => 'date', 'start_date' => 'date', 'end_date' => 'date'];

    /** @return BelongsTo<Company, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'partner_id');
    }

    /** @return HasMany<PksAmendment, $this> */
    public function amendments(): HasMany
    {
        return $this->hasMany(PksAmendment::class)->orderBy('seq');
    }

    /** @return HasMany<Procurement, $this> */
    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    /**
     * Versi efektif: amandemen terakhir yang tanggal efektifnya tidak melewati tanggal acuan.
     *
     * @return array{label:string,end_date:?string,scope:?string,future:int}
     */
    public function effective(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $effective = $this->amendments->filter(fn (PksAmendment $a) => $a->effective_date->lte($asOf));
        $future = $this->amendments->count() - $effective->count();
        $endDate = $this->end_date?->toDateString();
        $scope = $this->scope;
        foreach ($effective as $a) {
            $endDate = $a->new_end_date?->toDateString() ?? $endDate;
            $scope = $a->new_scope ?: $scope;
        }
        $last = $effective->last();

        return [
            'label' => $last ? "Amandemen {$last->seq} ({$last->number})" : 'PKS Induk',
            'end_date' => $endDate,
            'scope' => $scope,
            'future' => $future,
        ];
    }
}
