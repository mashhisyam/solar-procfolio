import React, { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, AlertTriangle, AlertOctagon, Clock, Circle, Flame, SkipForward, Undo2, ChevronRight, CheckCheck, X, ArrowUp, ArrowDown, ArrowUpDown, RefreshCw } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge, Button, Tone as UiTone, cx, TableWrap, th, td, SearchSelect } from '@/Components/ui';
import { methodTone } from '@/lib/scheme';
import { fmtDate, rp } from '@/lib/format';
import { Pager, usePaged } from '@/Components/Pagination';
import type { Alert, PageProps, Pic, ProcRow, Revision, StepNode } from '@/types';

const STATUS_TONE: Record<string, UiTone> = {
  Draft: 'neutral', 'Persiapan Dokumen': 'info', 'Dalam Approval': 'info', 'Kontrak Rilis': 'brand', 'Pasca Pengadaan': 'info', 'Amandemen Berjalan': 'warn', Gagal: 'bad', Selesai: 'ok', Dibatalkan: 'neutral',
  'Dalam Proses': 'info', Dikembalikan: 'warn', Dilewati: 'outline',
  'Belum Diproses': 'neutral', 'Proses TTD': 'info', 'Perlu Revisi': 'warn', 'TTD Selesai': 'ok', 'Selesai TTD': 'ok',
  'Submit Invoice': 'info', Verifikasi: 'info', 'Antri Bayar': 'brand', Dibayar: 'ok',
  'Belum Diajukan': 'neutral', Diajukan: 'info', 'GR Terbit': 'ok', Rilis: 'ok', Tersedia: 'ok', 'Belum Tersedia': 'warn', Aktif: 'ok', Berakhir: 'neutral',
};
const TONE_ICON: Partial<Record<UiTone, LucideIcon>> = { ok: CheckCircle2, warn: AlertTriangle, bad: AlertOctagon, info: Clock, brand: Clock, neutral: Circle, outline: SkipForward };

export function StatusBadge({ status, className }: { status: string; className?: string }) {
  const tone = STATUS_TONE[status] || 'neutral';
  const icon = status === 'Dikembalikan' ? Undo2 : status === 'Dilewati' ? SkipForward : TONE_ICON[tone];
  return <Badge tone={tone} icon={icon} className={className}>{status}</Badge>;
}
export function PriorityBadge({ p }: { p: 'Urgent' | 'Normal' }) {
  return p === 'Urgent' ? <Badge tone="bad" icon={Flame}>Urgent</Badge> : <Badge tone="outline">Normal</Badge>;
}

/** Alert durasi Proses Pengadaan: hijau, kuning, merah (ambang di Master). */
export function DurationPill({ days, level, className }: { days: number; level: 'ok' | 'warn' | 'bad' | null; className?: string }) {
  const c = level === 'bad' ? 'bg-badsoft text-bad' : level === 'warn' ? 'bg-warnsoft text-warn' : 'bg-oksoft text-ok';
  const dot = level === 'bad' ? 'bg-bad' : level === 'warn' ? 'bg-warn' : 'bg-ok';
  return <span className={cx('num inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[12px] font-semibold', c, className)}><span className={cx('h-1.5 w-1.5 rounded-full', dot)} aria-hidden />{days} hari</span>;
}

/** Attention batas pekerjaan dari rencana tanggal selesai. */
export function DeadlinePill({ date, warnDays = 7 }: { date?: string | null; warnDays?: number }) {
  const { app } = usePage<PageProps>().props;
  if (!date) return null;
  const left = Math.round((Date.parse(date) - Date.parse(app.today)) / 864e5);
  const tone = left < 0 ? 'bg-badsoft text-bad' : left <= warnDays ? 'bg-warnsoft text-warn' : 'bg-oksoft text-ok';
  return <span className={cx('num inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[11.5px] font-semibold', tone)} title={`Batas pekerjaan ${fmtDate(date)}`}><CalendarClock size={12} aria-hidden />{left < 0 ? `Lewat ${-left} hari` : left === 0 ? 'Batas hari ini' : `${left} hari lagi`}</span>;
}

