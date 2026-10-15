import React, { useEffect, useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { Button, Checkbox, Field, Input, LinkField, Modal, MoneyInput, Select, Textarea, cx } from '@/Components/ui';
import { DeadlinePill } from '@/Components/common';
import { fmtDate, rp } from '@/lib/format';
import type { PageProps, StageDef } from '@/types';

interface Vendor { id: number; name: string }

/** Rilis SP2K/PO: kontrak + tahapan pembayaran (syarat penyerahan, rencana selesai, persentase). */
export function ReleaseContractModal({ open, onClose, code, method, title, candidates, rab, contractTypes, runningStage }: { open: boolean; onClose: () => void; code: string; method: 'SPP Pusat' | 'SPP Kemitraan'; title: string; candidates: Vendor[]; rab: number; contractTypes: string[]; runningStage?: string | null }) {
  const { app } = usePage<PageProps>().props;
  const form = useForm({
    kind: method === 'SPP Kemitraan' ? 'PO' : 'SP2K', number: '', title, vendor_id: (candidates.length === 1 ? candidates[0].id : '') as number | '',
    date: app.today, kickoff_date: '', value_excl: 0, value_incl: 0, scope: '', link: '', close_stage: true,
    terms: [{ name: '', planned_end: '', percent: 100 }] as { name: string; planned_end: string; percent: number }[],
  });
  useEffect(() => { if (open) { form.reset(); form.clearErrors(); } }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  const d = form.data; const e = form.errors as Record<string, string>;
  const sum = d.terms.reduce((a, t) => a + (Number(t.percent) || 0), 0);
  const lastEnd = d.terms.map((t) => t.planned_end).filter(Boolean).sort().slice(-1)[0];
  const setTerm = (k: number, patch: Partial<(typeof d.terms)[number]>) => form.setData('terms', d.terms.map((t, j) => (j === k ? { ...t, ...patch } : t)));
  const termErr = Object.entries(e).find(([k]) => k.startsWith('terms'))?.[1];
  return (
    <Modal open={open} onClose={onClose} width="max-w-4xl" title={`Rilis ${method === 'SPP Pusat' ? 'SP2K' : 'PO'}`}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/pengadaan/${code}/kontrak`, { preserveScroll: true, onSuccess: onClose })}>Simpan & rilis</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Jenis dokumen kontrak" required><Select value={d.kind} onChange={(x) => form.setData('kind', x.target.value)}>{contractTypes.map((t) => <option key={t}>{t}</option>)}</Select></Field>
        <Field label="Nomor kontrak" required error={e.number}><Input value={d.number} invalid={!!e.number} onChange={(x) => form.setData('number', x.target.value)} placeholder={d.kind === 'PO' ? 'PO/ICP/2026/45xxxxx' : 'SP2K/ICP/2026/0xxx'} /></Field>
        <Field label="Judul pekerjaan" required className="sm:col-span-2"><Input value={d.title} onChange={(x) => form.setData('title', x.target.value)} /></Field>
        <Field label={method === 'SPP Kemitraan' ? 'Mitra pemenang' : 'Vendor pemenang'} required error={e.vendor_id} hint="Dipilih dari calon vendor; menjadi Pihak Kedua di BA."><Select value={d.vendor_id} invalid={!!e.vendor_id} onChange={(x) => form.setData('vendor_id', Number(x.target.value) || '')}><option value="">Pilih pemenang</option>{candidates.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</Select></Field>
        <Field label="Tanggal kontrak" required error={e.date}><Input type="date" value={d.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Nilai sebelum PPN" required error={e.value_excl}><MoneyInput value={d.value_excl} onChange={(n) => form.setData('value_excl', n)} invalid={!!e.value_excl} /></Field>
        <Field label="Nilai setelah PPN" required error={e.value_incl} hint={rab ? `RAB ${rp(rab)}` : undefined}><MoneyInput value={d.value_incl} onChange={(n) => form.setData('value_incl', n)} invalid={!!e.value_incl} /></Field>
        <Field label="Tanggal mulai pekerjaan (kick off)" required error={e.kickoff_date}><Input type="date" value={d.kickoff_date} invalid={!!e.kickoff_date} onChange={(x) => form.setData('kickoff_date', x.target.value)} /></Field>
        <Field label={`Tautan dokumen ${d.kind}`} error={e.link}><LinkField value={d.link} onChange={(v) => form.setData('link', v)} /></Field>
        <Field label="Ruang lingkup" className="sm:col-span-2"><Textarea value={d.scope} onChange={(x) => form.setData('scope', x.target.value)} /></Field>
      </div>
      <div className="mt-5 rounded-xl border border-line">
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3"><p className="text-[14px] font-bold">Tahapan pembayaran <span className="text-bad">*</span></p><span className={cx('num text-[12.5px] font-semibold', sum !== 100 ? 'text-warn' : 'text-mute')}>Total {sum}% · {rp(Math.round((d.value_incl * sum) / 100))}</span></div>
        <div className="overflow-x-auto"><table className="w-full min-w-[820px] text-[12.5px]">
          <thead><tr className="bg-sunken/60 text-left text-[11.5px] text-mute">{['Tahap', 'Syarat Penyerahan Pekerjaan', 'Rencana Selesai Pekerjaan', 'Persentase', 'Nilai Bayar', ''].map((h, k) => <th key={k} className={cx('px-3 py-2 font-semibold', h === 'Nilai Bayar' && 'text-right')}>{h}</th>)}</tr></thead>
          <tbody>{d.terms.map((t, k) => (
            <tr key={k} className="border-t border-line align-top">
              <td className="num px-3 py-3 font-bold">{k + 1}</td>
              <td className="min-w-[260px] px-3 py-2"><Textarea rows={2} className="min-h-0" value={t.name} onChange={(x) => setTerm(k, { name: x.target.value })} placeholder="Contoh: Dokumen analisis & desain disetujui (BAPS)" aria-label={`Syarat penyerahan tahap ${k + 1}`} /></td>
              <td className="w-[190px] px-3 py-2"><Input type="date" className="h-8" value={t.planned_end} onChange={(x) => setTerm(k, { planned_end: x.target.value })} aria-label={`Rencana selesai tahap ${k + 1}`} />{t.planned_end && <div className="mt-1.5"><DeadlinePill date={t.planned_end} /></div>}</td>
              <td className="w-[110px] px-3 py-2"><div className="relative"><Input className="num h-8 pr-7 text-right" type="number" min={0} max={100} value={t.percent || ''} onChange={(x) => setTerm(k, { percent: Number(x.target.value) })} aria-label={`Persentase tahap ${k + 1}`} /><span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[12px] text-mute">%</span></div></td>
              <td className="num w-[160px] px-3 py-3 text-right font-semibold">{rp(Math.round((d.value_incl * (t.percent || 0)) / 100))}</td>
              <td className="w-[36px] px-2 py-3">{d.terms.length > 1 && <button aria-label="Hapus tahap" className="text-mute hover:text-bad" onClick={() => form.setData('terms', d.terms.filter((_, j) => j !== k))}><Trash2 size={14} /></button>}</td>
            </tr>))}</tbody>
        </table></div>
        <div className="flex flex-wrap items-center gap-3 px-4 py-3"><Button size="xs" icon={Plus} onClick={() => form.setData('terms', [...d.terms, { name: '', planned_end: '', percent: Math.max(0, 100 - sum) }])}>Tambah tahap</Button>{lastEnd && <span className="flex items-center gap-1.5 text-[12.5px] text-mute">Batas pekerjaan {fmtDate(lastEnd)} <DeadlinePill date={lastEnd} /></span>}</div>
        {termErr && <p className="px-4 pb-3 text-[12px] font-medium text-bad">{termErr}</p>}
      </div>
      {runningStage && <div className="mt-4 rounded-lg border border-warn/30 bg-warnsoft p-3"><Checkbox checked={d.close_stage} onChange={(v) => form.setData('close_stage', v)} label={`Tutup tahap ${runningStage} dengan aktivitas ${method === 'SPP Pusat' ? 'SP2K Rilis' : 'PO Rilis'}`} /></div>}
    </Modal>
  );
}

/** Koreksi data non-finansial kontrak. */
export function ContractEditModal({ open, onClose, contract }: { open: boolean; onClose: () => void; contract: { id: number; number: string; title: string; date: string | null; scope: string | null; link: string | null } }) {
  const form = useForm({ number: contract.number, title: contract.title, date: contract.date || '', scope: contract.scope || '', link: contract.link || '' });
  useEffect(() => { if (open) form.setData({ number: contract.number, title: contract.title, date: contract.date || '', scope: contract.scope || '', link: contract.link || '' }); }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  const e = form.errors;
  return (
    <Modal open={open} onClose={onClose} width="max-w-2xl" title="Koreksi data kontrak" desc="Nilai, tanggal mulai dan tahapan pembayaran hanya dapat diubah melalui amandemen."
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.put(`/kontrak/${contract.id}`, { preserveScroll: true, onSuccess: onClose })}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Nomor kontrak" required error={e.number}><Input value={form.data.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Tanggal kontrak" required><Input type="date" value={form.data.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Judul pekerjaan" required className="sm:col-span-2"><Input value={form.data.title} onChange={(x) => form.setData('title', x.target.value)} /></Field>
        <Field label="Ruang lingkup" className="sm:col-span-2"><Textarea value={form.data.scope} onChange={(x) => form.setData('scope', x.target.value)} /></Field>
        <Field label="Tautan dokumen" className="sm:col-span-2" error={e.link}><LinkField value={form.data.link} onChange={(v) => form.setData('link', v)} /></Field>
      </div>
    </Modal>
  );
}

const CHANGE_TYPES = ['Nilai', 'Jangka Waktu', 'Ruang Lingkup', 'Termin', 'Lainnya'];
function Chips({ value, onChange, options }: { value: string[]; onChange: (v: string[]) => void; options: { key: string; label: string }[] }) {
  return <div className="flex flex-wrap gap-2">{options.map((o) => { const on = value.includes(o.key); return <button type="button" key={o.key} aria-pressed={on} onClick={() => onChange(on ? value.filter((x) => x !== o.key) : [...value, o.key])} className={cx('rounded-lg border px-3 py-1.5 text-[12.5px] font-semibold', on ? 'border-brand bg-brandsoft text-brand' : 'border-line text-mute hover:text-ink')}>{o.label}</button>; })}</div>;
}

/** Ajukan amandemen SP2K/PO: membuka alur approval khusus amandemen di Proses Pengadaan. */
export function AmendmentRequestModal({ open, onClose, code, kind, number, stages }: { open: boolean; onClose: () => void; code: string; kind: string; number: string; stages: StageDef[] }) {
  const { app } = usePage<PageProps>().props;
  const enabled = stages.filter((s) => s.enabled);
  const form = useForm({ reason: '', change_types: [] as string[], proposal: '', stages: [enabled[0]?.key, enabled[enabled.length - 1]?.key].filter(Boolean) as string[], date: app.today });
  useEffect(() => { if (open) { form.reset(); form.clearErrors(); } }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  const e = form.errors as Record<string, string>;
  // Perubahan nilai wajib melewati seluruh jenjang Anggaran.
  const budgetKeys = enabled.filter((s) => s.group === 'Anggaran' || s.key.startsWith('anggaran')).map((s) => s.key);
  const setTypes = (v: string[]) => form.setData((x) => ({ ...x, change_types: v, stages: v.includes('Nilai') ? enabled.map((s) => s.key).filter((k) => x.stages.includes(k) || budgetKeys.includes(k)) : x.stages }));
  return (
    <Modal open={open} onClose={onClose} width="max-w-2xl" title={`Ajukan amandemen ${kind}`} desc={`${kind} ${number}. Proses Pengadaan hanya dapat dibuka kembali melalui amandemen; histori sebelumnya tetap utuh.`}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/pengadaan/${code}/amandemen`, { preserveScroll: true, onSuccess: onClose })}>Ajukan amandemen</Button></>}>
      <div className="grid grid-cols-1 gap-4">
        <Field label="Alasan amandemen" required error={e.reason}><Textarea value={form.data.reason} onChange={(x) => form.setData('reason', x.target.value)} /></Field>
        <Field label="Jenis perubahan" required error={e.change_types}><Chips value={form.data.change_types} onChange={setTypes} options={CHANGE_TYPES.map((t) => ({ key: t, label: t }))} /></Field>
        <Field label="Usulan perubahan" hint="Angka final dicatat saat amandemen rilis."><Textarea value={form.data.proposal} onChange={(x) => form.setData('proposal', x.target.value)} /></Field>
        <Field label="Tahap approval amandemen" required error={e.stages} hint="Seluruh jenjang Anggaran otomatis ditambahkan bila nilai berubah."><Chips value={form.data.stages} onChange={(v) => form.setData('stages', v)} options={enabled.map((s) => ({ key: s.key, label: s.name }))} /></Field>
        <Field label="Tanggal pengajuan" required><Input type="date" value={form.data.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
      </div>
    </Modal>
  );
}

