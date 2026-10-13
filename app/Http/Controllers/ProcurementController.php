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
    public const REQUIRED_DOCS = ['SPP' => ['TOR', 'BCR (Benefit Cost Ratio)'], 'Kemitraan' => ['BAK', 'BCR (Benefit Cost Ratio)']];

    public function __construct(private ProcurementInsight $insight, private WorkflowService $workflow, private AuditLogger $audit) {}

    public function index(): Response
    {
        $procs = Procurement::query()->with(ProcurementInsight::WITH)->latest('registered_at')->get();

        return Inertia::render('Procurements/Index', [
            'rows' => $procs->map(fn ($p) => $this->insight->row($p) + [
                'is_draft' => $p->is_draft, 'cancelled' => (bool) $p->cancelled_at,
                'amendment_running' => $p->contract?->amendments->contains('status', 'Dalam Proses') ?? false,
            ])->values(),
            'filters' => [
                'procMethods' => MasterItem::labels('proc_method'),
                'customerContracts' => CustomerContract::query()->orderBy('number')->get(['id', 'number']),
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
                ...$p->only(['code', 'spp_number', 'title', 'summary', 'method', 'proc_type', 'proc_method', 'priority', 'sub_bidang', 'pengaju_name', 'pks_id', 'bak_number', 'is_draft', 'workflow']),
                'spp_date' => Cal::iso($p->spp_date), 'registered_at' => Cal::iso($p->registered_at), 'bak_date' => Cal::iso($p->bak_date),
                'candidate_vendor_ids' => $p->candidateVendors->pluck('id'), 'customer_contract_ids' => $p->customerContracts->pluck('id'),
                'budget_code_ids' => $p->budgetCodes->pluck('id'), 'has_contract' => (bool) $p->contract,
                'rab_items' => $p->rabItems->map(fn ($r) => ['description' => $r->description, 'budget_type' => $r->budget_type, 'budget_code_id' => $r->budget_code_id, 'qty' => (float) $r->qty, 'unit' => $r->unit, 'price' => (float) $r->price]),
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
            $p = new Procurement(['code' => $numbering->nextProcurementCode((int) substr($data['registered_at'], 0, 4)), 'created_by' => $request->user()->id]);
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
            foreach (['title' => 'Judul', 'spp_number' => 'Nomor SPP', 'priority' => 'Prioritas', 'proc_method' => 'Metode pengadaan', 'pengaju_name' => 'User Pengaju'] as $k => $label) {
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
        $d = $request->validate(['reason' => ['required', 'string', 'min:5']]);
        $procurement->update(['cancelled_at' => now(), 'cancel_reason' => $d['reason']]);
        $this->audit->log($procurement->id, 'Pengadaan', $procurement->code, 'Batalkan pengadaan', 'Status', null, 'Dibatalkan', $d['reason']);

        return $this->done('Pengadaan dibatalkan.');
    }

    public function reactivate(Procurement $procurement): RedirectResponse
    {
        $procurement->update(['cancelled_at' => null, 'cancel_reason' => null]);
        $this->audit->log($procurement->id, 'Pengadaan', $procurement->code, 'Aktifkan kembali pengadaan', 'Status', 'Dibatalkan', 'Aktif');

        return $this->done('Pengadaan diaktifkan kembali.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Procurement $existing = null): array
    {
        $draft = $request->boolean('draft');
        $isSpp = $request->input('method') === 'SPP';
        $hasContract = (bool) $existing?->contract()->exists();
        $data = $request->validate([
            'draft' => ['boolean'],
            'method' => ['required', Rule::in(['SPP', 'Kemitraan'])],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'proc_type' => [$draft ? 'nullable' : 'required', 'string'],
            'proc_method' => [$isSpp && ! $draft ? 'required' : 'nullable', 'string'],
            'priority' => ['required', Rule::in(['Normal', 'Urgent'])],
            'sub_bidang' => ['nullable', 'string', 'max:20'],
            'registered_at' => ['required', 'date'],
            'spp_number' => [$isSpp ? 'required' : 'nullable', 'string', 'max:100'],
            'spp_date' => [$isSpp && ! $draft ? 'required' : 'nullable', 'date'],
            'pengaju_name' => [$draft ? 'nullable' : 'required', 'string', 'max:150'],
            'candidate_vendor_ids' => [$draft ? 'nullable' : 'required', 'array', $draft ? 'min:0' : 'min:1'],
            'candidate_vendor_ids.*' => ['integer', 'exists:companies,id'],
            'customer_contract_ids' => ['array'],
            'customer_contract_ids.*' => ['integer', 'exists:customer_contracts,id'],
            'pks_id' => [! $isSpp && ! $draft ? 'required' : 'nullable', 'nullable', 'exists:pks,id'],
            'bak_number' => [! $isSpp && ! $draft ? 'required' : 'nullable', 'nullable', 'string'],
            'bak_date' => ['nullable', 'date'],
            'ref_docs' => ['array'],
            'ref_docs.*.doc_type' => ['required', Rule::in(Document::REFERENCE_TYPES)],
            'ref_docs.*.number' => ['nullable', 'string'],
            'ref_docs.*.date' => ['nullable', 'date'],
            'ref_docs.*.link' => ['nullable', new DocumentLink],
            'ref_docs.*.customer_id' => ['nullable', 'exists:companies,id'],
            'budget_code_ids' => [$draft ? 'nullable' : 'required', 'array', $draft ? 'min:0' : 'min:1'],
            'budget_code_ids.*' => ['integer', 'exists:budget_codes,id'],
            'rab_items' => [$draft ? 'nullable' : 'required', 'array', $draft ? 'min:0' : 'min:1'],
            'rab_items.*.description' => ['required', 'string'],
            'rab_items.*.budget_type' => ['required', Rule::in(['OPEX', 'CAPEX'])],
            'rab_items.*.budget_code_id' => ['required', 'exists:budget_codes,id'],
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
        ], [], ['candidate_vendor_ids' => 'calon vendor', 'budget_code_ids' => 'kode anggaran', 'rab_items' => 'RAB']);

        if (! $draft) {
            $hasMom = collect($data['ref_docs'] ?? [])->contains(fn ($d) => $d['doc_type'] === 'MoM' && trim((string) ($d['number'] ?? '')) !== '');
            if (empty($data['customer_contract_ids']) && ! $hasMom) {
                throw ValidationException::withMessages(['customer_contract_ids' => 'Setiap pengadaan wajib memiliki dasar dengan pelanggan: pilih BAKB/Kontrak pelanggan atau isi MoM.']);
            }
            $codes = $data['budget_code_ids'] ?? [];
            foreach ($data['rab_items'] ?? [] as $k => $r) {
                if (! in_array((int) $r['budget_code_id'], array_map('intval', $codes), true)) {
                    throw ValidationException::withMessages(["rab_items.{$k}.budget_code_id" => 'Kode anggaran baris RAB harus termasuk kode anggaran yang dipilih.']);
                }
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
            'method' => $d['method'], 'proc_type' => $d['proc_type'] ?? null, 'proc_method' => $d['method'] === 'SPP' ? ($d['proc_method'] ?? null) : null,
            'priority' => $d['priority'], 'sub_bidang' => $d['sub_bidang'] ?? null, 'pengaju_name' => trim((string) ($d['pengaju_name'] ?? '')),
            'pic_pengaju_id' => Pic::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) ($d['pengaju_name'] ?? '')))])->value('id'),
            'registered_at' => $d['registered_at'], 'rab_value' => $rab,
            'pks_id' => $d['method'] === 'Kemitraan' ? ($d['pks_id'] ?? null) : null,
            'bak_number' => $d['method'] === 'Kemitraan' ? ($d['bak_number'] ?? null) : null,
            'bak_date' => $d['method'] === 'Kemitraan' ? ($d['bak_date'] ?? null) : null,
            'is_draft' => (bool) ($d['draft'] ?? false),
        ]);
        if (! $p->exists || ! $p->contract()->exists()) {
            $p->workflow = collect($d['workflow'] ?? WorkflowTemplate::stagesFor($d['method']))->map(fn ($s) => [
                'key' => $s['key'], 'name' => $s['name'], 'enabled' => (bool) ($s['enabled'] ?? true), 'optional' => (bool) ($s['optional'] ?? false),
                'pic_id' => $s['pic_id'] ?? null, 'default_activities' => $s['default_activities'] ?? [],
            ])->values()->all();
        }
        $p->save();

        $p->candidateVendors()->sync($d['candidate_vendor_ids'] ?? []);
        $p->customerContracts()->sync($d['customer_contract_ids'] ?? []);
        $p->budgetCodes()->sync($d['budget_code_ids'] ?? []);
        $p->rabItems()->delete();
        foreach (array_values($d['rab_items'] ?? []) as $k => $r) {
            $p->rabItems()->create([...collect($r)->only(['description', 'budget_type', 'budget_code_id', 'qty', 'unit', 'price'])->all(), 'sort' => $k]);
        }
        $keep = [];
        foreach ($d['documents'] ?? [] as $doc) {
            $row = [
                'doc_type' => $doc['doc_type'], 'number' => $doc['number'] ?? null, 'title' => $doc['title'] ?? $doc['doc_type'], 'date' => $doc['date'] ?? null,
                'required' => (bool) ($doc['required'] ?? false), 'link' => $doc['link'] ?? null, 'note' => $doc['note'] ?? null,
                'available' => (bool) ($doc['available'] ?? false) || ! empty($doc['link']),
            ];
            $keep[] = (! empty($doc['id']) && ($m = $p->documents()->find($doc['id']))) ? tap($m)->update($row)->id : $p->documents()->create($row)->id;
        }
        foreach ($d['ref_docs'] ?? [] as $doc) {
            if (empty($doc['number']) && empty($doc['link'])) {
                continue;
            }
            $keep[] = $p->documents()->create([
                'doc_type' => $doc['doc_type'], 'number' => $doc['number'] ?? null, 'title' => $doc['doc_type'], 'date' => $doc['date'] ?? null,
                'link' => $doc['link'] ?? null, 'customer_id' => $doc['customer_id'] ?? null, 'available' => true, 'required' => false,
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
                'procMethods' => MasterItem::labels('proc_method'),
                'docTypes' => MasterItem::labels('doc_type'),
                'rabUnits' => MasterItem::labels('rab_unit'),
                'requiredDocs' => self::REQUIRED_DOCS,
                'pics' => Pic::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'role', 'title']),
                'vendors' => Company::query()->whereIn('type', ['Vendor', 'Mitra'])->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
                'customers' => Company::query()->where('type', 'Pelanggan')->where('active', true)->orderBy('name')->get(['id', 'name']),
                'customerContracts' => CustomerContract::query()->with('customer:id,name')->orderBy('number')->get()
                    ->map(fn ($c) => ['id' => $c->id, 'number' => $c->number, 'title' => $c->title, 'customer' => $c->customer?->name]),
                'pks' => Pks::query()->with(['partner:id,name', 'amendments'])->orderBy('number')->get()
                    ->map(fn ($k) => ['id' => $k->id, 'number' => $k->number, 'title' => $k->title, 'partner_id' => $k->partner_id, 'partner' => $k->partner?->name, ...$k->effective()]),
                'budgetCodes' => BudgetCode::query()->orderBy('code')->get(),
                'templates' => ['SPP' => WorkflowTemplate::stagesFor('SPP'), 'Kemitraan' => WorkflowTemplate::stagesFor('Kemitraan')],
            ],
        ];
    }
}
