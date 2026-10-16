import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Workflow, Flame, BellRing, Wallet, RotateCcw, Filter, CheckCheck, TrendingUp, Route, Trophy, ClipboardList, FileSignature, Scale, XCircle, Ban, AlertTriangle, ArrowRight, Shapes } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, Segmented, Select, cx } from '@/Components/ui';
import { MetricCard, PageHeader } from '@/Components/common';
import { PeriodFilter } from '@/Components/PeriodFilter';
import { Pager, usePaged } from '@/Components/Pagination';
import { fmtDateLong, rp, rpShort } from '@/lib/format';
import { METHODS } from '@/lib/scheme';
import { useAccess } from '@/lib/access';
import type { PageProps } from '@/types';

type Ident = { code: string; spp_number: string | null; title: string; method: string; note?: string };
type TimeItem = Ident & { days: number; done: boolean };
type ValueItem = Ident & { value: number };
type DaysItem = Ident & { days: number };
type VendorItem = { vendor: string; count: number; value: number; sp2k: number; po: number };
interface Position { name: string; track: 'pengadaan' | 'pasca'; count: number; spp: number; kemitraan: number; spr: number; stage_avg: number | null; stage_max: number | null; process_avg: number | null }
interface CcRow { id: number; kind: string; number: string; title: string; customer: string | null; revenue: number; rab: number; contract_value: number; count: number; contracted: number }
interface Props {
  kpi: { active: number; spp: number; kemitraan: number; spr: number; revision_major: number; revision_minor: number; urgent: number; attention: number; done: number; unpaid_terms: number; unpaid_value: number };
  money: { rab_proposed: number; rab_proposed_count: number; rab_realized: number; rab_realized_count: number; revenue: number };
  per_customer_contract: CcRow[];
  positions: Position[];
  top: {
    time: { slowest: TimeItem[]; fastest: TimeItem[] };
    value: Record<'SP2K' | 'PO', { largest: ValueItem[]; smallest: ValueItem[] }>;
    approval: { groups: { name: string; avg: number | null; count: number }[]; items: Record<string, { fastest: DaysItem[]; slowest: DaysItem[] }> };
    vendor: { by_count: VendorItem[]; by_value: VendorItem[] };
    category: { name: string; count: number; rab: number; contracted: number }[];
  };
  stopped: { gagal: number; gagal_rab: number; batal: number; batal_rab: number; by_position: { name: string; gagal: number; batal: number }[] };
  filters: { method: string; priority: string; pengaju: number; cc: number };
  options: { pengaju: { id: number; name: string }[]; customerContracts: { id: number; kind: string; number: string }[] };
}

/** Parameter URL dashboard; periode tidak ikut karena disimpan di sesi. */
const QUERY: Record<keyof Props['filters'], string> = { method: 'skema', priority: 'prioritas', pengaju: 'pengaju', cc: 'perjanjian' };

