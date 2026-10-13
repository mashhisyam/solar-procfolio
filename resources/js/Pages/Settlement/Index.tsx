import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Card, SearchInput, Segmented, TableWrap, cx, td, th } from '@/Components/ui';
import { DeadlinePill, PageHeader, ProcLink, StatusBadge } from '@/Components/common';
import { fmtDate, rp } from '@/lib/format';

interface Row { id: number; seq: number; name: string; amount: number; planned_end: string | null; paid_at: string | null; procurement: { code: string; spp_number: string | null; title: string }; vendor: string | null; gr: { status: string; number: string | null } | null; invoice: { number: string; status: string; amount: number; updated_at: string | null } | null }
export default function SettlementIndex({ terms }: { terms: Row[] }) {
  const [q, setQ] = useState(''); const [seg, setSeg] = useState('open');
  const view = terms.filter((t) => (seg === 'open' ? !t.paid_at : seg === 'paid' ? !!t.paid_at : true) && (!q || [t.name, t.procurement.spp_number, t.procurement.title, t.vendor, t.invoice?.number].join(' ').toLowerCase().includes(q.toLowerCase())));
  return (
    <>
      <Head title="Tahapan, GR & Invoice" />
      <PageHeader title="Tahapan, GR & Invoice" desc="Register tahap pembayaran lintas pengadaan beserta status GR dan invoice. Pembaruan dilakukan di halaman pengadaan." />
      <Card className="mb-4 flex flex-wrap items-center gap-3 p-3"><Segmented value={seg} onChange={setSeg} options={[{ value: 'open', label: 'Belum dibayar' }, { value: 'paid', label: 'Dibayar' }, { value: 'all', label: 'Semua' }]} /><SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari tahap, No SPP, vendor atau nomor invoice" /></Card>
      <Card><TableWrap><thead><tr>{['Pengadaan', 'Tahap', 'Nilai Bayar', 'Rencana Selesai', 'Vendor', 'GR', 'Invoice', 'Status Bayar'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
        <tbody>{view.map((t) => <tr key={t.id}><td className={td}><ProcLink code={t.procurement.code} spp={t.procurement.spp_number} tab="pasca" /><span className="block max-w-[240px] truncate text-[12px] text-mute">{t.procurement.title}</span></td><td className={cx(td, 'min-w-[200px]')}><b>Tahap {t.seq}</b> · {t.name}</td><td className={cx(td, 'num whitespace-nowrap text-right font-semibold')}>{rp(t.amount)}</td><td className={cx(td, 'whitespace-nowrap')}>{fmtDate(t.planned_end)}{!t.paid_at && <div className="mt-1"><DeadlinePill date={t.planned_end} /></div>}</td><td className={td}>{t.vendor || '–'}</td><td className={td}>{t.gr ? <><StatusBadge status={t.gr.status} /><span className="num block text-[12px] text-mute">{t.gr.number}</span></> : '–'}</td><td className={td}>{t.invoice ? <><StatusBadge status={t.invoice.status} /><span className="num block text-[12px] text-mute">{t.invoice.number}</span></> : '–'}</td><td className={td}>{t.paid_at ? <StatusBadge status="Dibayar" /> : <span className="text-[12px] text-mute">Belum</span>}</td></tr>)}</tbody>
      </TableWrap></Card>
    </>
  );
}
