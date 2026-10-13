<?php

namespace App\Http\Controllers;

use App\Enums\GrStatus;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Procurement;
use App\Rules\DocumentLink;
use App\Enums\VisitStatus;
use App\Models\StageVisit;
use App\Services\AuditLogger;
use App\Services\WorkflowService;
use App\Support\WorkdayCalendar as Cal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Goods Receipt, invoice dan pembayaran per tahap pembayaran. */
class SettlementController extends Controller
{
    public function __construct(private AuditLogger $audit, private WorkflowService $workflow) {}

    /** Register tahapan, GR dan invoice lintas pengadaan. */
    public function index(): Response
    {
        $terms = PaymentTerm::query()->with(['procurement:id,code,spp_number,title', 'procurement.contract.vendor:id,name', 'goodsReceipt', 'invoice.payment'])
            ->orderByDesc('id')->get()
            ->map(fn (PaymentTerm $t) => [
                'id' => $t->id, 'seq' => $t->seq, 'name' => $t->name, 'amount' => (float) $t->amount, 'planned_end' => Cal::iso($t->planned_end), 'paid_at' => Cal::iso($t->paid_at),
                'procurement' => $t->procurement->only(['code', 'spp_number', 'title']), 'vendor' => $t->procurement->contract?->vendor?->name,
                'gr' => $t->goodsReceipt ? ['status' => $t->goodsReceipt->status, 'number' => $t->goodsReceipt->number] : null,
                'invoice' => $t->invoice ? ['number' => $t->invoice->number, 'status' => $t->invoice->status, 'amount' => (float) $t->invoice->amount, 'updated_at' => $t->invoice->updated_at?->toDateString()] : null,
            ]);

        return Inertia::render('Settlement/Index', ['terms' => $terms]);
    }

    public function saveGr(Request $request, Procurement $procurement, PaymentTerm $term): RedirectResponse
    {
        abort_unless($term->procurement_id === $procurement->id, 404);
        $d = $request->validate([
            'status' => ['required', Rule::enum(GrStatus::class)],
            'number' => ['required_if:status,GR Terbit', 'nullable', 'string', 'max:120', Rule::unique('goods_receipts', 'number')->ignore($term->goodsReceipt?->id)],
            'submitted_at' => ['required_unless:status,Belum Diajukan', 'nullable', 'date'],
            'gr_date' => ['required_if:status,GR Terbit', 'nullable', 'date'],
            'source_app' => ['nullable', 'string', 'max:60'],
            'pic_id' => ['nullable', 'exists:pics,id'],
            'link' => ['nullable', new DocumentLink],
            'note' => ['nullable', 'string'],
            'ba_ids' => ['required', 'array', 'min:1'],
            'ba_ids.*' => ['integer', 'exists:berita_acara,id'],
        ], [], ['ba_ids' => 'BA referensi']);
        $old = $term->goodsReceipt?->status;
        $gr = $term->goodsReceipt()->updateOrCreate([], collect($d)->except('ba_ids')->all());
        $gr->beritaAcara()->sync($d['ba_ids']);
        $this->audit->log($procurement->id, 'Goods Receipt', $gr->id, $old ? 'Ubah GR' : 'Catat GR', "GR Tahap {$term->seq}", $old, $gr->status);

        return $this->done("GR tahap {$term->seq} disimpan.");
    }

    public function saveInvoice(Request $request, Procurement $procurement, PaymentTerm $term): RedirectResponse
    {
        abort_unless($term->procurement_id === $procurement->id, 404);
        $inv = $term->invoice;
        $d = $request->validate([
            'number' => ['required', 'string', 'max:120', Rule::unique('invoices', 'number')->ignore($inv?->id)],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'pic_id' => ['nullable', 'exists:pics,id'],
            'status' => ['required', Rule::in(['Submit Invoice', 'Verifikasi', 'Perlu Revisi', 'Antri Bayar'])],
            'checklist' => ['array'],
            'checklist.*.required' => ['boolean'],
            'checklist.*.done' => ['boolean'],
            'note' => ['nullable', 'string'],
        ]);
        if ($inv?->status === 'Dibayar') {
            throw ValidationException::withMessages(['status' => 'Invoice sudah dibayar.']);
        }
        $old = $inv?->status;
        $inv = $term->invoice()->updateOrCreate([], $d);
        $this->audit->log($procurement->id, 'Invoice', $inv->id, $old ? 'Ubah invoice' : 'Catat invoice', "Invoice Tahap {$term->seq}", $old, $inv->status);

        return $this->done("Invoice tahap {$term->seq} disimpan.");
    }

    /** Pembayaran final (satu kali per invoice, tanpa parsial). */
    public function pay(Request $request, Procurement $procurement, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->term->procurement_id === $procurement->id, 404);
        $d = $request->validate(['date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string']]);
        if ($invoice->payment) {
            if (empty($d['reason'])) {
                throw ValidationException::withMessages(['reason' => 'Koreksi pembayaran wajib disertai alasan.']);
            }
            $this->audit->log($procurement->id, 'Pembayaran', $invoice->id, 'Koreksi pembayaran', 'Pembayaran', Cal::iso($invoice->payment->date).' · '.$invoice->payment->amount, "{$d['date']} · {$d['amount']}", $d['reason']);
            $invoice->payment->update(['date' => $d['date'], 'amount' => $d['amount']]);
        } else {
            $invoice->payment()->create(['date' => $d['date'], 'amount' => $d['amount']]);
            $invoice->update(['status' => 'Dibayar']);
            $invoice->term->update(['paid_at' => $d['date']]);
            // tutup alur Pasca Pengadaan tahap ini bila masih berjalan
            StageVisit::query()->where('term_id', $invoice->payment_term_id)->where('status', VisitStatus::Running->value)->get()
                ->each(fn (StageVisit $v) => $this->workflow->closeVisit($v, $d['date'], VisitStatus::Done, 'Penjadwalan Pembayaran'));
            $this->audit->log($procurement->id, 'Pembayaran', $invoice->id, 'Catat pembayaran', 'Pembayaran', null, "{$d['date']} · {$d['amount']}");
        }

        return $this->done('Pembayaran disimpan.');
    }
}
