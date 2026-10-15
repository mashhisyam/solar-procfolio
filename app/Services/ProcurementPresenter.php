<?php

namespace App\Services;

use App\Enums\Track;
use App\Models\AuditLog;
use App\Models\BeritaAcara;
use App\Models\Company;
use App\Models\ContractAmendment;
use App\Models\Document;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\MasterItem;
use App\Models\Pic;
use App\Models\Procurement;
use App\Models\VendorContract;
use App\Models\WorkflowTemplate;
use App\Support\WorkdayCalendar as Cal;

/** Menyusun data halaman detail pengadaan (dua progres, kontrak, tahapan, BA, GR, invoice, riwayat). */
class ProcurementPresenter
{
    public function __construct(private ProcurementInsight $insight, private WorkflowService $workflow) {}

    /** @return array<string, mixed> */
    public function detail(Procurement $p): array
    {
        $p->loadMissing([...ProcurementInsight::WITH, 'workContract.vendor', 'visits.activities', 'rabItems.budgetCode', 'budgetCodes', 'candidateVendors',
            'customerContracts.customer', 'pks.partner', 'pks.amendments', 'documents.customer', 'terms.beritaAcara', 'terms.goodsReceipt.beritaAcara', 'terms.invoice.pic', 'terms.goodsReceipt.pic', 'beritaAcara.terms:id,seq']);
        $i = $this->insight;
        $row = $i->row($p);
        $approval = $i->currentApproval($p);
        $am = $p->activeAmendment();
        $amContract = $am ? collect([$p->contract, $p->workContract])->filter()->firstWhere('id', $am->vendor_contract_id) : null;
        $plans = $this->workflow->termPlans($p);
        $bas = $p->beritaAcara->keyBy('id');
        $pk = $p->pks;

        return [
            'procurement' => [
                'code' => $p->code, 'spp_number' => $p->spp_number, 'spp_date' => Cal::iso($p->spp_date), 'title' => $p->title, 'summary' => $p->summary,
                'method' => $p->method, 'proc_type' => $p->proc_type, 'category' => $p->category, 'proc_method' => $p->proc_method, 'priority' => $p->priority,
                'sub_bidang' => $p->sub_bidang, 'pengaju_name' => $p->pengaju_name, 'registered_at' => Cal::iso($p->registered_at),
                'rab_value' => (float) $p->rab_value, 'is_draft' => $p->is_draft, 'cancelled' => (bool) $p->cancelled_at, 'cancel_reason' => $p->cancel_reason,
                'cancel_type' => $p->cancel_type, 'cancelled_at' => Cal::iso($p->cancelled_at),
                // Pilihan penghentian yang tersedia saat ini: 'batal' (sebelum unit pengadaan), 'gagal' (sesudahnya), atau null.
                'stop_option' => WorkflowService::stopTypeFor($p),
                'status' => $i->status($p), 'post_state' => $i->postState($p), 'process_days' => $i->processDays($p),
                'last_update' => $row['last_update'], 'last_update_days' => $row['last_update_days'],
                'bak_number' => $p->bak_number, 'bak_date' => Cal::iso($p->bak_date), 'pa_number' => $p->pa_number,
                'pks' => $pk ? ['id' => $pk->id, 'number' => $pk->number, 'title' => $pk->title, 'partner' => $pk->partner?->name, ...$pk->effective()] : null,
                'candidate_vendors' => $p->candidateVendors->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                'vendor_id' => $p->vendor_id,
                'budget_codes' => $p->budgetCodes->map(fn ($b) => $b->only(['id', 'coa', 'cost_center', 'code', 'program', 'klasifikasi']))->values(),
                'rab_items' => $p->rabItems->map(fn ($r) => [
                    'id' => $r->id, 'description' => $r->description, 'budget_type' => $r->budget_type, 'budget_code' => $r->budgetCode?->code,
                    'program' => $r->budgetCode?->program, 'qty' => (float) $r->qty, 'unit' => $r->unit, 'price' => (float) $r->price, 'total' => $r->total(),
                ])->values(),
                'customer_contracts' => $p->customerContracts->map(fn ($c) => ['id' => $c->id, 'revenue' => $c->revenue !== null ? (float) $c->revenue : null, 'kind' => $c->kind, 'number' => $c->number, 'title' => $c->title, 'customer' => $c->customer?->name, 'link' => $c->link])->values(),
            ],
            'header' => $row,
            'alerts' => $i->alerts($p),
            'approval' => [
                'nodes' => $i->stepper($p, Track::Pengadaan),
                'history' => $i->history($p->visits->where('track', 'pengadaan')),
                'current' => $approval ? $this->visit($approval) : null,
                'stages' => $this->workflow->stages($p, Track::Pengadaan, $approval?->amendment_id),
                'all_stages' => $p->workflow,
                'locked' => (bool) $p->contract && ! $approval,
                'can_reopen' => ! $p->contract && ! $approval && ! $p->is_draft && $p->visits->isNotEmpty(),
                'cycle' => $i->approvalCycle($p, $approval?->amendment_id),
                'resume_stage' => $approval?->resume_key ? (collect($p->workflow)->firstWhere('key', $approval->resume_key)['name'] ?? null) : null,
            ],
            'amendment' => $am ? [
                'id' => $am->id, 'seq' => $am->seq, 'reason' => $am->reason, 'proposal' => $am->proposal, 'change_types' => $am->change_types,
                'contract_id' => $am->vendor_contract_id, 'contract_kind' => $amContract?->kind, 'contract_number' => $amContract?->number,
                'nodes' => $i->stepper($p, Track::Pengadaan, null, $am->id),
            ] : null,
            'contract' => $p->contract ? $this->contract($p->contract) : null,
            'work_contract' => $p->workContract ? $this->contract($p->workContract) : null,
            'terms' => $i->termFlows($p)->map(function ($f) use ($i, $p, $plans, $bas) {
                $t = $f['t'];
                $late = $f['cur']?->stage_key === 'pelaksanaan' && ! $f['scheduled'] && ($plans[$t->id]['end'] ?? null)
                    ? max(0, Cal::daysBetween($plans[$t->id]['end'], $i->today())) : 0;
                $gr = $t->goodsReceipt;
                $inv = $t->invoice;

                return [
                    'id' => $t->id, 'seq' => $t->seq, 'name' => $t->name, 'kind' => $t->kind, 'percent' => $t->percent !== null ? (float) $t->percent : null,
                    'amount' => (float) $t->amount, 'planned_end' => Cal::iso($t->planned_end), 'paid_at' => Cal::iso($t->paid_at), 'bucket_code' => $t->bucket_code, 'bucket_number' => $t->bucket_number, 'bucket_link' => $t->bucket_link, 'plan' => $f['plan'],
                    'scheduled' => $f['scheduled'], 'late_days' => $late,
                    'current' => $f['cur'] ? $this->visit($f['cur'], $i) : null,
                    'nodes' => $i->stepper($p, Track::Pasca, $t->id),
                    'history' => $i->history($f['visits']),
                    'ba_ids' => $t->beritaAcara->pluck('id')->values(),
                    // Status BA diambil dari BA pengadaan yang sudah memuat putaran TTD, agar tidak dimuat dua kali.
                    'ba_ready' => $t->beritaAcara->isNotEmpty() && $t->beritaAcara->every(fn ($b) => $bas->get($b->id)?->status() === 'TTD Selesai'),
                    'gr' => $gr ? [
                        'id' => $gr->id, 'status' => $gr->status, 'number' => $gr->number, 'gr_type' => $gr->gr_type, 'amount' => $gr->amount !== null ? (float) $gr->amount : null, 'submitted_at' => Cal::iso($gr->submitted_at),
                        'gr_date' => Cal::iso($gr->gr_date), 'source_app' => $gr->source_app, 'pic_id' => $gr->pic_id, 'pic' => $gr->pic?->name,
                        'link' => $gr->link, 'note' => $gr->note, 'ba_ids' => $gr->beritaAcara->pluck('id')->values(),
                    ] : null,
                    'invoice' => $inv ? [
                        'id' => $inv->id, 'number' => $inv->number, 'date' => Cal::iso($inv->date), 'amount' => (float) $inv->amount, 'status' => $inv->status,
                        'pic_id' => $inv->pic_id, 'pic' => $inv->pic?->name, 'note' => $inv->note, 'checklist' => $inv->checklist ?? [],
                        'payment' => $inv->payment ? ['date' => Cal::iso($inv->payment->date), 'amount' => (float) $inv->payment->amount] : null,
                    ] : null,
                ];
            })->values(),
            'pasca_stages' => WorkflowTemplate::stagesFor('Pasca'),
            'documents' => $p->documents->sortBy('id')->map(fn (Document $d) => [
                'id' => $d->id, 'doc_type' => $d->doc_type, 'number' => $d->number, 'title' => $d->title, 'date' => Cal::iso($d->date),
                'required' => $d->required, 'available' => $d->available, 'link' => $d->link, 'note' => $d->note, 'customer' => $d->customer?->name,
            ])->values(),
            'bas' => $p->beritaAcara->map(function (BeritaAcara $b) use ($i) {
                $info = $i->baInfo($b);

                return [
                    'id' => $b->id, 'type' => $b->type, 'number' => $b->number, 'title' => $b->title, 'date' => Cal::iso($b->date), 'link' => $b->link,
                    'status' => $info['status'], 'round' => $info['round'], 'signed' => $info['signed'], 'total' => $info['total'], 'days' => $info['days'],
                    'term_ids' => $b->terms->pluck('id')->values(), 'term_seqs' => $b->terms->pluck('seq')->sort()->values(),
                ];
            })->values(),
            'all_documents' => $this->allDocuments($p),
            'history' => AuditLog::query()->where('procurement_id', $p->id)->with('user:id,name')->latest('created_at')->limit(300)->get()
                ->map(fn (AuditLog $a) => [
                    'at' => $a->created_at?->format('Y-m-d H:i'), 'user' => $a->user?->name, 'entity' => $a->entity, 'action' => $a->action,
                    'field' => $a->field, 'old' => $a->old_value, 'new' => $a->new_value, 'reason' => $a->reason,
                ]),
            'options' => $this->options($p),
        ];
    }

