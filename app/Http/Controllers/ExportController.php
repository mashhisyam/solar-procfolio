<?php

namespace App\Http\Controllers;

use App\Exports\ProcurementsExport;
use App\Services\ProcurementInsight;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function procurements(ProcurementInsight $insight): BinaryFileResponse
    {
        return Excel::download(new ProcurementsExport($insight), 'procfolio-pengadaan-'.now()->format('Ymd').'.xlsx');
    }
}
