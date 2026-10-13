<?php

namespace App\Http\Controllers;

use App\Models\PaymentTerm;
use App\Models\Procurement;
use App\Services\AuditLogger;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Penyesuaian tahapan pembayaran setelah rilis (mis. akibat amandemen). */
class PaymentTermController extends Controller
{
    public function __construct(private WorkflowService $workflow, private AuditLogger $audit) {}

    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        abort_unless($procurement->contract()->exists(), 422, 'Tahapan pembayaran dibuat saat SP2K/PO dirilis.');
        $d = $this->rules($request);
        $seq = (int) $procurement->terms()->max('seq') + 1;
        $t = $procurement->terms()->create([...$d, 'seq' => $seq, 'amount' => $this->amount($procurement, $d)]);
        $t->beritaAcara()->sync($d['ba_ids'] ?? []);
        $this->workflow->ensureTermFlows($procurement);
        $this->audit->log($procurement->id, 'Tahap Pembayaran', $t->id, 'Tambah tahap pembayaran', null, null, "Tahap {$seq} · {$t->name}");

        return $this->done("Tahap {$seq} ditambahkan beserta alur Pasca Pengadaan-nya.");
    }

    public function update(Request $request, Procurement $procurement, PaymentTerm $term): RedirectResponse
    {
        abort_unless($term->procurement_id === $procurement->id, 404);
        $d = $this->rules($request);
        $term->update([...$d, 'amount' => $this->amount($procurement, $d)]);
        $term->beritaAcara()->sync($d['ba_ids'] ?? []);
        $this->workflow->ensureTermFlows($procurement);
        $this->audit->log($procurement->id, 'Tahap Pembayaran', $term->id, 'Ubah tahap pembayaran', null, null, "Tahap {$term->seq} · {$term->name}");

        return $this->done("Tahap {$term->seq} diperbarui.");
    }

    public function destroy(Procurement $procurement, PaymentTerm $term): RedirectResponse
    {
        abort_unless($term->procurement_id === $procurement->id, 404);
        if ($term->invoice()->exists()) {
            throw ValidationException::withMessages(['term' => 'Tahap yang sudah memiliki invoice tidak dapat dihapus.']);
        }
        $this->audit->log($procurement->id, 'Tahap Pembayaran', $term->id, 'Hapus tahap pembayaran', null, "Tahap {$term->seq} · {$term->name}");
        $term->delete();

        return $this->done('Tahap dihapus.');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string'],
            'kind' => ['required', Rule::in(['Persentase', 'Nominal', 'Berkala'])],
            'percent' => ['required_if:kind,Persentase', 'nullable', 'numeric', 'gt:0', 'max:100'],
            'amount' => ['required_unless:kind,Persentase', 'nullable', 'numeric', 'gt:0'],
            'planned_end' => ['required', 'date'],
            'period' => ['nullable', 'string', 'size:7'],
            'ba_ids' => ['array'],
            'ba_ids.*' => ['integer', 'exists:berita_acara,id'],
        ]);
    }

    /** @param array<string, mixed> $d */
    private function amount(Procurement $p, array $d): float
    {
        return $d['kind'] === 'Persentase' ? round(((float) $p->contract->value_incl) * ((float) $d['percent']) / 100, 2) : (float) $d['amount'];
    }
}
