import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ExternalLink, FileSignature } from 'lucide-react';
import { Badge, Button, Card, CardHead, KV, LinkButton } from '@/Components/ui';
import { DeadlinePill, PageHeader, ProcLink, StatusBadge } from '@/Components/common';
import { fmtDate, rp } from '@/lib/format';

interface Props {
  contract: { id: number; kind: string; number: string; title: string; scope: string | null; link: string | null; vendor: string | null; date: string; kickoff_date: string; end_date: string | null; value_excl: number; value_incl: number; amendments: { id: number; seq: number; status: string; reason: string; number: string | null; change_types: string[]; description: string | null; date: string | null; requested_at: string }[] };
  procurement: { code: string; spp_number: string | null; title: string; method: string; pengaju_name: string; rab_value: number };
  terms: { seq: number; name: string; percent: number | null; amount: number; planned_end: string | null; paid_at: string | null }[];
}
export default function ContractShow({ contract: c, procurement: p, terms }: Props) {
  return (
    <>
      <Head title={c.number} />
      <PageHeader crumbs={[['Daftar SP2K/PO', '/kontrak'], [c.number]]} title={`${c.kind} ${c.number}`} desc={`${c.title} · ${c.vendor}`}
        meta={<><span className="text-[12.5px] text-mute">Asal pengadaan</span><ProcLink code={p.code} spp={p.spp_number} /></>}
        actions={<><LinkButton url={c.link} label={`Buka ${c.kind}`} size="sm" /><Link href={`/pengadaan/${p.code}?tab=kontrak`}><Button icon={ExternalLink}>Kelola di pengadaan</Button></Link></>} />
      <div className="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_360px]">
        <div className="space-y-5">
          <Card><CardHead title="Asal pengadaan" /><div className="p-5"><KV cols={3} items={[['No SPP / pengajuan', <span key="s" className="num font-bold">{p.spp_number}</span>], ['ID monitoring', p.code], ['Judul pengadaan', p.title], ['Skema', p.method], ['User Pengaju', p.pengaju_name], ['Nilai RAB', rp(p.rab_value)]]} /></div></Card>
          <Card><CardHead icon={FileSignature} title="Data kontrak (berlaku saat ini)" /><div className="p-5"><KV cols={3} items={[['Jenis', c.kind], ['Nomor', c.number], ['Tanggal', fmtDate(c.date)], ['Vendor / mitra', c.vendor || '–'], ['Nilai sebelum PPN', rp(c.value_excl)], ['Nilai setelah PPN', rp(c.value_incl)], ['Mulai – rencana selesai', `${fmtDate(c.kickoff_date)} – ${fmtDate(c.end_date)}`], ['Ruang lingkup', c.scope || '–']]} /></div></Card>
          <Card><CardHead title={`Amandemen (${c.amendments.length})`} /><div className="space-y-3 p-5">{c.amendments.length === 0 ? <p className="text-[13px] text-mute">Belum ada amandemen.</p> : c.amendments.map((a) => <div key={a.id} className="rounded-lg border border-line p-3 text-[13px]"><div className="flex flex-wrap items-center gap-2"><b>Amandemen {a.seq}</b><StatusBadge status={a.status === 'Dalam Proses' ? 'Dalam Proses' : a.status} />{a.number && <span className="num font-semibold">{a.number}</span>}<span className="text-mute">{fmtDate(a.date || a.requested_at)}</span>{a.change_types.map((t) => <Badge key={t} tone="outline">{t}</Badge>)}</div><p className="mt-1">{a.description || a.reason}</p></div>)}</div></Card>
        </div>
        <Card className="self-start"><CardHead title="Tahapan pembayaran" />
          <ul className="divide-y divide-line">{terms.map((t) => <li key={t.seq} className="px-4 py-2.5 text-[13px]"><div className="flex justify-between gap-2"><b>Tahap {t.seq} · {t.name}</b><span className="num font-semibold">{rp(t.amount)}</span></div><div className="mt-1 flex items-center gap-1.5 text-[12px] text-mute">Rencana selesai {fmtDate(t.planned_end)}{t.paid_at ? <StatusBadge status="Dibayar" /> : <DeadlinePill date={t.planned_end} />}</div></li>)}</ul>
        </Card>
      </div>
    </>
  );
}
