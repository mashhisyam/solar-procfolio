<?php

namespace App\Http\Controllers;

use App\Models\BudgetCode;
use App\Models\Company;
use App\Models\CustomerContract;
use App\Models\Document;
use App\Models\MasterItem;
use App\Models\Pic;
use App\Models\Pks;
use App\Models\Procurement;
use App\Models\WorkflowTemplate;
use App\Rules\DocumentLink;
use App\Services\AuditLogger;
use App\Services\Numbering;
use App\Services\ProcurementInsight;
use App\Services\ProcurementPresenter;
use App\Services\WorkflowService;
use App\Support\Period;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    public const REQUIRED_DOCS = ['SPP Pusat' => ['TOR (Term of Reference)', 'BCR (Benefit Cost Ratio)'], 'SPP Kemitraan' => ['BAK', 'BCR (Benefit Cost Ratio)']];

    public function __construct(private ProcurementInsight $insight, private WorkflowService $workflow, private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $procs = Period::apply(Procurement::query(), Period::current($request))->with(ProcurementInsight::WITH)->latest('registered_at')->get();

        return Inertia::render('Procurements/Index', [
            'rows' => $procs->map(fn ($p) => $this->insight->row($p) + [
                'is_draft' => $p->is_draft, 'cancelled' => (bool) $p->cancelled_at, 'cancel_type' => $p->cancel_type,
                'amendment_running' => $p->activeAmendment() !== null,
            ])->values(),
            'filters' => [
                'procMethods' => MasterItem::labels('proc_method'),
                'customerContracts' => CustomerContract::query()->orderBy('number')->get(['id', 'kind', 'number']),
                'stages' => collect(WorkflowTemplate::query()->orderBy('sort')->get(['scheme', 'key', 'name']))->groupBy('scheme'),
            ],
        ]);
    }

    public function create(Numbering $numbering): Response
    {
        return Inertia::render('Procurements/Form', ['procurement' => null, 'nextCode' => $numbering->nextProcurementCode()] + $this->formOptions());
    }

    public function edit(Procurement $procurement): Response
    {
        $p = $procurement->load(['documents', 'rabItems', 'budgetCodes', 'candidateVendors', 'customerContracts', 'contract']);

        return Inertia::render('Procurements/Form', [
            'procurement' => [
                ...$p->only(['code', 'spp_number', 'title', 'summary', 'method', 'proc_type', 'category', 'proc_method', 'priority', 'sub_bidang', 'pengaju_name', 'pic_pengaju_id', 'rab_value', 'pks_id', 'bak_number', 'pa_number', 'is_draft', 'workflow']),
                'spp_date' => Cal::iso($p->spp_date), 'registered_at' => Cal::iso($p->registered_at), 'bak_date' => Cal::iso($p->bak_date),
                'candidate_vendor_ids' => $p->candidateVendors->pluck('id'), 'customer_contract_ids' => $p->customerContracts->pluck('id'),
                'budget_code_id' => $p->budgetCodes->first()?->id, 'has_contract' => (bool) $p->contract,
                'rab_items' => $p->rabItems->map(fn ($r) => ['description' => $r->description, 'budget_type' => $r->budget_type, 'qty' => (float) $r->qty, 'unit' => $r->unit, 'price' => (float) $r->price]),
                'documents' => $p->documents->whereNotIn('doc_type', Document::REFERENCE_TYPES)->values()->map(fn ($d) => [...$d->only(['id', 'doc_type', 'number', 'title', 'required', 'available', 'link', 'note']), 'date' => Cal::iso($d->date)]),
                'ref_docs' => $p->documents->whereIn('doc_type', Document::REFERENCE_TYPES)->values()->map(fn ($d) => [...$d->only(['id', 'doc_type', 'number', 'link', 'customer_id']), 'date' => Cal::iso($d->date)]),
            ],
            'nextCode' => $p->code,
        ] + $this->formOptions());
    }

    public function store(Request $request, Numbering $numbering): RedirectResponse
    {
        $data = $this->validated($request);
        $p = DB::transaction(function () use ($data, $numbering, $request) {
            $p = new Procurement(['code' => $numbering->nextProcurementCode((int) now()->format('Y')), 'created_by' => $request->user()->id]);
            $this->persist($p, $data);
            if (! $p->is_draft) {
                $this->workflow->register($p);
            }

            return $p;
        });

        return redirect("/pengadaan/{$p->code}")->with('success', $p->is_draft ? "Draft {$p->code} disimpan." : "Pengadaan {$p->code} terdaftar. Monitoring Pengaju dimulai.");
    }

    public function update(Request $request, Procurement $procurement): RedirectResponse
    {
        $data = $this->validated($request, $procurement);
        DB::transaction(function () use ($procurement, $data) {
            $wasDraft = $procurement->is_draft;
            foreach (['title' => 'Judul', 'spp_number' => 'Nomor SPP', 'priority' => 'Prioritas', 'proc_method' => 'Metode pengadaan', 'category' => 'Kategori pengadaan', 'pic_pengaju_id' => 'User Pengaju', 'pa_number' => 'Nomor PA'] as $k => $label) {
                if ((string) $procurement->{$k} !== (string) ($data[$k] ?? '')) {
                    $this->audit->log($procurement->id, 'Pengadaan', $procurement->code, 'Ubah identitas', $label, $procurement->{$k}, $data[$k] ?? null);
                }
            }
            $this->persist($procurement, $data);
            if ($wasDraft && ! $procurement->is_draft) {
                $this->workflow->register($procurement);
            }
        });

        return redirect("/pengadaan/{$procurement->code}")->with('success', 'Perubahan pengadaan disimpan.');
    }

    public function show(Procurement $procurement, ProcurementPresenter $presenter): Response
    {
        return Inertia::render('Procurements/Show', $presenter->detail($procurement));
    }

    public function cancel(Request $request, Procurement $procurement): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:5']], [], ['reason' => 'alasan']);
        $type = $this->workflow->stop($procurement, $d['reason']);

        return $this->done($type === 'gagal' ? 'Pengadaan dinyatakan gagal. Data tetap tersimpan.' : 'Pengadaan dibatalkan. Data tetap tersimpan.');
    }

    public function reactivate(Procurement $procurement): RedirectResponse
    {
        $old = $procurement->cancel_type === 'gagal' ? 'Gagal' : 'Dibatalkan';
        $procurement->update(['cancelled_at' => null, 'cancel_type' => null, 'cancel_reason' => null]);
        $this->audit->log($procurement->id, 'Pengadaan', $procurement->code, 'Aktifkan kembali pengadaan', 'Status', $old, 'Aktif');

        return $this->done('Pengadaan diaktifkan kembali.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Procurement $existing = null): array
    {
        $draft = $request->boolean('draft');
        $isSpp = $request->input('method') === 'SPP Pusat';
        $hasContract = (bool) $existing?->contract()->exists();
        $data = $request->validate([
            'draft' => ['boolean'],
            'method' => ['required', Rule::in(['SPP Pusat', 'SPP Kemitraan'])],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'proc_type' => [$draft ? 'nullable' : 'required', 'string'],
            'proc_method' => [$isSpp && ! $draft ? 'required' : 'nullable', 'string'],
            'priority' => ['required', Rule::in(['Normal', 'Urgent'])],
            'sub_bidang' => ['nullable', 'string', 'max:20'],
            'registered_at' => ['nullable', 'date'],
            'category' => [$draft ? 'nullable' : 'required', 'nullable', 'string', 'max:60'],
            'spp_number' => [$isSpp ? 'required' : 'nullable', 'string', 'max:100'],
            'spp_date' => [$isSpp && ! $draft ? 'required' : 'nullable', 'date'],
            'pic_pengaju_id' => [$draft ? 'nullable' : 'required', 'nullable', 'exists:pics,id'],
            'candidate_vendor_ids' => [$draft ? 'nullable' : 'required', 'array', $draft ? 'min:0' : 'min:1'],
            'candidate_vendor_ids.*' => ['integer', 'exists:companies,id'],
            // Dasar pelanggan wajib: minimal satu perjanjian pelanggan, atau nomor PA (dicek di bawah).
            'customer_contract_ids' => ['nullable', 'array'],
            'customer_contract_ids.*' => ['integer', 'exists:customer_contracts,id'],
            'pa_number' => ['nullable', 'string', 'max:255'],
            'pks_id' => [! $isSpp && ! $draft ? 'required' : 'nullable', 'nullable', 'exists:pks,id'],
            'bak_number' => [! $isSpp && ! $draft ? 'required' : 'nullable', 'nullable', 'string'],
            'bak_date' => ['nullable', 'date'],
            'ref_docs' => ['array'],
            'ref_docs.*.doc_type' => ['required', Rule::in(Document::REFERENCE_TYPES)],
            'ref_docs.*.number' => ['nullable', 'string'],
            'ref_docs.*.date' => ['nullable', 'date'],
            'ref_docs.*.link' => ['nullable', new DocumentLink],
            'budget_code_id' => [$draft ? 'nullable' : 'required', 'nullable', 'exists:budget_codes,id'],
            'rab_items' => [$draft ? 'nullable' : 'required', 'array', $draft ? 'min:0' : 'min:1'],
            'rab_items.*.description' => ['required', 'string'],
            'rab_items.*.budget_type' => ['required', Rule::in(['OPEX', 'CAPEX'])],
            'rab_items.*.qty' => ['required', 'numeric', 'gt:0'],
            'rab_items.*.unit' => ['required', 'string', 'max:60'],
            'rab_items.*.price' => ['required', 'numeric', 'gt:0'],
            'documents' => ['array'],
            'documents.*.id' => ['nullable', 'integer'],
            'documents.*.doc_type' => ['required', 'string'],
            'documents.*.number' => ['nullable', 'string'],
            'documents.*.title' => ['nullable', 'string'],
            'documents.*.date' => ['nullable', 'date'],
            'documents.*.required' => ['boolean'],
            'documents.*.available' => ['boolean'],
            'documents.*.link' => ['nullable', new DocumentLink],
            'documents.*.note' => ['nullable', 'string'],
            'workflow' => [$hasContract ? 'nullable' : 'required', 'array', 'min:1'],
            'workflow.*.key' => ['required', 'string'],
            'workflow.*.name' => ['required', 'string'],
            'workflow.*.enabled' => ['boolean'],
            'workflow.*.pic_id' => ['nullable', 'exists:pics,id'],
            'workflow.*.group' => ['nullable', 'string'],
        ], [], ['candidate_vendor_ids' => 'calon vendor', 'budget_code_id' => 'PRK', 'pic_pengaju_id' => 'User Pengaju', 'rab_items' => 'RAB', 'customer_contract_ids' => 'perjanjian pelanggan']);

        if (! $draft) {
            if (empty($data['customer_contract_ids']) && blank($data['pa_number'] ?? null)) {
                throw ValidationException::withMessages(['customer_contract_ids' => 'Pilih minimal satu perjanjian pelanggan atau isi nomor PA (Project Activation).']);
            }
            if (! $hasContract && ! collect($data['workflow'] ?? [])->contains(fn ($s) => $s['enabled'] ?? true)) {
                throw ValidationException::withMessages(['workflow' => 'Minimal satu tahap aktif.']);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $d */
    private function persist(Procurement $p, array $d): void
    {
        $rab = collect($d['rab_items'] ?? [])->sum(fn ($r) => (float) $r['qty'] * (float) $r['price']);
        $p->fill([
            'spp_number' => $d['spp_number'] ?? null, 'spp_date' => $d['spp_date'] ?? null, 'title' => $d['title'], 'summary' => $d['summary'] ?? null,
            'method' => $d['method'], 'proc_type' => $d['proc_type'] ?? null, 'category' => $d['category'] ?? null, 'proc_method' => $d['method'] === 'SPP Pusat' ? ($d['proc_method'] ?? null) : null,
            'priority' => $d['priority'], 'sub_bidang' => $d['sub_bidang'] ?? null, 'pengaju_name' => (string) Pic::query()->whereKey($d['pic_pengaju_id'] ?? 0)->value('name'),
            'pic_pengaju_id' => $d['pic_pengaju_id'] ?? null,
            // Tanggal registrasi tidak lagi diisi pengguna: otomatis tanggal pengadaan dicatat (tetap saat diedit).
            'registered_at' => $p->registered_at ?? ($d['registered_at'] ?? now()->toDateString()), 'rab_value' => $rab,
            'pks_id' => $d['method'] === 'SPP Kemitraan' ? ($d['pks_id'] ?? null) : null,
            'bak_number' => $d['method'] === 'SPP Kemitraan' ? ($d['bak_number'] ?? null) : null,
            'bak_date' => $d['method'] === 'SPP Kemitraan' ? ($d['bak_date'] ?? null) : null,
            'pa_number' => filled($d['pa_number'] ?? null) ? trim($d['pa_number']) : null,
            'is_draft' => (bool) ($d['draft'] ?? false),
        ]);
        if (! $p->exists || ! $p->contract()->exists()) {
            $flow = collect($d['workflow'] ?? ($d['method'] === 'SPP Pusat' ? WorkflowService::applyRouting(WorkflowTemplate::stagesFor('SPP Pusat'), $d['proc_method'] ?? null) : WorkflowTemplate::stagesFor($d['method'])))->map(fn ($s) => [
                'key' => $s['key'], 'name' => $s['name'], 'group' => $s['group'] ?? null, 'enabled' => (bool) ($s['enabled'] ?? true), 'optional' => (bool) ($s['optional'] ?? false),
                'pic_id' => $s['pic_id'] ?? null, 'default_activities' => $s['default_activities'] ?? [],
            ])->values()->all();
            // Rute default menurut metode diterapkan di form; pilihan toggle pengguna dihormati. Manager Pengadaan selalu aktif.
            $p->workflow = WorkflowService::enforceRequired($flow);
        }
        $p->save();

        $p->candidateVendors()->sync($d['candidate_vendor_ids'] ?? []);
        $p->customerContracts()->sync($d['customer_contract_ids'] ?? []);
        // Satu pengadaan memakai satu PRK; seluruh baris RAB mengikuti PRK tersebut.
        $prk = $d['budget_code_id'] ?? null;
        $p->budgetCodes()->sync($prk ? [$prk] : []);
        $p->rabItems()->delete();
        foreach (array_values($d['rab_items'] ?? []) as $k => $r) {
            if (! $prk) {
                break;
            }
            $p->rabItems()->create([...collect($r)->only(['description', 'budget_type', 'qty', 'unit', 'price'])->all(), 'budget_code_id' => $prk, 'sort' => $k]);
        }
        $keep = [];
        foreach ($d['documents'] ?? [] as $doc) {
            $row = [
                'doc_type' => $doc['doc_type'], 'number' => $doc['number'] ?? null, 'title' => $doc['title'] ?? $doc['doc_type'], 'date' => $doc['date'] ?? null,
                'required' => (bool) ($doc['required'] ?? false), 'link' => $doc['link'] ?? null, 'note' => $doc['note'] ?? null,
                'available' => (bool) ($doc['available'] ?? false) || ! empty($doc['link']),
            ];
            $m = ! empty($doc['id']) ? $p->documents()->whereKey($doc['id'])->first() : null;
            if ($m) {
                $m->update($row);
                $keep[] = $m->id;
            } else {
                $keep[] = $p->documents()->create($row)->id;
            }
        }
        foreach ($d['ref_docs'] ?? [] as $doc) {
            if (empty($doc['number']) && empty($doc['link'])) {
                continue;
            }
            $keep[] = $p->documents()->create([
                'doc_type' => $doc['doc_type'], 'number' => $doc['number'] ?? null, 'title' => $doc['doc_type'], 'date' => $doc['date'] ?? null,
                'link' => $doc['link'] ?? null, 'available' => true, 'required' => false,
            ])->id;
        }
        $p->documents()->whereNotIn('id', $keep)->delete();
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'options' => [
                'procTypes' => MasterItem::labels('proc_type'),
                'categories' => MasterItem::labels('proc_category'),
                'procMethods' => MasterItem::labels('proc_method'),
                'docTypes' => MasterItem::labels('doc_type'),
                'rabUnits' => MasterItem::labels('rab_unit'),
                'requiredDocs' => self::REQUIRED_DOCS,
                'pics' => Pic::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'role', 'title']),
                'vendors' => Company::query()->whereIn('type', ['Vendor', 'Mitra'])->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
                'customers' => Company::query()->where('type', 'Pelanggan')->where('active', true)->orderBy('name')->get(['id', 'name']),
                'customerContracts' => CustomerContract::query()->with('customer:id,name')->orderBy('number')->get()
                    ->map(fn ($c) => ['id' => $c->id, 'kind' => $c->kind, 'number' => $c->number, 'title' => $c->title, 'customer' => $c->customer?->name]),
                // Dipakai teks bantuan langkah Perjanjian Pelanggan; sebelumnya tidak dikirim sehingga langkah 2 gagal tampil.
                'ccKinds' => CustomerContract::KINDS,
                'pks' => Pks::query()->with(['partner:id,name', 'amendments'])->orderBy('number')->get()
                    ->map(fn ($k) => ['id' => $k->id, 'number' => $k->number, 'title' => $k->title, 'partner_id' => $k->partner_id, 'partner' => $k->partner?->name, ...$k->effective()]),
                'budgetCodes' => (function () {
                    $used = BudgetCode::usedAmounts();

                    return BudgetCode::query()->orderBy('code')->get()->map(fn ($b) => [
                        ...$b->only(['id', 'coa', 'cost_center', 'sub_bidang', 'code', 'program', 'klasifikasi', 'active', 'year']),
                        'budget_amount' => (float) $b->budget_amount, 'used' => $used[$b->id] ?? 0.0,
                    ]);
                })(),
                'templates' => ['SPP Pusat' => WorkflowTemplate::stagesFor('SPP Pusat'), 'SPP Kemitraan' => WorkflowTemplate::stagesFor('SPP Kemitraan')],
            ],
        ];
    }
}
