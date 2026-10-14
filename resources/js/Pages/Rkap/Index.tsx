import React, { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ChevronDown, Landmark, PiggyBank, Plus, Save, Wallet, TrendingDown, Pencil } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, Field, Input, Modal, MoneyInput, SearchInput, Switch, cx } from '@/Components/ui';
import { MetricCard, PageHeader, ProcLink, StatusBadge } from '@/Components/common';
import { rp, rpShort } from '@/lib/format';
import type { PageProps } from '@/types';

interface Row {
  id: number; coa: string; cost_center: string; code: string; program: string; klasifikasi: string; year: number | null; active: boolean;
  budget_amount: number; used: number; remaining: number; percent: number | null;
  procurements: { code: string; spp_number: string | null; title: string; rab_value: number; status: string; registered_at: string | null }[];
}

/** RKAP: pagu per PRK berkurang mengikuti RAB pengadaan yang memakai PRK tersebut. Sisa minus hanya diinformasikan. */
export default function RkapIndex({ rows }: { rows: Row[] }) {
  const { auth } = usePage<PageProps>().props;
  const [q, setQ] = useState('');
  const [open, setOpen] = useState<number | null>(null);
  const [edit, setEdit] = useState<Row | null | 'new'>(null);
  const view = rows.filter((r) => !q || [r.code, r.program, r.coa, r.cost_center].join(' ').toLowerCase().includes(q.toLowerCase()));
  const total = rows.reduce((a, r) => ({ pagu: a.pagu + r.budget_amount, used: a.used + r.used }), { pagu: 0, used: 0 });
  const minus = rows.filter((r) => r.remaining < 0);
  const noPagu = rows.filter((r) => r.active && r.budget_amount <= 0);

  return (
    <>
      <Head title="RKAP" />
      <PageHeader title="RKAP" desc="Monitoring pagu anggaran per PRK. Sisa berkurang otomatis sesuai RAB pengadaan yang memakai PRK tersebut."
        actions={auth.can.edit && <Button variant="primary" icon={Plus} onClick={() => setEdit('new')}>Tambah PRK</Button>} />
      <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Total Pagu" value={rpShort(total.pagu)} sub={`${rows.length} PRK`} icon={Landmark} />
        <MetricCard label="Terpakai" value={rpShort(total.used)} sub={total.pagu ? `${((total.used / total.pagu) * 100).toFixed(1)}% dari pagu` : 'Pagu belum diisi'} icon={Wallet} tone="info" />
        <MetricCard label="Sisa" value={rpShort(total.pagu - total.used)} sub="Pagu dikurangi RAB pengadaan aktif" icon={PiggyBank} tone="info" />
        <MetricCard label="PRK Minus" value={minus.length} sub="RAB melebihi pagu" icon={TrendingDown} tone={minus.length ? 'bad' : 'info'} />
      </div>
      {minus.length > 0 && (
        <div className="mb-5 flex items-start gap-2.5 rounded-xl border border-bad/30 bg-badsoft px-4 py-3 text-[13px]">
          <AlertTriangle size={17} className="mt-0.5 shrink-0 text-bad" />
          <div><b className="text-bad">Terdapat anggaran minus.</b> {minus.map((r) => `${r.code} (${rp(r.remaining)})`).join(', ')}. Pengadaan tetap berjalan; tinjau pagu atau RAB terkait.</div>
        </div>
      )}
      {noPagu.length > 0 && (
        <div className="mb-5 rounded-xl border border-warn/30 bg-warnsoft px-4 py-3 text-[13px]">Pagu belum diisi untuk {noPagu.map((r) => r.code).join(', ')}.</div>
      )}
      <Card className="mb-4 p-3"><SearchInput value={q} onChange={setQ} placeholder="Cari PRK, program kerja, COA atau cost center" /></Card>
      <Card>
        <CardHead title={`PRK (${view.length})`} />
        {view.length === 0 ? <EmptyState icon={Landmark} title="Belum ada PRK" /> : (
          <ul className="divide-y divide-line">
            {view.map((r) => {
              const pct = r.budget_amount > 0 ? Math.min(100, (r.used / r.budget_amount) * 100) : r.used > 0 ? 100 : 0;
              return (
                <li key={r.id} className={cx(!r.active && 'opacity-60')}>
                  <div className="flex flex-wrap items-center gap-x-5 gap-y-2 px-5 py-3.5">
                    <button onClick={() => setOpen(open === r.id ? null : r.id)} aria-expanded={open === r.id} className="flex min-w-[260px] flex-1 items-start gap-2 text-left">
                      <ChevronDown size={16} className={cx('mt-0.5 shrink-0 text-mute transition', open === r.id ? '' : '-rotate-90')} />
                      <span className="min-w-0"><span className="num block font-bold">{r.code}{r.year ? <span className="ml-2 font-medium text-mute">{r.year}</span> : null}</span><span className="block text-[12.5px]">{r.program}</span><span className="block text-[11.5px] text-mute">COA {r.coa} · {r.cost_center} · {r.klasifikasi}</span></span>
                    </button>
                    <div className="w-[230px]">
                      <div className="mb-1 flex justify-between text-[11.5px] text-mute"><span>Terpakai {r.percent !== null ? `${r.percent}%` : ''}</span><span>{r.procurements.length} pengadaan</span></div>
                      <div className="h-2 rounded-full bg-sunken"><div className={cx('h-2 rounded-full', r.remaining < 0 ? 'bg-bad' : pct >= 80 ? 'bg-warn' : 'bg-ok')} style={{ width: `${pct}%` }} /></div>
                    </div>
                    <dl className="grid grid-cols-3 gap-4 text-right text-[12.5px]">
                      <div><dt className="text-mute">Pagu</dt><dd className="num font-semibold">{rp(r.budget_amount)}</dd></div>
                      <div><dt className="text-mute">Terpakai</dt><dd className="num font-semibold">{rp(r.used)}</dd></div>
                      <div><dt className="text-mute">Sisa</dt><dd className={cx('num font-bold', r.remaining < 0 && 'text-bad')}>{rp(r.remaining)}</dd></div>
                    </dl>
                    <div className="flex items-center gap-2">{r.remaining < 0 && <Badge tone="bad">Minus</Badge>}{auth.can.edit && <Button size="xs" variant="ghost" icon={Pencil} onClick={() => setEdit(r)}>Ubah</Button>}</div>
                  </div>
                  {open === r.id && (
                    <div className="border-t border-line bg-sunken/40 px-5 py-3">
                      {r.procurements.length === 0 ? <p className="text-[12.5px] text-mute">Belum ada pengadaan yang memakai PRK ini.</p> : (
                        <table className="w-full text-[12.5px]"><thead><tr className="text-left text-[11.5px] text-mute"><th className="py-1.5">No SPP</th><th>Judul</th><th>Status</th><th className="text-right">RAB</th></tr></thead>
                          <tbody>{r.procurements.map((p) => <tr key={p.code} className="border-t border-line"><td className="py-2"><ProcLink code={p.code} spp={p.spp_number} /></td><td className="pr-3">{p.title}</td><td><StatusBadge status={p.status} /></td><td className="num text-right font-semibold">{rp(p.rab_value)}</td></tr>)}</tbody></table>
                      )}
                    </div>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </Card>
      <PrkModal item={edit} onClose={() => setEdit(null)} defaults={rows[0]} />
    </>
  );
}

function PrkModal({ item, onClose, defaults }: { item: Row | null | 'new'; onClose: () => void; defaults?: Row }) {
  const r = item && item !== 'new' ? item : null;
  const init = () => ({ coa: r?.coa || '', cost_center: r?.cost_center || defaults?.cost_center || '', code: r?.code || '', program: r?.program || '', klasifikasi: r?.klasifikasi || 'PRODUKSI LANGSUNG', budget_amount: r?.budget_amount || 0, year: r?.year || new Date().getFullYear(), active: r?.active ?? true });
  const form = useForm(init());
  React.useEffect(() => { if (item) { form.setData(init()); form.clearErrors(); } }, [item]); // eslint-disable-line react-hooks/exhaustive-deps
  const e = form.errors;
  return (
    <Modal open={!!item} onClose={onClose} width="max-w-2xl" title={r ? `Ubah PRK ${r.code}` : 'Tambah PRK'}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/rkap/prk${r ? `/${r.id}` : ''}`, { preserveScroll: true, onSuccess: onClose })}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="PRK" required error={e.code}><Input className="num" value={form.data.code} onChange={(x) => form.setData('code', x.target.value)} placeholder="IC01052023" /></Field>
        <Field label="COA" required error={e.coa}><Input className="num" value={form.data.coa} onChange={(x) => form.setData('coa', x.target.value)} placeholder="5211990003" /></Field>
        <Field label="Program kerja" required error={e.program} className="sm:col-span-2"><Input value={form.data.program} onChange={(x) => form.setData('program', x.target.value)} /></Field>
        <Field label="Cost center" required error={e.cost_center} className="sm:col-span-2"><Input value={form.data.cost_center} onChange={(x) => form.setData('cost_center', x.target.value)} /></Field>
        <Field label="Klasifikasi beban" required error={e.klasifikasi}><Input value={form.data.klasifikasi} onChange={(x) => form.setData('klasifikasi', x.target.value)} /></Field>
        <Field label="Tahun RKAP" error={e.year}><Input type="number" className="num" value={form.data.year || ''} onChange={(x) => form.setData('year', Number(x.target.value))} /></Field>
        <Field label="Pagu anggaran" required error={e.budget_amount} className="sm:col-span-2"><MoneyInput value={form.data.budget_amount} onChange={(n) => form.setData('budget_amount', n)} /></Field>
        <Switch checked={form.data.active} onChange={(v) => form.setData('active', v)} label="Aktif" />
      </div>
    </Modal>
  );
}
