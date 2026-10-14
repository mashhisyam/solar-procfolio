import React, { useMemo, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, Workflow, Flame, BellRing, Wallet, ArrowRight, RotateCcw, Filter, ClipboardCheck, CheckCheck, Coins, TrendingUp, Route } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, Segmented, Select, cx } from '@/Components/ui';
import { MetricCard, PageHeader, ProcTable } from '@/Components/common';
import { fmtDateLong, rpShort } from '@/lib/format';
import type { PageProps, ProcRow } from '@/types';

interface Props {
  rows: ProcRow[];
  kpi: { active: number; spp: number; kemitraan: number; urgent: number; attention: number; unpaid_terms: number; unpaid_value: number; done: number };
  filters: { pengaju: string[]; customerContracts: { id: number; kind: string; number: string; revenue: number }[] };
}

/** Urutan posisi proses dari awal sampai pembayaran. */
const ORDER = ['Pengaju', 'Anggaran', 'Manager Pengadaan', 'Perencana Pengadaan', 'Pelaksana Pengadaan', 'Partnership',
  'Pelaksanaan Pekerjaan', 'BA Penutupan', 'Proses GR', 'Invoicing', 'Verifikasi Invoice', 'Antri Bayar', 'Terjadwal'];
const avg = (xs: number[]) => (xs.length ? Math.round(xs.reduce((a, b) => a + b, 0) / xs.length) : null);

