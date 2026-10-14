import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { Button, Card, Field, Input, LinkField, MultiSelect, Select } from '@/Components/ui';
import { PageHeader } from '@/Components/common';
import type { Pic } from '@/types';

interface Props {
  procurement: { code: string; spp_number: string | null; title: string; sub_bidang: string | null; method: string };
  ba: { id: number; type: string; number: string | null; title: string; sub_bidang: string | null; contract_ref_type: string | null; contract_ref_number: string | null; director_internal: string | null; director_external: string | null; first_party: string | null; second_party: string | null; link: string | null; date: string | null; term_ids: number[] } | null;
  defaults: { contract_ref_type: string; contract_ref_number: string | null; first_party: string; second_party: string | null; winner_locked: boolean };
  terms: { id: number; seq: number; name: string }[]; types: string[]; pics: Pic[]; presetTermId?: number | null;
}
export default function BaForm({ procurement: p, ba, defaults, terms, types, pics, presetTermId }: Props) {
  const form = useForm({
    type: ba?.type || types[0] || 'BAPS', number: ba?.number || '', title: ba?.title || '', sub_bidang: ba?.sub_bidang || p.sub_bidang || '', date: ba?.date || '',
    contract_ref_type: ba?.contract_ref_type || defaults.contract_ref_type, contract_ref_number: ba?.contract_ref_number || defaults.contract_ref_number || '',
    director_internal: ba?.director_internal || '', director_external: ba?.director_external || '', first_party: ba?.first_party || defaults.first_party,
    second_party: defaults.winner_locked ? defaults.second_party || '' : ba?.second_party || defaults.second_party || '', link: ba?.link || '',
    term_ids: ba?.term_ids || (presetTermId ? [presetTermId] : []) as number[], signatory_ids: [] as number[], sent_at: '',
  });
  const d = form.data; const e = form.errors as Record<string, string>;
  const save = () => (ba ? form.put(`/ba/${ba.id}`) : form.post(`/pengadaan/${p.code}/ba`));
  return (
    <>
      <Head title={ba ? 'Ubah BA' : 'Tambah BA'} />
      <PageHeader crumbs={[['Daftar Pengadaan', '/pengadaan'], [p.spp_number || p.code, `/pengadaan/${p.code}?tab=pasca`], [ba ? 'Ubah BA' : 'Tambah BA']]} title={ba ? `Ubah ${ba.type}` : 'Tambah Berita Acara'} desc={`${p.spp_number || p.code} · ${p.title}`}
        actions={<><Link href={ba ? `/ba/${ba.id}` : `/pengadaan/${p.code}?tab=pasca`}><Button>Batal</Button></Link><Button variant="primary" icon={Save} disabled={form.processing} onClick={save}>Simpan BA</Button></>} />
      <Card className="p-6"><div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Field label="Jenis dokumen" required><Select value={d.type} onChange={(x) => form.setData('type', x.target.value)}>{types.map((t) => <option key={t}>{t}</option>)}</Select></Field>
        <Field label="PIC Sub Bidang"><Select value={d.sub_bidang} onChange={(x) => form.setData('sub_bidang', x.target.value)}><option value="">–</option><option>PLN 1</option><option>PLN 2</option></Select></Field>
        <Field label="Nomor dokumen" error={e.number}><Input value={d.number} onChange={(x) => form.setData('number', x.target.value)} placeholder="Contoh: 40887.BA/STI.01.03/IC010208/2026" /></Field>
        <Field label="Tanggal dokumen"><Input type="date" value={d.date} onChange={(x) => form.setData('date', x.target.value)} /></Field>
        <Field label="Judul dokumen" required error={e.title} className="md:col-span-2"><Input value={d.title} onChange={(x) => form.setData('title', x.target.value)} /></Field>
        <Field label="Acuan kontrak"><Select value={d.contract_ref_type} onChange={(x) => form.setData('contract_ref_type', x.target.value)}>{['SP2K', 'PO', 'PKS', 'BAK'].map((t) => <option key={t}>{t}</option>)}</Select></Field>
        <Field label="Nomor acuan"><Input value={d.contract_ref_number} onChange={(x) => form.setData('contract_ref_number', x.target.value)} /></Field>
        <Field label="Direksi Pekerjaan Internal"><Input list="pic-names" value={d.director_internal} onChange={(x) => form.setData('director_internal', x.target.value)} /></Field>
        <Field label="Direksi Pekerjaan Eksternal"><Input list="pic-names" value={d.director_external} onChange={(x) => form.setData('director_external', x.target.value)} /></Field>
        <Field label="Pihak Pertama"><Input value={d.first_party} onChange={(x) => form.setData('first_party', x.target.value)} /></Field>
        <Field label="Pihak Kedua" hint={defaults.winner_locked ? 'Vendor pemenang dari SP2K/PO.' : undefined}><Input value={d.second_party} disabled={defaults.winner_locked} onChange={(x) => form.setData('second_party', x.target.value)} /></Field>
        <Field label="Tahap pembayaran terkait" required className="md:col-span-2" hint="Satu tahap boleh memiliki lebih dari satu BA."><MultiSelect options={terms.map((t) => ({ value: String(t.id), label: `Tahap ${t.seq}`, sub: t.name }))} value={d.term_ids.map(String)} onChange={(v) => form.setData('term_ids', v.map(Number))} /></Field>
        {!ba && <><Field label="PIC sirkuler TTD" className="md:col-span-2" hint="Penandatanganan berjalan paralel tanpa urutan."><MultiSelect options={pics.map((x) => ({ value: String(x.id), label: x.name, sub: x.title || x.role || '' }))} value={d.signatory_ids.map(String)} onChange={(v) => form.setData('signatory_ids', v.map(Number))} /></Field>
          <Field label="Tanggal kirim sirkuler"><Input type="date" value={d.sent_at} onChange={(x) => form.setData('sent_at', x.target.value)} /></Field></>}
        <Field label="Tautan dokumen BA" className="md:col-span-2" error={e.link}><LinkField value={d.link} onChange={(v) => form.setData('link', v)} /></Field>
      </div></Card>
      <datalist id="pic-names">{pics.map((x) => <option key={x.id} value={x.name} />)}</datalist>
    </>
  );
}
