<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Procurement;
use App\Models\VendorContract;
use App\Rules\DocumentLink;
use App\Services\AuditLogger;
use App\Services\WorkflowService;
use App\Support\Period;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Daftar SP2K/PO dan Kontrak Pengadaan, rilis kontrak beserta tahapan pembayaran, dan koreksi data kontrak. */
class ContractController extends Controller
{
    public function __construct(private WorkflowService $workflow, private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $rows = Period::apply(VendorContract::query(), Period::current($request), 'procurement')
            ->with(['vendor:id,name', 'procurement.terms', 'amendments'])->latest('date')->get()
            ->map(fn (VendorContract $c) => [
                'id' => $c->id, 'is_primary' => (bool) $c->is_primary, 'kind' => $c->kind, 'number' => $c->number, 'title' => $c->title, 'vendor' => $c->vendor?->name, 'vendor_id' => $c->vendor_id,
                'date' => Cal::iso($c->date), 'kickoff_date' => Cal::iso($c->kickoff_date), 'end_date' => Cal::iso($c->end_date),
                'value_incl' => (float) $c->value_incl, 'amendments' => $c->amendments->count(),
                'terms_total' => $c->procurement->terms->count(), 'terms_paid' => $c->procurement->terms->whereNotNull('paid_at')->count(),
                'procurement' => ['code' => $c->procurement->code, 'spp_number' => $c->procurement->spp_number, 'title' => $c->procurement->title],
            ]);

        return Inertia::render('Contracts/Index', [
            'rows' => $rows,
            'vendors' => Company::query()->whereIn('type', ['Vendor', 'Mitra'])->orderBy('name')->get(['id', 'name']),
            'pending' => Procurement::query()->whereNull('cancelled_at')->where('is_draft', false)->doesntHave('contract')->orderBy('code')->get(['code', 'spp_number', 'title']),
        ]);
    }

    public function show(VendorContract $contract): Response
    {
        $contract->load(['vendor:id,name', 'amendments', 'procurement.terms', 'procurement.contract:id,procurement_id,is_primary,kind,number', 'procurement.workContract:id,procurement_id,is_primary,kind,number']);
        $p = $contract->procurement;
        // Kontrak lain pada pengadaan yang sama (SP2K/PO <-> Kontrak Pengadaan) untuk navigasi.
        $sibling = $contract->is_primary ? $p->workContract : $p->contract;

        return Inertia::render('Contracts/Show', [
            'contract' => [
                ...$contract->only(['id', 'kind', 'number', 'title', 'scope', 'link']), 'is_primary' => (bool) $contract->is_primary,
                'vendor' => $contract->vendor?->name, 'date' => Cal::iso($contract->date), 'kickoff_date' => Cal::iso($contract->kickoff_date),
                'end_date' => Cal::iso($contract->end_date), 'value_excl' => (float) $contract->value_excl, 'value_incl' => (float) $contract->value_incl,
                'amendments' => $contract->amendments->map(fn ($a) => [...$a->only(['id', 'seq', 'status', 'reason', 'number', 'change_types', 'description', 'before', 'after', 'link']), 'date' => Cal::iso($a->date), 'requested_at' => Cal::iso($a->requested_at)]),
            ],
            'procurement' => [
                'code' => $p->code, 'spp_number' => $p->spp_number, 'title' => $p->title, 'method' => $p->method, 'pengaju_name' => $p->pengaju_name, 'rab_value' => (float) $p->rab_value,
            ],
            'sibling' => $sibling ? ['id' => $sibling->id, 'kind' => $sibling->kind, 'number' => $sibling->number] : null,
            'terms' => $p->terms->map(fn ($t) => ['seq' => $t->seq, 'name' => $t->name, 'percent' => $t->percent !== null ? (float) $t->percent : null, 'amount' => (float) $t->amount, 'planned_end' => Cal::iso($t->planned_end), 'paid_at' => Cal::iso($t->paid_at)]),
        ]);
    }

