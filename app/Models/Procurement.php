<?php

namespace App\Models;

use App\Enums\Track;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Procurement extends Model
{
    protected $fillable = [
        'code', 'spp_number', 'spp_date', 'title', 'summary', 'method', 'proc_type', 'proc_method', 'priority',
        'sub_bidang', 'pengaju_name', 'pic_pengaju_id', 'registered_at', 'rab_value', 'pks_id', 'bak_number',
        'bak_date', 'vendor_id', 'workflow', 'is_draft', 'cancelled_at', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'spp_date' => 'date', 'registered_at' => 'date', 'bak_date' => 'date', 'cancelled_at' => 'datetime',
        'workflow' => 'array', 'is_draft' => 'boolean', 'rab_value' => 'decimal:2',
    ];

    /** URL memakai ID monitoring (PRC-...) */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function picPengaju(): BelongsTo
    {
        return $this->belongsTo(Pic::class, 'pic_pengaju_id');
    }

    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'vendor_id');
    }

    public function customerContracts(): BelongsToMany
    {
        return $this->belongsToMany(CustomerContract::class, 'procurement_customer_contract');
    }

    public function candidateVendors(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'procurement_candidate_vendor');
    }

    public function budgetCodes(): BelongsToMany
    {
        return $this->belongsToMany(BudgetCode::class);
    }

    public function rabItems(): HasMany
    {
        return $this->hasMany(RabItem::class)->orderBy('sort');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(StageVisit::class)->orderBy('entered_at')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StageActivity::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(VendorContract::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(PaymentTerm::class)->orderBy('seq');
    }

    public function termHistories(): HasMany
    {
        return $this->hasMany(TermPlanHistory::class)->latest();
    }

    public function beritaAcara(): HasMany
    {
        return $this->hasMany(BeritaAcara::class)->orderBy('date');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest('created_at');
    }

    /** Label utama pengadaan untuk tampilan: Nomor SPP, fallback ke ID monitoring. */
    public function label(): string
    {
        return $this->spp_number ?: $this->code;
    }

    /** Tahap Proses Pengadaan yang sedang berjalan (alur asli atau amandemen). */
    public function currentApprovalVisit(): ?StageVisit
    {
        return $this->visits->where('track', Track::Pengadaan->value)->where('status', VisitStatus::Running->value)->last();
    }

    public function activeAmendment(): ?ContractAmendment
    {
        return $this->contract?->amendments->firstWhere('status', 'Dalam Proses');
    }
}
