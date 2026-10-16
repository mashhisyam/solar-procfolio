<?php

namespace App\Models;

use App\Enums\Track;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Procurement extends Model
{
    protected $fillable = [
        'code', 'spp_number', 'spp_date', 'title', 'summary', 'method', 'category', 'proc_method', 'priority',
        'sub_bidang', 'pengaju_name', 'pic_pengaju_id', 'registered_at', 'rab_value', 'pks_id',
        'pa_number', 'vendor_id', 'workflow', 'is_draft', 'cancelled_at', 'cancel_type', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'spp_date' => 'date', 'registered_at' => 'date', 'cancelled_at' => 'datetime',
        'workflow' => 'array', 'is_draft' => 'boolean', 'rab_value' => 'decimal:2',
    ];

    /** URL memakai ID monitoring (PRC-...) */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return BelongsTo<Pic, $this> */
    public function picPengaju(): BelongsTo
    {
        return $this->belongsTo(Pic::class, 'pic_pengaju_id');
    }

    /** @return BelongsTo<Pks, $this> */
    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'vendor_id');
    }

    /** @return BelongsToMany<CustomerContract, $this> */
    public function customerContracts(): BelongsToMany
    {
        return $this->belongsToMany(CustomerContract::class, 'procurement_customer_contract');
    }

    /** @return BelongsToMany<Company, $this> */
    public function candidateVendors(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'procurement_candidate_vendor');
    }

    /** @return BelongsToMany<BudgetCode, $this> */
    public function budgetCodes(): BelongsToMany
    {
        return $this->belongsToMany(BudgetCode::class);
    }

    /** @return HasMany<RabItem, $this> */
    public function rabItems(): HasMany
    {
        return $this->hasMany(RabItem::class)->orderBy('sort');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<StageVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(StageVisit::class)->orderBy('entered_at')->orderBy('id');
    }

    /** @return HasMany<StageActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(StageActivity::class);
    }

    /** Nomor BAK (SPP Kemitraan) diambil dari dokumen referensi BAK. */
    public function bakDocument(): ?Document
    {
        return $this->documents->firstWhere('doc_type', 'BAK');
    }

    /**
     * SP2K / PO: dokumen yang mengunci Proses Pengadaan dan menjadi dasar tahapan pembayaran.
     *
     * @return HasOne<VendorContract, $this>
     */
    public function contract(): HasOne
    {
        return $this->hasOne(VendorContract::class)->where('is_primary', true);
    }

    /**
     * Kontrak Pengadaan: kontrak di samping SP2K/PO, diperlakukan sama (koreksi data, amandemen).
     *
     * @return HasOne<VendorContract, $this>
     */
    public function workContract(): HasOne
    {
        return $this->hasOne(VendorContract::class)->where('is_primary', false);
    }

    /** @return HasMany<PaymentTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(PaymentTerm::class)->orderBy('seq');
    }

    /** @return HasMany<TermPlanHistory, $this> */
    public function termHistories(): HasMany
    {
        return $this->hasMany(TermPlanHistory::class)->latest();
    }

    /** @return HasMany<BeritaAcara, $this> */
    public function beritaAcara(): HasMany
    {
        return $this->hasMany(BeritaAcara::class)->orderBy('date');
    }

    /** @return HasMany<AuditLog, $this> */
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

    /**
     * Seluruh amandemen SP2K/PO dan Kontrak Pengadaan.
     *
     * @return Collection<int, ContractAmendment>
     */
    public function contractAmendments(): Collection
    {
        return collect([$this->contract, $this->workContract])->filter()->flatMap(fn (VendorContract $c) => $c->amendments->all())->values();
    }

    /** Amandemen yang sedang berjalan; hanya satu per pengadaan karena memakai alur Proses Pengadaan yang sama. */
    public function activeAmendment(): ?ContractAmendment
    {
        return $this->contractAmendments()->firstWhere('status', 'Dalam Proses');
    }
}
