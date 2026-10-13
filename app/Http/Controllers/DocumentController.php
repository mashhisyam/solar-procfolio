<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Procurement;
use App\Rules\DocumentLink;
use App\Services\AuditLogger;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Register dokumen pengadaan. Dokumen tidak diunggah; cukup tautan SharePoint. */
class DocumentController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Documents/Index', [
            'rows' => Document::query()->with('procurement:id,code,spp_number,title')->latest('id')->get()->map(fn (Document $d) => [
                ...$d->only(['id', 'doc_type', 'number', 'title', 'required', 'available', 'link']), 'date' => Cal::iso($d->date),
                'procurement' => $d->procurement->only(['code', 'spp_number', 'title']),
            ]),
        ]);
    }

    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $d = $this->rules($request);
        $doc = $procurement->documents()->create([...$d, 'available' => ($d['available'] ?? false) || ! empty($d['link'])]);
        $this->audit->log($procurement->id, 'Dokumen', $doc->id, 'Tambah dokumen', $doc->doc_type, null, $doc->number);

        return $this->done('Dokumen ditambahkan.');
    }

    public function update(Request $request, Procurement $procurement, Document $document): RedirectResponse
    {
        abort_unless($document->procurement_id === $procurement->id, 404);
        $d = $this->rules($request);
        $old = $document->available;
        $document->update([...$d, 'available' => ($d['available'] ?? false) || ! empty($d['link'])]);
        $this->audit->log($procurement->id, 'Dokumen', $document->id, 'Ubah dokumen', $document->doc_type, $old ? 'Tersedia' : 'Belum', $document->available ? 'Tersedia' : 'Belum');

        return $this->done('Dokumen disimpan.');
    }

    public function destroy(Procurement $procurement, Document $document): RedirectResponse
    {
        abort_unless($document->procurement_id === $procurement->id && ! $document->required, 422, 'Dokumen wajib tidak dapat dihapus.');
        $this->audit->log($procurement->id, 'Dokumen', $document->id, 'Hapus dokumen', $document->doc_type, $document->number);
        $document->delete();

        return $this->done('Dokumen dihapus.');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request): array
    {
        return $request->validate([
            'doc_type' => ['required', 'string'], 'number' => ['nullable', 'string'], 'title' => ['nullable', 'string'],
            'date' => ['nullable', 'date'], 'required' => ['boolean'], 'available' => ['boolean'],
            'link' => ['nullable', new DocumentLink], 'note' => ['nullable', 'string'],
        ]);
    }
}
