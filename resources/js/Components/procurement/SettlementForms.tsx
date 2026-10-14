import React, { useEffect } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Save, Wallet } from 'lucide-react';
import { Button, Checkbox, Drawer, Field, Input, LinkField, Modal, MoneyInput, MultiSelect, Select, Textarea, cx } from '@/Components/ui';
import { DeadlinePill, PicSelect, StatusBadge } from '@/Components/common';
import { fmtDate, rp } from '@/lib/format';
import type { BaSummary, PageProps, Pic, Term } from '@/types';

/** Goods Receipt per tahap: diajukan di aplikasi lain dengan BA sebagai referensi, dicatat di Procfolio. */
export function GrDrawer({ open, onClose, code, term, bas, pics, statuses }: { open: boolean; onClose: () => void; code: string; term: Term | null; bas: BaSummary[]; pics: Pic[]; statuses: string[] }) {
  const gr = term?.gr;
  const form = useForm({ status: 'Belum Diajukan', number: '', gr_type: '', amount: 0, submitted_at: '', gr_date: '', source_app: 'SAP', pic_id: null as number | null, link: '', note: '', ba_ids: [] as number[] });
  useEffect(() => { if (open && term) form.setData({ status: gr?.status || 'Belum Diajukan', number: gr?.number || '', gr_type: gr?.gr_type || '', amount: gr?.amount || term.amount, submitted_at: gr?.submitted_at || '', gr_date: gr?.gr_date || '', source_app: gr?.source_app || 'SAP', pic_id: gr?.pic_id ?? null, link: gr?.link || '', note: gr?.note || '', ba_ids: gr?.ba_ids?.length ? gr.ba_ids : term.ba_ids }); }, [open, term?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  if (!term) return null;
  const d = form.data; const e = form.errors as Record<string, string>;
  const notDone = bas.filter((b) => d.ba_ids.includes(b.id) && b.status !== 'TTD Selesai');
  return (
    <Drawer open={open} onClose={onClose} title={`GR · Tahap ${term.seq}`} desc={term.name}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/pengadaan/${code}/tahapan/${term.id}/gr`, { preserveScroll: true, onSuccess: onClose })}>Simpan GR</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="BA referensi" required error={e.ba_ids} className="sm:col-span-2"><MultiSelect options={bas.map((b) => ({ value: String(b.id), label: `${b.type} ${b.number || ''}`, sub: `${b.title} · ${b.status}` }))} value={d.ba_ids.map(String)} onChange={(v) => form.setData('ba_ids', v.map(Number))} /></Field>
        {notDone.length > 0 && <p className="rounded-lg bg-warnsoft p-2.5 text-[12.5px] text-warn sm:col-span-2">{notDone.map((b) => b.type).join(', ')} belum selesai TTD.</p>}
        <Field label="Status GR" required><Select value={d.status} onChange={(x) => form.setData('status', x.target.value)}>{statuses.map((s) => <option key={s}>{s}</option>)}</Select></Field>
        <Field label="Aplikasi pengajuan"><Input value={d.source_app} onChange={(x) => form.setData('source_app', x.target.value)} placeholder="Contoh: SAP" /></Field>
        <Field label="Tanggal pengajuan" required={d.status !== 'Belum Diajukan'} error={e.submitted_at}><Input type="date" value={d.submitted_at} onChange={(x) => form.setData('submitted_at', x.target.value)} /></Field>
        <Field label="PIC pengaju GR"><PicSelect pics={pics} value={d.pic_id} onChange={(v) => form.setData('pic_id', v)} roles={['Solution Architect']} /></Field>
        <Field label="Nomor GR" required={d.status === 'GR Terbit'} error={e.number}><Input value={d.number} invalid={!!e.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Jenis GR"><Select value={d.gr_type} onChange={(x) => form.setData('gr_type', x.target.value)}><option value="">–</option>{['BAST', 'BAPS', 'BA Rekon', 'BAUK'].map((t) => <option key={t}>{t}</option>)}</Select></Field>
        <Field label="Nominal GR" error={e.amount}><MoneyInput value={d.amount} onChange={(n) => form.setData('amount', n)} /></Field>
        <Field label="Tanggal GR terbit" required={d.status === 'GR Terbit'} error={e.gr_date}><Input type="date" value={d.gr_date} onChange={(x) => form.setData('gr_date', x.target.value)} /></Field>
        <Field label="Tautan dokumen GR" className="sm:col-span-2" error={e.link}><LinkField value={d.link} onChange={(v) => form.setData('link', v)} /></Field>
        <Field label="Catatan" className="sm:col-span-2"><Textarea value={d.note} onChange={(x) => form.setData('note', x.target.value)} /></Field>
      </div>
    </Drawer>
  );
}

/** Invoice per tahap (satu invoice per tahap) beserta pembayaran final. */
export function InvoiceDrawer({ open, onClose, code, term, pics, checklistLabels }: { open: boolean; onClose: () => void; code: string; term: Term | null; pics: Pic[]; checklistLabels: Record<string, string> }) {
  const { app, errors } = usePage<PageProps>().props;
  const inv = term?.invoice;
  const blankCheck = () => Object.fromEntries(Object.keys(checklistLabels).map((k) => [k, { required: k !== 'laporan', done: false }])) as Record<string, { required: boolean; done: boolean }>;
  const form = useForm({ number: '', date: app.today, amount: 0, pic_id: null as number | null, status: 'Submit Invoice', checklist: blankCheck(), note: '' });
  const pay = useForm({ date: app.today, amount: 0, reason: '' });
  useEffect(() => { if (open && term) { form.setData({ number: inv?.number || '', date: inv?.date || app.today, amount: inv?.amount || term.amount, pic_id: inv?.pic_id ?? null, status: inv && inv.status !== 'Dibayar' ? inv.status : 'Submit Invoice', checklist: { ...blankCheck(), ...(inv?.checklist || {}) }, note: inv?.note || '' }); pay.setData({ date: inv?.payment?.date || app.today, amount: inv?.payment?.amount || inv?.amount || term.amount, reason: '' }); } }, [open, term?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  if (!term) return null;
  const d = form.data; const e = form.errors as Record<string, string>;
  const paid = inv?.status === 'Dibayar';
  const req = Object.entries(d.checklist).filter(([, c]) => c.required);
  return (
    <Drawer open={open} onClose={onClose} title={`Invoice · Tahap ${term.seq}`} desc={`${term.name} · nilai tahap ${rp(term.amount)}`}
      footer={!paid ? <><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/pengadaan/${code}/tahapan/${term.id}/invoice`, { preserveScroll: true, onSuccess: onClose })}>Simpan invoice</Button></> : <Button onClick={onClose}>Tutup</Button>}>
      {paid ? <div className="mb-4 rounded-lg bg-oksoft p-3 text-[13px] text-ok">Invoice dibayar {fmtDate(inv?.payment?.date)} sebesar {rp(inv?.payment?.amount)}.</div> : null}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Nomor invoice" required error={e.number}><Input value={d.number} disabled={paid} invalid={!!e.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Tanggal invoice" required><Input type="date" value={d.date} disabled={paid} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Nilai invoice" required error={e.amount}><MoneyInput value={d.amount} disabled={paid} onChange={(n) => form.setData('amount', n)} /></Field>
        <Field label="Status" required>{paid ? <StatusBadge status="Dibayar" /> : <Select value={d.status} onChange={(x) => form.setData('status', x.target.value)}>{['Submit Invoice', 'Verifikasi', 'Perlu Revisi', 'Antri Bayar'].map((s) => <option key={s}>{s}</option>)}</Select>}</Field>
        <Field label="PIC invoice"><PicSelect pics={pics} value={d.pic_id} onChange={(v) => form.setData('pic_id', v)} roles={['Finance']} /></Field>
        <Field label="Catatan" className="sm:col-span-2"><Textarea value={d.note} disabled={paid} onChange={(x) => form.setData('note', x.target.value)} /></Field>
      </div>
      <div className="mt-5 rounded-xl border border-line p-4">
        <div className="mb-2 flex items-center justify-between"><b className="text-[13.5px]">Kelengkapan penagihan</b><span className="num text-[12.5px] text-mute">{req.filter(([, c]) => c.done).length} dari {req.length} wajib</span></div>
        <ul className="space-y-2">{Object.entries(checklistLabels).map(([k, label]) => { const c = d.checklist[k]; return (
          <li key={k} className="flex flex-wrap items-center gap-3 text-[13px]"><Checkbox checked={!!c?.done} disabled={paid} onChange={(v) => form.setData('checklist', { ...d.checklist, [k]: { ...c, done: v } })} label={label} /><label className="ml-auto flex items-center gap-1.5 text-[12px] text-mute"><input type="checkbox" checked={!!c?.required} disabled={paid} onChange={(x) => form.setData('checklist', { ...d.checklist, [k]: { ...c, required: x.target.checked } })} />Wajib</label></li>); })}</ul>
      </div>
      {inv && (
        <div className="mt-5 rounded-xl border border-line p-4">
          <p className="mb-3 flex items-center gap-2 text-[13.5px] font-bold"><Wallet size={15} />{paid ? 'Koreksi pembayaran' : 'Catat pembayaran final'}</p>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Field label="Tanggal bayar" required><Input type="date" value={pay.data.date} onChange={(x) => pay.setData('date', x.target.value)} /></Field>
            <Field label="Nominal" required><MoneyInput value={pay.data.amount} onChange={(n) => pay.setData('amount', n)} /></Field>
            {paid && <Field label="Alasan koreksi" required error={errors.reason} className="sm:col-span-2"><Textarea value={pay.data.reason} onChange={(x) => pay.setData('reason', x.target.value)} /></Field>}
          </div>
          <Button className="mt-3" variant={paid ? 'secondary' : 'primary'} disabled={pay.processing} onClick={() => pay.post(`/pengadaan/${code}/invoice/${inv.id}/bayar`, { preserveScroll: true, onSuccess: onClose })}>{paid ? 'Simpan koreksi' : 'Tandai dibayar'}</Button>
        </div>
      )}
    </Drawer>
  );
}

