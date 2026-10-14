import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, Save, Plus, Trash2, BadgeInfo, Handshake, Wallet, ClipboardList, GitBranch, ClipboardCheck } from 'lucide-react';
import { Button, Card, Checkbox, Field, Input, KV, LinkField, MoneyInput, MultiSelect, Segmented, Select, Switch, Textarea, cx } from '@/Components/ui';
import { PageHeader, PicSelect } from '@/Components/common';
import { fmtDate, rp } from '@/lib/format';
import type { Pic, StageDef } from '@/types';

type Method = 'SPP' | 'Kemitraan';
interface RabRow { description: string; budget_type: 'OPEX' | 'CAPEX'; qty: number; unit: string; price: number }
interface DocRow { id?: number; doc_type: string; number: string; title: string; date: string; required: boolean; available: boolean; link: string; note: string }
interface RefDoc { doc_type: 'Surat Penugasan'; number: string; date: string; link: string }
interface Options {
  procTypes: string[]; procMethods: string[]; docTypes: string[]; rabUnits: string[]; requiredDocs: Record<Method, string[]>; pics: Pic[];
  vendors: { id: number; name: string; type: string }[]; customers: { id: number; name: string }[];
  customerContracts: { id: number; kind: string; number: string; title: string; customer: string | null }[];
  pks: { id: number; number: string; title: string; partner_id: number; partner: string | null; label: string; end_date: string | null; future: number }[];
  budgetCodes: { id: number; coa: string; cost_center: string; code: string; program: string; klasifikasi: string; active: boolean; year: number | null; budget_amount: number; used: number }[];
  templates: Record<Method, StageDef[]>;
}
interface Props { procurement: (Record<string, unknown> & { code: string; has_contract: boolean }) | null; nextCode: string; options: Options }

const STEPS = [
  { key: 1, label: 'Identitas', icon: BadgeInfo }, { key: 2, label: 'Referensi', icon: Handshake }, { key: 3, label: 'RAB & PRK', icon: Wallet },
  { key: 4, label: 'Checklist Dokumen', icon: ClipboardList }, { key: 5, label: 'Workflow', icon: GitBranch }, { key: 6, label: 'Review', icon: ClipboardCheck },
];
const STEP_OF: Record<string, number> = { title: 1, method: 1, proc_type: 1, proc_method: 1, spp_number: 1, spp_date: 1, pic_pengaju_id: 1, candidate_vendor_ids: 1, registered_at: 1, customer_contract_ids: 2, pks_id: 2, bak_number: 2, budget_code_id: 3, rab_items: 3, documents: 4, workflow: 5 };
const today = () => new Date().toISOString().slice(0, 10);
/** Default tahap Perencana Pengadaan (Rendan) menurut metode. Pengguna tetap dapat mengubahnya lewat toggle. */
const routed = (flow: StageDef[], procMethod: string): StageDef[] => flow.map((s) => (s.key.startsWith('rendan_') ? { ...s, enabled: procMethod !== 'Pengadaan Langsung' } : s));
/** Dokumen yang tidak memakai nomor dokumen. */
const NO_NUMBER = ['TOR', 'BCR (Benefit Cost Ratio)'];

