import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Pencil, ClipboardList } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, KV, cx } from '@/Components/ui';
import { PageHeader, ProcTable } from '@/Components/common';
import { rp } from '@/lib/format';
import type { PageProps, ProcRow } from '@/types';
import PrkModal, { type PrkRow } from '@/Components/rkap/PrkModal';

interface Prk extends PrkRow { used: number; remaining: number; percent: number | null }

/** Detail PRK: pagu, terpakai, sisa, persentase terpakai, dan daftar pengadaan yang memakainya. */
export default function RkapShow({ prk, rows }: { prk: Prk; rows: ProcRow[] }) {
  const { auth } = usePage<PageProps>().props;
  const [edit, setEdit] = useState(false);
  const pct = prk.budget_amount > 0 ? Math.min(100, (prk.used / prk.budget_amount) * 100) : prk.used > 0 ? 100 : 0;
  return (
    <>
      <Head title={`PRK ${prk.code}`} />
      <PageHeader crumbs={[['RKAP', '/rkap'], [prk.code]]} title={prk.code} desc={prk.program}
        meta={prk.remaining < 0 ? <Badge tone="bad">Anggaran minus {rp(-prk.remaining)}</Badge> : undefined}
        actions={auth.can.edit && <Button icon={Pencil} onClick={() => setEdit(true)}>Ubah PRK</Button>} />
      <div className="mb-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
        <Card className="xl:col-span-2"><CardHead title="Identitas PRK" />
          <div className="p-5"><KV cols={3} items={[['Kode anggaran', prk.code], ['COA', prk.coa], ['Cost center', prk.cost_center], ['Sub bidang', prk.sub_bidang || '–'], ['Klasifikasi beban', prk.klasifikasi], ['Tahun RKAP', prk.year ? String(prk.year) : '–']]} /></div>
        </Card>
        <Card><CardHead title="Penggunaan anggaran" />
          <div className="space-y-4 p-5 text-[13px]">
            <div>
              <div className="mb-1.5 flex items-end justify-between"><span className="text-mute">Terpakai</span><span className={cx('num font-display text-[28px] font-bold leading-none', prk.remaining < 0 ? 'text-bad' : 'text-ink')}>{prk.percent !== null ? `${prk.percent}%` : '–'}</span></div>
              <div className="h-2.5 rounded-full bg-sunken"><div className={cx('h-2.5 rounded-full', prk.remaining < 0 ? 'bg-bad' : pct >= 80 ? 'bg-warn' : 'bg-ok')} style={{ width: `${pct}%` }} /></div>
            </div>
            <dl className="divide-y divide-line">
              {([['Pagu', prk.budget_amount], ['Terpakai', prk.used], ['Sisa', prk.remaining]] as [string, number][]).map(([k, v]) => (
                <div key={k} className="flex justify-between py-2"><dt className="text-mute">{k}</dt><dd className={cx('num font-bold', k === 'Sisa' && v < 0 && 'text-bad')}>{rp(v)}</dd></div>
              ))}
            </dl>
          </div>
        </Card>
      </div>
      <Card>
        <CardHead icon={ClipboardList} title={`Pengadaan yang memakai PRK ini (${rows.length})`} desc="Terpakai dihitung dari RAB pengadaan aktif (bukan draft dan tidak dibatalkan)." />
        {rows.length ? <ProcTable rows={rows} /> : <EmptyState compact title="Belum ada pengadaan yang memakai PRK ini" />}
      </Card>
      <PrkModal item={edit ? prk : null} onClose={() => setEdit(false)} />
    </>
  );
}
