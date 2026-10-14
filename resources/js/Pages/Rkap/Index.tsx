import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { AlertTriangle, Landmark, PiggyBank, Plus, Wallet, TrendingDown } from 'lucide-react';
import { Badge, Button, Card, EmptyState, SearchInput, Select, TableWrap, cx, td, th } from '@/Components/ui';
import { MetricCard, PageHeader, RowLink } from '@/Components/common';
import { rp, rpShort } from '@/lib/format';
import type { PageProps } from '@/types';
import PrkModal, { type PrkRow } from '@/Components/rkap/PrkModal';

export interface RkapRow extends PrkRow { used: number; remaining: number; percent: number | null; count: number }

/** RKAP: daftar PRK seperti daftar pengadaan; detail per PRK berisi persentase terpakai dan pengadaannya. */
export default function RkapIndex({ rows }: { rows: RkapRow[] }) {
  const { auth } = usePage<PageProps>().props;
  const [q, setQ] = useState('');
  const [sub, setSub] = useState('');
  const [onlyMinus, setOnlyMinus] = useState(false);
  const [edit, setEdit] = useState<PrkRow | null | 'new'>(null);
  const subs = [...new Set(rows.map((r) => r.sub_bidang).filter(Boolean))] as string[];
  const view = rows.filter((r) => (!sub || r.sub_bidang === sub) && (!onlyMinus || r.remaining < 0) && (!q || [r.code, r.program, r.coa, r.cost_center].join(' ').toLowerCase().includes(q.toLowerCase())));
  const total = rows.reduce((a, r) => ({ pagu: a.pagu + r.budget_amount, used: a.used + r.used }), { pagu: 0, used: 0 });
  const minus = rows.filter((r) => r.remaining < 0);

  return (
    <>
      <Head title="RKAP" />
      <PageHeader title="RKAP" desc="Pagu anggaran per PRK. Sisa berkurang otomatis sesuai RAB pengadaan yang memakai PRK tersebut. Buka baris untuk melihat rinciannya."
        actions={auth.can.edit && <Button variant="primary" icon={Plus} onClick={() => setEdit('new')}>Tambah PRK</Button>} />
      <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Pagu" value={rpShort(total.pagu)} sub={`${rows.length} PRK`} icon={Landmark} />
        <MetricCard label="Terpakai" value={rpShort(total.used)} sub={total.pagu ? `${((total.used / total.pagu) * 100).toFixed(1)}% dari pagu` : 'Pagu belum diisi'} icon={Wallet} tone="info" />
        <MetricCard label="Sisa Anggaran" value={rpShort(total.pagu - total.used)} sub="Pagu dikurangi RAB pengadaan aktif" icon={PiggyBank} tone="info" />
        <MetricCard label="PRK Minus" value={minus.length} sub="RAB melebihi pagu" icon={TrendingDown} tone={minus.length ? 'bad' : 'info'} onClick={() => setOnlyMinus(!onlyMinus)} active={onlyMinus} />
      </div>
      {minus.length > 0 && (
        <div className="mb-5 flex items-start gap-2.5 rounded-xl border border-bad/30 bg-badsoft px-4 py-3 text-[13px]">
          <AlertTriangle size={17} className="mt-0.5 shrink-0 text-bad" />
          <div><b className="text-bad">Terdapat anggaran minus</b> pada {minus.map((r) => r.code).join(', ')}. Pengadaan tetap berjalan; tinjau pagu atau RAB terkait.</div>
        </div>
      )}
      <Card className="mb-4 flex flex-wrap items-center gap-2 p-3">
        <SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari kode anggaran, program kerja, COA atau cost center" />
        <div className="w-[180px]"><Select value={sub} onChange={(e) => setSub(e.target.value)} aria-label="Sub bidang"><option value="">Semua sub bidang</option>{subs.map((x) => <option key={x}>{x}</option>)}</Select></div>
      </Card>
      <Card>
        {view.length === 0 ? <EmptyState icon={Landmark} title="Tidak ada PRK yang cocok" /> : (
          <TableWrap>
            <thead><tr>{['Kode Anggaran', 'COA', 'Cost Center', 'Sub Bidang', 'Sisa Anggaran'].map((x) => <th key={x} className={cx(th, x === 'Sisa Anggaran' && 'text-right')}>{x}</th>)}</tr></thead>
            <tbody>{view.map((r) => (
              <RowLink key={r.id} href={`/rkap/${r.id}`} className={cx(!r.active && 'opacity-60')}>
                <td className={cx(td, 'min-w-[260px]')}><div className="num font-bold text-navy">{r.code}</div><div className="text-[12px] text-mute">{r.program}</div></td>
                <td className={cx(td, 'num')}>{r.coa}</td>
                <td className={cx(td, 'min-w-[200px]')}>{r.cost_center}</td>
                <td className={td}>{r.sub_bidang || '–'}</td>
                <td className={cx(td, 'whitespace-nowrap text-right')}><span className={cx('num font-bold', r.remaining < 0 && 'text-bad')}>{rp(r.remaining)}</span>{r.remaining < 0 && <Badge tone="bad" className="ml-2">Minus</Badge>}</td>
              </RowLink>
            ))}</tbody>
          </TableWrap>
        )}
      </Card>
      <PrkModal item={edit} onClose={() => setEdit(null)} defaults={rows[0]} />
    </>
  );
}
