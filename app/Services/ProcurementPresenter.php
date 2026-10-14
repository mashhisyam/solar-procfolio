<?php

namespace App\Services;

use App\Enums\Track;
use App\Models\AuditLog;
use App\Models\BeritaAcara;
use App\Models\Company;
use App\Models\ContractAmendment;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\MasterItem;
use App\Models\Pic;
use App\Models\Procurement;
use App\Models\WorkflowTemplate;
use App\Support\WorkdayCalendar as Cal;

/** Menyusun data halaman detail pengadaan (dua progres, kontrak, tahapan, BA, GR, invoice, riwayat). */
class ProcurementPresenter
{
    public function __construct(private ProcurementInsight $insight, private WorkflowService $workflow) {}

    /** @return array<string, mixed> */
    public function detail(Procurement $p): array
    {
        $p->loadMissing([...ProcurementInsight::WITH, 'visits.activities', 'rabItems.budgetCode', 'budgetCodes', 'candidateVendors',
            'customerContracts.customer', 'pks.partner', 'pks.amendments', 'documents.customer', 'terms.goodsReceipt.beritaAcara', 'terms.invoice.pic', 'terms.goodsReceipt.pic', 'beritaAcara.terms:id,seq']);
        $i = $this->insight;
        $row = $i->row($p);
        $approval = $i->currentApproval($p);
        $am = $p->contract?->amendments->firstWhere('status', 'Dalam Proses');
        $plans = $this->workflow->termPlans($p);
        $pk = $p->pks;

        return [
            'procurement' => [
                'code' => $p->code, 'spp_number' => $p->spp_number, 'spp_date' => Cal::iso($p->spp_date), 'title' => $p->title, 'summary' => $p->summary,
                'method' => $p->method, 'proc_type' => $p->proc_type, 'proc_method' => $p->proc_method, 'priority' => $p->priority,
                'sub_bidang' => $p->sub_bidang, 'pengaju_name' => $p->pengaju_name, 'registered_at' => Cal::iso($p->registered_at),
                'rab_value' => (float) $p->rab_value, 'is_draft' => $p->is_draft, 'cancelled' => (bool) $p->cancelled_at, 'cancel_reason' => $p->cancel_reason,
                'status' => $i->status($p), 'post_state' => $i->postState($p), 'process_days' => $i->processDays($p),
                'last_update' => $row['last_update'], 'last_update_days' => $row['last_update_days'],
                'bak_number' => $p->bak_number, 'bak_date' => Cal::iso($p->bak_date),
                'pks' => $pk ? ['id' => $pk->id, 'number' => $pk->number, 'title' => $pk->title, 'partner' => $pk->partner?->name, ...$pk->effective()] : null,
                'candidate_vendors' => $p->candidateVendors->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                'vendor_id' => $p->vendor_id,
                'budget_codes' => $p->budgetCodes->map(fn ($b) => $b->only(['id', 'coa', 'cost_center', 'code', 'program', 'klasifikasi']))->values(),
                'rab_items' => $p->rabItems->map(fn ($r) => [
                    'id' => $r->id, 'description' => $r->description, 'budget_type' => $r->budget_type, 'budget_code' => $r->budgetCode?->code,
                    'program' => $r->budgetCode?->program, 'qty' => (float) $r->qty, 'unit' => $r->unit, 'price' => (float) $r->price, 'total' => $r->total(),
                ])->values(),
                'customer_contracts' => $p->customerContracts->map(fn ($c) => ['id' => $c->id, 'kind' => $c->kind, 'number' => $c->number, 'title' => $c->title, 'customer' => $c->customer?->name, 'link' => $c->link])->values(),
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
            ],
            'amendment' => $am ? [
                'id' => $am->id, 'seq' => $am->seq, 'reason' => $am->reason, 'proposal' => $am->proposal, 'change_types' => $am->change_types,
                'nodes' => $i->stepper($p, Track::Pengadaan, null, $am->id),
            ] : null,
            'contract' => $p->contract ? [
                'id' => $p->contract->id, 'kind' => $p->contract->kind, 'number' => $p->contract->number, 'title' => $p->contract->title,
                'vendor' => $p->contract->vendor?->name, 'vendor_id' => $p->contract->vendor_id, 'date' => Cal::iso($p->contract->date),
                'kickoff_date' => Cal::iso($p->contract->kickoff_date), 'end_date' => Cal::iso($p->contract->end_date),
                'value_excl' => (float) $p->contract->value_excl, 'value_incl' => (float) $p->contract->value_incl, 'scope' => $p->contract->scope, 'link' => $p->contract->link,
                'amendments' => $p->contract->amendments->map(fn (ContractAmendment $a) => [
                    'id' => $a->id, 'seq' => $a->seq, 'status' => $a->status, 'reason' => $a->reason, 'requested_at' => Cal::iso($a->requested_at),
                    'number' => $a->number, 'date' => Cal::iso($a->date), 'change_types' => $a->change_types, 'description' => $a->description,
                    'before' => $a->before, 'after' => $a->after, 'proposal' => $a->proposal, 'link' => $a->link,
                ])->values(),
            ] : null,
            'terms' => $i->termFlows($p)->map(function ($f) use ($i, $p, $plans) {
                $t = $f['t'];
                $late = $f['cur']?->stage_key === 'pelaksanaan' && ! $f['scheduled'] && ($plans[$t->id]['end'] ?? null)
                    ? max(0, Cal::daysBetween($plans[$t->id]['end'], $i->today())) : 0;
                $gr = $t->goodsReceipt;
                $inv = $t->invoice;

                return [
                    'id' => $t->id, 'seq' => $t->seq, 'name' => $t->name, 'kind' => $t->kind, 'percent' => $t->percent !== null ? (float) $t->percent : null,
                    'amount' => (float) $t->amount, 'planned_end' => Cal::iso($t->planned_end), 'paid_at' => Cal::iso($t->paid_at), 'plan' => $f['plan'],
                    'scheduled' => $f['scheduled'], 'late_days' => $late,
                    'current' => $f['cur'] ? $this->visit($f['cur'], $i) : null,
                    'nodes' => $i->stepper($p, Track::Pasca, $t->id),
                    'history' => $i->history($f['visits']),
                    'ba_ids' => $t->beritaAcara->pluck('id')->values(),
                    'ba_ready' => $t->beritaAcara->isNotEmpty() && $t->beritaAcara->every(fn ($b) => $b->status() === 'TTD Selesai'),
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
            'history' => AuditLog::query()->where('procurement_id', $p->id)->with('user:id,name')->latest('created_at')->limit(300)->get()
                ->map(fn (AuditLog $a) => [
                    'at' => $a->created_at?->format('Y-m-d H:i'), 'user' => $a->user?->name, 'entity' => $a->entity, 'action' => $a->action,
                    'field' => $a->field, 'old' => $a->old_value, 'new' => $a->new_value, 'reason' => $a->reason,
                ]),
            'options' => $this->options($p),
        ];
    }

    /** @return array<string, mixed> */
    private function visit($v, ?ProcurementInsight $i = null): array
    {
        $i ??= $this->insight;

        return [
            'id' => $v->id, 'stage_key' => $v->stage_key, 'stage' => $v->stage_name, 'cycle' => $v->cycle,
            'entered_at' => Cal::iso($v->entered_at), 'pic_id' => $v->pic_id, 'pic' => $v->pic?->name, 'activity' => $v->activity,
            'amendment_id' => $v->amendment_id, 'term_id' => $v->term_id, 'days' => $i->scheduled($v) ? null : $i->visitDays($v),
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
            'gr_types' => \App\Models\GoodsReceipt::TYPES,
            'checklist' => Invoice::CHECKLIST,
            'vendors' => Company::query()->whereIn('type', ['Vendor', 'Mitra'])->where('active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
