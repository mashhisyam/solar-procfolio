import React, { useEffect, useRef, useState } from 'react';
import { X, ChevronDown, Check, Search, MoreHorizontal, Info, Link2, ExternalLink } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { parseMoney } from '@/lib/format';

export const cx = (...a: (string | false | null | undefined)[]) => a.filter(Boolean).join(' ');

type BtnVariant = 'primary' | 'navy' | 'secondary' | 'ghost' | 'danger' | 'subtle';
export function Button({ variant = 'secondary', size = 'md', icon: Icon, iconRight: IconR, className, children, ...rest }: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: BtnVariant; size?: 'sm' | 'md' | 'xs'; icon?: LucideIcon; iconRight?: LucideIcon }) {
  const v: Record<BtnVariant, string> = {
    primary: 'btn-primary bg-brand text-white hover:brightness-110 border border-brand shadow-[0_1px_2px_rgb(10_47_107/.25)]',
    navy: 'bg-navy text-white hover:brightness-125 border border-navy',
    secondary: 'bg-surface text-ink border border-line hover:bg-sunken',
    ghost: 'text-ink hover:bg-sunken border border-transparent',
    subtle: 'bg-brandsoft text-brand border border-transparent hover:brightness-95',
    danger: 'bg-surface text-bad border border-line hover:bg-badsoft',
  };
  const s = { xs: 'h-7 px-2 text-xs gap-1', sm: 'h-8 px-3 text-[13px] gap-1.5', md: 'h-9 px-3.5 text-[13.5px] gap-2' }[size];
  return (
    <button {...rest} className={cx('inline-flex items-center justify-center rounded-lg font-semibold whitespace-nowrap transition disabled:opacity-50 disabled:cursor-not-allowed', v[variant], s, className)}>
      {Icon && <Icon size={size === 'md' ? 16 : 14} strokeWidth={2.2} />}
      {children}
      {IconR && <IconR size={14} />}
    </button>
  );
}
export function IconButton({ icon: Icon, label, className, ...rest }: React.ButtonHTMLAttributes<HTMLButtonElement> & { icon: LucideIcon; label: string }) {
  return <button aria-label={label} title={label} {...rest} className={cx('inline-flex h-8 w-8 items-center justify-center rounded-lg text-mute hover:bg-sunken hover:text-ink transition', className)}><Icon size={17} /></button>;
}

export type Tone = 'neutral' | 'ok' | 'warn' | 'bad' | 'info' | 'brand' | 'navy' | 'teal' | 'outline';
export function Badge({ tone = 'neutral', icon: Icon, children, className, title }: { tone?: Tone; icon?: LucideIcon; children: React.ReactNode; className?: string; title?: string }) {
  const t: Record<Tone, string> = {
    neutral: 'bg-sunken text-mute', ok: 'bg-oksoft text-ok', warn: 'bg-warnsoft text-warn', bad: 'bg-badsoft text-bad', info: 'bg-infosoft text-info',
    brand: 'bg-brandsoft text-brand', navy: 'bg-navy text-white', teal: 'bg-tealsoft text-teal', outline: 'border border-line text-mute',
  };
  return <span title={title} className={cx('inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11.5px] font-semibold leading-4 whitespace-nowrap', t[tone], className)}>{Icon && <Icon size={12} strokeWidth={2.4} />}{children}</span>;
}

