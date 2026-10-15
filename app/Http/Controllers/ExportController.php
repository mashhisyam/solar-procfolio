<?php

namespace App\Http\Controllers;

use App\Exports\ProcurementsExport;
use App\Services\ProcurementInsight;
use App\Support\Period;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /** Ekspor mengikuti periode yang sedang dipilih di Daftar Pengadaan. */
    public function procurements(Request $request, ProcurementInsight $insight): BinaryFileResponse
    {
        return Excel::download(new ProcurementsExport($insight, Period::since(Period::current($request))), 'procfolio-pengadaan-'.now()->format('Ymd').'.xlsx');
    }
}