type StepGroup = { name: string; nodes: StepNode[] };
function groupNodes(nodes: StepNode[]): StepGroup[] {
  const out: StepGroup[] = [];
  nodes.forEach((n) => {
    const last = out[out.length - 1];
    if (n.group && last && last.nodes[0].group === n.group) last.nodes.push(n);
    else out.push({ name: n.group || n.name, nodes: [n] });
  });
  return out;
}
function groupState(g: StepGroup): StepNode['state'] {
  const st = g.nodes.map((n) => n.state);
  if (st.includes('current')) return 'current';
  if (st.every((x) => x === 'done' || x === 'skipped')) return st.every((x) => x === 'skipped') ? 'skipped' : 'done';
  if (st.includes('returned')) return 'returned';
  if (st.some((x) => x === 'done' || x === 'skipped')) return 'current';
  return 'pending';
}

/**
 * Stepper berkelompok: tahap berjenjang (mis. User → Manager → VP Pengaju) tampil sebagai satu simpul
 * dengan titik sub-tahap. Tahap yang dilewati tetap hijau dengan keterangan "Dilewati".
 */
export function Stepper({ nodes, compact }: { nodes: StepNode[]; compact?: boolean }) {
  const groups = groupNodes(nodes);
  return (
    <ol className={cx('flex w-full items-start', compact ? 'gap-1' : 'gap-2')}>
      {groups.map((g, i) => {
        const state = groupState(g);
        const cur = g.nodes.find((n) => n.state === 'current');
        const dot = { done: 'bg-ok text-white border-ok', skipped: 'bg-ok text-white border-ok', current: 'bg-brand text-white border-brand ring-4 ring-brand/15', returned: 'bg-warnsoft text-warn border-warn', pending: 'bg-surface text-mute border-line' }[state];
        const Icon = state === 'done' || state === 'skipped' ? CheckCircle2 : state === 'returned' ? Undo2 : state === 'current' ? Clock : Circle;
        const lastDone = [...g.nodes].reverse().find((n) => n.state === 'done' && n.date);
        const sub = state === 'skipped' ? 'Dilewati'
          : cur ? (g.nodes.length > 1 ? cur.name : '') + (cur.scheduled ? ` · mulai ${fmtDate(cur.date)}` : cur.days !== null ? `${g.nodes.length > 1 ? ' · ' : ''}${cur.days} hari kerja` : '')
          : lastDone ? fmtDate(lastDone.date) : '–';
        const cycles = Math.max(...g.nodes.map((n) => n.cycles));
        return (
          <li key={g.name + i} className="relative flex min-w-0 flex-1 flex-col items-center text-center">
            {i < groups.length - 1 && <span className={cx('absolute left-1/2 top-[13px] h-0.5 w-full', state === 'done' || state === 'skipped' ? 'bg-ok/50' : 'bg-line')} />}
            <span className={cx('relative z-[1] flex h-7 w-7 items-center justify-center rounded-full border-2', dot)} aria-hidden><Icon size={14} strokeWidth={2.4} /></span>
            <span className="mt-1.5 text-[12.5px] font-bold text-ink">{g.name}</span>
            {g.nodes.length > 1 && (
              <span className="mt-1 flex items-center gap-[3px]" aria-label={g.nodes.map((n) => `${n.name}: ${n.state}`).join(', ')}>
                {g.nodes.map((n) => <span key={n.key} title={`${n.name}${n.state === 'skipped' ? ' (dilewati)' : ''}`} className={cx('h-1.5 w-3 rounded-full', n.state === 'done' || n.state === 'skipped' ? 'bg-ok' : n.state === 'current' ? 'bg-brand' : n.state === 'returned' ? 'bg-warn' : 'bg-line')} />)}
              </span>
            )}
            {!compact && (
              <span className="mt-0.5 text-[11.5px] text-mute">
                {sub}
                {cycles > 1 && <span className="ml-1 font-semibold text-warn">· {cycles} siklus</span>}
              </span>
            )}
          </li>
        );
      })}
    </ol>
  );
}

export function Crumbs({ items }: { items: [string, string?][] }) {
  return (
    <nav aria-label="Breadcrumb" className="mb-2 flex flex-wrap items-center gap-1 text-[12.5px] text-mute">
      {items.map(([label, href], i) => (
        <React.Fragment key={i}>
          {i > 0 && <ChevronRight size={13} />}
          {href ? <Link href={href} className="hover:text-brand hover:underline">{label}</Link> : <span className="font-semibold text-ink">{label}</span>}
        </React.Fragment>
      ))}
    </nav>
  );
}

