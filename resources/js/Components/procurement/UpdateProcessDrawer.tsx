import React, { useEffect, useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Save, CheckCircle2, Undo2, ArrowRightLeft, SkipForward, AlertTriangle } from 'lucide-react';
import { Button, Drawer, Field, Input, Select, Textarea, cx } from '@/Components/ui';
import { PicSelect } from '@/Components/common';
import { fmtDate } from '@/lib/format';
import type { CurrentVisit, PageProps, Pic, StageDef, Term } from '@/types';

export type Mode = 'update' | 'complete' | 'return' | 'move' | 'skip';
const MODES: { key: Mode; label: string; icon: typeof Save; desc: string }[] = [
  { key: 'update', label: 'Simpan Update', icon: Save, desc: 'Perbarui aktivitas, PIC atau catatan di tahap yang sama.' },
  { key: 'complete', label: 'Selesaikan Tahap', icon: CheckCircle2, desc: 'Tutup tahap aktif dan lanjut ke tahap berikutnya.' },
  { key: 'return', label: 'Kembalikan', icon: Undo2, desc: 'Kembalikan ke tahap sebelumnya. Siklus baru dibuat dan durasi dihitung ulang.' },
  { key: 'move', label: 'Pindah Tahap', icon: ArrowRightLeft, desc: 'Lompat maju ke tahap tertentu. Tahap yang dilompati tercatat Dilewati.' },
  { key: 'skip', label: 'Lewati', icon: SkipForward, desc: 'Tandai tahap aktif sebagai Dilewati dan lanjut ke tahap berikutnya.' },
];

interface Props {
  open: boolean; onClose: () => void; code: string; track: 'pengadaan' | 'pasca'; term?: Term | null; current: CurrentVisit | null;
  stages: StageDef[]; pics: Pic[]; activities: string[]; finalLabel: string; initialMode?: Mode;
}