export function Card({ className, children, ...rest }: React.HTMLAttributes<HTMLDivElement>) {
  return <div {...rest} className={cx('rounded-xl border border-line bg-surface shadow-card', className)}>{children}</div>;
}
export function CardHead({ title, desc, actions, icon: Icon, className }: { title: React.ReactNode; desc?: React.ReactNode; actions?: React.ReactNode; icon?: LucideIcon; className?: string }) {
  return (
    <div className={cx('flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-3.5', className)}>
      <div className="min-w-0">
        <h3 className="flex items-center gap-2 text-[15px] font-bold text-ink">{Icon && <Icon size={16} className="text-brand" />}{title}</h3>
        {desc && <p className="mt-0.5 text-[12.5px] text-mute">{desc}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

export function Field({ label, required, hint, error, children, className }: { label: string; required?: boolean; hint?: React.ReactNode; error?: string; children: React.ReactNode; className?: string }) {
  return (
    <label className={cx('block', className)}>
      <span className="mb-1 flex items-center gap-1 text-[12.5px] font-semibold text-ink">{label}{required ? <span className="text-bad" aria-hidden>*</span> : <span className="font-normal text-mute">(opsional)</span>}</span>
      {children}
      {error ? <span role="alert" className="mt-1 block text-[12px] font-medium text-bad">{error}</span> : hint ? <span className="mt-1 block text-[12px] text-mute">{hint}</span> : null}
    </label>
  );
}
export const inputCls = 'w-full h-9 rounded-lg border border-line bg-surface px-3 text-[13.5px] text-ink placeholder:text-mute/70 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 disabled:bg-sunken disabled:text-mute';
/**
 * Isian teks bebas otomatis huruf kapital. Tidak berlaku untuk email, kata sandi, URL, angka,
 * tanggal, pencarian, atau bila diberi prop `noCaps` (mis. tautan dan kredensial).
 */
const NO_CAPS_TYPES = ['email', 'password', 'url', 'number', 'date', 'datetime-local', 'month', 'time', 'search', 'tel'];
const toCaps = (v: string) => v.toUpperCase();
export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean; noCaps?: boolean }>(({ className, invalid, noCaps, onChange, type, ...rest }, ref) => {
  const caps = !noCaps && !NO_CAPS_TYPES.includes(type || 'text') && !rest.inputMode;
  return (
    <input ref={ref} type={type} {...rest}
      onChange={caps && onChange ? (e) => { const pos = e.target.selectionStart; e.target.value = toCaps(e.target.value); if (pos !== null) e.target.setSelectionRange(pos, pos); onChange(e); } : onChange}
      aria-invalid={invalid || undefined} className={cx(inputCls, invalid && 'border-bad ring-2 ring-bad/15', className)} />
  );
});
export function Textarea({ className, noCaps, onChange, ...rest }: React.TextareaHTMLAttributes<HTMLTextAreaElement> & { noCaps?: boolean }) {
  return (
    <textarea {...rest}
      onChange={!noCaps && onChange ? (e) => { const pos = e.target.selectionStart; e.target.value = toCaps(e.target.value); e.target.setSelectionRange(pos, pos); onChange(e); } : onChange}
      className={cx(inputCls, 'h-auto min-h-[72px] py-2', className)} />
  );
}
export function Select({ className, invalid, children, ...rest }: React.SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
  return (
    <div className="relative">
      <select {...rest} aria-invalid={invalid || undefined} className={cx(inputCls, 'appearance-none pr-8', invalid && 'border-bad', className)}>{children}</select>
      <ChevronDown size={15} className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-mute" />
    </div>
  );
}
export function Checkbox({ checked, onChange, label, disabled }: { checked: boolean; onChange: (v: boolean) => void; label?: React.ReactNode; disabled?: boolean }) {
  return (
    <label className={cx('inline-flex items-center gap-2 text-[13px]', disabled ? 'opacity-60' : 'cursor-pointer')}>
      <input type="checkbox" className="h-4 w-4 accent-[rgb(var(--brand))]" checked={checked} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
      {label}
    </label>
  );
}
export function Switch({ checked, onChange, label, hint }: { checked: boolean; onChange: (v: boolean) => void; label: string; hint?: string }) {
  return (
    <button type="button" role="switch" aria-checked={checked} onClick={() => onChange(!checked)} className="flex items-start gap-3 text-left">
      <span className={cx('mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full p-0.5 transition', checked ? 'bg-brand' : 'bg-line')}>
        <span className={cx('h-4 w-4 rounded-full bg-white shadow transition', checked && 'translate-x-4')} />
      </span>
      <span><span className="block text-[13px] font-semibold">{label}</span>{hint && <span className="block text-[12px] text-mute">{hint}</span>}</span>
    </button>
  );
}
export function MoneyInput({ value, onChange, invalid, placeholder, disabled }: { value: number; onChange: (n: number) => void; invalid?: boolean; placeholder?: string; disabled?: boolean }) {
  return (
    <div className="relative">
      <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] font-semibold text-mute">Rp</span>
      <Input disabled={disabled} inputMode="numeric" invalid={invalid} className="num pl-9 text-right" placeholder={placeholder || '0'} value={value ? value.toLocaleString('id-ID') : ''} onChange={(e) => onChange(parseMoney(e.target.value))} />
    </div>
  );
}

export interface Opt { value: string; label: string; sub?: string }
export function MultiSelect({ options, value, onChange, placeholder = 'Pilih…', invalid, emptyText = 'Tidak ada pilihan' }: { options: Opt[]; value: string[]; onChange: (v: string[]) => void; placeholder?: string; invalid?: boolean; emptyText?: string }) {
  const [open, setOpen] = useState(false); const [q, setQ] = useState('');
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => { const h = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); }; document.addEventListener('mousedown', h); return () => document.removeEventListener('mousedown', h); }, []);
  const sel = value.map((v) => options.find((o) => o.value === v)).filter(Boolean) as Opt[];
  const list = options.filter((o) => (o.label + ' ' + (o.sub || '')).toLowerCase().includes(q.toLowerCase()));
  return (
    <div ref={ref} className="relative">
      <div onClick={() => setOpen(true)} className={cx('flex min-h-9 w-full cursor-text flex-wrap items-center gap-1 rounded-lg border bg-surface px-2 py-1', invalid ? 'border-bad' : 'border-line', open && 'border-brand ring-2 ring-brand/20')}>
        {sel.map((o) => (
          <span key={o.value} className="inline-flex items-center gap-1 rounded-md bg-brandsoft px-1.5 py-0.5 text-[12px] font-semibold text-brand">
            {o.label}<button type="button" aria-label={`Hapus ${o.label}`} onClick={(e) => { e.stopPropagation(); onChange(value.filter((v) => v !== o.value)); }}><X size={12} /></button>
          </span>
        ))}
        <input value={q} onChange={(e) => { setQ(e.target.value); setOpen(true); }} onFocus={() => setOpen(true)} placeholder={sel.length ? '' : placeholder} className="min-w-[80px] flex-1 bg-transparent px-1 text-[13.5px] outline-none placeholder:text-mute/70" />
        <ChevronDown size={15} className="text-mute" />
      </div>
      {open && (
        <div className="absolute z-40 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-line bg-surface p-1 shadow-pop fade-in">
          {list.length === 0 && <div className="px-3 py-2 text-[12.5px] text-mute">{emptyText}</div>}
          {list.map((o) => {
            const on = value.includes(o.value);
            return (
              <button type="button" key={o.value} onClick={() => onChange(on ? value.filter((v) => v !== o.value) : [...value, o.value])} className={cx('flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-left hover:bg-sunken', on && 'bg-brandsoft/60')}>
                <span className={cx('mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border', on ? 'border-brand bg-brand text-white' : 'border-line')}>{on && <Check size={11} strokeWidth={3} />}</span>
                <span className="min-w-0"><span className="block text-[13px] font-semibold">{o.label}</span>{o.sub && <span className="block truncate text-[12px] text-mute">{o.sub}</span>}</span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

/** Tautan dapat dibuka langsung bila diawali http(s)://; isian lain tetap diterima. */
export const isLink = (u?: string | null) => !!u && /^https?:\/\/\S+$/i.test(u.trim());

/** Isian tautan dokumen (SharePoint/sharefolder). Dokumen tidak diunggah ke Procfolio. */
export function LinkField({ value, onChange, placeholder = 'Tempel tautan dokumen SharePoint/sharefolder', invalid }: { value?: string | null; onChange: (v: string) => void; placeholder?: string; invalid?: boolean }) {
  const text = value || '';
  const valid = isLink(text);
  return (
    <div>
      <div className="flex items-center gap-2">
        <div className="relative min-w-0 flex-1">
          <Link2 size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-mute" aria-hidden />
          <input type="text" value={text} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} aria-label="Tautan dokumen" className={cx(inputCls, 'pl-9', invalid && 'border-warn')} />
        </div>
        {valid && <a href={text.trim()} target="_blank" rel="noopener noreferrer" className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-line px-3 text-[12.5px] font-semibold text-brand hover:bg-sunken"><ExternalLink size={14} />Buka</a>}
      </div>
      
    </div>
  );
}

/** Tombol buka tautan dokumen di tab baru. */
export function LinkButton({ url, label = 'Buka tautan', size = 'xs' }: { url?: string | null; label?: string; size?: 'xs' | 'sm' }) {
  if (!url) return <span className="inline-flex items-center gap-1 text-[12px] text-mute"><Link2 size={13} />Belum ada tautan</span>;
  return <a href={url} target="_blank" rel="noopener noreferrer" onClick={(e) => e.stopPropagation()} className={cx('inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg font-semibold text-brand hover:bg-brandsoft', size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-7 px-2 text-[12px]')}><ExternalLink size={13} />{label}</a>;
}

export function Modal({ open, onClose, title, desc, children, footer, width = 'max-w-lg' }: { open: boolean; onClose: () => void; title: string; desc?: string; children: React.ReactNode; footer?: React.ReactNode; width?: string }) {
  useEffect(() => { if (!open) return; const h = (e: KeyboardEvent) => e.key === 'Escape' && onClose(); window.addEventListener('keydown', h); return () => window.removeEventListener('keydown', h); }, [open, onClose]);
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-[rgb(8_20_33/.45)] p-4 pt-[8vh] fade-in" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div role="dialog" aria-modal="true" aria-label={title} className={cx('w-full rounded-xl border border-line bg-surface shadow-pop', width)}>
        <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div><h2 className="text-[16px] font-bold">{title}</h2>{desc && <p className="mt-0.5 text-[12.5px] text-mute">{desc}</p>}</div>
          <IconButton icon={X} label="Tutup" onClick={onClose} />
        </div>
        <div className="px-5 py-4">{children}</div>
        {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-3">{footer}</div>}
      </div>
    </div>
  );
}
export function Drawer({ open, onClose, title, desc, children, footer, width = 'max-w-[560px]' }: { open: boolean; onClose: () => void; title: string; desc?: React.ReactNode; children: React.ReactNode; footer?: React.ReactNode; width?: string }) {
  useEffect(() => { if (!open) return; const h = (e: KeyboardEvent) => e.key === 'Escape' && onClose(); window.addEventListener('keydown', h); return () => window.removeEventListener('keydown', h); }, [open, onClose]);
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-[rgb(8_20_33/.4)] fade-in" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <aside role="dialog" aria-modal="true" aria-label={title} className={cx('slide-in flex h-full w-full flex-col border-l border-line bg-surface shadow-pop', width)}>
        <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div className="min-w-0"><h2 className="text-[16px] font-bold">{title}</h2>{desc && <div className="mt-0.5 text-[12.5px] text-mute">{desc}</div>}</div>
          <IconButton icon={X} label="Tutup" onClick={onClose} />
        </div>
        <div className="flex-1 overflow-y-auto px-5 py-4">{children}</div>
        {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-3">{footer}</div>}
      </aside>
    </div>
  );
}
export function ConfirmDialog({ open, onClose, onConfirm, title, desc, confirmLabel = 'Konfirmasi', tone = 'primary', needReason, reasonLabel = 'Alasan', children }: { open: boolean; onClose: () => void; onConfirm: (reason: string) => void; title: string; desc?: string; confirmLabel?: string; tone?: 'primary' | 'danger'; needReason?: boolean; reasonLabel?: string; children?: React.ReactNode }) {
  const [reason, setReason] = useState(''); const [err, setErr] = useState('');
  useEffect(() => { if (open) { setReason(''); setErr(''); } }, [open]);
  return (
    <Modal open={open} onClose={onClose} title={title} desc={desc} footer={<>
      <Button onClick={onClose}>Batal</Button>
      <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={() => { if (needReason && reason.trim().length < 5) { setErr('Tuliskan alasan minimal 5 karakter.'); return; } onConfirm(reason.trim()); onClose(); }}>{confirmLabel}</Button>
    </>}>
      {children}
      {needReason && <Field label={reasonLabel} required error={err} className="mt-3"><Textarea autoFocus value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Contoh: dokumen fisik sudah lengkap ditandatangani" /></Field>}
    </Modal>
  );
}

export function Tabs({ tabs, value, onChange, className }: { tabs: { key: string; label: string; count?: number | string; dot?: boolean }[]; value: string; onChange: (k: string) => void; className?: string }) {
  return (
    <div role="tablist" className={cx('flex gap-1 overflow-x-auto border-b border-line', className)}>
      {tabs.map((t) => (
        <button key={t.key} role="tab" aria-selected={value === t.key} onClick={() => onChange(t.key)}
          className={cx('relative flex items-center gap-1.5 whitespace-nowrap px-2.5 py-2.5 text-[13px] font-semibold transition', value === t.key ? 'text-brand' : 'text-mute hover:text-ink')}>
          {t.label}
          {t.count !== undefined && <span className={cx('rounded px-1.5 text-[11px] num', value === t.key ? 'bg-brandsoft text-brand' : 'bg-sunken text-mute')}>{t.count}</span>}
          {t.dot && <span className="h-1.5 w-1.5 rounded-full bg-warn" aria-label="perlu perhatian" />}
          {value === t.key && <span className="absolute inset-x-2 -bottom-px h-0.5 rounded bg-brand" />}
        </button>
      ))}
    </div>
  );
}
export function Segmented({ options, value, onChange }: { options: { value: string; label: string }[]; value: string; onChange: (v: string) => void }) {
  return (
    <div className="inline-flex rounded-lg border border-line bg-sunken p-0.5">
      {options.map((o) => <button key={o.value} type="button" onClick={() => onChange(o.value)} className={cx('rounded-md px-3 py-1 text-[12.5px] font-semibold transition', value === o.value ? 'bg-surface text-ink shadow-sm' : 'text-mute hover:text-ink')}>{o.label}</button>)}
    </div>
  );
}

export const th = 'px-3 py-2.5 text-left text-[11.5px] font-bold text-mute whitespace-nowrap bg-sunken/60 border-b border-line';
export const td = 'px-3 py-3 align-middle border-b border-line text-[13px]';
export function TableWrap({ children, className }: { children: React.ReactNode; className?: string }) { return <div className={cx('overflow-x-auto', className)}><table className="w-full border-collapse">{children}</table></div>; }

export function EmptyState({ icon: Icon = Info, title, desc, action, compact }: { icon?: LucideIcon; title: string; desc?: string; action?: React.ReactNode; compact?: boolean }) {
  return (
    <div className={cx('flex flex-col items-center justify-center text-center', compact ? 'px-4 py-6' : 'px-6 py-12')}>
      <span className="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-sunken text-mute"><Icon size={20} /></span>
      <p className="text-[14px] font-bold">{title}</p>
      {desc && <p className="mt-1 max-w-sm text-[12.5px] text-mute">{desc}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
export function Skeleton({ className }: { className?: string }) { return <div className={cx('skeleton rounded-md', className)} />; }

export function Menu({ items, label = 'Aksi lainnya' }: { items: { label: string; icon?: LucideIcon; onClick: () => void; danger?: boolean; disabled?: boolean }[]; label?: string }) {
  const [open, setOpen] = useState(false); const ref = useRef<HTMLDivElement>(null);
  useEffect(() => { const h = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); }; document.addEventListener('mousedown', h); return () => document.removeEventListener('mousedown', h); }, []);
  return (
    <div ref={ref} className="relative">
      <IconButton icon={MoreHorizontal} label={label} onClick={(e) => { e.stopPropagation(); setOpen(!open); }} className="border border-line" />
      {open && (
        <div className="absolute right-0 z-40 mt-1 min-w-[200px] rounded-lg border border-line bg-surface p-1 shadow-pop fade-in">
          {items.map((it) => (
            <button key={it.label} disabled={it.disabled} onClick={(e) => { e.stopPropagation(); setOpen(false); it.onClick(); }} className={cx('flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-[13px] hover:bg-sunken disabled:opacity-40', it.danger && 'text-bad')}>
              {it.icon && <it.icon size={15} />}{it.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

export function SearchInput({ value, onChange, placeholder = 'Cari…', className }: { value: string; onChange: (v: string) => void; placeholder?: string; className?: string }) {
  return (
    <div className={cx('relative', className)}>
      <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-mute" />
      <Input type="search" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-9" />
      {value && <button className="absolute right-2 top-1/2 -translate-y-1/2 text-mute hover:text-ink" aria-label="Bersihkan" onClick={() => onChange('')}><X size={14} /></button>}
    </div>
  );
}
export function KV({ items, cols = 2 }: { items: [string, React.ReactNode][]; cols?: 1 | 2 | 3 }) {
  return (
    <dl className={cx('grid gap-x-6 gap-y-3', cols === 1 ? 'grid-cols-1' : cols === 2 ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3')}>
      {items.map(([k, v]) => (
        <div key={k} className="min-w-0"><dt className="text-[12px] font-medium text-mute">{k}</dt><dd className="mt-0.5 break-words text-[13.5px] font-semibold text-ink">{v ?? '–'}</dd></div>
      ))}
    </dl>
  );
}
export function Bar({ value, max, tone = 'brand', marker }: { value: number; max: number; tone?: 'brand' | 'ok' | 'warn' | 'bad' | 'info'; marker?: number }) {
  const p = max > 0 ? Math.min(100, (value / max) * 100) : 0;
  const c = { brand: 'bg-brand', ok: 'bg-ok', warn: 'bg-warn', bad: 'bg-bad', info: 'bg-info' }[tone];
  return (
    <div className="relative h-1.5 w-full rounded-full bg-sunken">
      <div className={cx('h-1.5 rounded-full transition-all', c)} style={{ width: `${p}%` }} />
      {marker !== undefined && <span className="absolute -top-0.5 h-2.5 w-0.5 rounded bg-ink/40" style={{ left: `${marker}%` }} title="Ambang peringatan" />}
    </div>
  );
}

export interface SearchOpt { value: string; label: string; sub?: string | null; group?: string }

/** Dropdown dengan kotak pencarian. Dipakai untuk pilihan yang panjang, seperti daftar PIC. */
export function SearchSelect({ value, onChange, options, placeholder = 'Pilih', searchPlaceholder = 'Cari nama, jabatan atau peran', invalid, disabled, ariaLabel }: {
  value: string; onChange: (v: string) => void; options: SearchOpt[]; placeholder?: string; searchPlaceholder?: string; invalid?: boolean; disabled?: boolean; ariaLabel?: string;
}) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [hi, setHi] = useState(0);
  const ref = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (!open) return;
    setQ(''); setHi(0);
    setTimeout(() => inputRef.current?.focus(), 0);
    const close = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, [open]);
  const sel = options.find((o) => o.value === value);
  const term = q.trim().toLowerCase();
  const list = options.filter((o) => !term || `${o.label} ${o.sub || ''} ${o.group || ''}`.toLowerCase().includes(term));
  const pick = (v: string) => { onChange(v); setOpen(false); };
  const onKey = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setHi((h) => Math.min(list.length - 1, h + 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setHi((h) => Math.max(0, h - 1)); }
    else if (e.key === 'Enter') { e.preventDefault(); if (list[hi]) pick(list[hi].value); }
    else if (e.key === 'Escape') setOpen(false);
  };
  let lastGroup: string | undefined;
  return (
    <div ref={ref} className="relative">
      <button type="button" disabled={disabled} aria-haspopup="listbox" aria-expanded={open} aria-label={ariaLabel}
        onClick={() => setOpen(!open)}
        className={cx(inputCls, 'flex items-center justify-between gap-2 text-left disabled:opacity-60', invalid && 'border-bad ring-2 ring-bad/15')}>
        <span className={cx('min-w-0 truncate', !sel && 'text-mute')}>{sel ? sel.label : placeholder}{sel?.sub ? <span className="text-mute"> · {sel.sub}</span> : null}</span>
        <ChevronDown size={15} className="shrink-0 text-mute" />
      </button>
      {open && (
        <div className="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-lg border border-line bg-surface shadow-pop" onKeyDown={onKey}>
          <div className="relative border-b border-line p-2">
            <Search size={14} className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-mute" />
            <input ref={inputRef} type="search" value={q} onChange={(e) => { setQ(e.target.value); setHi(0); }} placeholder={searchPlaceholder} aria-label={searchPlaceholder}
              className="h-8 w-full rounded-md border border-line bg-surface pl-8 pr-2 text-[13px] outline-none focus:border-brand" />
          </div>
          <ul role="listbox" className="max-h-64 overflow-y-auto py-1 text-[13px]">
            <li><button type="button" onClick={() => pick('')} className="w-full px-3 py-1.5 text-left text-mute hover:bg-sunken">{placeholder}</button></li>
            {list.length === 0 && <li className="px-3 py-2 text-mute">Tidak ditemukan.</li>}
            {list.map((o, i) => {
              const head = o.group && o.group !== lastGroup ? o.group : null;
              lastGroup = o.group;
              return (
                <React.Fragment key={o.value}>
                  {head && <li className="px-3 pb-1 pt-2 text-[11px] font-bold uppercase tracking-wide text-mute">{head}</li>}
                  <li role="option" aria-selected={o.value === value}>
                    <button type="button" onMouseEnter={() => setHi(i)} onClick={() => pick(o.value)}
                      className={cx('flex w-full items-center gap-2 px-3 py-1.5 text-left', i === hi && 'bg-sunken', o.value === value && 'font-semibold text-brand')}>
                      <span className="min-w-0 flex-1 truncate">{o.label}{o.sub ? <span className="font-normal text-mute"> · {o.sub}</span> : null}</span>
                      {o.value === value && <Check size={14} className="shrink-0" />}
                    </button>
                  </li>
                </React.Fragment>
              );
            })}
          </ul>
        </div>
      )}
    </div>
  );
}