export function PageHeader({ title, desc, actions, crumbs, meta }: { title: React.ReactNode; desc?: React.ReactNode; actions?: React.ReactNode; crumbs?: [string, string?][]; meta?: React.ReactNode }) {
  return (
    <div className="mb-5">
      {crumbs && <Crumbs items={crumbs} />}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-[24px] font-extrabold leading-tight tracking-[-0.01em] text-ink">{title}</h1>
          {desc && <p className="mt-1 max-w-3xl text-[13.5px] text-mute">{desc}</p>}
          {meta && <div className="mt-2 flex flex-wrap items-center gap-2">{meta}</div>}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
      </div>
    </div>
  );
}

export function MetricCard({ label, value, sub, icon: Icon, tone = 'navy', onClick, active }: { label: string; value: React.ReactNode; sub: string; icon: LucideIcon; tone?: 'navy' | 'bad' | 'warn' | 'info'; onClick?: () => void; active?: boolean }) {
  const ic = { navy: 'grad-brand text-white', bad: 'bg-badsoft text-bad', warn: 'bg-warnsoft text-warn', info: 'bg-infosoft text-info' }[tone];
  return (
    <button onClick={onClick} className={cx('group rounded-xl border bg-surface p-4 text-left shadow-card transition hover:border-brand/50', active ? 'border-brand ring-2 ring-brand/15' : 'border-line')}>
      <div className="flex items-start justify-between gap-3"><span className="text-[13px] font-semibold text-mute">{label}</span><span className={cx('flex h-8 w-8 items-center justify-center rounded-lg', ic)}><Icon size={16} /></span></div>
      <div className="num mt-1 font-display text-[34px] font-bold leading-none tracking-tight text-ink">{value}</div>
      <div className="mt-2 text-[12.5px] text-mute">{sub}</div>
    </button>
  );
}

export function AlertList({ alerts, onPick, empty = 'Tidak ada indikator perhatian.' }: { alerts: Alert[]; onPick?: (a: Alert) => void; empty?: string }) {
  if (!alerts.length) return <div className="flex items-center gap-2 rounded-lg bg-oksoft px-3 py-2 text-[13px] font-medium text-ok"><CheckCheck size={16} />{empty}</div>;
  return (
    <ul className="space-y-2">
      {alerts.map((a, i) => {
        const Icon = a.tone === 'bad' ? AlertOctagon : a.tone === 'warn' ? AlertTriangle : Clock;
        const c = a.tone === 'bad' ? 'border-bad/30 bg-badsoft' : a.tone === 'warn' ? 'border-warn/30 bg-warnsoft' : 'border-info/25 bg-infosoft';
        const ic = a.tone === 'bad' ? 'text-bad' : a.tone === 'warn' ? 'text-warn' : 'text-info';
        return (
          <li key={i}>
            <button onClick={() => onPick?.(a)} className={cx('flex w-full items-start gap-2.5 rounded-lg border px-3 py-2 text-left', c)}>
              <Icon size={16} className={cx('mt-0.5 shrink-0', ic)} /><span className="text-[13px] font-semibold text-ink">{a.label}</span>
              {onPick && <ChevronRight size={15} className="ml-auto mt-0.5 shrink-0 text-mute" />}
            </button>
          </li>
        );
      })}
    </ul>
  );
}