/** Rilis amandemen: nomor, tanggal dan nilai sebelum–sesudah diterapkan ke kontrak. */
export function AmendmentReleaseModal({ open, onClose, code, amendment, contract }: { open: boolean; onClose: () => void; code: string; amendment: { id: number; seq: number; reason: string; proposal: string | null; change_types: string[] } | null; contract: { kind: string; number: string; value_excl: number; value_incl: number; end_date: string | null; scope: string | null } | null }) {
  const { app } = usePage<PageProps>().props;
  const form = useForm({ number: '', date: app.today, change_types: [] as string[], description: '', value_excl: 0, value_incl: 0, end_date: '', scope: '', link: '' });
  useEffect(() => { if (open && amendment && contract) form.setData({ number: '', date: app.today, change_types: amendment.change_types, description: amendment.reason, value_excl: contract.value_excl, value_incl: contract.value_incl, end_date: contract.end_date || '', scope: contract.scope || '', link: '' }); }, [open]); // eslint-disable-line react-hooks/exhaustive-deps
  if (!amendment || !contract) return null;
  const d = form.data; const e = form.errors as Record<string, string>; const has = (t: string) => d.change_types.includes(t);
  return (
    <Modal open={open} onClose={onClose} width="max-w-2xl" title={`Rilis Amandemen ${amendment.seq}`} desc={`${contract.kind} ${contract.number}${amendment.proposal ? ` · Usulan: ${amendment.proposal}` : ''}`}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/pengadaan/${code}/amandemen/${amendment.id}/rilis`, { preserveScroll: true, onSuccess: onClose })}>Simpan & terapkan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Nomor amandemen" required error={e.number}><Input value={d.number} invalid={!!e.number} onChange={(x) => form.setData('number', x.target.value)} /></Field>
        <Field label="Tanggal amandemen" required><Input type="date" value={d.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Jenis perubahan" required className="sm:col-span-2"><Chips value={d.change_types} onChange={(v) => form.setData('change_types', v)} options={CHANGE_TYPES.map((t) => ({ key: t, label: t }))} /></Field>
        <Field label="Uraian perubahan" required error={e.description} className="sm:col-span-2"><Textarea value={d.description} onChange={(x) => form.setData('description', x.target.value)} /></Field>
        {has('Nilai') && <><Field label="Nilai baru sebelum PPN" required hint={`Sebelumnya ${rp(contract.value_excl)}`} error={e.value_excl}><MoneyInput value={d.value_excl} onChange={(n) => form.setData('value_excl', n)} /></Field><Field label="Nilai baru setelah PPN" required hint={`Sebelumnya ${rp(contract.value_incl)}`} error={e.value_incl}><MoneyInput value={d.value_incl} onChange={(n) => form.setData('value_incl', n)} /></Field></>}
        {has('Jangka Waktu') && <Field label="Rencana selesai pekerjaan (baru)" required className="sm:col-span-2" hint={`Sebelumnya ${fmtDate(contract.end_date)}. Sesuaikan juga rencana selesai tiap tahap.`} error={e.end_date}><Input type="date" value={d.end_date} onChange={(x) => form.setData('end_date', x.target.value)} /></Field>}
        {has('Ruang Lingkup') && <Field label="Ruang lingkup (baru)" required className="sm:col-span-2" error={e.scope}><Textarea value={d.scope} onChange={(x) => form.setData('scope', x.target.value)} /></Field>}
        <Field label="Tautan dokumen amandemen" className="sm:col-span-2" error={e.link}><LinkField value={d.link} onChange={(v) => form.setData('link', v)} /></Field>
      </div>
    </Modal>
  );
}

export function CancelModal({ open, onClose, code }: { open: boolean; onClose: () => void; code: string }) {
  const [reason, setReason] = useState(''); const { errors } = usePage<PageProps>().props;
  return (
    <Modal open={open} onClose={onClose} title="Batalkan pengadaan" desc="Data tetap tersimpan dan dapat diaktifkan kembali."
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="danger" onClick={() => router.post(`/pengadaan/${code}/batal`, { reason }, { preserveScroll: true, onSuccess: onClose })}>Batalkan pengadaan</Button></>}>
      <Field label="Alasan pembatalan" required error={errors.reason}><Textarea value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
    </Modal>
  );
}
