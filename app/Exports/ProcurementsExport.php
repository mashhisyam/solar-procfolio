<?php

namespace App\Exports;

use App\Models\Procurement;
use App\Services\ProcurementInsight;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProcurementsExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private ProcurementInsight $insight, private ?string $since = null) {}

    public function headings(): array
    {
        return ['No SPP', 'ID Monitoring', 'Judul', 'Skema', 'Metode', 'Prioritas', 'User Pengaju', 'Nomor PA', 'RAB', 'Aliran Data', 'Aktivitas', 'PIC', 'Durasi Tahap (HK)', 'Status', 'Vendor', 'Last Update', 'Perhatian'];
    }

    public function array(): array
    {
        return Procurement::query()->with(ProcurementInsight::WITH)
            ->when($this->since, fn ($q) => $q->whereDate('registered_at', '>=', $this->since))
            ->orderBy('code')->get()->map(function (Procurement $p) {
                $r = $this->insight->row($p);

                return [
                    $r['spp_number'], $r['code'], $r['title'], $r['method'], $r['proc_method'], $r['priority'], $r['pengaju_name'], $p->pa_number, $r['rab_value'],
                    $r['stage'], $r['activity'], $r['pic'], $r['days'], $r['status'], $r['vendor'], $r['last_update'],
                    collect($r['alerts'])->pluck('label')->implode('; '),
                ];
            })->all();
    }
}
