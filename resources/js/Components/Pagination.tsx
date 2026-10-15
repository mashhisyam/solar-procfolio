import React, { useEffect, useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { cx } from '@/Components/ui';

export const PAGE_SIZES = [10, 25, 50, 100] as const;
const STORE_KEY = 'procfolio-page-size';

function readSize(fallback: number): number {
  try { const n = Number(localStorage.getItem(STORE_KEY)); return (PAGE_SIZES as readonly number[]).includes(n) ? n : fallback; } catch { return fallback; }
}

export interface Paged<T> {
  view: T[]; page: number; pages: number; size: number; total: number;
  setPage: (n: number) => void; setSize: (n: number) => void;
}

/**
 * Paginasi sisi tampilan untuk satu daftar. Jumlah baris per halaman diingat di peramban dan berlaku untuk semua daftar.
 * Halaman kembali ke 1 bila jumlah baris atau `resetKey` (mis. gabungan filter dan kata kunci) berubah.
 */
export function usePaged<T>(rows: T[], resetKey: string = '', initialSize = 25): Paged<T> {
  const [size, setSizeState] = useState(() => readSize(initialSize));
  const [page, setPage] = useState(1);
  const total = rows.length;
  const pages = Math.max(1, Math.ceil(total / size));
  useEffect(() => { setPage(1); }, [total, resetKey]);
  useEffect(() => { if (page > pages) setPage(pages); }, [page, pages]);
  const view = useMemo(() => rows.slice((page - 1) * size, page * size), [rows, page, size]);
  const setSize = (n: number) => { setSizeState(n); setPage(1); try { localStorage.setItem(STORE_KEY, String(n)); } catch { /* abaikan */ } };
  return { view, page, pages, size, total, setPage, setSize };
}

/** Bilah paginasi: info jumlah, pilihan baris per halaman, dan tombol halaman. */
export function Pager<T>({ p, className, label = 'data' }: { p: Paged<T>; className?: string; label?: string }) {
  if (p.total === 0) return null;
  const from = (p.page - 1) * p.size + 1;
  const to = Math.min(p.total, p.page * p.size);
  const nums = pageNumbers(p.page, p.pages);
  const btn = 'flex h-8 min-w-8 items-center justify-center rounded-lg border border-line px-2 text-[12.5px] font-semibold text-ink/80 hover:bg-sunken disabled:cursor-not-allowed disabled:opacity-40';
  return (
    <div className={cx('flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3 text-[12.5px]', className)}>
      <div className="flex flex-wrap items-center gap-3 text-mute">
        <span className="num"><b className="text-ink">{from}–{to}</b> dari <b className="text-ink">{p.total}</b> {label}</span>
        <label className="flex items-center gap-1.5">Tampilkan
          <select value={p.size} onChange={(e) => p.setSize(Number(e.target.value))} aria-label="Jumlah baris per halaman"
            className="h-8 rounded-lg border border-line bg-surface px-2 text-[12.5px] font-semibold text-ink focus:border-brand focus:outline-none">
            {PAGE_SIZES.map((n) => <option key={n} value={n}>{n}</option>)}
          </select>per halaman
        </label>
      </div>
      {p.pages > 1 && (
        <nav className="flex items-center gap-1" aria-label="Halaman">
          <button className={btn} disabled={p.page <= 1} onClick={() => p.setPage(1)} aria-label="Halaman pertama"><ChevronsLeft size={14} /></button>
          <button className={btn} disabled={p.page <= 1} onClick={() => p.setPage(p.page - 1)} aria-label="Halaman sebelumnya"><ChevronLeft size={14} /></button>
          {nums.map((n, i) => n === 0
            ? <span key={`gap${i}`} className="px-1 text-mute">…</span>
            : <button key={n} className={cx(btn, 'num', n === p.page && 'border-brand bg-brandsoft text-brand')} aria-current={n === p.page ? 'page' : undefined} onClick={() => p.setPage(n)}>{n}</button>)}
          <button className={btn} disabled={p.page >= p.pages} onClick={() => p.setPage(p.page + 1)} aria-label="Halaman berikutnya"><ChevronRight size={14} /></button>
          <button className={btn} disabled={p.page >= p.pages} onClick={() => p.setPage(p.pages)} aria-label="Halaman terakhir"><ChevronsRight size={14} /></button>
        </nav>
      )}
    </div>
  );
}

/** Nomor halaman ringkas, 0 = celah (…). */
function pageNumbers(cur: number, last: number): number[] {
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
  const set = new Set([1, last, cur - 1, cur, cur + 1].filter((n) => n >= 1 && n <= last));
  const sorted = [...set].sort((a, b) => a - b);
  const out: number[] = [];
  sorted.forEach((n, i) => { if (i > 0 && n - sorted[i - 1] > 1) out.push(0); out.push(n); });
  return out;
}
