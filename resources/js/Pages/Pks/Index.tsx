import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import { Button, Card, Field, Input, LinkField, Modal, SearchInput, Select, TableWrap, Textarea, cx, td, th } from '@/Components/ui';
import { PageHeader, RowLink, StatusBadge } from '@/Components/common';
import { Pager, usePaged } from '@/Components/Pagination';
import { fmtDate } from '@/lib/format';
import { useAccess } from '@/lib/access';
import type { PageProps } from '@/types';

export interface PksRow { id: number; number: string; title: string; partner_id: number; partner: string | null; scope: string | null; link: string | null; date: string | null; start_date: string | null; end_date: string | null; effective: { label: string; end_date: string | null; scope: string | null; future: number }; amendments?: unknown; procurements?: number }
export function PksModal({ open, onClose, item, partners }: { open: boolean; onClose: () => void; item: PksRow | null; partners: { id: number; name: string }[] }) {
  const init = () => ({ number: item?.number || '', title: item?.title || '', partner_id: (item?.partner_id || '') as number | '', date: item?.date || '', start_date: item?.start_date || '', end_date: item?.end_date || '', scope: item?.scope || '', link: item?.link || '' });
  const form = useForm(init());
  React.useEffect(() => { if (open) form.setData(init()); }, [open, item?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  const e = form.errors; const d = form.data;
  return (
    <Modal open={open} onClose={onClose} width="max-w-2xl" title={item ? 'Ubah PKS' : 'Tambah PKS'} footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => (item ? form.put(`/pks/${item.id}`, { onSuccess: onClose }) : form.post('/pks', { onSuccess: onClose }))}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Nomor PKS" required error={e.number}><Input value={d.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Mitra" required error={e.partner_id}><Select value={d.partner_id} onChange={(x) => form.setData('partner_id', Number(x.target.value) || '')}><option value="">Pilih mitra</option>{partners.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
        <Field label="Judul" required error={e.title} className="sm:col-span-2"><Input value={d.title} onChange={(x) => form.setData('title', x.target.value)} /></Field>
        <Field label="Tanggal"><Input type="date" value={d.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Berlaku mulai"><Input type="date" value={d.start_date} onChange={(x) => form.setData('start_date', x.target.value)} /></Field>
        <Field label="Berlaku sampai"><Input type="date" value={d.end_date} onChange={(x) => form.setData('end_date', x.target.value)} /></Field>
        <Field label="Ruang lingkup" className="sm:col-span-2"><Textarea value={d.scope} onChange={(x) => form.setData('scope', x.target.value)} /></Field>
        <Field label="Tautan dokumen" className="sm:col-span-2" error={e.link}><LinkField value={d.link} onChange={(v) => form.setData('link', v)} /></Field>
      </div>
    </Modal>
  );
}
export default function PksIndex({ rows, partners }: { rows: PksRow[]; partners: { id: number; name: string }[] }) {
  const { auth, app } = usePage<PageProps>().props;
  const access = useAccess();
  const [q, setQ] = useState(''); const [modal, setModal] = useState(false);
  const filtered = rows.filter((r) => !q || [r.number, r.title, r.partner].join(' ').toLowerCase().includes(q.toLowerCase()));
  const pg = usePaged(filtered, q);
  return (
    <>
      <Head title="PKS & Amandemen" />
      <PageHeader title="PKS & Amandemen" actions={access.canEdit('pks') && <Button variant="primary" icon={Plus} onClick={() => setModal(true)}>Tambah PKS</Button>} />
      <Card className="mb-4 p-3"><SearchInput value={q} onChange={setQ} placeholder="Cari nomor, judul atau mitra" /></Card>
      <Card><TableWrap><thead><tr><th className={cx(th, 'w-12')}>No</th>{['Nomor', 'Judul', 'Mitra', 'Versi efektif', 'Berlaku s.d.', 'Status', 'Pengadaan'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
        <tbody>{pg.view.map((k, rowIdx) => <RowLink key={k.id} href={`/pks/${k.id}`}><td className={cx(td, 'num w-12 text-mute')}>{pg.start + rowIdx + 1}</td><td className={cx(td, 'num font-bold text-brand')}>{k.number}</td><td className={td}>{k.title}</td><td className={td}>{k.partner}</td><td className={td}>{k.effective.label}{k.effective.future > 0 && <span className="block text-[11.5px] text-warn">{k.effective.future} amandemen belum efektif</span>}</td><td className={td}>{fmtDate(k.effective.end_date)}</td><td className={td}><StatusBadge status={k.effective.end_date && k.effective.end_date < app.today ? 'Berakhir' : 'Aktif'} /></td><td className={cx(td, 'num')}>{k.procurements || 0}</td></RowLink>)}</tbody>
      </TableWrap><Pager p={pg} label="PKS" /></Card>
      <PksModal open={modal} onClose={() => setModal(false)} item={null} partners={partners} />
    </>
  );
}