export default function ProcurementForm({ procurement: p, nextCode, options: o }: Props) {
  const edit = !!p;
  const initDocs = (m: Method): DocRow[] => [...o.requiredDocs[m].map((t) => ({ doc_type: t, number: '', title: t, date: '', required: true, available: false, link: '', note: '' }))];
  const form = useForm({
    draft: false,
    method: ((p?.method as Method) || 'SPP') as Method,
    title: (p?.title as string) || '', summary: (p?.summary as string) || '', proc_type: (p?.proc_type as string) || '', proc_method: (p?.proc_method as string) || '',
    priority: ((p?.priority as string) || 'Normal') as 'Normal' | 'Urgent', sub_bidang: (p?.sub_bidang as string) || 'PLN 1', registered_at: (p?.registered_at as string) || today(),
    spp_number: (p?.spp_number as string) || '', spp_date: (p?.spp_date as string) || '', pic_pengaju_id: ((p?.pic_pengaju_id as number) || null) as number | null,
    candidate_vendor_ids: ((p?.candidate_vendor_ids as number[]) || []) as number[], customer_contract_ids: ((p?.customer_contract_ids as number[]) || []) as number[],
    pks_id: ((p?.pks_id as number) || '') as number | '', bak_number: (p?.bak_number as string) || '', bak_date: (p?.bak_date as string) || '',
    ref_docs: (((p?.ref_docs as RefDoc[]) || []).length ? (p!.ref_docs as RefDoc[]) : [{ doc_type: 'Surat Penugasan', number: '', date: '', link: '' }]) as RefDoc[],
    budget_code_id: ((p?.budget_code_id as number) || '') as number | '',
    rab_items: (((p?.rab_items as RabRow[]) || []).length ? (p!.rab_items as RabRow[]) : [{ description: '', budget_type: 'OPEX', qty: 1, unit: o.rabUnits[0] || 'LOT (SERVICE & MATERIAL)', price: 0 }]) as RabRow[],
    documents: (((p?.documents as DocRow[]) || []).map((d) => ({ ...d, number: d.number || '', title: d.title || '', date: d.date || '', link: d.link || '', note: d.note || '' })).length ? (p!.documents as DocRow[]) : initDocs((p?.method as Method) || 'SPP')) as DocRow[],
    workflow: ((p?.workflow as StageDef[]) || routed(o.templates[(p?.method as Method) || 'SPP'], (p?.proc_method as string) || '')) as StageDef[],
  });
  const d = form.data;
  const [step, setStep] = useState(1);
  const [local, setLocal] = useState<Record<string, string>>({});
  const err = { ...local, ...form.errors } as Record<string, string>;
  const set = <K extends keyof typeof d>(k: K, v: (typeof d)[K]) => form.setData((prev) => ({ ...prev, [k]: v }));
  const prk = o.budgetCodes.find((b) => b.id === d.budget_code_id);
  const rab = d.rab_items.reduce((a, r) => a + (Number(r.qty) || 0) * (Number(r.price) || 0), 0);
  const locked = !!p?.has_contract;

  const switchMethod = (m: Method) => form.setData({ ...d, method: m, workflow: routed(o.templates[m], m === 'SPP' ? d.proc_method : ''), documents: [...initDocs(m), ...d.documents.filter((x) => !x.required)], proc_method: m === 'SPP' ? d.proc_method : '', pks_id: '', bak_number: '' });

  function validate(s: number): Record<string, string> {
    const e: Record<string, string> = {};
    if (s === 1) {
      if (!d.title.trim()) e.title = 'Judul pengadaan wajib diisi.';
      if (!d.proc_type) e.proc_type = 'Pilih jenis pengadaan.';
      if (d.method === 'SPP' && !d.spp_number.trim()) e.spp_number = 'Nomor SPP wajib diisi.';
      if (d.method === 'SPP' && !d.spp_date) e.spp_date = 'Tanggal SPP wajib diisi.';
      if (d.method === 'SPP' && !d.proc_method) e.proc_method = 'Pilih metode pengadaan.';
      if (!d.pic_pengaju_id) e.pic_pengaju_id = 'Pilih User Pengaju.';
      if (!d.candidate_vendor_ids.length) e.candidate_vendor_ids = 'Isi minimal satu calon vendor/mitra.';
    }
    if (s === 2) {
      if (!d.customer_contract_ids.length) e.customer_contract_ids = 'Pilih minimal satu perjanjian pelanggan (BAKB, MoM Kesepakatan Harga, atau Kontrak Pelanggan).';
      if (d.method === 'Kemitraan' && !d.pks_id) e.pks_id = 'Kemitraan wajib merujuk PKS.';
      if (d.method === 'Kemitraan' && !d.bak_number.trim()) e.bak_number = 'Nomor BAK wajib diisi.';
    }
    if (s === 3) {
      if (!d.budget_code_id) e.budget_code_id = 'Pilih PRK.';
      if (d.rab_items.some((r) => !r.description.trim() || !r.qty || !r.price || !r.unit.trim())) e.rab_items = 'Lengkapi deskripsi, jumlah, satuan dan harga setiap baris RAB.';
    }
    if (s === 5 && !locked && !d.workflow.some((w) => w.enabled)) e.workflow = 'Minimal satu tahap aktif.';
    return e;
  }
  const next = () => { const e = validate(step); setLocal(e); if (!Object.keys(e).length) setStep(Math.min(6, step + 1)); };
  const submit = (draft: boolean) => {
    if (!draft) { for (const s of [1, 2, 3, 5]) { const e = validate(s); if (Object.keys(e).length) { setLocal(e); setStep(s); return; } } }
    else if (!d.title.trim() || (d.method === 'SPP' && !d.spp_number.trim())) { setLocal({ title: d.title.trim() ? '' : 'Judul wajib diisi.', spp_number: 'Nomor SPP wajib diisi, termasuk untuk draft.' }); setStep(1); return; }
    setLocal({});
    form.transform((x) => ({ ...x, draft }));
    const opts = { preserveScroll: true, onError: (errors: Record<string, string>) => { const first = Object.keys(errors)[0]?.split('.')[0]; if (first && STEP_OF[first]) setStep(STEP_OF[first]); } };
    if (edit) form.put(`/pengadaan/${p!.code}`, opts); else form.post('/pengadaan', opts);
  };
  const stepHasErr = (s: number) => Object.keys(err).some((k) => err[k] && STEP_OF[k.split('.')[0]] === s);

  return (
    <>
      <Head title={edit ? `Edit ${p!.code}` : 'Tambah Pengadaan'} />
      <PageHeader crumbs={[['Dashboard', '/'], ['Daftar Pengadaan', '/pengadaan'], [edit ? `Edit ${String(p!.spp_number || p!.code)}` : 'Tambah']]} title={edit ? 'Edit pengadaan' : 'Tambah pengadaan'}
        desc="ID monitoring dibuat otomatis. Nomor SPP disalin dari aplikasi pengadaan dan wajib diisi sejak awal."
        actions={<><Link href={edit ? `/pengadaan/${p!.code}` : '/pengadaan'}><Button>Batal</Button></Link>{(!edit || p?.is_draft) && <Button icon={Save} onClick={() => submit(true)} disabled={form.processing}>Simpan Draft</Button>}</>} />
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-[230px_1fr]">
        <div className="space-y-3 lg:sticky lg:top-0 lg:self-start">
          <Card className="p-2"><ol>{STEPS.map((s) => (
            <li key={s.key}><button onClick={() => setStep(s.key)} aria-current={step === s.key ? 'step' : undefined} className={cx('flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13.5px] font-semibold', step === s.key ? 'bg-brandsoft text-brand' : 'text-ink/80 hover:bg-sunken')}>
              <span className={cx('flex h-6 w-6 items-center justify-center rounded-full text-[12px]', stepHasErr(s.key) ? 'bg-bad text-white' : step > s.key ? 'bg-ok text-white' : step === s.key ? 'bg-brand text-white' : 'bg-sunken text-mute')}>{stepHasErr(s.key) ? '!' : step > s.key ? <Check size={13} /> : s.key}</span>{s.label}</button></li>
          ))}</ol></Card>
          <Card className="p-4 text-[13px]"><p className="text-mute">ID monitoring</p><p className="num text-[16px] font-extrabold text-brand">{nextCode}</p><p className="mt-2 text-mute">Total RAB</p><p className="num font-bold">{rp(rab)}</p></Card>
        </div>

        <Card>
          <div className="border-b border-line px-6 py-4"><p className="text-[12px] font-semibold text-mute">Langkah {step} dari 6</p><h2 className="text-[18px] font-bold">{STEPS[step - 1].label}</h2></div>
          <div className="px-6 py-5">
            {step === 1 && (
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Field label="Skema" required className="md:col-span-2"><Segmented value={d.method} onChange={(v) => !edit && switchMethod(v as Method)} options={[{ value: 'SPP', label: 'SPP' }, { value: 'Kemitraan', label: 'Kemitraan' }]} /></Field>
                <Field label="Judul pengadaan" required error={err.title} className="md:col-span-2"><Input value={d.title} invalid={!!err.title} onChange={(e) => set('title', e.target.value)} placeholder="Contoh: Pengembangan Aplikasi Customer Portal" /></Field>
                <Field label={d.method === 'SPP' ? 'Nomor SPP' : 'Nomor pengajuan'} required={d.method === 'SPP'} error={err.spp_number}><Input value={d.spp_number} invalid={!!err.spp_number} onChange={(e) => set('spp_number', e.target.value)} placeholder={d.method === 'SPP' ? 'Contoh: SPP/2026/BJB/000024' : 'Contoh: PGJ/2026/BJB/000027'} /></Field>
                <Field label={d.method === 'SPP' ? 'Tanggal SPP' : 'Tanggal pengajuan'} required={d.method === 'SPP'} error={err.spp_date}><Input type="date" value={d.spp_date} onChange={(e) => set('spp_date', e.target.value)} /></Field>
                <Field label="Jenis pengadaan" required error={err.proc_type}><Select value={d.proc_type} invalid={!!err.proc_type} onChange={(e) => set('proc_type', e.target.value)}><option value="">Pilih jenis</option>{o.procTypes.map((x) => <option key={x}>{x}</option>)}</Select></Field>
                {d.method === 'SPP' && <Field label="Metode pengadaan" required error={err.proc_method}><Select value={d.proc_method} invalid={!!err.proc_method} onChange={(e) => form.setData({ ...d, proc_method: e.target.value, workflow: locked ? d.workflow : routed(d.workflow, e.target.value) })}><option value="">Pilih metode</option>{o.procMethods.map((x) => <option key={x}>{x}</option>)}</Select></Field>}
                <Field label="Prioritas" required><Segmented value={d.priority} onChange={(v) => set('priority', v as 'Normal' | 'Urgent')} options={[{ value: 'Normal', label: 'Normal' }, { value: 'Urgent', label: 'Urgent' }]} /></Field>
                <Field label="Sub bidang"><Select value={d.sub_bidang} onChange={(e) => set('sub_bidang', e.target.value)}><option>PLN 1</option><option>PLN 2</option></Select></Field>
                <Field label="Tanggal registrasi" required error={err.registered_at}><Input type="date" value={d.registered_at} onChange={(e) => set('registered_at', e.target.value)} /></Field>
                <Field label="User Pengaju" required error={err.pic_pengaju_id} hint="Daftar diambil dari Master & Konfigurasi, tab Organisasi & PIC."><PicSelect pics={o.pics} roles={['User Pengaju', 'Solution Architect']} value={d.pic_pengaju_id} onChange={(v) => set('pic_pengaju_id', v)} placeholder="Pilih User Pengaju" /></Field>
                <Field label={d.method === 'Kemitraan' ? 'Calon mitra' : 'Calon vendor'} required error={err.candidate_vendor_ids} className="md:col-span-2" hint="Pemenang dipilih dari daftar ini saat rilis SP2K/PO dan otomatis menjadi Pihak Kedua di BA.">
                  <MultiSelect options={o.vendors.filter((v) => d.method !== 'Kemitraan' || v.type === 'Mitra').map((v) => ({ value: String(v.id), label: v.name, sub: v.type }))} value={d.candidate_vendor_ids.map(String)} onChange={(v) => set('candidate_vendor_ids', v.map(Number))} placeholder="Cari dan pilih calon" invalid={!!err.candidate_vendor_ids} />
                </Field>
                <Field label="Ringkasan kebutuhan" className="md:col-span-2"><Textarea value={d.summary} onChange={(e) => set('summary', e.target.value)} /></Field>
              </div>
            )}

            {step === 2 && (
              <div className="space-y-5">
                <div className={cx('rounded-lg border px-3 py-2.5 text-[13px]', err.customer_contract_ids ? 'border-bad/40 bg-badsoft text-bad' : 'border-info/30 bg-infosoft')}><b>Perjanjian pelanggan wajib:</b> pilih BAKB, MoM Kesepakatan Harga, atau Kontrak Pelanggan yang menjadi dasar pengadaan ini. Daftarnya dikelola di menu Perjanjian Pelanggan.</div>
                <Field label="Perjanjian pelanggan" required error={err.customer_contract_ids}><MultiSelect options={o.customerContracts.map((c) => ({ value: String(c.id), label: `${c.kind} · ${c.number}`, sub: `${c.title} · ${c.customer ?? ''}` }))} value={d.customer_contract_ids.map(String)} onChange={(v) => set('customer_contract_ids', v.map(Number))} placeholder="Cari jenis, nomor, judul atau pelanggan" invalid={!!err.customer_contract_ids} /></Field>
                {d.method === 'Kemitraan' && (
                  <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Field label="PKS" required error={err.pks_id} className="md:col-span-3"><Select value={d.pks_id} invalid={!!err.pks_id} onChange={(e) => { const id = Number(e.target.value) || ''; const pk = o.pks.find((x) => x.id === id); form.setData({ ...d, pks_id: id, candidate_vendor_ids: pk && !d.candidate_vendor_ids.includes(pk.partner_id) ? [...d.candidate_vendor_ids, pk.partner_id] : d.candidate_vendor_ids }); }}><option value="">Pilih PKS</option>{o.pks.map((k) => <option key={k.id} value={k.id}>{k.number} · {k.partner} · {k.label}</option>)}</Select></Field>
                    <Field label="Nomor BAK" required error={err.bak_number}><Input value={d.bak_number} invalid={!!err.bak_number} onChange={(e) => set('bak_number', e.target.value)} /></Field>
                    <Field label="Tanggal BAK"><Input type="date" value={d.bak_date} onChange={(e) => set('bak_date', e.target.value)} /></Field>
                  </div>
                )}
                <div className="rounded-xl border border-line p-4">
                  <p className="text-[14px] font-bold">Dokumen referensi pendukung</p><p className="text-[12.5px] text-mute">Opsional.</p>
                  <div className="mt-3 space-y-3">{d.ref_docs.map((r, i) => { const upd = (patch: Partial<RefDoc>) => set('ref_docs', d.ref_docs.map((x, j) => (j === i ? { ...x, ...patch } : x))); return (
                    <div key={i} className="grid grid-cols-1 gap-2 md:grid-cols-[170px_1fr_170px]">
                      <span className="self-center text-[13px] font-semibold">{r.doc_type}</span>
                      <Input value={r.number} onChange={(e) => upd({ number: e.target.value })} placeholder="Nomor dokumen" aria-label={`Nomor ${r.doc_type}`} />
                      <Input type="date" value={r.date} onChange={(e) => upd({ date: e.target.value })} aria-label={`Tanggal ${r.doc_type}`} />
                      <div className="md:col-span-3"><LinkField value={r.link} onChange={(v) => upd({ link: v })} /></div>
                    </div>); })}</div>
                </div>
              </div>
            )}

            {step === 3 && (
              <div className="space-y-5">
                <Field label="PRK" required error={err.budget_code_id} hint="Satu pengadaan memakai satu PRK. Pagu dan sisa dikelola di menu RKAP.">
                  <Select value={d.budget_code_id} invalid={!!err.budget_code_id} onChange={(e) => set('budget_code_id', Number(e.target.value) || '')}>
                    <option value="">Pilih PRK</option>
                    {o.budgetCodes.filter((b) => b.active || b.id === d.budget_code_id).map((b) => <option key={b.id} value={b.id}>{b.code} · {b.program}</option>)}
                  </Select>
                </Field>
                {prk && (() => {
                  const sisa = prk.budget_amount - prk.used + (edit ? Number(p?.rab_value || 0) : 0);
                  const after = sisa - rab;
                  return (
                    <div className={cx('grid grid-cols-2 gap-3 rounded-xl border p-4 text-[12.5px] sm:grid-cols-4', after < 0 ? 'border-warn/40 bg-warnsoft/40' : 'border-line')}>
                      <div className="col-span-2 sm:col-span-4"><p className="num font-bold">{prk.code} · {prk.program}</p><p className="text-mute">COA {prk.coa} · {prk.cost_center} · {prk.klasifikasi}</p></div>
                      <div><span className="text-mute">Pagu RKAP</span><div className="num font-bold">{rp(prk.budget_amount)}</div></div>
                      <div><span className="text-mute">Sisa sebelum pengadaan ini</span><div className="num font-bold">{rp(sisa)}</div></div>
                      <div><span className="text-mute">RAB pengadaan ini</span><div className="num font-bold">{rp(rab)}</div></div>
                      <div><span className="text-mute">Sisa setelah</span><div className={cx('num font-bold', after < 0 && 'text-warn')}>{rp(after)}</div></div>
                      {after < 0 && <p className="col-span-2 text-warn sm:col-span-4">RAB melebihi sisa pagu PRK sebesar {rp(-after)}. Pengadaan tetap dapat disimpan; kondisi ini tampil di menu RKAP.</p>}
                    </div>
                  );
                })()}
                <div className="rounded-xl border border-line">
                  <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <p className="text-[14px] font-bold">Informasi RAB</p>
                    <span className="num text-[12.5px] font-semibold text-mute">{d.rab_items.length} baris · total {rp(rab)}</span>
                  </div>
                  <div className="space-y-3 p-4">
                    {d.rab_items.map((r, k) => {
                      const upd = (patch: Partial<RabRow>) => set('rab_items', d.rab_items.map((x, j) => (j === k ? { ...x, ...patch } : x)));
                      return (
                        <div key={k} className="rounded-lg border border-line bg-sunken/40 p-3">
                          <div className="mb-2 flex items-center gap-2">
                            <span className="num text-[12px] font-bold text-mute">Baris {k + 1}</span>
                            <span className="num ml-auto text-[13px] font-extrabold">{rp((Number(r.qty) || 0) * (Number(r.price) || 0))}</span>
                            {d.rab_items.length > 1 && <button aria-label={`Hapus baris RAB ${k + 1}`} className="text-mute hover:text-bad" onClick={() => set('rab_items', d.rab_items.filter((_, j) => j !== k))}><Trash2 size={14} /></button>}
                          </div>
                          <Textarea rows={2} className="min-h-0" value={r.description} onChange={(e) => upd({ description: e.target.value })} placeholder="Deskripsi, contoh: ARCGIS ENTERPRISE ADVANCED MAINTENANCE - ANNUALLY" aria-label={`Deskripsi RAB ${k + 1}`} />
                          <div className="mt-2 grid grid-cols-2 gap-2 lg:grid-cols-4">
                            <label className="text-[11.5px] font-semibold text-mute">Tipe anggaran
                              <Select className="mt-1 h-9" value={r.budget_type} onChange={(e) => upd({ budget_type: e.target.value as 'OPEX' | 'CAPEX' })} aria-label={`Tipe anggaran ${k + 1}`}><option>OPEX</option><option>CAPEX</option></Select></label>
                            <label className="text-[11.5px] font-semibold text-mute">Jumlah
                              <Input className="num mt-1 h-9 text-right" type="number" min={0} value={r.qty || ''} onChange={(e) => upd({ qty: Number(e.target.value) })} aria-label={`Jumlah ${k + 1}`} /></label>
                            <label className="text-[11.5px] font-semibold text-mute">Satuan
                              <Select className="mt-1 h-9" value={r.unit} onChange={(e) => upd({ unit: e.target.value })} aria-label={`Satuan ${k + 1}`}>{[...new Set([...o.rabUnits, ...(r.unit ? [r.unit] : [])])].map((u) => <option key={u}>{u}</option>)}</Select></label>
                            <label className="text-[11.5px] font-semibold text-mute">Harga satuan
                              <div className="mt-1"><MoneyInput value={r.price} onChange={(n) => upd({ price: n })} /></div></label>
                          </div>
                        </div>
                      );
                    })}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <Button size="sm" icon={Plus} onClick={() => set('rab_items', [...d.rab_items, { description: '', budget_type: 'OPEX', qty: 1, unit: o.rabUnits[0] || 'LOT (SERVICE & MATERIAL)', price: 0 }])}>Tambah baris</Button>
                      <span className="num text-[14px] font-extrabold">Total {rp(rab)}</span>
                    </div>
                  </div>
                  {err.rab_items && <p className="px-4 pb-3 text-[12px] font-medium text-bad">{err.rab_items}</p>}
                </div>
              </div>
            )}

            {step === 4 && (
              <div className="space-y-3">
                <p className="text-[13px] text-mute">{o.requiredDocs[d.method].join(' dan ')} wajib. Dokumen lain dapat ditambahkan sesuai kebutuhan.</p>
                {d.documents.map((r, i) => { const upd = (patch: Partial<DocRow>) => set('documents', d.documents.map((x, j) => (j === i ? { ...x, ...patch } : x))); return (
                  <div key={i} className="rounded-xl border border-line p-3">
                    <div className="mb-2 flex flex-wrap items-center gap-3"><b className="text-[13.5px]">{r.doc_type}</b>{r.required && <span className="text-[11.5px] font-semibold text-bad">Wajib</span>}{!NO_NUMBER.includes(r.doc_type) && <Checkbox checked={r.available} onChange={(v) => upd({ available: v })} label="Tersedia" />}{NO_NUMBER.includes(r.doc_type) && <span className="text-[11.5px] text-mute">Tersedia bila tautan diisi</span>}{!r.required && <button className="ml-auto text-mute hover:text-bad" aria-label="Hapus dokumen" onClick={() => set('documents', d.documents.filter((_, j) => j !== i))}><Trash2 size={14} /></button>}</div>
<div className="grid grid-cols-1 gap-2 md:grid-cols-3">{!NO_NUMBER.includes(r.doc_type) && <Input value={r.number} onChange={(e) => upd({ number: e.target.value })} placeholder="Nomor dokumen" />}<Input type="date" value={r.date} onChange={(e) => upd({ date: e.target.value })} aria-label={`Tanggal ${r.doc_type}`} /><Input value={r.note} onChange={(e) => upd({ note: e.target.value })} placeholder={r.doc_type.startsWith('BCR') ? 'Persentase BCR' : 'Catatan'} aria-label={r.doc_type.startsWith('BCR') ? 'Persentase BCR' : `Catatan ${r.doc_type}`} /></div>
                    <div className="mt-2"><LinkField value={r.link} onChange={(v) => upd({ link: v })} /></div>
                  </div>); })}
                <div className="flex items-center gap-2"><Select className="w-[260px]" value="" onChange={(e) => e.target.value && set('documents', [...d.documents, { doc_type: e.target.value, number: '', title: e.target.value, date: '', required: false, available: false, link: '', note: '' }])}><option value="">+ Tambah dokumen opsional</option>{o.docTypes.filter((t) => !o.requiredDocs[d.method].includes(t) && t !== 'Surat Penugasan').map((t) => <option key={t}>{t}</option>)}</Select></div>
              </div>
            )}

            {step === 5 && (locked ? <p className="text-[13px] text-mute">SP2K/PO sudah rilis; alur Proses Pengadaan terkunci dan hanya dapat dibuka melalui amandemen.</p> : (
              <div>
                {d.method === 'SPP' && (
                  <div className="mb-3 rounded-lg bg-infosoft px-3 py-2 text-[12.5px]">
                    Setelah Anggaran, pengadaan masuk ke <b>Manager Pengadaan</b>.{' '}
                    {d.proc_method === 'Pengadaan Langsung'
                      ? <>Metode <b>Pengadaan Langsung</b> langsung dilanjutkan ke Pelaksana Pengadaan (SPV Lakdan → Staff Lakdan).</>
                      : <>Metode <b>{d.proc_method || 'selain Pengadaan Langsung'}</b> melewati Perencana Pengadaan (SPV Rendan → Staff Rendan) lebih dulu, lalu Pelaksana Pengadaan.</>}{' '}
                    Tahap Direktur bersifat opsional.
                  </div>
                )}
                <div className="space-y-3">{[...new Set(d.workflow.map((s) => s.group || s.name))].map((g) => {
                  const rows = d.workflow.map((s, i) => ({ s, i })).filter(({ s }) => (s.group || s.name) === g);
                  const rendan = g === 'Perencana Pengadaan';
                  const expectOn = d.proc_method !== 'Pengadaan Langsung';
                  const mismatch = rendan && rows.some(({ s }) => s.enabled !== expectOn);
                  return (
                    <div key={g} className="rounded-xl border border-line">
                      <p className="border-b border-line px-4 py-2 text-[13px] font-bold">{g}</p>
                      <table className="w-full text-[13px]"><tbody>{rows.map(({ s, i }) => {
                        const required = s.key === 'pengadaan_mgr';
                        return (
                          <tr key={s.key} className="border-t border-line first:border-t-0">
                            <td className="px-4 py-2 font-semibold">{s.name}{s.optional && <span className="ml-1 text-[11.5px] font-medium text-mute">(opsional)</span>}{required && <span className="ml-1 text-[11.5px] font-medium text-mute">(wajib)</span>}</td>
                            <td className="w-[150px] px-3 py-2">{required ? <span className="text-[12px] font-semibold text-ok">Selalu dilalui</span> : <Switch checked={s.enabled} onChange={(v) => set('workflow', d.workflow.map((x, j) => (j === i ? { ...x, enabled: v } : x)))} label={s.enabled ? 'Aktif' : 'Nonaktif'} />}</td>
                            <td className="w-[300px] px-3 py-2"><PicSelect pics={o.pics} roles={[s.name]} value={s.pic_id} onChange={(v) => set('workflow', d.workflow.map((x, j) => (j === i ? { ...x, pic_id: v } : x)))} placeholder="PIC default (opsional)" /></td>
                          </tr>
                        );
                      })}</tbody></table>
                      {mismatch && <p className="border-t border-line px-4 py-2 text-[12px] text-warn">{expectOn ? 'Metode ini seharusnya melewati Perencana Pengadaan.' : 'Pengadaan Langsung biasanya tidak melewati Perencana Pengadaan.'} Ubah toggle bila memang berbeda.</p>}
                    </div>
                  );
                })}</div>
                {err.workflow && <p className="mt-2 text-[12px] font-medium text-bad">{err.workflow}</p>}
              </div>
            ))}

            {step === 6 && (
              <div className="space-y-4">
                <ReviewBlock title="Identitas" onEdit={() => setStep(1)} items={[['Skema', d.method], ['Judul', d.title], [d.method === 'SPP' ? 'Nomor SPP' : 'Nomor pengajuan', d.spp_number || '–'], ['Tanggal SPP', fmtDate(d.spp_date)], ['Metode', d.proc_method || '–'], ['Prioritas', d.priority], ['User Pengaju', o.pics.find((x) => x.id === d.pic_pengaju_id)?.name || '–'], ['Calon vendor', o.vendors.filter((v) => d.candidate_vendor_ids.includes(v.id)).map((v) => v.name).join(', ') || '–']]} />
                <ReviewBlock title="Referensi" onEdit={() => setStep(2)} items={[['Perjanjian pelanggan', o.customerContracts.filter((c) => d.customer_contract_ids.includes(c.id)).map((c) => `${c.kind} ${c.number}`).join(', ') || '–'], ...(d.method === 'Kemitraan' ? [['PKS', o.pks.find((k) => k.id === d.pks_id)?.number || '–'], ['BAK', d.bak_number || '–']] as [string, string][] : [])]} />
                <ReviewBlock title="RAB & PRK" onEdit={() => setStep(3)} items={[['PRK', prk ? `${prk.code} · ${prk.program}` : '–'], ['Baris RAB', String(d.rab_items.length)], ['Total RAB', rp(rab)]]} />
                <ReviewBlock title="Dokumen" onEdit={() => setStep(4)} items={[['Wajib tersedia', `${d.documents.filter((x) => x.required && (x.available || x.link)).length} dari ${d.documents.filter((x) => x.required).length}`], ['Opsional', String(d.documents.filter((x) => !x.required).length)]]} />
                <ReviewBlock title="Workflow" onEdit={() => setStep(5)} items={[['Alur', d.workflow.filter((s) => s.enabled).map((s) => s.name).join(' → ')]]} />
              </div>
            )}
          </div>
          <div className="flex items-center justify-between gap-2 border-t border-line px-6 py-4">
            <Button icon={ChevronLeft} disabled={step === 1} onClick={() => setStep(step - 1)}>Sebelumnya</Button>
            {step < 6 ? <Button variant="primary" iconRight={ChevronRight} onClick={next}>Lanjut</Button> : <Button variant="primary" icon={Save} onClick={() => submit(false)} disabled={form.processing}>{edit ? 'Simpan Perubahan' : 'Simpan Pengadaan'}</Button>}
          </div>
        </Card>
      </div>
    </>
  );
}

function ReviewBlock({ title, items, onEdit }: { title: string; items: [string, React.ReactNode][]; onEdit: () => void }) {
  return (
    <div className="rounded-xl border border-line p-4">
      <div className="mb-2 flex items-center justify-between"><b className="text-[14px]">{title}</b><Button size="xs" variant="ghost" onClick={onEdit}>Ubah</Button></div>
      <KV cols={3} items={items} />
    </div>
  );
}
