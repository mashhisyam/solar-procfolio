import React from 'react';
import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { Button, Field, Input, Modal, MoneyInput, Select, Switch } from '@/Components/ui';

export interface PrkRow { id: number; coa: string; cost_center: string; sub_bidang: string | null; code: string; program: string; klasifikasi: string; year: number | null; active: boolean; budget_amount: number }

/** Tambah atau ubah PRK beserta nilai pengajuannya. */
export default function PrkModal({ item, onClose, defaults }: { item: PrkRow | null | 'new'; onClose: () => void; defaults?: Partial<PrkRow> }) {
  const r = item && item !== 'new' ? item : null;
  const init = () => ({ coa: r?.coa || '', cost_center: r?.cost_center || defaults?.cost_center || '', sub_bidang: r?.sub_bidang || defaults?.sub_bidang || '', code: r?.code || '', program: r?.program || '', klasifikasi: r?.klasifikasi || 'PRODUKSI LANGSUNG', budget_amount: r?.budget_amount || 0, year: r?.year || new Date().getFullYear(), active: r?.active ?? true });
  const form = useForm(init());
  React.useEffect(() => { if (item) { form.setData(init()); form.clearErrors(); } }, [item]); // eslint-disable-line react-hooks/exhaustive-deps
  const e = form.errors;
  return (
    <Modal open={!!item} onClose={onClose} width="max-w-2xl" title={r ? `Ubah PRK ${r.code}` : 'Tambah PRK'}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={() => form.post(`/rkap/prk${r ? `/${r.id}` : ''}`, { preserveScroll: true, onSuccess: onClose })}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Kode anggaran (PRK)" required error={e.code}><Input className="num" value={form.data.code} onChange={(x) => form.setData('code', x.target.value)} placeholder="IC01052023" /></Field>
        <Field label="COA" required error={e.coa}><Input className="num" value={form.data.coa} onChange={(x) => form.setData('coa', x.target.value)} placeholder="5211990003" /></Field>
        <Field label="Program kerja" required error={e.program} className="sm:col-span-2"><Input value={form.data.program} onChange={(x) => form.setData('program', x.target.value)} /></Field>
        <Field label="Cost center" required error={e.cost_center}><Input value={form.data.cost_center} onChange={(x) => form.setData('cost_center', x.target.value)} /></Field>
        <Field label="Sub bidang" error={e.sub_bidang}><Select value={form.data.sub_bidang} onChange={(x) => form.setData('sub_bidang', x.target.value)}><option value="">–</option><option>PLN 1</option><option>PLN 2</option></Select></Field>
        <Field label="Klasifikasi beban" required error={e.klasifikasi}><Input value={form.data.klasifikasi} onChange={(x) => form.setData('klasifikasi', x.target.value)} /></Field>
        <Field label="Tahun RKAP" error={e.year}><Input type="number" className="num" value={form.data.year || ''} onChange={(x) => form.setData('year', Number(x.target.value))} /></Field>
        <Field label="Nilai pengajuan" required error={e.budget_amount} className="sm:col-span-2" hint="Anggaran yang ditetapkan untuk PRK ini."><MoneyInput value={form.data.budget_amount} onChange={(n) => form.setData('budget_amount', n)} /></Field>
        <Switch checked={form.data.active} onChange={(v) => form.setData('active', v)} label="Aktif" />
      </div>
    </Modal>
  );
}
