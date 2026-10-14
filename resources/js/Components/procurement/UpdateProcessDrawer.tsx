import React, { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Save, CheckCircle2, Undo2, ArrowRightLeft, SkipForward, AlertTriangle, RotateCcw } from 'lucide-react';
import { Button, Drawer, Field, Input, LinkField, MoneyInput, Select, Textarea, cx } from '@/Components/ui';
import { PicSelect } from '@/Components/common';
import { fmtDate } from '@/lib/format';
import type { CurrentVisit, PageProps, Pic, StageDef, Term } from '@/types';

export type Mode = 'update' | 'complete' | 'return' | 'move' | 'skip' | 'rev_minor' | 'rev_major';
type Tone = 'brand' | 'warn' | 'bad';
/** Proses Pasca Pengadaan. */
const MODES: { key: Mode; label: string; icon: typeof Save; desc: string; tone?: Tone }[] = [
  { key: 'update', label: 'Simpan Update', icon: Save, desc: 'Perbarui aktivitas, PIC atau catatan di tahap yang sama.' },
  { key: 'complete', label: 'Selesaikan Tahap', icon: CheckCircle2, desc: 'Tutup tahap aktif dan lanjut ke tahap berikutnya.' },
  { key: 'return', label: 'Kembalikan', icon: Undo2, desc: 'Kembalikan ke tahap sebelumnya. Siklus baru dibuat dan durasi dihitung ulang.' },
  { key: 'move', label: 'Pindah Tahap', icon: ArrowRightLeft, desc: 'Lompat maju ke tahap tertentu. Tahap yang dilompati tercatat Dilewati.' },
  { key: 'skip', label: 'Lewati', icon: SkipForward, desc: 'Tandai tahap aktif sebagai Dilewati dan lanjut ke tahap berikutnya.' },
];
/** Proses Pengadaan (approval): urutan tombol Simpan Update, Rev Minor, Rev Mayor, Lanjut Approval. */
const APPROVAL_MODES: { key: Mode; label: string; icon: typeof Save; desc: string; tone?: Tone }[] = [
  { key: 'update', label: 'Simpan Update', icon: Save, desc: 'Perbarui aktivitas, PIC atau catatan di tahap yang sama.' },
  { key: 'rev_minor', label: 'Rev Minor', icon: Undo2, tone: 'warn', desc: 'Kembalikan ke User Pengaju. Setelah diperbaiki, proses langsung kembali ke tahap ini tanpa mengulang approval.' },
  { key: 'rev_major', label: 'Rev Mayor', icon: RotateCcw, tone: 'bad', desc: 'Kembalikan ke User Pengaju dan ulangi seluruh approval dari awal. Siklus approval bertambah satu.' },
  { key: 'complete', label: 'Lanjut Approval', icon: CheckCircle2, desc: 'Tutup tahap aktif dan lanjut ke tahap berikutnya.' },
];
const TONE_ON: Record<Tone, string> = { brand: 'border-brand bg-brandsoft text-brand', warn: 'border-warn bg-warnsoft text-warn', bad: 'border-bad bg-badsoft text-bad' };
const TONE_BTN: Record<Tone, string> = { brand: '', warn: '!bg-warn !text-white hover:!bg-warn/90', bad: '!bg-bad !text-white hover:!bg-bad/90' };

interface Props {
  open: boolean; onClose: () => void; code: string; track: 'pengadaan' | 'pasca'; term?: Term | null; current: CurrentVisit | null;
  stages: StageDef[]; pics: Pic[]; activities: string[]; finalLabel: string; initialMode?: Mode; grTypes?: string[];
}

