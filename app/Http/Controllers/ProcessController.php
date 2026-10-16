<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\Procurement;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Update Proses untuk Progres 1 (Proses Pengadaan/amandemen) dan Progres 2 (per tahap pembayaran). */
class ProcessController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $d = $request->validate([
            'track' => ['required', Rule::in(['pengadaan', 'pasca'])],
            'term_id' => ['required_if:track,pasca', 'nullable', Rule::exists('payment_terms', 'id')->where('procurement_id', $procurement->id)],
            'mode' => ['required', Rule::in(['update', 'complete', 'return', 'move', 'skip', 'rev_minor', 'rev_major'])],
            'activity' => ['nullable', 'string', 'max:255'],
            'pic_id' => ['nullable', 'exists:pics,id'],
            'entered_at' => ['nullable', 'date'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'target' => ['nullable', 'string'],
            'target_pic_id' => ['nullable', 'exists:pics,id'],
            // Penyelesaian Proses GR (wajib saat menyelesaikan tahap GR)
            'gr_number' => ['nullable', 'string', 'max:120'],
            'gr_type' => ['nullable', Rule::in(GoodsReceipt::TYPES)],
            'gr_amount' => ['nullable', 'numeric', 'gt:0'],
            'gr_link' => ['nullable', 'string', 'max:1000'],
            // Invoice (opsional saat menyelesaikan Invoicing)
            'invoice_number' => ['nullable', 'string', 'max:120'],
            'invoice_date' => ['nullable', 'date'],
            'invoice_amount' => ['nullable', 'numeric', 'gt:0'],
            // Kode bucket invoicing (opsional)
            'bucket_code' => ['nullable', 'string', 'max:60'],
            'bucket_number' => ['nullable', 'string', 'max:60'],
            'bucket_link' => ['nullable', 'string', 'max:1000'],
            // Nomor PA dapat dilengkapi saat approval Sales bila belum diisi saat registrasi.
            'pa_number' => ['nullable', 'string', 'max:255'],
        ]);
        if (filled($d['pa_number'] ?? null) && $d['track'] === 'pengadaan') {
            $this->workflow->recordPaNumber($procurement, $d['pa_number']);
        }
        $res = $this->workflow->updateProcess($procurement, $d);

        return back()->with('success', $res['message'])->with('next', $res['next'] ? ['type' => $res['next'], 'amendment_id' => $res['amendment_id']] : null);
    }

    public function reopen(Request $request, Procurement $procurement): RedirectResponse
    {
        $d = $request->validate(['stage' => ['required', 'string']]);
        $this->workflow->reopen($procurement, $d['stage']);

        return $this->done('Tahap dibuka kembali.');
    }
}