export function RowLink({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) {
  return <tr tabIndex={0} onClick={() => router.visit(href)} onKeyDown={(e) => e.key === 'Enter' && router.visit(href)} className={cx('cursor-pointer transition hover:bg-sunken/60 focus-visible:bg-sunken', className)}>{children}</tr>;
}

/** Tautan pengadaan memakai Nomor SPP (ID PRC sebagai tooltip). */
export function ProcLink({ code, spp, tab }: { code: string; spp?: string | null; tab?: string }) {
  return <Link href={`/pengadaan/${code}${tab ? `?tab=${tab}` : ''}`} onClick={(e) => e.stopPropagation()} title={code} className="num whitespace-nowrap font-bold text-brand hover:underline">{spp || code}</Link>;
}

export function PicSelect({ value, onChange, pics, roles, placeholder = 'Pilih PIC', invalid }: { value?: number | null; onChange: (v: number | null) => void; pics: Pic[]; roles?: string[]; placeholder?: string; invalid?: boolean }) {
  const norm = (x: string) => x.trim().toLowerCase();
  const pref = roles?.length ? pics.filter((p) => p.role && roles.map(norm).includes(norm(p.role))) : [];
  const rest = pics.filter((p) => !pref.includes(p));
  const opt = (p: Pic, group: string) => ({ value: String(p.id), label: p.name, sub: [p.title, p.role].filter(Boolean).join(' · ') || null, group });
  return (
    <SearchSelect value={value ? String(value) : ''} onChange={(v) => onChange(v ? Number(v) : null)} placeholder={placeholder} invalid={invalid} ariaLabel={placeholder}
      options={[...pref.map((p) => opt(p, 'Disarankan')), ...rest.map((p) => opt(p, pref.length ? 'Lainnya' : 'PIC'))]} />
  );
}

/** Dua progres ringkas per pengadaan. */
export function TrackProgress({ row }: { row: ProcRow }) {
  const { approval, contract, terms } = row.progress;
  return (
    <div className="space-y-1">
      <div className="flex items-center gap-2"><span className="w-[64px] text-[11px] font-semibold text-mute">Pengadaan</span>
        <span className="flex items-center gap-[3px]">{approval.map((s, i) => <span key={i} className={cx('h-2 w-2 rounded-full', s === 'Dalam Proses' ? 'bg-brand ring-2 ring-brand/25' : s === 'Selesai' || s === 'Dilewati' ? 'bg-ok' : 'bg-line')} />)}<span className={cx('h-2 w-2 rotate-45 rounded-[2px]', contract ? 'bg-ok' : 'bg-line')} /></span>
      </div>
      <div className="flex items-center gap-2" title={terms.map((t, i) => `Tahap ${i + 1}: ${t.paid ? 'dibayar' : t.scheduled ? 'terjadwal' : t.stage || '–'}`).join('\n')}><span className="w-[64px] text-[11px] font-semibold text-mute">Pasca</span>
        {terms.length ? <span className="flex items-center gap-[3px]">{terms.map((t, i) => <span key={i} className={cx('h-2 w-3 rounded-sm', t.paid ? 'bg-ok' : t.scheduled ? 'bg-line' : 'bg-brand')} />)}<span className="num ml-1 text-[11px] text-mute">{terms.filter((t) => t.paid).length}/{terms.length}</span></span> : <span className="text-[11px] text-mute">–</span>}
      </div>
    </div>
  );
}

export type SortKey = 'priority' | 'spp' | 'title' | 'rab' | 'days' | 'update';
// Status tepat setelah judul; Durasi Tahap tepat setelah Aktivitas Saat Ini.
const COLS: { label: string; sort?: SortKey }[] = [
  { label: 'No' }, { label: 'Prioritas', sort: 'priority' }, { label: 'No SPP / ID', sort: 'spp' }, { label: 'Judul Pengadaan', sort: 'title' }, { label: 'Status' },
  { label: 'RAB Pengadaan', sort: 'rab' }, { label: 'Skema / Metode' }, { label: 'User Pengaju' }, { label: 'Aliran Data' }, { label: 'Aktivitas Saat Ini' },
  { label: 'Durasi Tahap', sort: 'days' }, { label: 'PIC Aliran Data' }, { label: 'Last Update', sort: 'update' },
];
export function sortRows(rows: ProcRow[], k: SortKey, dir: 1 | -1): ProcRow[] {
  const v = (r: ProcRow): string | number => ({ priority: r.priority === 'Urgent' ? 1 : 0, spp: r.spp_number || r.code, title: r.title, rab: r.rab_value, days: r.days ?? -1, update: r.last_update }[k]);
  return [...rows].sort((a, b) => { const x = v(a), y = v(b); return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y))) * dir; });
}

