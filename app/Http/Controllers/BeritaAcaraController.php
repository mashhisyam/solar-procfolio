<?php

namespace App\Http\Controllers;

use App\Models\BaRound;
use App\Models\BaSignatory;
use App\Models\BeritaAcara;
use App\Models\MasterItem;
use App\Models\Pic;
use App\Models\Procurement;
use App\Rules\DocumentLink;
use App\Services\AuditLogger;
use App\Services\ProcurementInsight;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Berita Acara: dibuat dari halaman pengadaan; sirkuler TTD paralel dengan putaran revisi. */
class BeritaAcaraController extends Controller
{
    public function __construct(private AuditLogger $audit, private ProcurementInsight $insight) {}

    public function index(): Response
    {
        $rows = BeritaAcara::query()->with(['procurement:id,code,spp_number,title', 'rounds.signatories'])->latest('date')->get()
            ->map(fn (BeritaAcara $b) => [
                ...$b->only(['id', 'type', 'number', 'title', 'sub_bidang', 'contract_ref_type', 'contract_ref_number', 'link']),
                'date' => Cal::iso($b->date), ...$this->insight->baInfo($b),
                'procurement' => $b->procurement->only(['code', 'spp_number', 'title']),
            ]);

        return Inertia::render('BeritaAcara/Index', ['rows' => $rows, 'types' => MasterItem::labels('ba_type')]);
    }

    public function create(Procurement $procurement): Response
    {
        return Inertia::render('BeritaAcara/Form', $this->formProps($procurement, null));
    }

    public function edit(BeritaAcara $ba): Response
    {
        return Inertia::render('BeritaAcara/Form', $this->formProps($ba->procurement, $ba));
    }

    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $d = $this->validated($request);
        $ba = $procurement->beritaAcara()->create(collect($d)->except(['term_ids', 'signatory_ids', 'sent_at'])->all());
        $ba->terms()->sync($d['term_ids'] ?? []);
        if (! empty($d['signatory_ids'])) {
            $round = $ba->rounds()->create(['seq' => 1, 'sent_at' => $d['sent_at'] ?? Cal::today(), 'context' => 'Sirkuler awal']);
            foreach ($d['signatory_ids'] as $pid) {
                $round->signatories()->create(['pic_id' => $pid, 'sent_at' => $round->sent_at, 'status' => 'Proses TTD']);
            }
        }
        $this->audit->log($procurement->id, 'Berita Acara', $ba->id, 'Tambah BA', null, null, "{$ba->type} {$ba->number}");

