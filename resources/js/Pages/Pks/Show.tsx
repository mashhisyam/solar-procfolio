import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Save } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, Field, Input, KV, LinkButton, LinkField, Modal, Textarea, cx } from '@/Components/ui';
import { PageHeader, PagedProcTable } from '@/Components/common';
import { fmtDate } from '@/lib/format';
import { useAccess } from '@/lib/access';
import type { PageProps, ProcRow } from '@/types';
import { PksModal, PksRow } from './Index';

type Amd = { id: number; seq: number; number: string; date: string; effective_date: string; changed_parts: string[] | null; description: string | null; new_end_date: string | null; new_scope: string | null; link: string | null };
export default function PksShow({ pks, rows, parts, partners }: { pks: PksRow & { amendments: Amd[] }; rows: ProcRow[]; parts: string[]; partners: { id: number; name: string }[] }) {
  const { auth, app } = usePage<PageProps>().props;
  const access = useAccess();
  const [edit, setEdit] = useState(false); const [amd, setAmd] = useState(false);
  const form = useForm({ number: '', date: app.today, effective_date: app.today, changed_parts: [] as string[], description: '', new_end_date: '', new_scope: '', link: '' });
  return (
    <>
      <Head title={pks.number} />
      <PageHeader crumbs={[['PKS & Amandemen', '/pks'], [pks.number]]} title={pks.number} desc={`${pks.title} · ${pks.partner}`} actions={<><LinkButton url={pks.link} label="Buka dokumen" size="sm" />{access.canEdit('pks') && <><Button icon={Pencil} onClick={() => setEdit(true)}>Ubah</Button><Button variant="primary" icon={Plus} onClick={() => { form.reset(); setAmd(true); }}>Tambah amandemen</Button></>}</>} />
      <Card className="mb-5"><div className="p-5"><KV cols={3} items={[['Versi efektif', pks.effective.label], ['Berlaku', `${fmtDate(pks.start_date)} – ${fmtDate(pks.effective.end_date)}`], ['Ruang lingkup efektif', pks.effective.scope || '–']]} /></div></Card>
      <Card className="mb-5"><CardHead title={`Riwayat amandemen (${pks.amendments.length})`} />
        <ol className="space-y-3 p-5">{pks.amendments.length === 0 ? <p className="text-[13px] text-mute">Belum ada amandemen.</p> : pks.amendments.map((a) => (
          <li key={a.id} className={cx('rounded-lg border p-3 text-[13px]', a.effective_date > app.today ? 'border-warn/40 bg-warnsoft/40' : 'border-line')}>
            <div className="flex flex-wrap items-center gap-2"><b>Amandemen {a.seq}</b><span className="num font-semibold">{a.number}</span><span className="text-mute">efektif {fmtDate(a.effective_date)}</span>{a.effective_date > app.today && <Badge tone="warn">Belum efektif</Badge>}{(a.changed_parts || []).map((x) => <Badge key={x} tone="outline">{x}</Badge>)}<LinkButton url={a.link} label="Buka" /></div>
            {a.description && <p className="mt-1">{a.description}</p>}
          </li>))}</ol>
      </Card>
      <Card><CardHead title="Pengadaan Kemitraan terkait" />{rows.length ? <PagedProcTable rows={rows} /> : <EmptyState compact title="Belum ada pengadaan yang merujuk PKS ini" />}</Card>
      <PksModal open={edit} onClose={() => setEdit(false)} item={pks} partners={partners} />
      <Modal open={amd} onClose={() => setAmd(false)} width="max-w-2xl" title="Tambah amandemen PKS" footer={<><Button onClick={() => setAmd(false)}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/pks/${pks.id}/amandemen`, { onSuccess: () => setAmd(false) })}>Simpan</Button></>}>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Nomor amandemen" required error={form.errors.number}><Input value={form.data.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
          <Field label="Tanggal dokumen" required><Input type="date" value={form.data.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
          <Field label="Tanggal efektif" required hint={form.data.effective_date < form.data.date ? 'Amandemen berlaku mundur.' : undefined}><Input type="date" value={form.data.effective_date} onChange={(x) => form.setData('effective_date', x.target.value)} /></Field>
          <Field label="Masa berlaku baru"><Input type="date" value={form.data.new_end_date} onChange={(x) => form.setData('new_end_date', x.target.value)} /></Field>
          <Field label="Bagian yang berubah" className="sm:col-span-2"><div className="flex flex-wrap gap-2">{parts.map((x) => { const on = form.data.changed_parts.includes(x); return <button type="button" key={x} onClick={() => form.setData('changed_parts', on ? form.data.changed_parts.filter((y) => y !== x) : [...form.data.changed_parts, x])} className={cx('rounded-lg border px-3 py-1.5 text-[12.5px] font-semibold', on ? 'border-brand bg-brandsoft text-brand' : 'border-line text-mute')}>{x}</button>; })}</div></Field>
          <Field label="Uraian" className="sm:col-span-2"><Textarea value={form.data.description} onChange={(x) => form.setData('description', x.target.value)} /></Field>
          <Field label="Ruang lingkup baru" className="sm:col-span-2"><Textarea value={form.data.new_scope} onChange={(x) => form.setData('new_scope', x.target.value)} /></Field>
          <Field label="Tautan dokumen" className="sm:col-span-2" error={form.errors.link}><LinkField value={form.data.link} onChange={(v) => form.setData('link', v)} /></Field>
        </div>
      </Modal>
    </>
  );
}
