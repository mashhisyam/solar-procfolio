import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Badge, Card, LinkButton, SearchInput, Select, TableWrap, cx, td, th } from '@/Components/ui';
import { PageHeader, ProcLink, RowLink, StatusBadge } from '@/Components/common';
import { PeriodFilter } from '@/Components/PeriodFilter';
import { Pager, usePaged } from '@/Components/Pagination';
import { fmtDate } from '@/lib/format';

interface Row { id: number; type: string; number: string | null; title: string; sub_bidang: string | null; contract_ref_type: string | null; contract_ref_number: string | null; link: string | null; date: string | null; status: string; round: number; signed: number; total: number; days: number; procurement: { code: string; spp_number: string | null; title: string }; term_seqs: number[] }
export default function BaIndex({ rows, types }: { rows: Row[]; types: string[] }) {
  const [q, setQ] = useState(''); const [type, setType] = useState(''); const [status, setStatus] = useState('');
  const filtered = rows.filter((r) => (!type || r.type === type) && (!status || r.status === status) && (!q || [r.number, r.title, r.procurement.spp_number, r.procurement.title].join(' ').toLowerCase().includes(q.toLowerCase())));
  const pg = usePaged(filtered, JSON.stringify([q, type, status]));
  return (
    <>
      <Head title="Berita Acara" />
      <PageHeader title="Berita Acara" />
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3">
        <PeriodFilter className="w-full" />
        <SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari nomor, judul atau No SPP" />
        <div className="w-[150px]"><Select value={type} onChange={(e) => setType(e.target.value)}><option value="">Semua jenis</option>{types.map((t) => <option key={t}>{t}</option>)}</Select></div>
        <div className="w-[170px]"><Select value={status} onChange={(e) => setStatus(e.target.value)}><option value="">Semua status</option>{['Draft', 'Belum Diproses', 'Proses TTD', 'Perlu Revisi', 'TTD Selesai'].map((s) => <option key={s}>{s}</option>)}</Select></div>
      </Card>
      <Card><TableWrap><thead><tr>{['Jenis', 'Nomor', 'Judul', 'Pengadaan', 'Tahap', 'Acuan', 'Tanggal', 'Status', 'Progres TTD', 'Tautan'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
        <tbody>{pg.view.map((b) => <RowLink key={b.id} href={`/ba/${b.id}`}><td className={td}><Badge tone="navy">{b.type}</Badge></td><td className={cx(td, 'num whitespace-nowrap font-semibold')}>{b.number || '–'}</td><td className={cx(td, 'min-w-[220px]')}>{b.title}</td><td className={td}><ProcLink code={b.procurement.code} spp={b.procurement.spp_number} /></td><td className={cx(td, 'num whitespace-nowrap')}>{b.term_seqs.length ? b.term_seqs.map((n) => `Tahap ${n}`).join(', ') : '–'}</td><td className={cx(td, 'whitespace-nowrap')}>{b.contract_ref_type} {b.contract_ref_number}</td><td className={td}>{fmtDate(b.date)}</td><td className={td}><StatusBadge status={b.status} /></td><td className={cx(td, 'num whitespace-nowrap')}>{b.signed}/{b.total}{b.round > 1 && <span className="text-warn"> · putaran {b.round}</span>}</td><td className={td}><LinkButton url={b.link} label="Buka" /></td></RowLink>)}</tbody>
      </TableWrap><Pager p={pg} label="BA" /></Card>
    </>
  );
}
