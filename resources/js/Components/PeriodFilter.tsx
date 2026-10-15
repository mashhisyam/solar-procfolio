import React from 'react';
import { router, usePage } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import { Segmented, cx } from '@/Components/ui';
import { fmtDate } from '@/lib/format';
import type { PageProps } from '@/types';

/** Ganti periode lalu muat ulang data halaman ini. Parameter lain di URL dipertahankan. */
export function setPeriod(value: string) {
  const q = new URLSearchParams(window.location.search);
  q.set('periode', value);
  router.get(window.location.pathname, Object.fromEntries(q.entries()), { preserveScroll: true, preserveState: true, replace: true });
}

/**
 * Filter periode pengadaan berdasarkan tanggal registrasi: 2 bulan (bawaan), 6 bulan, 1 tahun, atau semua.
 * Pilihan disimpan di sesi sehingga berlaku juga di menu lain.
 */
export function PeriodFilter({ className, compact }: { className?: string; compact?: boolean }) {
  const { period } = usePage<PageProps>().props;
  return (
    <div className={cx('flex flex-wrap items-center gap-2', className)}>
      {!compact && <span className="flex items-center gap-1.5 text-[12.5px] font-semibold text-mute"><CalendarRange size={14} />Periode registrasi</span>}
      <Segmented value={period.key} onChange={setPeriod} options={period.options} />
      {period.since && !compact && <span className="text-[12px] text-mute">sejak {fmtDate(period.since)}</span>}
    </div>
  );
}