export default function Dashboard({ rows, kpi, filters }: Props) {
  const { app, auth } = usePage<PageProps>().props;
  const [card, setCard] = useState<'' | 'urgent' | 'attention'>('');
  const [mode, setMode] = useState<'attention' | 'all'>('attention');
  const [pos, setPos] = useState('');
  const [f, setF] = useState({ method: '', priority: '', pengaju: '', bakb: '' });
  const set = (k: keyof typeof f, v: string) => setF((x) => ({ ...x, [k]: v }));
  const filtered = useMemo(() => rows.filter((r) => (!f.method || r.method === f.method) && (!f.priority || r.priority === f.priority) && (!f.pengaju || r.pengaju_name === f.pengaju) && (!f.bakb || r.customer_contract_ids.includes(Number(f.bakb)))), [rows, f]);
  const posOf = (r: ProcRow) => r.position || r.stage || 'Lainnya';

  // Biaya dan revenue mengikuti filter. Revenue dihitung sekali per perjanjian pelanggan walau dipakai beberapa pengadaan.
  const money = useMemo(() => {
    const cost = filtered.reduce((a, r) => a + (r.cost || 0), 0);
    const ids = new Set(filtered.flatMap((r) => r.customer_contract_ids));
    const revenue = filters.customerContracts.filter((c) => ids.has(c.id)).reduce((a, c) => a + (c.revenue || 0), 0);
    return { cost, revenue, margin: revenue - cost };
  }, [filtered, filters.customerContracts]);

  // Posisi setiap proses: jumlah pengadaan, rata-rata lama di tahap, rata-rata lama proses.
  const positions = useMemo(() => {
    const m = new Map<string, ProcRow[]>();
    filtered.forEach((r) => { const k = posOf(r); m.set(k, [...(m.get(k) || []), r]); });
    return [...m.entries()].map(([name, list]) => ({
      name, count: list.length,
      stageAvg: avg(list.map((r) => r.days).filter((d): d is number => d !== null)),
      stageMax: Math.max(0, ...list.map((r) => r.days ?? 0)),
      processAvg: avg(list.map((r) => r.process_days)),
      track: list[0].track,
    })).sort((a, b) => (ORDER.indexOf(a.name) === -1 ? 99 : ORDER.indexOf(a.name)) - (ORDER.indexOf(b.name) === -1 ? 99 : ORDER.indexOf(b.name)));
  }, [filtered]); // eslint-disable-line react-hooks/exhaustive-deps
  const maxCount = Math.max(1, ...positions.map((p) => p.count));

  const shown = filtered.filter((r) => (pos ? posOf(r) === pos : true) && (card === 'urgent' ? r.priority === 'Urgent' : card === 'attention' ? r.alerts.length > 0 : pos || mode === 'all' || r.priority === 'Urgent' || r.alerts.length > 0));
  const hasFilter = Object.values(f).some(Boolean) || !!card || !!pos;

  return (
    <>
      <Head title="Dashboard" />
      <PageHeader title="Dashboard Monitoring" desc="Posisi, biaya, dan pengadaan yang perlu ditindaklanjuti."
        meta={<span className="text-[12.5px] text-mute">Data per {fmtDateLong(app.today)}</span>}
        actions={auth.can.edit && <Link href="/pengadaan/baru"><Button variant="primary" icon={Plus}>Tambah Pengadaan</Button></Link>} />

      <div className="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-2.5 shadow-card">
        <span className="flex items-center gap-1.5 px-1.5 text-[12.5px] font-semibold text-mute"><Filter size={14} />Filter</span>
        <div className="w-[160px]"><Select value={f.method} onChange={(e) => set('method', e.target.value)} aria-label="Skema"><option value="">Semua skema</option><option>SPP</option><option>Kemitraan</option></Select></div>
        <div className="w-[165px]"><Select value={f.priority} onChange={(e) => set('priority', e.target.value)} aria-label="Prioritas"><option value="">Semua prioritas</option><option>Urgent</option><option>Normal</option></Select></div>
        <div className="w-[200px]"><Select value={f.pengaju} onChange={(e) => set('pengaju', e.target.value)} aria-label="User Pengaju"><option value="">Semua User Pengaju</option>{filters.pengaju.map((x) => <option key={x}>{x}</option>)}</Select></div>
        <div className="w-[230px]"><Select value={f.bakb} onChange={(e) => set('bakb', e.target.value)} aria-label="Perjanjian pelanggan"><option value="">Semua perjanjian pelanggan</option>{filters.customerContracts.map((c) => <option key={c.id} value={c.id}>{c.kind} · {c.number}</option>)}</Select></div>
        {hasFilter && <Button size="sm" variant="ghost" icon={RotateCcw} onClick={() => { setF({ method: '', priority: '', pengaju: '', bakb: '' }); setCard(''); setPos(''); }}>Reset</Button>}
      </div>

      <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Pengadaan Aktif" value={kpi.active} sub={`SPP ${kpi.spp}, Kemitraan ${kpi.kemitraan}`} icon={Workflow} onClick={() => { setCard(''); setMode('all'); setPos(''); }} active={!card && !pos && mode === 'all'} />
        <MetricCard label="Urgent" value={kpi.urgent} sub="Dipilih saat registrasi" icon={Flame} tone="bad" onClick={() => setCard(card === 'urgent' ? '' : 'urgent')} active={card === 'urgent'} />
        <MetricCard label="Butuh Perhatian" value={kpi.attention} sub="Durasi kuning/merah, terlambat, atau tertahan" icon={BellRing} tone="warn" onClick={() => setCard(card === 'attention' ? '' : 'attention')} active={card === 'attention'} />
        <Link href="/pengadaan" className="contents"><MetricCard label="Selesai" value={kpi.done} sub="Seluruh tahap telah dibayar" icon={CheckCheck} tone="info" /></Link>
      </div>
      <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <MetricCard label="Cost" value={rpShort(money.cost)} sub="Nilai SP2K/PO setelah PPN; RAB bila kontrak belum rilis" icon={Coins} />
        <MetricCard label="Revenue" value={rpShort(money.revenue)} sub={`Dari perjanjian pelanggan · selisih ${money.margin < 0 ? '−' : ''}${rpShort(Math.abs(money.margin))}`} icon={TrendingUp} tone={money.margin < 0 ? 'bad' : 'info'} />
        <Link href="/tahapan" className="contents"><MetricCard label="Tahap Belum Dibayar" value={kpi.unpaid_terms} sub={`Nilai ${rpShort(kpi.unpaid_value)}`} icon={Wallet} tone="info" /></Link>
      </div>

      <Card className="mb-5">
        <CardHead icon={Route} title="Posisi setiap proses" desc="Jumlah pengadaan aktif per posisi, lama di tahapan, dan lama proses pengadaan (hari kerja). Klik baris untuk menyaring daftar di bawah." />
        {positions.length === 0 ? <EmptyState compact title="Belum ada pengadaan aktif" /> : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-[13px]">
              <thead><tr className="border-b border-line text-left text-[11.5px] text-mute"><th className="px-5 py-2 font-semibold">Posisi</th><th className="px-3 py-2 font-semibold">Jumlah pengadaan</th><th className="px-3 py-2 text-right font-semibold">Rata-rata lama di tahapan</th><th className="px-3 py-2 text-right font-semibold">Terlama di tahapan</th><th className="px-5 py-2 text-right font-semibold">Rata-rata lama proses</th></tr></thead>
              <tbody>{positions.map((p) => (
                <tr key={p.name} onClick={() => setPos(pos === p.name ? '' : p.name)} className={cx('cursor-pointer border-b border-line last:border-0 hover:bg-sunken/60', pos === p.name && 'bg-brandsoft/60')}>
                  <td className="px-5 py-2.5"><span className="font-semibold">{p.name}</span> <Badge tone={p.track === 'pasca' ? 'teal' : 'navy'} className="ml-1">{p.track === 'pasca' ? 'Pasca' : 'Pengadaan'}</Badge></td>
                  <td className="px-3 py-2.5"><div className="flex items-center gap-2"><div className="h-2 w-40 rounded-full bg-sunken"><div className="h-2 rounded-full bg-brand" style={{ width: `${(p.count / maxCount) * 100}%` }} /></div><span className="num font-bold">{p.count}</span></div></td>
                  <td className="num px-3 py-2.5 text-right">{p.stageAvg !== null ? `${p.stageAvg} hari` : '–'}</td>
                  <td className="num px-3 py-2.5 text-right">{p.stageMax ? `${p.stageMax} hari` : '–'}</td>
                  <td className="num px-5 py-2.5 text-right">{p.processAvg !== null ? `${p.processAvg} hari` : '–'}</td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        )}
      </Card>

      <Card>
        <CardHead icon={ClipboardCheck} title={pos ? `Pengadaan di posisi ${pos}` : 'Pengadaan Perlu Tindak Lanjut'}
          actions={<>{!card && !pos ? <Segmented value={mode} onChange={(v) => setMode(v as 'attention' | 'all')} options={[{ value: 'attention', label: 'Perlu tindak lanjut' }, { value: 'all', label: 'Semua aktif' }]} /> : <Badge tone="brand">{pos ? `Posisi: ${pos}` : card === 'urgent' ? 'Hanya Urgent' : 'Hanya Butuh Perhatian'}</Badge>}<Link href="/pengadaan"><Button size="sm" variant="ghost" iconRight={ArrowRight}>Daftar Pengadaan</Button></Link></>} />
        {shown.length === 0 ? <EmptyState compact icon={ClipboardCheck} title="Tidak ada pengadaan yang perlu ditindaklanjuti" desc="Semua pengadaan sesuai filter berjalan normal dan sesuai jadwal." /> : <ProcTable rows={shown} />}
      </Card>
    </>
  );
}
