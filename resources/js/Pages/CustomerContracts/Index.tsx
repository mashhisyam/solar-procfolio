import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import { Badge, Button, Card, Field, Input, LinkButton, LinkField, Modal, MoneyInput, SearchInput, Select, TableWrap, cx, td, th } from '@/Components/ui';
import { PageHeader, RowLink } from '@/Components/common';
import { Pager, usePaged } from '@/Components/Pagination';
import { fmtDate, rp } from '@/lib/format';
import { useAccess } from '@/lib/access';
import type { PageProps } from '@/types';

export interface CC { id: number; kind: string; sub_bidang: string | null; revenue: number | null; number: string; title: string; customer_id: number; customer: string | null; date: string | null; link: string | null; procurements?: { code: string; spp_number: string | null }[] }
export function CustomerContractModal({ open, onClose, item, customers, kinds }: { open: boolean; onClose: () => void; item: CC | null; customers: { id: number; name: string }[]; kinds: string[] }) {
  const form = useForm({ kind: item?.kind || kinds[0], sub_bidang: item?.sub_bidang || 'PLN 1', revenue: item?.revenue ?? 0, number: item?.number || '', title: item?.title || '', customer_id: (item?.customer_id || '') as number | '', date: item?.date || '', link: item?.link || '' });
  React.useEffect(() => { if (open) form.setData({ kind: item?.kind || kinds[0], sub_bidang: item?.sub_bidang || 'PLN 1', revenue: item?.revenue ?? 0, number: item?.number || '', title: item?.title || '', customer_id: (item?.customer_id || '') as number | '', date: item?.date || '', link: item?.link || '' }); }, [open, item?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  const e = form.errors;
  const save = () => (item ? form.put(`/bakb/${item.id}`, { onSuccess: onClose }) : form.post('/bakb', { onSuccess: onClose }));
  return (
    <Modal open={open} onClose={onClose} title={item ? 'Ubah perjanjian pelanggan' : 'Tambah perjanjian pelanggan'} footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={save}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4">
        <Field label="Jenis dokumen" required error={e.kind}><Select value={form.data.kind} onChange={(x) => form.setData('kind', x.target.value)}>{kinds.map((k) => <option key={k}>{k}</option>)}</Select></Field>
        <Field label="Nomor" required error={e.number}><Input value={form.data.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Judul dokumen" required error={e.title}><Input value={form.data.title} onChange={(x) => form.setData('title', x.target.value)} /></Field>
        <Field label="Pelanggan" required error={e.customer_id}><Select value={form.data.customer_id} onChange={(x) => form.setData('customer_id', Number(x.target.value) || '')}><option value="">Pilih pelanggan</option>{customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
        <Field label="Sub bidang" error={e.sub_bidang}><Select value={form.data.sub_bidang} onChange={(x) => form.setData('sub_bidang', x.target.value)}><option>PLN 1</option><option>PLN 2</option></Select></Field>
        <Field label="Revenue" error={e.revenue} hint="Nilai pendapatan dari pelanggan sesuai perjanjian."><MoneyInput value={form.data.revenue} onChange={(n) => form.setData('revenue', n)} /></Field>
        <Field label="Tanggal dokumen"><Input type="date" value={form.data.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Tautan dokumen" error={e.link}><LinkField value={form.data.link} onChange={(v) => form.setData('link', v)} /></Field>
      </div>
    </Modal>
  );
}
export default function CustomerContractIndex({ rows, customers, kinds }: { rows: CC[]; customers: { id: number; name: string }[]; kinds: string[] }) {
  const { auth } = usePage<PageProps>().props;
  const access = useAccess();
  const [q, setQ] = useState(''); const [modal, setModal] = useState<{ open: boolean; item: CC | null }>({ open: false, item: null });
  const [kind, setKind] = useState('');
  const filtered = rows.filter((r) => (!kind || r.kind === kind) && (!q || [r.kind, r.number, r.title, r.customer].join(' ').toLowerCase().includes(q.toLowerCase())));
  const pg = usePaged(filtered, JSON.stringify([q, kind]));
  return (
    <>
      <Head title="Perjanjian Pelanggan" />
      <PageHeader title="Perjanjian Pelanggan" actions={access.canEdit('bakb') && <Button variant="primary" icon={Plus} onClick={() => setModal({ open: true, item: null })}>Tambah</Button>} />
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3"><SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari nomor, judul atau pelanggan" /><div className="w-[230px]"><Select value={kind} onChange={(e) => setKind(e.target.value)} aria-label="Jenis dokumen"><option value="">Semua jenis</option>{kinds.map((k) => <option key={k}>{k}</option>)}</Select></div></Card>
      <Card><TableWrap><thead><tr><th className={cx(th, 'w-12')}>No</th>{['Jenis', 'Nomor', 'Judul Dokumen', 'Pelanggan', 'Sub Bidang', 'Revenue', 'Tanggal', 'Pengadaan Terkait', 'Tautan'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
        <tbody>{pg.view.map((c, rowIdx) => <RowLink key={c.id} href={`/bakb/${c.id}`}><td className={cx(td, 'num w-12 text-mute')}>{pg.start + rowIdx + 1}</td><td className={cx(td, 'whitespace-nowrap')}><Badge tone={c.kind.startsWith('Berita Acara') ? 'navy' : c.kind === 'Kontrak Perjanjian' ? 'brand' : c.kind === 'Surat Penugasan' ? 'teal' : 'outline'}>{c.kind}</Badge></td><td className={cx(td, 'num font-bold text-brand')}>{c.number}</td><td className={td}>{c.title}</td><td className={td}>{c.customer}</td><td className={td}>{c.sub_bidang || '–'}</td><td className={cx(td, 'num whitespace-nowrap text-right font-semibold')}>{c.revenue ? rp(c.revenue) : '–'}</td><td className={td}>{fmtDate(c.date)}</td><td className={td}><div className="flex flex-wrap gap-1">{(c.procurements || []).slice(0, 3).map((p) => <span key={p.code} className="num rounded-md border border-line px-1.5 py-0.5 text-[11.5px]" title={p.code}>{p.spp_number || p.code}</span>)}{(c.procurements || []).length > 3 && <span className="text-[12px] text-mute">+{(c.procurements || []).length - 3}</span>}</div></td><td className={td}><LinkButton url={c.link} label="Buka" /></td></RowLink>)}</tbody>
      </TableWrap><Pager p={pg} label="perjanjian" /></Card>
      <CustomerContractModal open={modal.open} onClose={() => setModal({ open: false, item: null })} item={modal.item} customers={customers} kinds={kinds} />
    </>
  );
}