export default function Dashboard({ kpi, money, per_customer_contract: perCc, positions, top, stopped, filters, options }: Props) {
  const { app, auth } = usePage<PageProps>().props;
  const access = useAccess();
  const apply = (patch: Partial<Props['filters']>) => {
    const next = { ...filters, ...patch };
    const q: Record<string, string> = {};
    (Object.keys(QUERY) as (keyof Props['filters'])[]).forEach((k) => { if (next[k]) q[QUERY[k]] = String(next[k]); });
    router.get('/', q, { preserveScroll: true, preserveState: true, replace: true });
  };
  const hasFilter = !!(filters.method || filters.priority || filters.pengaju || filters.cc);

  return (
    <>
      <Head title="Dashboard" />
      <PageHeader title="Dashboard Monitoring"
        meta={<span className="text-[12.5px] text-mute">Data per {fmtDateLong(app.today)}</span>}
        actions={access.canEdit('pengadaan') && <Link href="/pengadaan/baru"><Button variant="primary" icon={Plus}>Tambah Pengadaan</Button></Link>} />

      <div className="mb-5 space-y-2.5 rounded-xl border border-line bg-surface p-3 shadow-card">
        <PeriodFilter />
        <div className="flex flex-wrap items-center gap-2">
          <span className="flex items-center gap-1.5 px-0.5 text-[12.5px] font-semibold text-mute"><Filter size={14} />Filter</span>
          <div className="w-[160px]"><Select value={filters.method} onChange={(e) => apply({ method: e.target.value })} aria-label="Skema"><option value="">Semua skema</option>{METHODS.map((m) => <option key={m}>{m}</option>)}</Select></div>
          <div className="w-[165px]"><Select value={filters.priority} onChange={(e) => apply({ priority: e.target.value })} aria-label="Prioritas"><option value="">Semua prioritas</option><option>Urgent</option><option>Normal</option></Select></div>
          <div className="w-[210px]"><Select value={filters.pengaju || ''} onChange={(e) => apply({ pengaju: Number(e.target.value) || 0 })} aria-label="User Pengaju"><option value="">Semua User Pengaju</option>{options.pengaju.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</Select></div>
          <div className="w-[240px]"><Select value={filters.cc || ''} onChange={(e) => apply({ cc: Number(e.target.value) || 0 })} aria-label="Perjanjian pelanggan"><option value="">Semua perjanjian pelanggan</option>{options.customerContracts.map((c) => <option key={c.id} value={c.id}>{c.kind} · {c.number}</option>)}</Select></div>
          {hasFilter && <Button size="sm" variant="ghost" icon={RotateCcw} onClick={() => apply({ method: '', priority: '', pengaju: 0, cc: 0 })}>Reset</Button>}
        </div>
      </div>

      {kpi.revision_major + kpi.revision_minor > 0 && (
        <Link href="/pengadaan?seg=revisi" role="alert" className="mb-4 flex flex-wrap items-center gap-3 rounded-xl border-2 border-bad bg-badsoft px-4 py-3 text-[13.5px] shadow-card hover:bg-badsoft/80">
          <span className="flex h-9 w-9 items-center justify-center rounded-full bg-bad text-white"><AlertTriangle size={18} /></span>
          <span className="min-w-0 flex-1"><b className="text-bad">{kpi.revision_major + kpi.revision_minor} pengadaan menunggu revisi pengaju.</b> <span className="text-ink/80">Revisi Mayor mengulang seluruh approval; makin lama diperbaiki, makin panjang proses.</span></span>
          {kpi.revision_major > 0 && <span className="num rounded-full bg-bad px-2.5 py-1 text-[12px] font-extrabold uppercase text-white">Mayor {kpi.revision_major}</span>}
          {kpi.revision_minor > 0 && <span className="num rounded-full bg-warn px-2.5 py-1 text-[12px] font-extrabold uppercase text-white">Minor {kpi.revision_minor}</span>}
          <span className="flex items-center gap-1 font-semibold text-bad">Lihat daftar <ArrowRight size={14} /></span>
        </Link>
      )}
      <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
        <Link href="/pengadaan" className="contents"><MetricCard label="Pengadaan Aktif" value={kpi.active} sub={`SPP Pusat ${kpi.spp} · Kemitraan ${kpi.kemitraan} · SPR ${kpi.spr}`} icon={Workflow} /></Link>
        <Link href="/pengadaan?seg=urgent" className="contents"><MetricCard label="Urgent" value={kpi.urgent} sub="Pengadaan aktif berprioritas Urgent" icon={Flame} tone="bad" /></Link>
        <Link href="/pengadaan?seg=perhatian" className="contents"><MetricCard label="Butuh Perhatian" value={kpi.attention} sub="Durasi kuning/merah, terlambat, atau tertahan" icon={BellRing} tone="warn" /></Link>
        <Link href="/pengadaan?seg=selesai" className="contents"><MetricCard label="Selesai" value={kpi.done} sub="Seluruh tahap telah dibayar" icon={CheckCheck} tone="info" /></Link>
        <Link href="/pengadaan?seg=gagal" className="contents"><MetricCard label="Pengadaan Gagal" value={stopped.gagal} sub={`RAB ${rpShort(stopped.gagal_rab)} · berhenti di unit pengadaan`} icon={XCircle} tone="bad" /></Link>
        <Link href="/pengadaan?seg=dibatalkan" className="contents"><MetricCard label="Pengadaan Dibatalkan" value={stopped.batal} sub={`RAB ${rpShort(stopped.batal_rab)} · sebelum unit pengadaan`} icon={Ban} /></Link>
      </div>
      <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="RAB Usulan Pengadaan" value={rpShort(money.rab_proposed)} sub={`Total RAB ${money.rab_proposed_count} pengadaan`} icon={ClipboardList} />
        <MetricCard label="RAB Realisasi (Terkontrak)" value={rpShort(money.rab_realized)} sub={`Nilai SP2K/PO setelah PPN · ${money.rab_realized_count} pengadaan`} icon={FileSignature} tone="info" />
        <MetricCard label="Revenue" value={rpShort(money.revenue)} sub="Dari perjanjian pelanggan terkait" icon={TrendingUp} tone="info" />
        <Link href="/tahapan" className="contents"><MetricCard label="Tahap Belum Dibayar" value={kpi.unpaid_terms} sub={`Nilai ${rpShort(kpi.unpaid_value)}`} icon={Wallet} tone="info" /></Link>
      </div>

      <RevenueVsCost rows={perCc} />
      <PositionSpread positions={positions} />
      <StoppedByPosition rows={stopped.by_position} />
      <TopFive top={top} />
    </>
  );
}

const C = { revenue: 'bg-teal', rab: 'bg-sky', contract: 'bg-navy' };

/** Revenue vs Cost per perjanjian pelanggan: Revenue, RAB Pengadaan, dan Nilai SP2K. */
function RevenueVsCost({ rows }: { rows: CcRow[] }) {
  const pg = usePaged(rows, '', 10);
  const max = Math.max(1, ...rows.flatMap((r) => [r.revenue, r.rab, r.contract_value]));
  const bar = (v: number, cls: string, label: string) => (
    <div className="flex items-center gap-2" title={`${label}: ${rp(v)}`}>
      <span className="w-[96px] shrink-0 text-[11.5px] text-mute">{label}</span>
      <div className="h-2.5 min-w-0 flex-1 rounded-full bg-sunken"><div className={cx('h-2.5 rounded-full', cls)} style={{ width: `${(v / max) * 100}%` }} /></div>
      <span className="num w-[92px] shrink-0 text-right text-[12px] font-semibold">{v ? rpShort(v) : '–'}</span>
    </div>
  );
  return (
    <Card className="mb-5">
      <CardHead icon={Scale} title="Revenue vs Cost per Perjanjian Pelanggan"
        actions={<div className="flex flex-wrap items-center gap-3 text-[12px] text-mute">{([['Revenue', C.revenue], ['RAB Pengadaan', C.rab], ['Nilai SP2K', C.contract]] as const).map(([l, c]) => <span key={l} className="flex items-center gap-1.5"><span className={cx('h-2.5 w-2.5 rounded-sm', c)} />{l}</span>)}</div>} />
      {rows.length === 0 ? <EmptyState compact title="Belum ada pengadaan yang merujuk perjanjian pelanggan" /> : (
        <>
          <ul className="divide-y divide-line">{pg.view.map((r) => (
            <li key={r.id} className="grid grid-cols-1 gap-3 px-5 py-3.5 lg:grid-cols-[minmax(220px,320px)_1fr]">
              <Link href={`/bakb/${r.id}`} className="min-w-0 hover:text-brand">
                <span className="flex flex-wrap items-center gap-1.5"><Badge tone={r.kind.startsWith('Berita Acara') ? 'navy' : r.kind === 'Kontrak Perjanjian' ? 'brand' : 'teal'}>{r.kind.startsWith('Berita Acara') ? 'BAKB' : r.kind}</Badge><span className="num text-[13px] font-bold">{r.number}</span></span>
                <span className="mt-0.5 block truncate text-[12.5px] text-mute" title={r.title}>{r.title}</span>
                <span className="block text-[11.5px] text-mute">{r.customer || '–'} · {r.count} pengadaan, {r.contracted} terkontrak</span>
              </Link>
              <div className="space-y-1.5 self-center">{bar(r.revenue, C.revenue, 'Revenue')}{bar(r.rab, C.rab, 'RAB Pengadaan')}{bar(r.contract_value, C.contract, 'Nilai SP2K')}</div>
            </li>
          ))}</ul>
          <p className="border-t border-line px-5 py-2 text-[11.5px] text-mute">Nilai SP2K setelah PPN; untuk SPP Kemitraan memakai nilai PO.</p>
          <Pager p={pg} label="perjanjian" />
        </>
      )}
    </Card>
  );
}

/** Persebaran pengadaan aktif pada setiap posisi, dari Pengaju sampai Antri Bayar. */
function PositionSpread({ positions }: { positions: Position[] }) {
  const max = Math.max(1, ...positions.map((p) => p.count));
  const total = positions.reduce((a, p) => a + p.count, 0);
  const go = (name: string) => router.visit(`/pengadaan?posisi=${encodeURIComponent(name)}`);
  const section = (track: 'pengadaan' | 'pasca', title: string) => {
    const list = positions.filter((p) => p.track === track);
    return (
      <div className="min-w-0">
        <p className="mb-2 text-[12px] font-bold text-mute">{title} <span className="num font-semibold">({list.reduce((a, p) => a + p.count, 0)})</span></p>
        <div className="overflow-x-auto pb-1">
          <div className="flex min-w-max items-end gap-2" style={{ height: 190 }}>
            {list.map((p) => (
              <button key={p.name} onClick={() => p.count && go(p.name)} disabled={!p.count}
                title={`${p.name}: ${p.count} pengadaan (SPP Pusat ${p.spp}, Kemitraan ${p.kemitraan}, SPR ${p.spr})${p.stage_avg !== null ? ` · rata-rata ${p.stage_avg} hari di tahapan` : ''}`}
                className={cx('group flex h-full w-[74px] flex-col items-center justify-end', p.count ? 'cursor-pointer' : 'cursor-default')}>
                <span className={cx('num mb-1 text-[13px] font-extrabold', p.count ? 'text-ink' : 'text-mute/60')}>{p.count}</span>
                <span className="flex w-9 flex-col-reverse overflow-hidden rounded-t-md bg-sunken transition group-hover:opacity-80" style={{ height: `${Math.max(4, (p.count / max) * 130)}px` }}>
                  {p.spp > 0 && <span className="w-full bg-navy" style={{ height: `${(p.spp / Math.max(1, p.count)) * 100}%` }} />}
                  {p.kemitraan > 0 && <span className="w-full bg-teal" style={{ height: `${(p.kemitraan / Math.max(1, p.count)) * 100}%` }} />}
                  {p.spr > 0 && <span className="w-full bg-brand" style={{ height: `${(p.spr / Math.max(1, p.count)) * 100}%` }} />}
                </span>
                <span className="mt-1.5 line-clamp-2 h-[30px] text-center text-[11px] font-semibold leading-tight text-ink/80">{p.name}</span>
              </button>
            ))}
          </div>
        </div>
      </div>
    );
  };
  const rows = positions.filter((p) => p.count > 0);
  return (
    <Card className="mb-5">
      <CardHead icon={Route} title="Persebaran Proses Pengadaan per Posisi" desc={`${total} pengadaan aktif. Klik batang untuk membuka daftarnya.`}
        actions={<div className="flex items-center gap-3 text-[12px] text-mute"><span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-navy" />SPP Pusat</span><span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-teal" />SPP Kemitraan</span><span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-brand" />SPR</span></div>} />
      <div className="grid grid-cols-1 gap-6 px-5 pb-4 pt-1 2xl:grid-cols-2">{section('pengadaan', 'Proses Pengadaan')}{section('pasca', 'Pasca Pengadaan')}</div>
      {rows.length > 0 && (
        <div className="overflow-x-auto border-t border-line">
          <table className="w-full min-w-[640px] text-[12.5px]">
            <thead><tr className="border-b border-line text-left text-[11.5px] text-mute"><th className="px-5 py-2 font-semibold">Posisi</th><th className="px-3 py-2 text-right font-semibold">Jumlah</th><th className="px-3 py-2 text-right font-semibold">Rata-rata lama di tahapan</th><th className="px-3 py-2 text-right font-semibold">Terlama di tahapan</th><th className="px-5 py-2 text-right font-semibold">Rata-rata lama proses</th></tr></thead>
            <tbody>{rows.map((p) => (
              <tr key={p.name} onClick={() => go(p.name)} className="cursor-pointer border-b border-line last:border-0 hover:bg-sunken/60">
                <td className="px-5 py-2"><span className="font-semibold">{p.name}</span> <Badge tone={p.track === 'pasca' ? 'teal' : 'navy'} className="ml-1">{p.track === 'pasca' ? 'Pasca' : 'Pengadaan'}</Badge></td>
                <td className="num px-3 py-2 text-right font-bold">{p.count}</td>
                <td className="num px-3 py-2 text-right">{p.stage_avg !== null ? `${p.stage_avg} hari` : '–'}</td>
                <td className="num px-3 py-2 text-right">{p.stage_max !== null ? `${p.stage_max} hari` : '–'}</td>
                <td className="num px-5 py-2 text-right">{p.process_avg !== null ? `${p.process_avg} hari` : '–'}</td>
              </tr>
            ))}</tbody>
          </table>
          <p className="px-5 py-2 text-[11.5px] text-mute">Durasi dalam hari kerja.</p>
        </div>
      )}
    </Card>
  );
}

/** Posisi terakhir pengadaan yang dinyatakan Gagal atau Dibatalkan, untuk melihat di mana pengadaan paling sering berhenti. */
function StoppedByPosition({ rows }: { rows: Props['stopped']['by_position'] }) {
  if (!rows.length) return null;
  const max = Math.max(1, ...rows.map((r) => r.gagal + r.batal));
  return (
    <Card className="mb-5">
      <CardHead icon={XCircle} title="Pengadaan Gagal & Dibatalkan per Posisi Terakhir"
        actions={<div className="flex items-center gap-3 text-[12px] text-mute"><span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-bad" />Gagal</span><span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-mute/60" />Dibatalkan</span></div>} />
      <ul className="space-y-2 px-5 pb-4">{rows.map((r) => (
        <li key={r.name} className="flex items-center gap-3 text-[12.5px]">
          <span className="w-[170px] shrink-0 font-semibold">{r.name}</span>
          <span className="flex h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-sunken">
            <span className="h-2.5 bg-bad" style={{ width: `${(r.gagal / max) * 100}%` }} />
            <span className="h-2.5 bg-mute/60" style={{ width: `${(r.batal / max) * 100}%` }} />
          </span>
          <span className="num w-[150px] shrink-0 text-right text-mute"><b className="text-bad">{r.gagal}</b> gagal · <b className="text-ink">{r.batal}</b> dibatalkan</span>
        </li>
      ))}</ul>
    </Card>
  );
}

type Cat = 'time' | 'value' | 'approval' | 'vendor' | 'category';
const CATS: { value: Cat; label: string }[] = [
  { value: 'time', label: 'Waktu Proses Pengadaan' }, { value: 'value', label: 'Nilai SP2K / PO' },
  { value: 'approval', label: 'Proses Approval' }, { value: 'vendor', label: 'Vendor Pemenang' }, { value: 'category', label: 'Kategori Pengadaan' },
];

/** Top 5 dengan kategori yang dipilih pengguna. */
function TopFive({ top }: { top: Props['top'] }) {
  const [cat, setCat] = useState<Cat>('time');
  const [timeDir, setTimeDir] = useState<'slowest' | 'fastest'>('slowest');
  const [kind, setKind] = useState<'SP2K' | 'PO'>('SP2K');
  const [valDir, setValDir] = useState<'largest' | 'smallest'>('largest');
  const [group, setGroup] = useState('');
  const [apDir, setApDir] = useState<'fastest' | 'slowest'>('slowest');
  const [vBy, setVBy] = useState<'by_count' | 'by_value'>('by_count');
  const [cBy, setCBy] = useState<'count' | 'rab'>('count');

  let controls: React.ReactNode = null;
  let list: { key: string; title: React.ReactNode; sub?: React.ReactNode; value: number; label: string; href?: string }[] = [];
  let caption = '';
  if (cat === 'time') {
    controls = <Segmented value={timeDir} onChange={(v) => setTimeDir(v as 'slowest' | 'fastest')} options={[{ value: 'slowest', label: 'Terlama' }, { value: 'fastest', label: 'Tercepat' }]} />;
    list = top.time[timeDir].map((x) => ({ key: x.code, title: x.spp_number || x.code, sub: <>{x.title}<span className="block text-[11.5px]">{x.note}</span></>, value: x.days, label: `${x.days} hari`, href: `/pengadaan/${x.code}` }));
    caption = timeDir === 'slowest' ? 'Registrasi sampai SP2K/PO rilis (hari kerja); yang belum rilis dihitung sampai hari ini.' : 'Registrasi sampai SP2K/PO rilis (hari kerja); hanya yang sudah rilis.';
  } else if (cat === 'value') {
    controls = <><Segmented value={kind} onChange={(v) => setKind(v as 'SP2K' | 'PO')} options={[{ value: 'SP2K', label: 'SP2K' }, { value: 'PO', label: 'PO' }]} /><Segmented value={valDir} onChange={(v) => setValDir(v as 'largest' | 'smallest')} options={[{ value: 'largest', label: 'Terbesar' }, { value: 'smallest', label: 'Terkecil' }]} /></>;
    list = top.value[kind][valDir].map((x) => ({ key: x.code, title: x.spp_number || x.code, sub: <>{x.title}<span className="block text-[11.5px]">{x.note}</span></>, value: x.value, label: rp(x.value), href: `/pengadaan/${x.code}` }));
    caption = `Nilai ${kind} setelah PPN.`;
  } else if (cat === 'approval') {
    controls = <><div className="w-[200px]"><Select value={group} onChange={(e) => setGroup(e.target.value)} aria-label="Kelompok approval"><option value="">Semua kelompok (rata-rata)</option>{top.approval.groups.map((g) => <option key={g.name}>{g.name}</option>)}</Select></div><Segmented value={apDir} onChange={(v) => setApDir(v as 'fastest' | 'slowest')} options={[{ value: 'slowest', label: 'Terlama' }, { value: 'fastest', label: 'Tercepat' }]} /></>;
    if (!group) {
      const gs = top.approval.groups.filter((g) => g.avg !== null).sort((a, b) => (apDir === 'fastest' ? a.avg! - b.avg! : b.avg! - a.avg!));
      list = gs.map((g) => ({ key: g.name, title: g.name, sub: `${g.count} pengadaan selesai di kelompok ini`, value: g.avg!, label: `${g.avg} hari` }));
      caption = 'Rata-rata lama per kelompok approval (hari kerja), termasuk siklus revisi; alur amandemen tidak dihitung.';
    } else {
      list = (top.approval.items[group]?.[apDir] || []).map((x) => ({ key: x.code, title: x.spp_number || x.code, sub: x.title, value: x.days, label: `${x.days} hari`, href: `/pengadaan/${x.code}?tab=proses` }));
      caption = `Lama di kelompok ${group} (hari kerja), hanya pengadaan yang sudah melewatinya.`;
    }
  } else if (cat === 'category') {
    controls = <Segmented value={cBy} onChange={(v) => setCBy(v as 'count' | 'rab')} options={[{ value: 'count', label: 'Jumlah pengadaan' }, { value: 'rab', label: 'Total RAB' }]} />;
    list = [...top.category].sort((a, b) => (cBy === 'count' ? b.count - a.count || b.rab - a.rab : b.rab - a.rab))
      .map((x) => ({ key: x.name, title: x.name, sub: `${x.count} pengadaan · ${x.contracted} terkontrak · RAB ${rpShort(x.rab)}`, value: cBy === 'count' ? x.count : x.rab, label: cBy === 'count' ? `${x.count} pengadaan` : rp(x.rab), href: `/pengadaan?seg=all&kategori=${encodeURIComponent(x.name)}` }));
    caption = 'Seluruh pengadaan terdaftar dalam periode dan filter ini, termasuk yang sudah selesai (tanpa Gagal/Dibatalkan).';
  } else {
    controls = <Segmented value={vBy} onChange={(v) => setVBy(v as 'by_count' | 'by_value')} options={[{ value: 'by_count', label: 'Jumlah menang' }, { value: 'by_value', label: 'Total nilai' }]} />;
    list = top.vendor[vBy].map((x) => ({ key: x.vendor, title: x.vendor, sub: `${x.count} pengadaan · SP2K ${x.sp2k}, PO ${x.po} · ${rpShort(x.value)}`, value: vBy === 'by_count' ? x.count : x.value, label: vBy === 'by_count' ? `${x.count} pengadaan` : rp(x.value) }));
    caption = 'Dari pengadaan yang sudah SP2K/PO.';
  }
  const max = Math.max(1, ...list.map((x) => x.value));

  return (
    <Card>
      <CardHead icon={Trophy} title="Top 5" actions={<div className="w-[230px]"><Select value={cat} onChange={(e) => setCat(e.target.value as Cat)} aria-label="Kategori Top 5">{CATS.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</Select></div>} />
      <div className="flex flex-wrap items-center gap-2 border-b border-line px-5 pb-3">{controls}</div>
      {list.length === 0 ? <EmptyState compact title="Belum ada data untuk kategori ini" desc="Coba perluas periode atau ubah filter." /> : (
        <ol className="divide-y divide-line">{list.slice(0, 5).map((x, i) => {
          const body = (
            <>
              <span className={cx('num flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12.5px] font-extrabold', i === 0 ? 'grad-brand text-white' : 'bg-sunken text-ink/80')}>{i + 1}</span>
              <span className="min-w-0 flex-1"><span className="num block text-[13.5px] font-bold">{x.title}</span>{x.sub && <span className="block truncate text-[12px] text-mute">{x.sub}</span>}</span>
              <span className="hidden w-[180px] shrink-0 sm:block"><span className="block h-2 rounded-full bg-sunken"><span className="block h-2 rounded-full bg-brand" style={{ width: `${(x.value / max) * 100}%` }} /></span></span>
              <span className="num w-[140px] shrink-0 text-right text-[13px] font-bold">{x.label}</span>
            </>
          );
          return <li key={x.key}>{x.href ? <Link href={x.href} className="flex items-center gap-3 px-5 py-3 hover:bg-sunken/60">{body}</Link> : <div className="flex items-center gap-3 px-5 py-3">{body}</div>}</li>;
        })}</ol>
      )}
      <p className="border-t border-line px-5 py-2 text-[11.5px] text-mute">{caption}</p>
    </Card>
  );
}
