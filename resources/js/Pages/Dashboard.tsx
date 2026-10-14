import React, { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Plus, Workflow, Flame, BellRing, Wallet, ArrowRight, RotateCcw, Filter, ClipboardCheck } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, Segmented, Select } from '@/Components/ui';
import { MetricCard, PageHeader, ProcTable } from '@/Components/common';
import { fmtDateLong, rpShort } from '@/lib/format';
import type { PageProps, ProcRow } from '@/types';
import { usePage } from '@inertiajs/react';

interface Props { rows: ProcRow[]; kpi: { active: number; spp: number; kemitraan: number; urgent: number; attention: number; unpaid_terms: number; unpaid_value: number }; filters: { pengaju: string[]; customerContracts: { id: number; kind: string; number: string }[] } }

export default function Dashboard({ rows, kpi, filters }: Props) {
  const { app, auth } = usePage<PageProps>().props;
  const [card, setCard] = useState<'' | 'urgent' | 'attention'>('');
  const [mode, setMode] = useState<'attention' | 'all'>('attention');
  const [f, setF] = useState({ method: '', priority: '', pengaju: '', bakb: '' });
  const set = (k: keyof typeof f, v: string) => setF((x) => ({ ...x, [k]: v }));
  const filtered = useMemo(() => rows.filter((r) => (!f.method || r.method === f.method) && (!f.priority || r.priority === f.priority) && (!f.pengaju || r.pengaju_name === f.pengaju) && (!f.bakb || r.customer_contract_ids.includes(Number(f.bakb)))), [rows, f]);
  const shown = filtered.filter((r) => (card === 'urgent' ? r.priority === 'Urgent' : card === 'attention' ? r.alerts.length > 0 : mode === 'all' || r.priority === 'Urgent' || r.alerts.length > 0));
  const hasFilter = Object.values(f).some(Boolean) || !!card;

  return (
    <>
      <Head title="Dashboard" />
      <PageHeader title="Dashboard Monitoring" desc="Pengadaan yang perlu ditindaklanjuti. Rincian dua progres tersedia di halaman detail setiap pengadaan."
        meta={<span className="text-[12.5px] text-mute">Data per {fmtDateLong(app.today)}</span>}
        actions={auth.can.edit && <Link href="/pengadaan/baru"><Button variant="primary" icon={Plus}>Tambah Pengadaan</Button></Link>} />
      <div className="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-line bg-surface p-2.5 shadow-card">
        <span className="flex items-center gap-1.5 px-1.5 text-[12.5px] font-semibold text-mute"><Filter size={14} />Filter</span>
        <div className="w-[160px]"><Select value={f.method} onChange={(e) => set('method', e.target.value)} aria-label="Skema"><option value="">Semua skema</option><option>SPP</option><option>Kemitraan</option></Select></div>
        <div className="w-[165px]"><Select value={f.priority} onChange={(e) => set('priority', e.target.value)} aria-label="Prioritas"><option value="">Semua prioritas</option><option>Urgent</option><option>Normal</option></Select></div>
        <div className="w-[200px]"><Select value={f.pengaju} onChange={(e) => set('pengaju', e.target.value)} aria-label="User Pengaju"><option value="">Semua User Pengaju</option>{filters.pengaju.map((x) => <option key={x}>{x}</option>)}</Select></div>
        <div className="w-[200px]"><Select value={f.bakb} onChange={(e) => set('bakb', e.target.value)} aria-label="Perjanjian pelanggan"><option value="">Semua perjanjian pelanggan</option>{filters.customerContracts.map((c) => <option key={c.id} value={c.id}>{c.kind} · {c.number}</option>)}</Select></div>
        {hasFilter && <Button size="sm" variant="ghost" icon={RotateCcw} onClick={() => { setF({ method: '', priority: '', pengaju: '', bakb: '' }); setCard(''); }}>Reset</Button>}
      </div>
      <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Pengadaan Aktif" value={kpi.active} sub={`SPP ${kpi.spp}, Kemitraan ${kpi.kemitraan}`} icon={Workflow} onClick={() => { setCard(''); setMode('all'); }} active={!card && mode === 'all'} />
        <MetricCard label="Urgent" value={kpi.urgent} sub="Dipilih saat registrasi" icon={Flame} tone="bad" onClick={() => setCard(card === 'urgent' ? '' : 'urgent')} active={card === 'urgent'} />
        <MetricCard label="Butuh Perhatian" value={kpi.attention} sub="Durasi kuning/merah, terlambat, atau tertahan" icon={BellRing} tone="warn" onClick={() => setCard(card === 'attention' ? '' : 'attention')} active={card === 'attention'} />
        <Link href="/tahapan" className="contents"><MetricCard label="Tahap Belum Dibayar" value={kpi.unpaid_terms} sub={`Nilai ${rpShort(kpi.unpaid_value)}`} icon={Wallet} tone="info" /></Link>
      </div>
      <Card>
        <CardHead icon={ClipboardCheck} title="Pengadaan Perlu Tindak Lanjut"
          actions={<>{!card ? <Segmented value={mode} onChange={(v) => setMode(v as 'attention' | 'all')} options={[{ value: 'attention', label: 'Perlu tindak lanjut' }, { value: 'all', label: 'Semua aktif' }]} /> : <Badge tone="brand">{card === 'urgent' ? 'Hanya Urgent' : 'Hanya Butuh Perhatian'}</Badge>}<Link href="/pengadaan"><Button size="sm" variant="ghost" iconRight={ArrowRight}>Daftar Pengadaan</Button></Link></>} />
        {shown.length === 0 ? <EmptyState compact icon={ClipboardCheck} title="Tidak ada pengadaan yang perlu ditindaklanjuti" desc="Semua pengadaan sesuai filter berjalan normal dan sesuai jadwal." /> : <ProcTable rows={shown} />}
      </Card>
    </>
  );
}
