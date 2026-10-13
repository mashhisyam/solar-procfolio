<?php

namespace App\Models;

use App\Enums\SignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BeritaAcara extends Model
{
    protected $table = 'berita_acara';

    protected $fillable = [
        'procurement_id', 'sub_bidang', 'type', 'number', 'contract_ref_type', 'contract_ref_number', 'title', 'date',
        'director_internal', 'director_external', 'first_party', 'second_party', 'link', 'manual_status', 'manual_reason',
    ];

    protected $casts = ['date' => 'date'];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(BaRound::class)->orderBy('seq');
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(PaymentTerm::class, 'berita_acara_payment_term');
    }

    public function activeRound(): ?BaRound
    {
        return $this->rounds->last();
    }

    /** Status otomatis dari putaran aktif; koreksi manual menang bila ada. */
    public function status(): string
    {
        if ($this->manual_status) {
            return $this->manual_status;
        }
        $round = $this->activeRound();
        if (! $round || $round->signatories->isEmpty()) {
            return 'Draft';
        }
        $statuses = $round->signatories->pluck('status');
        if ($statuses->every(fn ($s) => $s === SignStatus::Signed->value)) {
            return 'TTD Selesai';
        }
        if ($statuses->contains(SignStatus::Revision->value)) {
            return 'Perlu Revisi';
        }
        if ($statuses->every(fn ($s) => $s === SignStatus::Pending->value)) {
            return 'Belum Diproses';
        }

        return 'Proses TTD';
    }
}