    /** Rilis SP2K/PO: nomor, vendor pemenang, nilai, tanggal mulai, dan tahapan pembayaran. */
    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        if ($procurement->contract()->exists()) {
            throw ValidationException::withMessages(['number' => 'Pengadaan ini sudah memiliki SP2K/PO.']);
        }
        $d = $request->validate([
            'kind' => ['required', Rule::in(['SP2K', 'PO', 'Kontrak'])],
            'number' => ['required', 'string', 'max:120', 'unique:vendor_contracts,number'],
            'title' => ['required', 'string', 'max:255'],
            'vendor_id' => ['required', 'exists:companies,id'],
            'date' => ['required', 'date'],
            'kickoff_date' => ['required', 'date'],
            'value_excl' => ['required', 'numeric', 'gt:0'],
            'value_incl' => ['required', 'numeric', 'gte:value_excl'],
            'scope' => ['nullable', 'string'],
            'link' => ['nullable', new DocumentLink],
            'close_stage' => ['boolean'],
            'terms' => ['required', 'array', 'min:1'],
            'terms.*.name' => ['required', 'string'],
            'terms.*.planned_end' => ['required', 'date', 'after_or_equal:kickoff_date'],
            'terms.*.percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ], [], ['terms' => 'tahapan pembayaran', 'terms.*.name' => 'syarat penyerahan pekerjaan', 'terms.*.planned_end' => 'rencana selesai pekerjaan']);
        $ends = array_column($d['terms'], 'planned_end');
        for ($k = 1; $k < count($ends); $k++) {
            if ($ends[$k] <= $ends[$k - 1]) {
                throw ValidationException::withMessages(["terms.{$k}.planned_end" => 'Rencana selesai harus berurutan setelah tahap sebelumnya.']);
            }
        }
        $candidates = $procurement->candidateVendors()->pluck('companies.id')->all();
        if ($candidates && ! in_array((int) $d['vendor_id'], array_map('intval', $candidates), true)) {
            throw ValidationException::withMessages(['vendor_id' => 'Vendor pemenang harus salah satu calon vendor pengadaan.']);
        }
        $vc = $this->workflow->releaseContract($procurement, $d);

        return $this->done("{$vc->kind} {$vc->number} dirilis dengan ".count($d['terms']).' tahap pembayaran. Proses Pengadaan terkunci.');
    }

    /** Catat Kontrak Pengadaan di samping SP2K/PO; vendor mengikuti pemenang. */
    public function storeWork(Request $request, Procurement $procurement): RedirectResponse
    {
        $d = $request->validate([
            'number' => ['required', 'string', 'max:120', 'unique:vendor_contracts,number'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'kickoff_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:kickoff_date'],
            'value_excl' => ['required', 'numeric', 'gt:0'],
            'value_incl' => ['required', 'numeric', 'gte:value_excl'],
            'scope' => ['nullable', 'string'],
            'link' => ['nullable', new DocumentLink],
        ], [], ['number' => 'nomor kontrak', 'kickoff_date' => 'tanggal mulai', 'end_date' => 'rencana selesai']);
        $vc = $this->workflow->saveWorkContract($procurement, $d);

        return $this->done("Kontrak Pengadaan {$vc->number} dicatat.");
    }

    /** Koreksi data non-finansial; nilai, tanggal mulai dan tahapan hanya lewat amandemen. */
    public function update(Request $request, VendorContract $contract): RedirectResponse
    {
        $d = $request->validate([
            'number' => ['required', 'string', 'max:120', Rule::unique('vendor_contracts', 'number')->ignore($contract->id)],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'scope' => ['nullable', 'string'],
            'link' => ['nullable', new DocumentLink],
        ]);
        foreach ($d as $k => $v) {
            $old = $k === 'date' ? Cal::iso($contract->date) : $contract->{$k};
            if ((string) $old !== (string) $v) {
                $this->audit->log($contract->procurement_id, 'Kontrak Vendor', $contract->id, "Koreksi data {$contract->kind}", $k, $old, $v);
            }
        }
        $contract->update($d);

        return $this->done('Data kontrak disimpan.');
    }
}