/** Satu tabel monitoring: dipakai Dashboard dan Daftar Pengadaan. */
export function ProcTable({ rows, sort, onSort, start = 0 }: { rows: ProcRow[]; sort?: { k: SortKey; dir: 1 | -1 }; onSort?: (k: SortKey) => void; start?: number }) {
  return (
    <TableWrap>
      <thead><tr>{COLS.map((c) => { const on = c.sort && sort?.k === c.sort; return (
        <th key={c.label} className={th} aria-sort={on ? (sort!.dir === 1 ? 'ascending' : 'descending') : undefined}>
          {c.sort && onSort ? <button className="inline-flex items-center gap-1 hover:text-ink" onClick={() => onSort(c.sort!)}>{c.label}{on ? (sort!.dir === 1 ? <ArrowUp size={12} /> : <ArrowDown size={12} />) : <ArrowUpDown size={12} className="opacity-50" />}</button> : c.label}
        </th>); })}</tr></thead>
      <tbody>{rows.map((r, rowIdx) => (
        <RowLink key={r.code} href={`/pengadaan/${r.code}`} className={cx(r.cancelled && 'opacity-60')}>
          <td className={cx(td, 'num w-12 text-mute')}>{start + rowIdx + 1}</td>
          <td className={td}><PriorityBadge p={r.priority} /></td>
          <td className={cx(td, 'whitespace-nowrap')}><div className="num text-[13.5px] font-extrabold tracking-tight text-navy">{r.spp_number || '–'}</div><div className="num text-[11.5px] font-semibold text-mute">{r.code}</div></td>
          <td className={cx(td, 'min-w-[220px] max-w-[280px]')}><div className="line-clamp-2 font-semibold">{r.title}</div></td>
          <td className={td}><StatusBadge status={r.status} />{r.revision && <div className="mt-1"><RevisionTag r={r.revision} /></div>}</td>
          <td className={cx(td, 'num whitespace-nowrap text-right font-semibold')}>{r.rab_value ? rp(r.rab_value) : '–'}</td>
          <td className={cx(td, 'min-w-[120px]')}><Badge tone={methodTone(r.method)}>{r.method}</Badge>{r.proc_method && <div className="mt-1 whitespace-nowrap text-[11.5px] text-mute">{r.proc_method}</div>}</td>
          <td className={cx(td, 'whitespace-nowrap')}>{r.pengaju_name || '–'}</td>
          <td className={cx(td, 'min-w-[150px]')}><div className="text-[11px] font-semibold text-mute">{r.track === 'pasca' ? 'Pasca Pengadaan' : r.amendment ? 'Amandemen' : 'Proses Pengadaan'}</div><div className="font-semibold">{r.stage || '–'}{r.cycle > 1 && <span className="ml-1 text-[11.5px] text-warn">siklus {r.cycle}</span>}</div></td>
          <td className={cx(td, 'min-w-[130px]')}>{r.activity || '–'}</td>
          <td className={cx(td, 'whitespace-nowrap')}>{r.days !== null ? (r.track === 'pengadaan' ? <DurationPill days={r.days} level={r.level} /> : <span className="num font-semibold">{r.days} hari</span>) : '–'}{r.late_days > 0 && <div className="mt-1"><Badge tone="bad">Terlambat {r.late_days} hari</Badge></div>}</td>
          <td className={cx(td, 'whitespace-nowrap')}>{r.pic || '–'}</td>
          <td className={cx(td, 'whitespace-nowrap')}><div className="num">{fmtDate(r.last_update)}</div><div className="text-[12px] text-mute">{r.last_update_days === 0 ? 'hari ini' : `${r.last_update_days} hari lalu`}</div></td>
        </RowLink>
      ))}</tbody>
    </TableWrap>
  );
}

/** Tabel monitoring dengan paginasi, untuk daftar pengadaan di halaman detail (PRK, perjanjian, PKS). */
export function PagedProcTable({ rows }: { rows: ProcRow[] }) {
  const pg = usePaged(rows);
  return <><ProcTable rows={pg.view} start={pg.start} /><Pager p={pg} label="pengadaan" /></>;
}