export default function UpdateProcessDrawer({ open, onClose, code, track, term, current: v, stages, pics, activities, finalLabel, initialMode = 'update', grTypes = ['BAST', 'BAPS', 'BA Rekon', 'BAUK'] }: Props) {
  const { app, errors } = usePage<PageProps>().props;
  const list = stages.filter((s) => s.enabled);
  const idx = v ? list.findIndex((s) => s.key === v.stage_key) : -1;
  // Revisi Minor: setelah pengaju selesai, proses kembali ke tahap yang mengembalikan.
  const next = v?.resume_key ? list.find((s) => s.key === v.resume_key) : idx >= 0 ? list[idx + 1] : undefined;
  const earlier = idx > 0 ? list.slice(0, idx) : [];
  const later = idx >= 0 ? list.slice(idx + 1) : [];
  const [mode, setMode] = useState<Mode>(initialMode);
  const [f, setF] = useState({ activity: '', pic_id: null as number | null, date: app.today, note: '', target: '', target_pic_id: null as number | null, gr_number: '', gr_type: '', gr_amount: 0, gr_link: '', invoice_number: '', invoice_date: app.today, invoice_amount: 0, bucket_code: '', bucket_link: '' });
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (!open || !v) return;
    setMode(initialMode);
    const t = initialMode === 'return' ? earlier[earlier.length - 1] : initialMode === 'move' ? later[0] : next;
    setF({ activity: v.stage_key === 'pelaksanaan' ? '' : v.activity || 'Review Dokumen', pic_id: v.pic_id, date: app.today, note: '', target: t?.key || '', target_pic_id: t?.pic_id ?? null, gr_number: term?.gr?.number || '', gr_type: term?.gr?.gr_type || '', gr_amount: term?.gr?.amount || term?.amount || 0, gr_link: term?.gr?.link || '', invoice_number: term?.invoice?.number || '', invoice_date: term?.invoice?.date || app.today, invoice_amount: term?.invoice?.amount || term?.amount || 0, bucket_code: term?.bucket_code || '', bucket_link: term?.bucket_link || '' });
  }, [open, v?.id, initialMode]); // eslint-disable-line react-hooks/exhaustive-deps
  const targets = mode === 'return' ? earlier : mode === 'move' ? later : [];
  useEffect(() => { if (targets.length && !targets.some((t) => t.key === f.target)) setF((x) => ({ ...x, target: mode === 'return' ? targets[targets.length - 1].key : targets[0].key })); }, [mode]); // eslint-disable-line react-hooks/exhaustive-deps
  const stageActs = list.find((s) => s.key === v?.stage_key)?.default_activities || [];
  const isBa = track === 'pasca' && v?.stage_key === 'ba';
  const freeText = track === 'pasca' && v?.stage_key === 'pelaksanaan';
  const actOpts = useMemo(() => (isBa ? stageActs : [...new Set([...stageActs, ...activities])]), [v?.stage_key, activities]); // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => { if (!open || !v) return; if (isBa) setF((x) => ({ ...x, activity: mode === 'update' ? (stageActs.includes(x.activity) ? x.activity : stageActs[0] || '') : 'BA Fully Sign' })); }, [mode, open]); // eslint-disable-line react-hooks/exhaustive-deps
  const curGroup = list.find((s) => s.key === v?.stage_key)?.group;
  if (!v) return null;
  const targetDef = mode === 'complete' || mode === 'skip' ? next : list.find((s) => s.key === f.target);
  const isLast = (mode === 'complete' || mode === 'skip') && !next;
  const modes = track === 'pengadaan' ? APPROVAL_MODES : MODES;
  const atFirst = track === 'pengadaan' && idx <= 0;
  const cta = modes.find((m) => m.key === mode) || modes[0];
  const ctaLabel = track === 'pasca' && mode === 'complete' && !next ? 'Tandai Dibayar' : cta.label;
  const set = (k: keyof typeof f, val: unknown) => setF((x) => ({ ...x, [k]: val }));
  const submit = () => {
    setBusy(true);
    const extra = track === 'pasca' && mode === 'complete' && v.stage_key === 'gr' ? { gr_number: f.gr_number, gr_type: f.gr_type, gr_amount: f.gr_amount, gr_link: f.gr_link }
      : track === 'pasca' && mode === 'complete' && v.stage_key === 'invoice' && f.invoice_number.trim() ? { invoice_number: f.invoice_number, invoice_date: f.invoice_date, invoice_amount: f.invoice_amount } : {};
    const bucket = track === 'pasca' && v.stage_key === 'invoice' && (mode === 'update' || mode === 'complete') ? { bucket_code: f.bucket_code || null, bucket_link: f.bucket_link || null } : {};
    router.post(`/pengadaan/${code}/proses`, { track, term_id: term?.id ?? null, mode, activity: f.activity, pic_id: f.pic_id, date: f.date, note: f.note, target_pic_id: f.target_pic_id, target: targetDef?.key ?? f.target, ...extra, ...bucket }, {
      preserveScroll: true, onSuccess: () => onClose(), onFinish: () => setBusy(false),
    });
  };
  const subject = term ? `Tahap ${term.seq} · ${term.name}` : v.amendment_id ? 'Amandemen' : 'Proses Pengadaan';

  return (
    <Drawer open={open} onClose={onClose} title="Update Proses" width="max-w-[600px]"
      desc={`${subject} · tahap aktif ${v.stage}${v.cycle > 1 ? ` (siklus ${v.cycle})` : ''}, masuk ${fmtDate(v.entered_at)}`}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant={cta.tone === 'bad' ? 'danger' : 'primary'} className={TONE_BTN[cta.tone || 'brand']} icon={cta.icon} onClick={submit} disabled={busy || ((mode === 'return' || mode === 'move') && !targets.length)}>{ctaLabel}</Button></>}>
      <div className={cx('grid grid-cols-2 gap-2', modes.length === 4 ? 'sm:grid-cols-4' : 'sm:grid-cols-5')} role="radiogroup" aria-label="Jenis pembaruan">
        {modes.map((m) => {
          const disabled = (m.key === 'return' && !earlier.length) || (m.key === 'move' && !later.length) || ((m.key === 'rev_minor' || m.key === 'rev_major') && atFirst);
          const on = mode === m.key;
          return <button key={m.key} role="radio" aria-checked={on} disabled={disabled} onClick={() => setMode(m.key)} className={cx('flex flex-col items-center gap-1 rounded-lg border px-2 py-2.5 text-[12px] font-semibold transition disabled:opacity-40', on ? TONE_ON[m.tone || 'brand'] : m.tone === 'warn' ? 'border-warn/40 text-warn hover:bg-warnsoft' : m.tone === 'bad' ? 'border-bad/40 text-bad hover:bg-badsoft' : 'border-line text-mute hover:text-ink')}><m.icon size={17} />{m.label}</button>;
        })}
      </div>
      <p className="mt-2 text-[12.5px] text-mute">{cta.desc}</p>
      {errors.mode && <p role="alert" className="mt-3 flex items-start gap-2 rounded-lg border border-bad/30 bg-badsoft px-3 py-2 text-[12.5px] font-medium text-bad"><AlertTriangle size={15} className="mt-0.5 shrink-0" />{errors.mode}</p>}
      <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
        {!['skip', 'return', 'rev_minor', 'rev_major'].includes(mode) && (
          <Field label={mode === 'update' ? 'Aktivitas saat ini' : 'Aktivitas penutup'} required className="sm:col-span-2">
            {freeText ? <Input value={f.activity} onChange={(e) => set('activity', e.target.value)} placeholder={mode === 'update' ? 'CONTOH: DEVELOPMENT MODUL LAPORAN 60%' : 'CONTOH: SELURUH DELIVERABLE TAHAP INI DISERAHKAN'} />
              : isBa && mode !== 'update' ? <Select value="BA Fully Sign" disabled><option>BA Fully Sign</option></Select>
              : <Select value={f.activity} onChange={(e) => set('activity', e.target.value)}>{actOpts.map((a) => <option key={a}>{a}</option>)}</Select>}
          </Field>
        )}
        <Field label={`PIC ${v.stage}`}><PicSelect pics={pics} roles={[v.stage]} value={f.pic_id} onChange={(x) => set('pic_id', x)} /></Field>
        <Field label={track === 'pasca' && isLast ? 'Tanggal pembayaran' : 'Tanggal kegiatan'} required error={errors.date}><Input type="date" value={f.date} onChange={(e) => set('date', e.target.value)} invalid={!!errors.date} /></Field>
        {(mode === 'return' || mode === 'move') && (
          <Field label={mode === 'return' ? 'Kembalikan ke tahap' : 'Pindah ke tahap'} required error={errors.target}><Select value={f.target} onChange={(e) => set('target', e.target.value)}>{targets.map((t) => <option key={t.key} value={t.key}>{t.name}</option>)}</Select></Field>
        )}
        {mode !== 'update' && targetDef && <Field label={`PIC ${targetDef.name}`}><PicSelect pics={pics} roles={[targetDef.name]} value={f.target_pic_id} onChange={(x) => set('target_pic_id', x)} /></Field>}
        {track === 'pasca' && v.stage_key === 'gr' && mode === 'complete' && (
          <div className="rounded-xl border border-line p-4 sm:col-span-2">
            <p className="mb-3 text-[13.5px] font-bold">Detail GR <span className="text-bad">*</span></p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Field label="Nomor GR" required error={errors.gr_number}><Input value={f.gr_number} onChange={(e) => set('gr_number', e.target.value)} invalid={!!errors.gr_number} /></Field>
              <Field label="Jenis GR" required error={errors.gr_type}><Select value={f.gr_type} onChange={(e) => set('gr_type', e.target.value)} invalid={!!errors.gr_type}><option value="">Pilih jenis</option>{grTypes.map((t) => <option key={t}>{t}</option>)}</Select></Field>
              <Field label="Nominal GR" required error={errors.gr_amount}><MoneyInput value={f.gr_amount} onChange={(n) => set('gr_amount', n)} invalid={!!errors.gr_amount} /></Field>
              <Field label="Tautan dokumen GR" required error={errors.gr_link}><LinkField value={f.gr_link} onChange={(x) => set('gr_link', x)} invalid={!!errors.gr_link} /></Field>
            </div>
          </div>
        )}
        {track === 'pasca' && v.stage_key === 'invoice' && (mode === 'update' || mode === 'complete') && (
          <div className="grid grid-cols-1 gap-3 rounded-xl border border-line p-4 sm:col-span-2 sm:grid-cols-2">
            <p className="text-[13.5px] font-bold sm:col-span-2">Bucket invoicing <span className="font-normal text-mute">(opsional)</span></p>
            <Field label="Kode bucket"><Input value={f.bucket_code} onChange={(e) => set('bucket_code', e.target.value)} /></Field>
            <Field label="Tautan bucket"><LinkField value={f.bucket_link} onChange={(x) => set('bucket_link', x)} /></Field>
          </div>
        )}
        {track === 'pasca' && v.stage_key === 'invoice' && mode === 'complete' && (
          <div className="rounded-xl border border-line p-4 sm:col-span-2">
            <p className="text-[13.5px] font-bold">Invoice <span className="font-normal text-mute">(opsional)</span></p>
            <p className="mb-3 text-[12.5px] text-mute">Kosongkan bila invoice belum ingin dicatat.</p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <Field label="Nomor invoice" error={errors.invoice_number}><Input value={f.invoice_number} onChange={(e) => set('invoice_number', e.target.value)} /></Field>
              <Field label="Tanggal invoice"><Input type="date" value={f.invoice_date} onChange={(e) => set('invoice_date', e.target.value)} /></Field>
              <Field label="Nilai invoice"><MoneyInput value={f.invoice_amount} onChange={(n) => set('invoice_amount', n)} /></Field>
            </div>
          </div>
        )}
        <Field label={mode === 'rev_minor' || mode === 'rev_major' ? 'Alasan revisi' : mode === 'return' ? 'Alasan pengembalian' : mode === 'skip' ? 'Alasan melewati tahap' : 'Catatan'} required={['return', 'skip', 'rev_minor', 'rev_major'].includes(mode)} error={errors.note} className="sm:col-span-2">
          <Textarea value={f.note} onChange={(e) => set('note', e.target.value)} placeholder={mode === 'rev_minor' || mode === 'rev_major' || mode === 'return' ? 'CONTOH: DOKUMEN TOR PERLU DILENGKAPI' : 'Sumber informasi, hasil konfirmasi PIC, dan sebagainya'} />
        </Field>
      </div>
    </Drawer>
  );
}
