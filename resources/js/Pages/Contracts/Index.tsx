import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { FileSignature, SearchX } from 'lucide-react';
import { Badge, Button, Card, EmptyState, Field, Modal, SearchInput, Select, TableWrap, cx, td, th } from '@/Components/ui';
import { PageHeader, ProcLink, RowLink } from '@/Components/common';
import { PeriodFilter } from '@/Components/PeriodFilter';
import { Pager, usePaged } from '@/Components/Pagination';
import { fmtDate, rp } from '@/lib/format';
import { useAccess } from '@/lib/access';
import type { PageProps } from '@/types';

interface Row { id: number; is_primary: boolean; kind: string; number: string; title: string; vendor: string | null; vendor_id: number; date: string; kickoff_date: string; end_date: string | null; value_incl: number; amendments: number; terms_total: number; terms_paid: number; procurement: { code: string; spp_number: string | null; title: string } }
export default function ContractIndex({ rows, vendors, pending }: { rows: Row[]; vendors: { id: number; name: string }[]; pending: { code: string; spp_number: string | null; title: string }[] }) {
  const { auth } = usePage<PageProps>().props;
  const access = useAccess();
  const [q, setQ] = useState(''); const [kind, setKind] = useState(''); const [vendor, setVendor] = useState('');
  const [pick, setPick] = useState(false); const [code, setCode] = useState('');
  const filtered = rows.filter((r) => (!kind || r.kind === kind) && (!vendor || r.vendor_id === Number(vendor)) && (!q || [r.number, r.title, r.vendor, r.procurement.spp_number, r.procurement.code].join(' ').toLowerCase().includes(q.toLowerCase())));
  const pg = usePaged(filtered, JSON.stringify([q, kind, vendor]));
  const kinds = [...new Set(['SP2K', 'PO', 'Kontrak Pengadaan', ...rows.map((r) => r.kind)])];
  return (
    <>
      <Head title="SP2K/PO & Kontrak Pengadaan" />
      <PageHeader title="SP2K/PO & Kontrak Pengadaan" actions={access.canEdit('kontrak') && <Button variant="primary" icon={FileSignature} onClick={() => setPick(true)}>Rilis SP2K/PO</Button>} />
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3">
        <PeriodFilter className="w-full" />
        <SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari nomor, judul, vendor atau No SPP" />
        <div className="w-[190px]"><Select value={kind} onChange={(e) => setKind(e.target.value)}><option value="">Semua jenis</option>{kinds.map((k) => <option key={k}>{k}</option>)}</Select></div>
        <div className="w-[240px]"><Select value={vendor} onChange={(e) => setVendor(e.target.value)}><option value="">Semua vendor/mitra</option>{vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}</Select></div>
      </Card>
      <Card>{pg.view.length === 0 ? <EmptyState icon={SearchX} title="Belum ada SP2K/PO atau Kontrak Pengadaan" desc="Coba perluas periode atau ubah filter." /> : (<>
        <TableWrap><thead><tr><th className={cx(th, 'w-12')}>No</th>{['Jenis', 'Nomor', 'Asal Pengadaan', 'Judul Pekerjaan', 'Vendor / Mitra', 'Tanggal', 'Mulai – Selesai', 'Setelah PPN', 'Tahapan', 'Amandemen'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
          <tbody>{pg.view.map((c, rowIdx) => (
            <RowLink key={c.id} href={`/kontrak/${c.id}`}><td className={cx(td, 'num w-12 text-mute')}>{pg.start + rowIdx + 1}</td>
              <td className={td}><Badge tone={!c.is_primary ? 'teal' : c.kind === 'PO' ? 'brand' : 'navy'}>{c.kind}</Badge></td>
              <td className={cx(td, 'num whitespace-nowrap font-bold text-brand')}>{c.number}</td>
              <td className={cx(td, 'min-w-[200px]')}><ProcLink code={c.procurement.code} spp={c.procurement.spp_number} /><span className="block truncate text-[12px] text-mute">{c.procurement.title}</span></td>
              <td className={cx(td, 'min-w-[220px]')}>{c.title}</td>
              <td className={cx(td, 'min-w-[160px]')}>{c.vendor}</td>
              <td className={cx(td, 'num whitespace-nowrap')}>{fmtDate(c.date)}</td>
              <td className={cx(td, 'num whitespace-nowrap')}>{fmtDate(c.kickoff_date)} – {fmtDate(c.end_date)}</td>
              <td className={cx(td, 'num whitespace-nowrap text-right font-semibold')}>{rp(c.value_incl)}</td>
              <td className={cx(td, 'num whitespace-nowrap')}>{c.is_primary && c.terms_total ? `${c.terms_paid}/${c.terms_total} dibayar` : '–'}</td>
              <td className={cx(td, 'num')}>{c.amendments || '–'}</td>
            </RowLink>))}</tbody>
        </TableWrap><Pager p={pg} label="kontrak" /></>)}
      </Card>
      <Modal open={pick} onClose={() => setPick(false)} title="Rilis SP2K/PO" desc="Pilih pengadaan asal. Rilis dilakukan di halaman pengadaan beserta tahapan pembayarannya."
        footer={<><Button onClick={() => setPick(false)}>Batal</Button><Button variant="primary" disabled={!code} onClick={() => router.visit(`/pengadaan/${code}?tab=kontrak`)}>Lanjut</Button></>}>
        <Field label="Pengadaan"><Select value={code} onChange={(e) => setCode(e.target.value)}><option value="">Pilih pengadaan</option>{pending.map((p) => <option key={p.code} value={p.code}>{p.spp_number || p.code} · {p.title}</option>)}</Select></Field>
      </Modal>
    </>
  );
}
