import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, RotateCcw, CheckCheck, Plus, Trash2, X } from 'lucide-react';
import { Badge, Button, Card, CardHead, ConfirmDialog, Field, Input, KV, LinkButton, Modal, Select, Textarea, cx } from '@/Components/ui';
import { PageHeader, ProcLink, StatusBadge } from '@/Components/common';
import { fmtDate } from '@/lib/format';
import type { PageProps, Pic } from '@/types';

type Sig = { id: number; pic_id: number; name: string; title: string | null; company: string | null; status: string; sent_at: string | null; signed_at: string | null; note: string | null };
type Round = { id: number; seq: number; sent_at: string; context: string | null; signatories: Sig[] };
interface Props { ba: { id: number; type: string; number: string | null; title: string; sub_bidang: string | null; contract_ref_type: string | null; contract_ref_number: string | null; director_internal: string | null; director_external: string | null; first_party: string | null; second_party: string | null; link: string | null; manual_status: string | null; manual_reason: string | null; date: string | null; status: string; round: number; signed: number; total: number; days: number; procurement: { code: string; spp_number: string | null; title: string }; terms: { seq: number; name: string }[]; rounds: Round[] }; pics: Pic[] }

export default function BaShow({ ba, pics }: Props) {
  const { auth, app } = usePage<PageProps>().props;
  const can = auth.can.edit;
  const [active, setActive] = useState(ba.rounds.length ? ba.rounds[ba.rounds.length - 1].id : 0);
  const round = ba.rounds.find((r) => r.id === active) || ba.rounds[ba.rounds.length - 1];
  const isActive = round && round.id === ba.rounds[ba.rounds.length - 1]?.id;
  const [newRound, setNewRound] = useState(false); const [rf, setRf] = useState({ sent_at: app.today, context: '', copy: true });
  const [override, setOverride] = useState(false); const [ov, setOv] = useState({ status: 'TTD Selesai', reason: '' });
  const [addPic, setAddPic] = useState(''); const [del, setDel] = useState(false);
  const upd = (s: Sig, patch: Partial<Sig>) => router.put(`/ba-penandatangan/${s.id}`, { status: patch.status ?? s.status, signed_at: patch.signed_at ?? s.signed_at, note: patch.note ?? s.note }, { preserveScroll: true });
  return (
    <>
      <Head title={`${ba.type} ${ba.number || ''}`} />
      <PageHeader crumbs={[['Berita Acara', '/ba'], [`${ba.type} ${ba.number || ''}`]]} title={ba.title}
        meta={<><Badge tone="navy">{ba.type}</Badge><span className="num font-semibold">{ba.number || '–'}</span><StatusBadge status={ba.status} />{ba.manual_status && <Badge tone="warn">koreksi manual</Badge>}<ProcLink code={ba.procurement.code} spp={ba.procurement.spp_number} tab="pasca" /></>}
        actions={can && <><LinkButton url={ba.link} label="Buka dokumen" size="sm" /><Link href={`/ba/${ba.id}/edit`}><Button icon={Pencil}>Ubah</Button></Link><Button icon={RotateCcw} onClick={() => setNewRound(true)}>Buat Putaran Revisi</Button><Button variant="primary" icon={CheckCheck} onClick={() => setOverride(true)}>Koreksi status</Button><Button variant="ghost" icon={Trash2} onClick={() => setDel(true)}>Hapus</Button></>} />
      <div className="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_340px]">
        <Card>
          <CardHead title="Sirkuler tanda tangan" desc="PIC dapat menandatangani paralel tanpa urutan. Status BA selesai otomatis bila semua PIC putaran aktif selesai." />
          <div className="flex flex-wrap gap-2 border-b border-line px-5 py-3">{ba.rounds.map((r) => <button key={r.id} onClick={() => setActive(r.id)} className={cx('rounded-lg px-3 py-1.5 text-[12.5px] font-semibold', r.id === round?.id ? 'bg-brandsoft text-brand' : 'text-mute hover:bg-sunken')}>Putaran {r.seq}{r.id === ba.rounds[ba.rounds.length - 1].id ? ' (aktif)' : ''}</button>)}</div>
          {round ? (
            <div className="p-5">
              <p className="mb-3 text-[12.5px] text-mute">Dikirim {fmtDate(round.sent_at)}{round.context ? ` · ${round.context}` : ''}</p>
              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">{round.signatories.map((s) => (
                <div key={s.id} className={cx('rounded-xl border p-4', s.status === 'Selesai TTD' ? 'border-ok/40 bg-oksoft/40' : s.status === 'Perlu Revisi' ? 'border-warn/40 bg-warnsoft/40' : 'border-line')}>
                  <div className="flex items-start justify-between gap-2"><div><b className="text-[14px]">{s.name}</b><p className="text-[12px] text-mute">{s.title} · {s.company}</p></div>{can && isActive && s.status !== 'Selesai TTD' && <button aria-label="Hapus PIC" className="text-mute hover:text-bad" onClick={() => router.delete(`/ba-penandatangan/${s.id}`, { preserveScroll: true })}><X size={15} /></button>}</div>
                  <div className="mt-3 flex items-center gap-2">{can && isActive ? <Select className="h-8" value={s.status} onChange={(e) => upd(s, { status: e.target.value })}>{['Belum Diproses', 'Proses TTD', 'Selesai TTD', 'Perlu Revisi'].map((x) => <option key={x}>{x}</option>)}</Select> : <StatusBadge status={s.status} />}<span className="text-[12px] text-mute">{s.signed_at ? `Selesai ${fmtDate(s.signed_at)}` : `Kirim ${fmtDate(s.sent_at)}`}</span></div>
                  {s.note && <p className="mt-2 text-[12.5px] text-mute">{s.note}</p>}
                </div>))}</div>
              {can && isActive && <div className="mt-4 flex items-center gap-2"><Select className="max-w-[320px]" value={addPic} onChange={(e) => setAddPic(e.target.value)}><option value="">Tambah PIC penandatangan</option>{pics.filter((x) => !round.signatories.some((s) => s.pic_id === x.id)).map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</Select><Button icon={Plus} disabled={!addPic} onClick={() => router.post(`/ba-putaran/${round.id}/penandatangan`, { pic_id: addPic }, { preserveScroll: true, onSuccess: () => setAddPic('') })}>Tambah PIC</Button></div>}
            </div>
          ) : <p className="p-5 text-[13px] text-mute">Belum ada sirkuler. Buat putaran untuk mengedarkan BA.</p>}
        </Card>
        <Card className="self-start"><CardHead title="Data BA" /><div className="p-5"><KV cols={1} items={[['Jenis', ba.type], ['Nomor', ba.number || '–'], ['Acuan kontrak', `${ba.contract_ref_type || ''} ${ba.contract_ref_number || ''}`], ['Tanggal', fmtDate(ba.date)], ['Direksi internal', ba.director_internal || '–'], ['Direksi eksternal', ba.director_external || '–'], ['Pihak Pertama', ba.first_party || '–'], ['Pihak Kedua', ba.second_party || '–'], ['Tahap terkait', ba.terms.map((t) => `Tahap ${t.seq}`).join(', ') || '–'], ...(ba.manual_reason ? [['Alasan koreksi', ba.manual_reason]] as [string, string][] : [])]} /></div></Card>
      </div>
      <Modal open={newRound} onClose={() => setNewRound(false)} title="Buat putaran revisi" desc="Putaran lama tetap tersimpan." footer={<><Button onClick={() => setNewRound(false)}>Batal</Button><Button variant="primary" onClick={() => router.post(`/ba/${ba.id}/putaran`, rf, { preserveScroll: true, onSuccess: () => setNewRound(false) })}>Buka putaran</Button></>}>
        <div className="space-y-4"><Field label="Tanggal kirim ulang" required><Input type="date" value={rf.sent_at} onChange={(e) => setRf({ ...rf, sent_at: e.target.value })} /></Field><Field label="Konteks revisi" required><Textarea value={rf.context} onChange={(e) => setRf({ ...rf, context: e.target.value })} /></Field><label className="flex items-center gap-2 text-[13px]"><input type="checkbox" checked={rf.copy} onChange={(e) => setRf({ ...rf, copy: e.target.checked })} />Salin PIC dari putaran sebelumnya</label></div>
      </Modal>
      <Modal open={override} onClose={() => setOverride(false)} title="Koreksi manual status BA" desc="Gunakan bila dokumen fisik sudah lengkap namun status sirkuler belum diperbarui." footer={<><Button onClick={() => setOverride(false)}>Batal</Button>{ba.manual_status && <Button onClick={() => router.post(`/ba/${ba.id}/koreksi`, { status: '', reason: ov.reason || 'Batalkan koreksi' }, { onSuccess: () => setOverride(false) })}>Batalkan koreksi</Button>}<Button variant="primary" onClick={() => router.post(`/ba/${ba.id}/koreksi`, ov, { preserveScroll: true, onSuccess: () => setOverride(false) })}>Simpan koreksi</Button></>}>
        <div className="space-y-4"><Field label="Status" required><Select value={ov.status} onChange={(e) => setOv({ ...ov, status: e.target.value })}>{['TTD Selesai', 'Proses TTD', 'Perlu Revisi'].map((x) => <option key={x}>{x}</option>)}</Select></Field><Field label="Alasan" required><Textarea value={ov.reason} onChange={(e) => setOv({ ...ov, reason: e.target.value })} /></Field></div>
      </Modal>
      <ConfirmDialog open={del} onClose={() => setDel(false)} onConfirm={() => router.delete(`/ba/${ba.id}`)} title="Hapus BA?" desc="Relasi ke tahap pembayaran dan GR ikut dilepas." confirmLabel="Hapus" tone="danger" />
    </>
  );
}
