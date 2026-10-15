import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { Button, Card, CardHead, EmptyState, KV, LinkButton } from '@/Components/ui';
import { PageHeader, PagedProcTable } from '@/Components/common';
import { fmtDate, rp } from '@/lib/format';
import type { PageProps, ProcRow } from '@/types';
import { CC, CustomerContractModal } from './Index';

export default function CustomerContractShow({ contract: c, rows, customers, kinds }: { contract: CC; rows: ProcRow[]; customers: { id: number; name: string }[]; kinds: string[] }) {
  const { auth } = usePage<PageProps>().props;
  const [edit, setEdit] = useState(false);
  return (
    <>
      <Head title={c.number} />
      <PageHeader crumbs={[['Perjanjian Pelanggan', '/bakb'], [c.number]]} title={c.number} desc={c.title} actions={<><LinkButton url={c.link} label="Buka dokumen" size="sm" />{auth.can.edit && <Button icon={Pencil} onClick={() => setEdit(true)}>Ubah</Button>}</>} />
      <Card className="mb-5"><div className="p-5"><KV cols={3} items={[['Jenis dokumen', c.kind], ['Sub bidang', c.sub_bidang || '–'], ['Pelanggan', c.customer || '–'], ['Revenue', c.revenue ? rp(c.revenue) : '–'], ['Tanggal dokumen', fmtDate(c.date)], ['Pengadaan terkait', String(rows.length)]]} /></div></Card>
      <Card><CardHead title="Pengadaan terkait" />{rows.length ? <PagedProcTable rows={rows} /> : <EmptyState compact title="Belum ada pengadaan yang merujuk dokumen ini" />}</Card>
      <CustomerContractModal open={edit} onClose={() => setEdit(false)} item={c} customers={customers} kinds={kinds} />
    </>
  );
}
