<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\MasterItem;
use App\Models\Pks;
use App\Rules\DocumentLink;
use App\Services\ProcurementInsight;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** PKS (kontrak payung kemitraan) dan amandemennya. */
class PksController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pks/Index', [
            'rows' => Pks::query()->with(['partner:id,name', 'amendments', 'procurements:id,pks_id'])->orderBy('number')->get()->map(fn (Pks $k) => [
                ...$k->only(['id', 'number', 'title', 'partner_id', 'scope', 'link']), 'partner' => $k->partner?->name,
                'date' => Cal::iso($k->date), 'start_date' => Cal::iso($k->start_date), 'end_date' => Cal::iso($k->end_date),
                'effective' => $k->effective(), 'amendments' => $k->amendments->count(), 'procurements' => $k->procurements->count(),
            ]),
            'partners' => Company::query()->where('type', 'Mitra')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Pks $pks, ProcurementInsight $insight): Response
    {
        $pks->load(['partner:id,name', 'amendments', 'procurements' => fn ($q) => $q->with(ProcurementInsight::WITH)]);

        return Inertia::render('Pks/Show', [
            'pks' => [
                ...$pks->only(['id', 'number', 'title', 'partner_id', 'scope', 'link']), 'partner' => $pks->partner?->name,
                'date' => Cal::iso($pks->date), 'start_date' => Cal::iso($pks->start_date), 'end_date' => Cal::iso($pks->end_date), 'effective' => $pks->effective(),
                'amendments' => $pks->amendments->map(fn ($a) => [
                    ...$a->only(['id', 'seq', 'number', 'changed_parts', 'description', 'new_scope', 'link']),
                    'date' => Cal::iso($a->date), 'effective_date' => Cal::iso($a->effective_date), 'new_end_date' => Cal::iso($a->new_end_date),
                ]),
            ],
            'rows' => $pks->procurements->map(fn ($p) => $insight->row($p))->values(),
            'parts' => MasterItem::labels('change_part'),
            'partners' => Company::query()->where('type', 'Mitra')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Pks::query()->create($this->rules($request));

        return $this->done('PKS disimpan.');
    }

    public function update(Request $request, Pks $pks): RedirectResponse
    {
        $pks->update($this->rules($request, $pks->id));

        return $this->done('PKS diperbarui.');
    }

    public function storeAmendment(Request $request, Pks $pks): RedirectResponse
    {
        $d = $request->validate([
            'number' => ['required', 'string', 'unique:pks_amendments,number'], 'date' => ['required', 'date'], 'effective_date' => ['required', 'date'],
            'changed_parts' => ['array'], 'description' => ['nullable', 'string'], 'new_end_date' => ['nullable', 'date'], 'new_scope' => ['nullable', 'string'],
            'link' => ['nullable', new DocumentLink],
        ]);
        $pks->amendments()->create([...$d, 'seq' => $pks->amendments()->count() + 1]);
        $back = $d['effective_date'] < $d['date'] ? ' Catatan: amandemen berlaku mundur.' : '';

        return $this->done('Amandemen PKS disimpan.'.$back);
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:120', Rule::unique('pks', 'number')->ignore($id)],
            'title' => ['required', 'string', 'max:255'], 'partner_id' => ['required', 'exists:companies,id'],
            'date' => ['nullable', 'date'], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'],
            'scope' => ['nullable', 'string'], 'link' => ['nullable', new DocumentLink],
        ]);
    }
}