/** Notifikasi singkat dari flash session. */
export function Toaster() {
  const { flash, errors } = usePage<PageProps>().props;
  const [items, setItems] = useState<{ id: number; text: string; tone: 'ok' | 'bad' }[]>([]);
  useEffect(() => {
    const add: { id: number; text: string; tone: 'ok' | 'bad' }[] = [];
    if (flash.success) add.push({ id: Date.now(), text: flash.success, tone: 'ok' });
    if (flash.error) add.push({ id: Date.now() + 1, text: flash.error, tone: 'bad' });
    const errs = Object.values(errors || {});
    if (errs.length && !add.length) add.push({ id: Date.now() + 2, text: errs.length === 1 ? errs[0] : `${errs.length} isian perlu diperbaiki.`, tone: 'bad' });
    if (add.length) { setItems((x) => [...x, ...add]); add.forEach((a) => setTimeout(() => setItems((x) => x.filter((y) => y.id !== a.id)), 5000)); }
  }, [flash, errors]);
  return (
    <div className="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-[min(380px,calc(100vw-2rem))] flex-col gap-2" role="status" aria-live="polite">
      {items.map((t) => (
        <div key={t.id} className={cx('pointer-events-auto flex items-start gap-2.5 rounded-xl border bg-surface px-4 py-3 text-[13px] shadow-pop fade-in', t.tone === 'ok' ? 'border-ok/30' : 'border-bad/30')}>
          {t.tone === 'ok' ? <CheckCircle2 size={17} className="mt-0.5 shrink-0 text-ok" /> : <AlertOctagon size={17} className="mt-0.5 shrink-0 text-bad" />}
          <span className="flex-1">{t.text}</span>
          <button aria-label="Tutup" onClick={() => setItems((x) => x.filter((y) => y.id !== t.id))} className="text-mute hover:text-ink"><X size={15} /></button>
        </div>
      ))}
    </div>
  );
}

/** Label singkat revisi untuk tabel: Mayor merah, Minor kuning, berkedip agar menonjol. */
export function RevisionTag({ r }: { r: Revision }) {
  const major = r.type === 'mayor';
  return (
    <span className={cx('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[11.5px] font-extrabold uppercase tracking-wide', major ? 'bg-bad text-white' : 'bg-warn text-white')}
      title={`${major ? 'Revisi Mayor' : 'Revisi Minor'} dari ${r.from_stage}${r.reason ? `: ${r.reason}` : ''}`}>
      <span className="relative flex h-1.5 w-1.5"><span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75" /><span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-white" /></span>
      {major ? 'Revisi Mayor' : 'Revisi Minor'} · {r.days} hr
    </span>
  );
}

/**
 * Banner revisi di halaman detail: siapa yang mengembalikan, alasannya, sudah berapa lama menunggu,
 * dan apa yang terjadi setelah diperbaiki. Dibuat mencolok agar pengaju segera menindaklanjuti.
 */
export function RevisionBanner({ r, onAction }: { r: Revision; onAction?: () => void }) {
  const major = r.type === 'mayor';
  const late = r.level === 'bad' || r.level === 'warn';
  return (
    <div role="alert" className={cx('mb-5 overflow-hidden rounded-xl border-2 shadow-card', major ? 'border-bad bg-badsoft' : 'border-warn bg-warnsoft')}>
      <div className={cx('flex flex-wrap items-center gap-3 px-4 py-2.5 text-white', major ? 'bg-bad' : 'bg-warn')}>
        <AlertTriangle size={18} />
        <b className="text-[14.5px] uppercase tracking-wide">{major ? 'Revisi Mayor — perbaiki segera' : 'Revisi Minor — perbaiki segera'}</b>
        <span className="num ml-auto rounded-full bg-white/20 px-2.5 py-0.5 text-[12.5px] font-bold">{r.days} hari kerja menunggu perbaikan{late ? ' · melewati batas' : ''}</span>
      </div>
      <div className="grid grid-cols-1 gap-4 px-4 py-3 text-[13px] md:grid-cols-[1fr_auto]">
        <div className="space-y-1.5">
          <p>Dikembalikan oleh <b>{r.from_stage}</b>{r.from_pic ? ` (${r.from_pic})` : ''} pada <b>{fmtDate(r.since)}</b>. Saat ini di <b>{r.owner_stage}</b>{r.owner ? ` — ${r.owner}` : ''}.</p>
          {r.reason && <p className={cx('rounded-lg bg-surface px-3 py-2 font-medium', major ? 'text-bad' : 'text-warn')}>Alasan: {r.reason}</p>}
          <p className="text-ink/80">
            {major
              ? <>Setelah diperbaiki, <b>seluruh approval diulang dari awal</b> (siklus approval {r.cycle}). Setiap hari keterlambatan menambah waktu proses seluruh jenjang.</>
              : <>Setelah diperbaiki, proses <b>langsung kembali ke {r.resume_stage || r.from_stage}</b> tanpa mengulang approval.</>}
          </p>
        </div>
        {onAction && <div className="self-center"><Button variant="primary" icon={RefreshCw} onClick={onAction}>Update Proses</Button></div>}
      </div>
    </div>
  );
}