    /**
     * Data kontrak untuk halaman detail; bentuknya sama untuk SP2K/PO dan Kontrak Pengadaan.
     *
     * @return array<string, mixed>
     */
    private function contract(VendorContract $c): array
    {
        return [
            'id' => $c->id, 'is_primary' => (bool) $c->is_primary, 'kind' => $c->kind, 'number' => $c->number, 'title' => $c->title,
            'vendor' => $c->vendor?->name, 'vendor_id' => $c->vendor_id, 'date' => Cal::iso($c->date),
            'kickoff_date' => Cal::iso($c->kickoff_date), 'end_date' => Cal::iso($c->end_date),
            'value_excl' => (float) $c->value_excl, 'value_incl' => (float) $c->value_incl, 'scope' => $c->scope, 'link' => $c->link,
            'amendments' => $c->amendments->map(fn (ContractAmendment $a) => [
                'id' => $a->id, 'seq' => $a->seq, 'status' => $a->status, 'reason' => $a->reason, 'requested_at' => Cal::iso($a->requested_at),
                'number' => $a->number, 'date' => Cal::iso($a->date), 'change_types' => $a->change_types, 'description' => $a->description,
                'before' => $a->before, 'after' => $a->after, 'proposal' => $a->proposal, 'link' => $a->link,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function visit($v, ?ProcurementInsight $i = null): array
    {
        $i ??= $this->insight;

        return [
            'id' => $v->id, 'stage_key' => $v->stage_key, 'stage' => $v->stage_name, 'cycle' => $v->cycle,
            'entered_at' => Cal::iso($v->entered_at), 'pic_id' => $v->pic_id, 'pic' => $v->pic?->name, 'activity' => $v->activity,
            'amendment_id' => $v->amendment_id, 'term_id' => $v->term_id, 'resume_key' => $v->resume_key, 'days' => $i->scheduled($v) ? null : $i->visitDays($v),
            'scheduled' => $i->scheduled($v), 'level' => $v->track === 'pengadaan' ? $i->durLevel($i->visitDays($v)) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function options(?Procurement $p = null): array
    {
        return [
            'pics' => Pic::query()->where('active', true)->with('company:id,name')->orderBy('name')->get()
                ->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'role' => $x->role, 'title' => $x->title, 'company' => $x->company?->name])->values(),
            'activities' => MasterItem::labels('activity'),
            'contract_types' => MasterItem::labels('contract_type'),
            'ba_types' => MasterItem::labels('ba_type'),
            'change_types' => ContractAmendment::CHANGE_TYPES,
            'invoice_statuses' => ['Submit Invoice', 'Verifikasi', 'Perlu Revisi', 'Antri Bayar', 'Dibayar'],
            'gr_statuses' => ['Belum Diajukan', 'Diajukan', 'Perlu Revisi', 'GR Terbit'],
            'gr_types' => GoodsReceipt::TYPES,
            'checklist' => Invoice::CHECKLIST,
            'vendors' => Company::query()->whereIn('type', ['Vendor', 'Mitra'])->where('active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Seluruh dokumen yang terkait satu pengadaan: dokumen pengadaan, perjanjian pelanggan, SP2K/PO, amandemen,
     * Berita Acara, GR, invoice, dan bucket invoicing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function allDocuments(Procurement $p): array
    {
        $rows = [];
        $add = function (string $source, string $type, ?string $number, $date, ?string $link, ?string $info = null, ?string $href = null, ?int $docId = null) use (&$rows) {
            $rows[] = ['source' => $source, 'type' => $type, 'number' => $number, 'date' => Cal::iso($date), 'link' => $link, 'info' => $info, 'href' => $href, 'doc_id' => $docId];
        };
        foreach ($p->documents->sortBy('id') as $d) {
            $add('Dokumen pengadaan', $d->doc_type, $d->number, $d->date, $d->link, trim(($d->required ? 'Wajib' : 'Opsional').' · '.($d->available ? 'Tersedia' : 'Belum tersedia').($d->note ? ' · '.$d->note : '')), null, $d->id);
        }
        foreach ($p->customerContracts as $c) {
            $add('Perjanjian pelanggan', $c->kind, $c->number, $c->date, $c->link, $c->customer?->name, "/bakb/{$c->id}");
        }
        foreach (collect([$p->contract, $p->workContract])->filter() as $vc) {
            $add('Kontrak', $vc->kind, $vc->number, $vc->date, $vc->link, $vc->vendor?->name, "/kontrak/{$vc->id}");
            foreach ($vc->amendments->where('status', 'Rilis') as $a) {
                $add('Kontrak', "Amandemen {$a->seq} {$vc->kind}", $a->number, $a->date, $a->link, implode(', ', $a->change_types ?? []));
            }
        }
        if ($p->pa_number) {
            $add('Perjanjian pelanggan', 'PA (Project Activation)', $p->pa_number, null, null, 'Nomor PA');
        }
        foreach ($p->beritaAcara as $b) {
            $seqs = $b->terms->pluck('seq')->sort()->map(fn ($n) => "Tahap {$n}")->implode(', ');
            $add('Berita Acara', $b->type, $b->number, $b->date, $b->link, trim($seqs.' · '.$b->status(), ' ·'), "/ba/{$b->id}");
        }
        foreach ($p->terms as $t) {
            if ($gr = $t->goodsReceipt) {
                $add('Goods Receipt', 'GR'.($gr->gr_type ? " {$gr->gr_type}" : ''), $gr->number, $gr->gr_date ?? $gr->submitted_at, $gr->link, "Tahap {$t->seq} · {$gr->status}");
            }
            if ($inv = $t->invoice) {
                $add('Invoice', 'Invoice', $inv->number, $inv->date, null, "Tahap {$t->seq} · {$inv->status}");
            }
            if ($t->bucket_code || $t->bucket_number || $t->bucket_link) {
                $add('Invoice', 'Bucket invoicing', $t->bucket_number ?: $t->bucket_code, null, $t->bucket_link, trim("Tahap {$t->seq}".($t->bucket_code ? " · kode {$t->bucket_code}" : '')));
            }
        }

        return $rows;
    }
}