/** Tambah/ubah tahap pembayaran setelah SP2K/PO rilis (mis. menindaklanjuti amandemen). */
export function TermModal({ open, onClose, code, term, bas }: { open: boolean; onClose: () => void; code: string; term: Term | null; bas: BaSummary[] }) {
  const form = useForm({ name: '', kind: 'Persentase', percent: 0 as number | null, amount: 0 as number | null, planned_end: '', ba_ids: [] as number[] });
  useEffect(() => { if (open) form.setData({ name: term?.name || '', kind: term?.kind || 'Persentase', percent: term?.percent ?? 0, amount: term?.amount ?? 0, planned_end: term?.planned_end || '', ba_ids: term?.ba_ids || [] }); }, [open, term?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  const d = form.data; const e = form.errors as Record<string, string>;
  const save = () => (term ? form.put(`/pengadaan/${code}/tahapan/${term.id}`, { preserveScroll: true, onSuccess: onClose }) : form.post(`/pengadaan/${code}/tahapan`, { preserveScroll: true, onSuccess: onClose }));
  return (
    <Modal open={open} onClose={onClose} width="max-w-2xl" title={term ? `Ubah Tahap ${term.seq}` : 'Tambah tahap pembayaran'}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={save}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Syarat penyerahan pekerjaan" required error={e.name} className="sm:col-span-2"><Textarea value={d.name} onChange={(x) => form.setData('name', x.target.value)} /></Field>
        <Field label="Skema" required><Select value={d.kind} onChange={(x) => form.setData('kind', x.target.value)}><option>Persentase</option><option>Nominal</option><option>Berkala</option></Select></Field>
        {d.kind === 'Persentase' ? <Field label="Persentase" required error={e.percent}><Input className="num text-right" type="number" min={0} max={100} value={d.percent ?? ''} onChange={(x) => form.setData('percent', Number(x.target.value))} /></Field>
          : <Field label="Nilai bayar" required error={e.amount}><MoneyInput value={d.amount ?? 0} onChange={(n) => form.setData('amount', n)} /></Field>}
        <Field label="Rencana selesai pekerjaan" required error={e.planned_end}><div className="flex items-center gap-2"><Input type="date" value={d.planned_end} onChange={(x) => form.setData('planned_end', x.target.value)} /><DeadlinePill date={d.planned_end} /></div></Field>
        <Field label="BA pendukung" className="sm:col-span-2"><MultiSelect options={bas.map((b) => ({ value: String(b.id), label: `${b.type} ${b.number || ''}`, sub: b.title }))} value={d.ba_ids.map(String)} onChange={(v) => form.setData('ba_ids', v.map(Number))} /></Field>
      </div>
    </Modal>
  );
}

/** Tambah/ubah dokumen pengadaan (tautan SharePoint). */
export function DocModal({ open, onClose, code, doc, docTypes }: { open: boolean; onClose: () => void; code: string; doc: { id: number; doc_type: string; number: string | null; title: string | null; date: string | null; required: boolean; available: boolean; link: string | null; note: string | null } | null; docTypes: string[] }) {
  const form = useForm({ doc_type: '', number: '', title: '', date: '', required: false, available: false, link: '', note: '' });
  useEffect(() => { if (open) form.setData({ doc_type: doc?.doc_type || docTypes[0] || '', number: doc?.number || '', title: doc?.title || '', date: doc?.date || '', required: doc?.required || false, available: doc?.available || false, link: doc?.link || '', note: doc?.note || '' }); }, [open, doc?.id]); // eslint-disable-line react-hooks/exhaustive-deps
  const d = form.data; const e = form.errors as Record<string, string>;
  const save = () => (doc ? form.put(`/pengadaan/${code}/dokumen/${doc.id}`, { preserveScroll: true, onSuccess: onClose }) : form.post(`/pengadaan/${code}/dokumen`, { preserveScroll: true, onSuccess: onClose }));
  return (
    <Modal open={open} onClose={onClose} width="max-w-2xl" title={doc ? `Ubah ${doc.doc_type}` : 'Tambah dokumen'}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={save}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Jenis dokumen" required><Select value={d.doc_type} disabled={!!doc?.required} onChange={(x) => form.setData('doc_type', x.target.value)}>{docTypes.map((t) => <option key={t}>{t}</option>)}</Select></Field>
        <Field label="Nomor dokumen"><Input value={d.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Judul"><Input value={d.title} onChange={(x) => form.setData('title', x.target.value)} /></Field>
        <Field label="Tanggal"><Input type="date" value={d.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Tautan dokumen" className="sm:col-span-2" error={e.link}><LinkField value={d.link} onChange={(v) => form.setData('link', v)} /></Field>
        <div className={cx('flex items-center gap-5 sm:col-span-2')}><Checkbox checked={d.available} onChange={(v) => form.setData('available', v)} label="Tersedia" /></div>
        <Field label="Catatan" className="sm:col-span-2"><Textarea value={d.note} onChange={(x) => form.setData('note', x.target.value)} /></Field>
      </div>
    </Modal>
  );
}
