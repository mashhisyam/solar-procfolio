<x-mail::message>
# Pengadaan perlu ditindaklanjuti

Berikut pengadaan yang Urgent atau memiliki indikator perhatian per {{ now()->translatedFormat('d F Y') }}.

<x-mail::table>
| No SPP | Judul | Posisi | Perhatian |
|:--|:--|:--|:--|
@foreach ($rows as $r)
| [{{ $r['spp_number'] ?: $r['code'] }}]({{ $url }}/pengadaan/{{ $r['code'] }}) | {{ $r['title'] }} | {{ $r['stage'] ?? '-' }} | {{ collect($r['alerts'])->pluck('label')->implode('; ') ?: $r['priority'] }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$url">
Buka Procfolio
</x-mail::button>
</x-mail::message>