export default function UpdateProcessDrawer({ open, onClose, code, track, term, current: v, stages, pics, activities, finalLabel, initialMode = 'update' }: Props) {
  const { app, errors } = usePage<PageProps>().props;
  const list = stages.filter((s) => s.enabled);
  const idx = v ? list.findIndex((s) => s.key === v.stage_key) : -1;
  const next = idx >= 0 ? list[idx + 1] : undefined;
  const earlier = idx > 0 ? list.slice(0, idx) : [];
  const later = idx >= 0 ? list.slice(idx + 1) : [];
  const [mode, setMode] = useState<Mode>(initialMode);
  const [f, setF] = useState({ activity: '', pic_id: null as number | null, entered_at: '', date: app.today, note: '', target: '', target_pic_id: null as number | null });
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (!open || !v) return;
    setMode(initialMode);
    const t = initialMode === 'return' ? earlier[earlier.length - 1] : initialMode === 'move' ? later[0] : next;
    setF({ activity: v.activity || 'Review Dokumen', pic_id: v.pic_id, entered_at: v.entered_at, date: app.today, note: '', target: t?.key || '', target_pic_id: t?.pic_id ?? null });
  }, [open, v?.id, initialMode]); // eslint-disable-line react-hooks/exhaustive-deps
  const targets = mode === 'return' ? earlier : mode === 'move' ? later : [];
  useEffect(() => { if (targets.length && !targets.some((t) => t.key === f.target)) setF((x) => ({ ...x, target: mode === 'return' ? targets[targets.length - 1].key : targets[0].key })); }, [mode]); // eslint-disable-line react-hooks/exhaustive-deps
  const actOpts = useMemo(() => [...new Set([...(list.find((s) => s.key === v?.stage_key)?.default_activities || []), ...activities])], [v?.stage_key, activities]); // eslint-disable-line react-hooks/exhaustive-deps
  if (!v) return null;
  const targetDef = mode === 'complete' || mode === 'skip' ? next : list.find((s) => s.key === f.target);
  const isLast = (mode === 'complete' || mode === 'skip') && !next;
  const leavingBa = track === 'pasca' && ((v.stage_key === 'ba' && ['complete', 'move', 'skip'].includes(mode)) || (mode === 'move' && idx < list.findIndex((s) => s.key === 'ba') && list.findIndex((s) => s.key === f.target) > list.findIndex((s) => s.key === 'ba')));
  const cta = MODES.find((m) => m.key === mode)!;
  const ctaLabel = track === 'pasca' && mode === 'complete' && !next ? 'Tandai Dibayar' : cta.label;
  const set = (k: keyof typeof f, val: unknown) => setF((x) => ({ ...x, [k]: val }));
  const submit = () => {
    setBusy(true);
    router.post(`/pengadaan/${code}/proses`, { track, term_id: term?.id ?? null, mode, ...f, target: targetDef?.key ?? f.target }, {
      preserveScroll: true, onSuccess: () => onClose(), onFinish: () => setBusy(false),
    });
  };
  const subject = term ? `Tahap ${term.seq} · ${term.name}` : v.amendment_id ? 'Amandemen' : 'Proses Pengadaan';

  return (
    <Drawer open={open} onClose={onClose} title="Update Proses" width="max-w-[600px]"
      desc={`${subject} · tahap aktif ${v.stage}${v.cycle > 1 ? ` (siklus ${v.cycle})` : ''}, masuk ${fmtDate(v.entered_at)}`}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={cta.icon} onClick={submit} disabled={busy || ((mode === 'return' || mode === 'move') && !targets.length)}>{ctaLabel}</Button></>}>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-5" role="radiogroup" aria-label="Jenis pembaruan">
        {MODES.map((m) => {
          const disabled = (m.key === 'return' && !earlier.length) || (m.key === 'move' && !later.length);
          return <button key={m.key} role="radio" aria-checked={mode === m.key} disabled={disabled} onClick={() => setMode(m.key)} className={cx('flex flex-col items-center gap-1 rounded-lg border px-2 py-2.5 text-[12px] font-semibold transition disabled:opacity-40', mode === m.key ? 'border-brand bg-brandsoft text-brand' : 'border-line text-mute hover:text-ink')}><m.icon size={17} />{m.label}</button>;
        })}
      </div>
      <p className="mt-2 text-[12.5px] text-mute">{cta.desc}</p>
      {(errors.mode || (leavingBa && term && !term.ba_ready)) && <p role="alert" className="mt-3 flex items-start gap-2 rounded-lg border border-bad/30 bg-badsoft px-3 py-2 text-[12.5px] font-medium text-bad"><AlertTriangle size={15} className="mt-0.5 shrink-0" />{errors.mode || `Tahap ${term?.seq} belum memiliki BA berstatus TTD Selesai; tahap BA Penutupan belum dapat ditutup.`}</p>}
      <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
        {mode !== 'skip' && mode !== 'return' && (
          <Field label={mode === 'update' ? 'Aktivitas saat ini' : 'Aktivitas penutup'} required className="sm:col-span-2"><Select value={f.activity} onChange={(e) => set('activity', e.target.value)}>{actOpts.map((a) => <option key={a}>{a}</option>)}</Select></Field>
        )}
        <Field label={`PIC ${v.stage}`}><PicSelect pics={pics} value={f.pic_id} onChange={(x) => set('pic_id', x)} /></Field>
        <Field label="Tanggal masuk tahap" required hint="Koreksi bila tanggal masuk tercatat salah."><Input type="date" value={f.entered_at} onChange={(e) => set('entered_at', e.target.value)} /></Field>
        <Field label={track === 'pasca' && isLast ? 'Tanggal pembayaran' : 'Tanggal perubahan'} required error={errors.date}><Input type="date" value={f.date} onChange={(e) => set('date', e.target.value)} invalid={!!errors.date} /></Field>
        {(mode === 'return' || mode === 'move') && (
          <Field label={mode === 'return' ? 'Kembalikan ke tahap' : 'Pindah ke tahap'} required error={errors.target}><Select value={f.target} onChange={(e) => set('target', e.target.value)}>{targets.map((t) => <option key={t.key} value={t.key}>{t.name}</option>)}</Select></Field>
        )}
        {mode !== 'update' && targetDef && <Field label={`PIC ${targetDef.name}`}><PicSelect pics={pics} value={f.target_pic_id} onChange={(x) => set('target_pic_id', x)} /></Field>}
        <Field label={mode === 'return' ? 'Alasan pengembalian' : mode === 'skip' ? 'Alasan melewati tahap' : 'Catatan'} required={mode === 'return' || mode === 'skip'} error={errors.note} className="sm:col-span-2">
          <Textarea value={f.note} onChange={(e) => set('note', e.target.value)} placeholder={mode === 'return' ? 'Contoh: dokumen perlu dilengkapi' : 'Sumber informasi, hasil konfirmasi PIC, dan sebagainya'} />
        </Field>
      </div>
      <div className="mt-5 rounded-xl border border-line bg-sunken p-4 text-[13px]">
        <p className="mb-2 font-bold">Pratinjau dampak</p>
        <ul className="space-y-1.5">
          {mode === 'update' && <li>{v.stage} tetap berjalan{v.days !== null ? `; sudah ${v.days} hari kerja` : ''}.</li>}
          {mode !== 'update' && <li>{v.stage} ditutup sebagai {mode === 'return' ? 'Dikembalikan' : mode === 'skip' ? 'Dilewati' : 'Selesai'}. Histori tetap tersimpan.</li>}
          {mode !== 'update' && targetDef && <li>{targetDef.name} dimulai {fmtDate(f.date)}{mode === 'return' ? ' sebagai siklus baru' : ''}.</li>}
          {track === 'pasca' && mode !== 'update' && targetDef && ['invoice', 'verifikasi', 'antri'].includes(targetDef.key) && <li className="text-info">Status invoice tahap ini ikut diperbarui.</li>}
          {mode !== 'update' && isLast && <li className="font-semibold text-ok">{track === 'pasca' ? `Tahap terakhir: ${term ? `tahap ${term.seq}` : ''} ditandai Dibayar dan pembayaran invoice tercatat.` : `Tahap terakhir. Setelah disimpan, lanjutkan ${finalLabel}.`}</li>}
        </ul>
      </div>
    </Drawer>
  );
}
