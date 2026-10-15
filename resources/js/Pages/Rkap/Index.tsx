import React, { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { AlertTriangle, Landmark, PiggyBank, Plus, Wallet, TrendingDown } from 'lucide-react';
import { Badge, Button, Card, EmptyState, SearchInput, Segmented, TableWrap, cx, td, th } from '@/Components/ui';
import { MetricCard, PageHeader, RowLink } from '@/Components/common';
import { Pager, usePaged } from '@/Components/Pagination';
import { rp, rpShort } from '@/lib/format';
import type { PageProps } from '@/types';
import PrkModal, { type PrkRow } from '@/Components/rkap/PrkModal';

export interface RkapRow extends PrkRow { used: number; remaining: number; percent: number | null; count: number }

/** RKAP: daftar PRK seperti daftar pengadaan; detail per PRK berisi persentase terpakai dan pengadaannya. */
export default function RkapIndex({ rows }: { rows: RkapRow[] }) {
  const { auth } = usePage<PageProps>().props;
  const [q, setQ] = useState('');
  // Sub bidang dipilih lebih dulu; RKAP ditampilkan per sub bidang.
  // PRK tanpa sub bidang dikumpulkan di tab tersendiri agar tidak hilang dari daftar.
  const NONE = 'tanpa';
  const subs = [...new Set(rows.map((r) => r.sub_bidang).filter(Boolean))].sort() as string[];
  const tabs = [...subs.map((x) => ({ value: x, label: x })), ...(rows.some((r) => !r.sub_bidang) ? [{ value: NONE, label: 'Tanpa sub bidang' }] : [])];
  const [sub, setSub] = useState(() => { let v = ''; try { v = localStorage.getItem('procfolio-rkap-sub') || ''; } catch { /* abaikan */ } return tabs.some((t) => t.value === v) ? v : tabs[0]?.value || ''; });
  useEffect(() => { try { localStorage.setItem('procfolio-rkap-sub', sub); } catch { /* abaikan */ } }, [sub]);
  const [onlyMinus, setOnlyMinus] = useState(false);
  const [edit, setEdit] = useState<PrkRow | null | 'new'>(null);
  const inSub = rows.filter((r) => (sub && sub !== NONE ? r.sub_bidang === sub : !r.sub_bidang));
  const view = inSub.filter((r) => (!onlyMinus || r.remaining < 0) && (!q || [r.code, r.program, r.coa, r.cost_center].join(' ').toLowerCase().includes(q.toLowerCase())));
  const total = inSub.reduce((a, r) => ({ pagu: a.pagu + r.budget_amount, used: a.used + r.used }), { pagu: 0, used: 0 });
  const minus = inSub.filter((r) => r.remaining < 0);
  const pg = usePaged(view, JSON.stringify([sub, q, onlyMinus]));

  return (
    <>
      <Head title="RKAP" />
      <PageHeader title="Monitoring Penetapan RKAP" desc=""
        meta={<div className="flex flex-wrap items-center gap-2"><span className="text-[12.5px] text-mute">Sub bidang</span><Segmented value={sub} onChange={setSub} options={tabs} /><span className="num text-[12.5px] text-mute">{inSub.length} PRK</span></div>}
        actions={auth.can.edit && <Button variant="primary" icon={Plus} onClick={() => setEdit('new')}>Tambah PRK</Button>} />
      <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Nilai Pengajuan" value={rpShort(total.pagu)} icon={Landmark} sub={''} />
        <MetricCard label="Nilai Digunakan" value={rpShort(total.used)} sub={total.pagu ? `${((total.used / total.pagu) * 100).toFixed(1)}% dari pengajuan` : 'Nilai pengajuan belum diisi'} icon={Wallet} tone="info" />
        <MetricCard label="Sisa Anggaran" value={rpShort(total.pagu - total.used)} icon={PiggyBank} tone="info" sub={''} />
        <MetricCard label="PRK Minus" value={minus.length} sub="RAB melebihi pagu" icon={TrendingDown} tone={minus.length ? 'bad' : 'info'} onClick={() => setOnlyMinus(!onlyMinus)} active={onlyMinus} />
      </div>
      {minus.length > 0 && (
        <div className="mb-5 flex items-start gap-2.5 rounded-xl border border-bad/30 bg-badsoft px-4 py-3 text-[13px]">
          <AlertTriangle size={17} className="mt-0.5 shrink-0 text-bad" />
          <div><b className="text-bad">Terdapat anggaran minus</b> pada {minus.map((r) => r.code).join(', ')}</div>
        </div>
      )}
      <Card className="mb-4 p-3"><SearchInput value={q} onChange={setQ} placeholder="Cari kode anggaran, program kerja, COA atau cost center" /></Card>
      <Card>
        {view.length === 0 ? <EmptyState icon={Landmark} title="Tidak ada PRK yang cocok" /> : (
          <TableWrap>
            <thead><tr>{['Kode Anggaran', 'COA', 'Cost Center', 'Sub Bidang', 'Nilai Pengajuan', 'Nilai Digunakan', 'Sisa Anggaran'].map((x) => <th key={x} className={cx(th, ['Nilai Pengajuan', 'Nilai Digunakan', 'Sisa Anggaran'].includes(x) && 'text-right')}>{x}</th>)}</tr></thead>
            <tbody>{pg.view.map((r) => (
              <RowLink key={r.id} href={`/rkap/${r.id}`} className={cx(!r.active && 'opacity-60')}>
                <td className={cx(td, 'min-w-[260px]')}><div className="num font-bold text-navy">{r.code}</div><div className="text-[12px] text-mute">{r.program}</div></td>
                <td className={cx(td, 'num')}>{r.coa}</td>
                <td className={cx(td, 'min-w-[200px]')}>{r.cost_center}</td>
                <td className={td}>{r.sub_bidang || '–'}</td>
                <td className={cx(td, 'num whitespace-nowrap text-right')}>{rp(r.budget_amount)}</td>
                <td className={cx(td, 'num whitespace-nowrap text-right')}>{rp(r.used)}{r.percent !== null && <span className="block text-[11.5px] text-mute">{r.percent}%</span>}</td>
                <td className={cx(td, 'whitespace-nowrap text-right')}><span className={cx('num font-bold', r.remaining < 0 && 'text-bad')}>{rp(r.remaining)}</span>{r.remaining < 0 && <Badge tone="bad" className="ml-2">Minus</Badge>}</td>
              </RowLink>
            ))}</tbody>
          </TableWrap>
        )}
        <Pager p={pg} label="PRK" />
      </Card>
      <PrkModal item={edit} onClose={() => setEdit(null)} defaults={{ cost_center: inSub[0]?.cost_center, sub_bidang: sub === NONE ? '' : sub }} />
    </>
  );
}
