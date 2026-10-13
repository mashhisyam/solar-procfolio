import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Card, LinkButton, SearchInput, Select, TableWrap, cx, td, th } from '@/Components/ui';
import { PageHeader, ProcLink, StatusBadge } from '@/Components/common';
import { fmtDate } from '@/lib/format';

interface Row { id: number; doc_type: string; number: string | null; title: string | null; required: boolean; available: boolean; link: string | null; date: string | null; procurement: { code: string; spp_number: string | null; title: string } }
export default function DocumentIndex({ rows }: { rows: Row[] }) {
  const [q, setQ] = useState(''); const [type, setType] = useState(''); const [state, setState] = useState('');
  const types = [...new Set(rows.map((r) => r.doc_type))];
  const view = rows.filter((r) => (!type || r.doc_type === type) && (!state || (state === 'ada' ? r.available : !r.available)) && (!q || [r.number, r.title, r.procurement.spp_number, r.procurement.title].join(' ').toLowerCase().includes(q.toLowerCase())));
  return (
    <>
      <Head title="Dokumen" />
      <PageHeader title="Dokumen" desc="Register dokumen pengadaan. Dokumen tidak diunggah; setiap dokumen ditautkan ke SharePoint." />
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3">
        <SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari nomor, judul atau No SPP" />
        <div className="w-[200px]"><Select value={type} onChange={(e) => setType(e.target.value)}><option value="">Semua jenis</option>{types.map((t) => <option key={t}>{t}</option>)}</Select></div>
        <div className="w-[170px]"><Select value={state} onChange={(e) => setState(e.target.value)}><option value="">Semua status</option><option value="ada">Tersedia</option><option value="belum">Belum tersedia</option></Select></div>
      </Card>
      <Card><TableWrap><thead><tr>{['Jenis', 'Nomor', 'Pengadaan', 'Tanggal', 'Wajib', 'Status', 'Tautan'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
        <tbody>{view.map((d) => <tr key={d.id}><td className={cx(td, 'font-semibold')}>{d.doc_type}</td><td className={cx(td, 'num')}>{d.number || '–'}</td><td className={td}><ProcLink code={d.procurement.code} spp={d.procurement.spp_number} tab="dokumen" /><span className="block max-w-[260px] truncate text-[12px] text-mute">{d.procurement.title}</span></td><td className={td}>{fmtDate(d.date)}</td><td className={td}>{d.required ? 'Wajib' : 'Opsional'}</td><td className={td}><StatusBadge status={d.available ? 'Tersedia' : 'Belum Tersedia'} /></td><td className={td}><LinkButton url={d.link} label="Buka" /></td></tr>)}</tbody>
      </TableWrap></Card>
    </>
  );
}
