import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import { Badge, Button, Card, Field, Input, LinkButton, LinkField, Modal, SearchInput, Select, TableWrap, cx, td, th } from '@/Components/ui';
import { PageHeader, RowLink } from '@/Components/common';
import { fmtDate } from '@/lib/format';
import type { PageProps } from '@/types';

export interface CC { id: number; kind: string; number: string; title: string; customer_id: number; customer: string | null; date: string | null; link: string | null; procurements?: { code: string; spp_number: string | null }[] }
export function CustomerContractModal({ open, onClose, item, customers, kinds }: { open: boolean; onClose: () => void; item: CC | null; customers: { id: number; name: string }[]; kinds: string[] }) {
  const form = useForm({ kind: item?.kind || kinds[0], number: item?.number || '', title: item?.title || '', customer_id: (item?.customer_id || '') as number | '', date: item?.date || '', link: item?.link || '' });
  React.useEffect(() => { if (open) form.setData({ kind: item?.kind || kinds[0], number: item?.number || '', title: item?.title || '', customer_id: (item?.customer_id || '') as number | '', date: item?.date || '', link: item?.link || '' }); }, [open, item?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  const e = form.errors;
  const save = () => (item ? form.put(`/bakb/${item.id}`, { onSuccess: onClose }) : form.post('/bakb', { onSuccess: onClose }));
  return (
    <Modal open={open} onClose={onClose} title={item ? 'Ubah perjanjian pelanggan' : 'Tambah perjanjian pelanggan'} footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={save}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4">
        <Field label="Jenis dokumen" required error={e.kind}><Select value={form.data.kind} onChange={(x) => form.setData('kind', x.target.value)}>{kinds.map((k) => <option key={k}>{k}</option>)}</Select></Field>
        <Field label="Nomor" required error={e.number}><Input value={form.data.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Judul dokumen" required error={e.title}><Input value={form.data.title} onChange={(x) => form.setData('title', x.target.value)} /></Field>
        <Field label="Pelanggan" required error={e.customer_id}><Select value={form.data.customer_id} onChange={(x) => form.setData('customer_id', Number(x.target.value) || '')}><option value="">Pilih pelanggan</option>{customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
        <Field label="Tanggal dokumen"><Input type="date" value={form.data.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Tautan dokumen" error={e.link}><LinkField value={form.data.link} onChange={(v) => form.setData('link', v)} /></Field>
      </div>
    </Modal>
  );
}
export default function CustomerContractIndex({ rows, customers, kinds }: { rows: CC[]; customers: { id: number; name: string }[]; kinds: string[] }) {
  const { auth } = usePage<PageProps>().props;
  const [q, setQ] = useState(''); const [modal, setModal] = useState<{ open: boolean; item: CC | null }>({ open: false, item: null });
  const [kind, setKind] = useState('');
  const view = rows.filter((r) => (!kind || r.kind === kind) && (!q || [r.kind, r.number, r.title, r.customer].join(' ').toLowerCase().includes(q.toLowerCase())));
  return (
    <>
      <Head title="Perjanjian Pelanggan" />
      <PageHeader title="Perjanjian Pelanggan" desc="BAKB, MoM Kesepakatan Harga, dan Kontrak Pelanggan. Setiap pengadaan wajib merujuk salah satunya." actions={auth.can.edit && <Button variant="primary" icon={Plus} onClick={() => setModal({ open: true, item: null })}>Tambah</Button>} />
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3"><SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari nomor, judul atau pelanggan" /><div className="w-[230px]"><Select value={kind} onChange={(e) => setKind(e.target.value)} aria-label="Jenis dokumen"><option value="">Semua jenis</option>{kinds.map((k) => <option key={k}>{k}</option>)}</Select></div></Card>
      <Card><TableWrap><thead><tr>{['Jenis', 'Nomor', 'Judul Dokumen', 'Pelanggan', 'Tanggal', 'Pengadaan Terkait', 'Tautan'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
        <tbody>{view.map((c) => <RowLink key={c.id} href={`/bakb/${c.id}`}><td className={cx(td, 'whitespace-nowrap')}><Badge tone={c.kind === 'BAKB' ? 'navy' : c.kind === 'MoM Kesepakatan Harga' ? 'teal' : 'brand'}>{c.kind}</Badge></td><td className={cx(td, 'num font-bold text-brand')}>{c.number}</td><td className={td}>{c.title}</td><td className={td}>{c.customer}</td><td className={td}>{fmtDate(c.date)}</td><td className={td}><div className="flex flex-wrap gap-1">{(c.procurements || []).slice(0, 3).map((p) => <span key={p.code} className="num rounded-md border border-line px-1.5 py-0.5 text-[11.5px]" title={p.code}>{p.spp_number || p.code}</span>)}{(c.procurements || []).length > 3 && <span className="text-[12px] text-mute">+{(c.procurements || []).length - 3}</span>}</div></td><td className={td}><LinkButton url={c.link} label="Buka" /></td></RowLink>)}</tbody>
      </TableWrap></Card>
      <CustomerContractModal open={modal.open} onClose={() => setModal({ open: false, item: null })} item={modal.item} customers={customers} kinds={kinds} />
    </>
  );
}
