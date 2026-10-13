<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CustomerContract;
use App\Rules\DocumentLink;
use App\Services\ProcurementInsight;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** BAKB / kontrak pelanggan. Pengadaan ditampilkan dengan Nomor SPP. */
class CustomerContractController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('CustomerContracts/Index', [
            'rows' => CustomerContract::query()->with(['customer:id,name', 'procurements:id,code,spp_number'])->orderByDesc('date')->get()->map(fn (CustomerContract $c) => [
                ...$c->only(['id', 'number', 'title', 'customer_id', 'link']), 'date' => Cal::iso($c->date), 'customer' => $c->customer?->name,
                'procurements' => $c->procurements->map(fn ($p) => ['code' => $p->code, 'spp_number' => $p->spp_number]),
            ]),
            'customers' => Company::query()->where('type', 'Pelanggan')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(CustomerContract $customerContract, ProcurementInsight $insight): Response
    {
        $c = $customerContract->load(['customer:id,name', 'procurements' => fn ($q) => $q->with(ProcurementInsight::WITH)]);

        return Inertia::render('CustomerContracts/Show', [
            'contract' => [...$c->only(['id', 'number', 'title', 'customer_id', 'link']), 'date' => Cal::iso($c->date), 'customer' => $c->customer?->name],
            'rows' => $c->procurements->map(fn ($p) => $insight->row($p))->values(),
            'customers' => Company::query()->where('type', 'Pelanggan')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomerContract::query()->create($this->rules($request));

        return $this->done('BAKB/kontrak pelanggan disimpan.');
    }

    public function update(Request $request, CustomerContract $customerContract): RedirectResponse
    {
        $customerContract->update($this->rules($request, $customerContract->id));

        return $this->done('BAKB/kontrak pelanggan diperbarui.');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:120', Rule::unique('customer_contracts', 'number')->ignore($id)],
            'title' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'exists:companies,id'],
            'date' => ['nullable', 'date'],
            'link' => ['nullable', new DocumentLink],
        ]);
    }
}
