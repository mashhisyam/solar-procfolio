<?php

namespace App\Http\Controllers;

use App\Models\ContractAmendment;
use App\Models\Procurement;
use App\Rules\DocumentLink;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Amandemen SP2K/PO atau Kontrak Pengadaan: satu-satunya jalan membuka kembali Proses Pengadaan setelah kontrak rilis. */
class AmendmentController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    public function store(Request $request, Procurement $procurement): RedirectResponse
    {
        $d = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
            'change_types' => ['required', 'array', 'min:1'],
            'change_types.*' => [Rule::in(ContractAmendment::CHANGE_TYPES)],
            'proposal' => ['nullable', 'string'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*' => ['string'],
            'date' => ['required', 'date'],
            // Kosong = SP2K/PO; isi dengan id Kontrak Pengadaan untuk mengamandemen kontrak tersebut.
            'contract_id' => ['nullable', 'integer'],
        ]);
        $am = $this->workflow->startAmendment($procurement, $d);

        return $this->done("Amandemen {$am->seq} {$am->contract->kind} diajukan. Proses Pengadaan dibuka khusus untuk alur amandemen.");
    }

    public function release(Request $request, Procurement $procurement, ContractAmendment $amendment): RedirectResponse
    {
        if ($amendment->contract->procurement_id !== $procurement->id || $amendment->status !== 'Dalam Proses') {
            throw ValidationException::withMessages(['number' => 'Amandemen tidak dapat dirilis.']);
        }
        $types = (array) $request->input('change_types', []);
        $d = $request->validate([
            'number' => ['required', 'string', 'max:120', 'unique:contract_amendments,number'],
            'date' => ['required', 'date'],
            'change_types' => ['required', 'array', 'min:1'],
            'change_types.*' => [Rule::in(ContractAmendment::CHANGE_TYPES)],
            'description' => ['required', 'string'],
            'value_excl' => [in_array('Nilai', $types, true) ? 'required' : 'nullable', 'numeric'],
            'value_incl' => [in_array('Nilai', $types, true) ? 'required' : 'nullable', 'numeric'],
            'end_date' => [in_array('Jangka Waktu', $types, true) ? 'required' : 'nullable', 'date'],
            'scope' => [in_array('Ruang Lingkup', $types, true) ? 'required' : 'nullable', 'string'],
            'link' => ['nullable', new DocumentLink],
        ]);
        $this->workflow->releaseAmendment($procurement, $amendment, $d);

        return $this->done("Amandemen {$amendment->seq} rilis dan diterapkan ke kontrak.".(in_array('Termin', $types, true) ? ' Perbarui tahapan pembayaran sesuai amandemen.' : ''));
    }
}