        return redirect("/ba/{$ba->id}")->with('success', "{$ba->type} dicatat.");
    }

    public function update(Request $request, BeritaAcara $ba): RedirectResponse
    {
        $d = $this->validated($request, $ba);
        $ba->update(collect($d)->except(['term_ids', 'signatory_ids', 'sent_at'])->all());
        $ba->terms()->sync($d['term_ids'] ?? []);
        $this->audit->log($ba->procurement_id, 'Berita Acara', $ba->id, 'Ubah BA', null, null, "{$ba->type} {$ba->number}");

        return redirect("/ba/{$ba->id}")->with('success', 'BA diperbarui.');
    }

    public function show(BeritaAcara $ba): Response
    {
        $ba->load(['procurement:id,code,spp_number,title', 'rounds.signatories.pic.company', 'terms']);

        return Inertia::render('BeritaAcara/Show', [
            'ba' => [
                ...$ba->only(['id', 'type', 'number', 'title', 'sub_bidang', 'contract_ref_type', 'contract_ref_number', 'director_internal', 'director_external', 'first_party', 'second_party', 'link', 'manual_status', 'manual_reason']),
                'date' => Cal::iso($ba->date), ...$this->insight->baInfo($ba),
                'procurement' => $ba->procurement->only(['code', 'spp_number', 'title']),
                'terms' => $ba->terms->map(fn ($t) => ['seq' => $t->seq, 'name' => $t->name]),
                'rounds' => $ba->rounds->map(fn (BaRound $r) => [
                    'id' => $r->id, 'seq' => $r->seq, 'sent_at' => Cal::iso($r->sent_at), 'context' => $r->context,
                    'signatories' => $r->signatories->map(fn (BaSignatory $s) => [
                        'id' => $s->id, 'pic_id' => $s->pic_id, 'name' => $s->pic?->name, 'title' => $s->pic?->title, 'company' => $s->pic?->company?->name,
                        'status' => $s->status, 'sent_at' => Cal::iso($s->sent_at), 'signed_at' => Cal::iso($s->signed_at), 'note' => $s->note,
                    ]),
                ]),
            ],
            'pics' => Pic::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'title']),
        ]);
    }

    public function destroy(BeritaAcara $ba): RedirectResponse
    {
        $code = $ba->procurement->code;
        $this->audit->log($ba->procurement_id, 'Berita Acara', $ba->id, 'Hapus BA', null, "{$ba->type} {$ba->number}");
        $ba->delete();

        return redirect("/pengadaan/{$code}?tab=pasca")->with('success', 'BA dihapus.');
    }

    /** Putaran revisi baru; putaran lama tetap tersimpan. */
    public function newRound(Request $request, BeritaAcara $ba): RedirectResponse
    {
        $d = $request->validate(['sent_at' => ['required', 'date'], 'context' => ['required', 'string', 'min:3'], 'copy' => ['boolean']]);
        $prev = $ba->rounds()->with('signatories')->latest('seq')->first();
        $round = $ba->rounds()->create(['seq' => ($prev?->seq ?? 0) + 1, 'sent_at' => $d['sent_at'], 'context' => $d['context']]);
        if (($d['copy'] ?? true) && $prev) {
            foreach ($prev->signatories as $s) {
                $round->signatories()->create(['pic_id' => $s->pic_id, 'sent_at' => $d['sent_at'], 'status' => 'Proses TTD']);
            }
        }
        $ba->update(['manual_status' => null, 'manual_reason' => null]);
        $this->audit->log($ba->procurement_id, 'Berita Acara', $ba->id, "Buka putaran {$round->seq}", null, null, null, $d['context']);

        return $this->done("Putaran {$round->seq} dibuka.");
    }

    public function addSignatory(Request $request, BaRound $round): RedirectResponse
    {
        $d = $request->validate(['pic_id' => ['required', 'exists:pics,id']]);
        $round->signatories()->create(['pic_id' => $d['pic_id'], 'sent_at' => Cal::today(), 'status' => 'Proses TTD']);

        return $this->done('PIC penandatangan ditambahkan.');
    }

    public function updateSignatory(Request $request, BaSignatory $signatory): RedirectResponse
    {
        $d = $request->validate([
            'status' => ['required', Rule::in(['Belum Diproses', 'Proses TTD', 'Selesai TTD', 'Perlu Revisi'])],
            'signed_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);
        $old = $signatory->status;
        $signatory->update([...$d, 'signed_at' => $d['status'] === 'Selesai TTD' ? ($d['signed_at'] ?? Cal::today()) : null]);
        $ba = $signatory->round->beritaAcara;
        $this->audit->log($ba->procurement_id, 'Berita Acara', $ba->id, 'Status TTD', $signatory->pic?->name, $old, $d['status'], $d['note'] ?? null);

        return $this->done('Status tanda tangan diperbarui.');
    }

    public function removeSignatory(BaSignatory $signatory): RedirectResponse
    {
        abort_if($signatory->status === 'Selesai TTD', 422, 'PIC yang sudah TTD tidak dapat dihapus.');
        $signatory->delete();

        return $this->done('PIC dihapus dari sirkuler.');
    }

    /** Koreksi manual status BA (wajib alasan); kirim status kosong untuk membatalkan koreksi. */
    public function override(Request $request, BeritaAcara $ba): RedirectResponse
    {
        $d = $request->validate(['status' => ['nullable', Rule::in(['TTD Selesai', 'Proses TTD', 'Perlu Revisi'])], 'reason' => ['required', 'string', 'min:5']]);
        $old = $ba->status();
        $ba->update(['manual_status' => $d['status'] ?: null, 'manual_reason' => $d['status'] ? $d['reason'] : null]);
        $this->audit->log($ba->procurement_id, 'Berita Acara', $ba->id, $d['status'] ? 'Koreksi manual status BA' : 'Batalkan koreksi status BA', 'Status', $old, $ba->fresh()->status(), $d['reason']);

        return $this->done('Status BA dikoreksi.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?BeritaAcara $ba = null): array
    {
        return $request->validate([
            'sub_bidang' => ['nullable', 'string', 'max:20'],
            'type' => ['required', 'string'],
            'number' => ['nullable', 'string', 'max:120', Rule::unique('berita_acara', 'number')->ignore($ba?->id)],
            'contract_ref_type' => ['nullable', Rule::in(['SP2K', 'PO', 'PKS', 'BAK'])],
            'contract_ref_number' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'director_internal' => ['nullable', 'string'],
            'director_external' => ['nullable', 'string'],
            'first_party' => ['nullable', 'string'],
            'second_party' => ['nullable', 'string'],
            'link' => ['nullable', new DocumentLink],
            'term_ids' => ['array'],
            'term_ids.*' => ['integer', 'exists:payment_terms,id'],
            'signatory_ids' => ['array'],
            'signatory_ids.*' => ['integer', 'exists:pics,id'],
            'sent_at' => ['nullable', 'date'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formProps(Procurement $p, ?BeritaAcara $ba): array
    {
        $p->load(['contract.vendor', 'terms', 'pks']);
        $vc = $p->contract;

        return [
            'procurement' => ['code' => $p->code, 'spp_number' => $p->spp_number, 'title' => $p->title, 'sub_bidang' => $p->sub_bidang, 'method' => $p->method],
            'ba' => $ba ? [...$ba->only(['id', 'type', 'number', 'title', 'sub_bidang', 'contract_ref_type', 'contract_ref_number', 'director_internal', 'director_external', 'first_party', 'second_party', 'link']), 'date' => Cal::iso($ba->date), 'term_ids' => $ba->terms()->pluck('payment_terms.id')] : null,
            'defaults' => [
                'contract_ref_type' => $vc ? ($vc->kind === 'PO' ? 'PO' : 'SP2K') : ($p->method === 'Kemitraan' ? 'BAK' : 'SP2K'),
                'contract_ref_number' => $vc?->number ?? $p->bak_number,
                'first_party' => 'PT Indonesia Comnets Plus',
                'second_party' => $vc?->vendor?->name, // vendor pemenang menjadi Pihak Kedua
                'winner_locked' => (bool) $vc,
            ],
            'terms' => $p->terms->map(fn ($t) => ['id' => $t->id, 'seq' => $t->seq, 'name' => $t->name]),
            'types' => MasterItem::labels('ba_type'),
            'pics' => Pic::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'title', 'role']),
        ];
    }
}
