import React, { useMemo, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus, RotateCcw, SearchX, Download } from 'lucide-react';
import { Button, Card, EmptyState, Input, SearchInput, Segmented, Select } from '@/Components/ui';
import { PageHeader, ProcTable, SortKey, sortRows } from '@/Components/common';
import type { PageProps, ProcRow } from '@/types';

interface Props { rows: ProcRow[]; filters: { procMethods: string[]; customerContracts: { id: number; number: string }[]; stages: Record<string, { key: string; name: string }[]> } }
const SEGMENTS = [{ value: 'aktif', label: 'Semua aktif' }, { value: 'pengadaan', label: 'Proses Pengadaan' }, { value: 'pasca', label: 'Pasca Pengadaan' }, { value: 'amandemen', label: 'Amandemen' }, { value: 'selesai', label: 'Selesai' }, { value: 'draft', label: 'Draft' }, { value: 'all', label: 'Semua' }];
const PAGE = 15;

export default function ProcurementIndex({ rows, filters }: Props) {
  const { auth } = usePage<PageProps>().props;
  const [seg, setSeg] = useState('aktif');
  const [q, setQ] = useState('');
  const [f, setF] = useState({ method: '', priority: '', stage: '', procMethod: '', bakb: '', from: '', to: '' });
  const [sort, setSort] = useState<{ k: SortKey; dir: 1 | -1 }>({ k: 'spp', dir: -1 });
  const [page, setPage] = useState(1);
  const set = (k: keyof typeof f, v: string) => { setF((x) => ({ ...x, [k]: v })); setPage(1); };
  const inSeg = (r: ProcRow) => {
    const active = !r.is_draft && !r.cancelled && !['Selesai', 'Dibatalkan', 'Draft'].includes(r.status);
    switch (seg) {
      case 'aktif': return active;
      case 'pengadaan': return active && r.track === 'pengadaan';
      case 'pasca': return active && r.track === 'pasca';
      case 'amandemen': return !!r.amendment_running;
      case 'selesai': return r.status === 'Selesai';
      case 'draft': return !!r.is_draft;
      default: return true;
    }
  };
  const filtered = useMemo(() => rows.filter((r) => {
    if (!inSeg(r)) return false;
    const s = q.trim().toLowerCase();
    if (s && ![r.code, r.spp_number, r.title, r.pengaju_name, r.pic, r.vendor].join(' ').toLowerCase().includes(s)) return false;
    if (f.method && r.method !== f.method) return false;
    if (f.priority && r.priority !== f.priority) return false;
    if (f.procMethod && r.proc_method !== f.procMethod) return false;
    if (f.stage && r.stage_key !== f.stage) return false;
    if (f.bakb && !r.customer_contract_ids.includes(Number(f.bakb))) return false;
    if (f.from && r.registered_at < f.from) return false;
    if (f.to && r.registered_at > f.to) return false;
    return true;
  }), [rows, seg, q, f]); // eslint-disable-line react-hooks/exhaustive-deps
  const sorted = sortRows(filtered, sort.k, sort.dir);
  const pages = Math.max(1, Math.ceil(sorted.length / PAGE));
  const view = sorted.slice((page - 1) * PAGE, page * PAGE);
  const stageOpts = Object.entries(filters.stages);

  return (
    <>
      <Head title="Daftar Pengadaan" />
      <PageHeader title="Daftar Pengadaan" desc="Setiap pengadaan memiliki dua progres: Proses Pengadaan (approval sampai SP2K/PO rilis) dan Proses Pasca Pengadaan (alur per tahap pembayaran)."
        actions={<><a href="/ekspor/pengadaan"><Button icon={Download}>Ekspor Excel</Button></a>{auth.can.edit && <Link href="/pengadaan/baru"><Button variant="primary" icon={Plus}>Tambah Pengadaan</Button></Link>}</>} />
      <Card className="mb-4 p-3">
        <div className="mb-3"><Segmented value={seg} onChange={(v) => { setSeg(v); setPage(1); }} options={SEGMENTS} /></div>
        <div className="flex flex-wrap items-center gap-2">
          <SearchInput className="min-w-[240px] flex-1" value={q} onChange={(v) => { setQ(v); setPage(1); }} placeholder="Cari No SPP, ID, judul, User Pengaju, PIC atau vendor" />
          <div className="w-[140px]"><Select value={f.method} onChange={(e) => set('method', e.target.value)} aria-label="Skema"><option value="">Skema</option><option>SPP</option><option>Kemitraan</option></Select></div>
          <div className="w-[140px]"><Select value={f.priority} onChange={(e) => set('priority', e.target.value)} aria-label="Prioritas"><option value="">Prioritas</option><option>Urgent</option><option>Normal</option></Select></div>
          <div className="w-[190px]"><Select value={f.stage} onChange={(e) => set('stage', e.target.value)} aria-label="Aliran data"><option value="">Semua aliran data</option>{stageOpts.map(([scheme, list]) => <optgroup key={scheme} label={scheme === 'Pasca' ? 'Pasca Pengadaan' : `Proses ${scheme}`}>{list.map((s) => <option key={scheme + s.key} value={s.key}>{s.name}</option>)}</optgroup>)}</Select></div>
          <div className="w-[190px]"><Select value={f.procMethod} onChange={(e) => set('procMethod', e.target.value)} aria-label="Metode pengadaan"><option value="">Semua metode</option>{filters.procMethods.map((m) => <option key={m}>{m}</option>)}</Select></div>
          <div className="w-[180px]"><Select value={f.bakb} onChange={(e) => set('bakb', e.target.value)} aria-label="BAKB"><option value="">Semua BAKB</option>{filters.customerContracts.map((c) => <option key={c.id} value={c.id}>{c.number}</option>)}</Select></div>
          <label className="flex items-center gap-1.5 text-[12px] text-mute">Registrasi<Input type="date" className="w-[150px]" value={f.from} onChange={(e) => set('from', e.target.value)} /></label>
          <label className="flex items-center gap-1.5 text-[12px] text-mute">s.d.<Input type="date" className="w-[150px]" value={f.to} onChange={(e) => set('to', e.target.value)} /></label>
          {(q || Object.values(f).some(Boolean)) && <Button variant="ghost" icon={RotateCcw} onClick={() => { setQ(''); setF({ method: '', priority: '', stage: '', procMethod: '', bakb: '', from: '', to: '' }); }}>Reset</Button>}
        </div>
      </Card>
      <Card>
        <div className="border-b border-line px-5 py-3 text-[13px]"><b className="num">{sorted.length}</b> pengadaan</div>
        {view.length === 0 ? <EmptyState icon={SearchX} title="Tidak ada pengadaan yang cocok" desc="Ubah segmen, kata kunci atau filter." /> : <ProcTable rows={view} sort={sort} onSort={(k) => setSort({ k, dir: sort.k === k ? (sort.dir === 1 ? -1 : 1) : 1 })} />}
        <div className="flex items-center justify-between gap-2 px-5 py-3 text-[13px]">
          <span className="text-mute">Halaman {page} dari {pages}</span>
          <div className="flex gap-1"><Button size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Sebelumnya</Button><Button size="sm" disabled={page >= pages} onClick={() => setPage(page + 1)}>Berikutnya</Button></div>
        </div>
      </Card>
    </>
  );
}
