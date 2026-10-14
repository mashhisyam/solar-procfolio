import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { RefreshCw, Pencil, FileSignature, FilePlus2, Ban, RotateCcw, ChevronDown, PlayCircle, Plus, ArrowRight, Info, Handshake, ScrollText, FileStack, PenLine } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, KV, LinkButton, Menu, Select, TableWrap, Tabs, cx, td, th } from '@/Components/ui';
import { AlertList, DeadlinePill, DurationPill, PageHeader, PriorityBadge, StatusBadge, Stepper } from '@/Components/common';
import UpdateProcessDrawer, { Mode } from '@/Components/procurement/UpdateProcessDrawer';
import { AmendmentReleaseModal, AmendmentRequestModal, CancelModal, ContractEditModal, ReleaseContractModal } from '@/Components/procurement/ContractModals';
import { DocModal, GrDrawer, InvoiceDrawer, TermModal } from '@/Components/procurement/SettlementForms';
import { fmtDate, fmtDateTime, rp } from '@/lib/format';
import type { Alert, BaSummary, CurrentVisit, HistoryVisit, PageProps, Pic, ProcRow, StageDef, StepNode, Term } from '@/types';

type Doc = { id: number; doc_type: string; number: string | null; title: string | null; date: string | null; required: boolean; available: boolean; link: string | null; note: string | null; customer: string | null };
interface Props {
  procurement: {
    code: string; spp_number: string | null; spp_date: string | null; title: string; summary: string | null; method: 'SPP' | 'Kemitraan'; proc_type: string | null; category: string | null; proc_method: string | null;
    priority: 'Normal' | 'Urgent'; sub_bidang: string | null; pengaju_name: string; registered_at: string; rab_value: number; is_draft: boolean; cancelled: boolean; cancel_reason: string | null;
    status: string; post_state: string; process_days: number; last_update: string; last_update_days: number; bak_number: string | null; bak_date: string | null;
    pks: { id: number; number: string; title: string; partner: string | null; label: string; end_date: string | null; future: number } | null;
    candidate_vendors: { id: number; name: string }[]; vendor_id: number | null;
    budget_codes: { id: number; coa: string; cost_center: string; code: string; program: string; klasifikasi: string }[];
    rab_items: { id: number; description: string; budget_type: string; budget_code: string | null; program: string | null; qty: number; unit: string; price: number; total: number }[];
    customer_contracts: { id: number; kind: string; number: string; title: string; customer: string | null; link: string | null }[];
  };
  header: ProcRow; alerts: Alert[];
  approval: { nodes: StepNode[]; history: HistoryVisit[]; current: CurrentVisit | null; stages: StageDef[]; all_stages: StageDef[]; locked: boolean; can_reopen: boolean; cycle: number; resume_stage: string | null };
  all_documents: { source: string; type: string; number: string | null; date: string | null; link: string | null; info: string | null; href: string | null; doc_id: number | null }[];
  amendment: { id: number; seq: number; reason: string; proposal: string | null; change_types: string[]; nodes: StepNode[] } | null;
  contract: { id: number; kind: string; number: string; title: string; vendor: string | null; vendor_id: number; date: string | null; kickoff_date: string | null; end_date: string | null; value_excl: number; value_incl: number; scope: string | null; link: string | null;
    amendments: { id: number; seq: number; status: string; reason: string; requested_at: string | null; number: string | null; date: string | null; change_types: string[]; description: string | null; before: Record<string, unknown> | null; after: Record<string, unknown> | null; proposal: string | null; link: string | null }[] } | null;
  terms: Term[]; pasca_stages: StageDef[]; documents: Doc[]; bas: BaSummary[];
  history: { at: string; user: string | null; entity: string; action: string; field: string | null; old: string | null; new: string | null; reason: string | null }[];
  options: { pics: Pic[]; activities: string[]; gr_types: string[]; contract_types: string[]; ba_types: string[]; change_types: string[]; invoice_statuses: string[]; gr_statuses: string[]; checklist: Record<string, string> };
}
const TABS = ['ringkasan', 'proses', 'dokumen', 'kontrak', 'pasca', 'riwayat'] as const;

export default function ProcurementShow(props: Props) {
  const { procurement: p, header: h, alerts, approval, amendment, contract, terms, documents, bas, history, options: o } = props;
  const { auth, flash } = usePage<PageProps>().props;
  const can = auth.can.edit && !p.cancelled;
  const [tab, setTab] = useState<string>(() => { const t = new URLSearchParams(window.location.search).get('tab') || 'ringkasan'; return (TABS as readonly string[]).includes(t) ? t : 'ringkasan'; });
  const [drawer, setDrawer] = useState<{ open: boolean; track: 'pengadaan' | 'pasca'; termId?: number; mode: Mode }>({ open: false, track: 'pengadaan', mode: 'update' });
  const [modal, setModal] = useState<'' | 'contract' | 'editContract' | 'amendReq' | 'amendRel' | 'cancel'>('');
  const [grTerm, setGrTerm] = useState<Term | null>(null); const [invTerm, setInvTerm] = useState<Term | null>(null);
  const [termModal, setTermModal] = useState<{ open: boolean; term: Term | null }>({ open: false, term: null });
  const [docModal, setDocModal] = useState<{ open: boolean; doc: Doc | null }>({ open: false, doc: null });
  useEffect(() => { if (flash.next?.type === 'contract') setModal('contract'); if (flash.next?.type === 'amendment-release') setModal('amendRel'); }, [flash]);
  const go = (t: string) => { setTab(t); window.history.replaceState({}, '', `?tab=${t}`); };
  const finalLabel = amendment ? 'rilis amandemen' : `rilis ${p.method === 'SPP' ? 'SP2K' : 'PO'}`;
  const openUpdate = (track: 'pengadaan' | 'pasca', termId?: number, mode: Mode = 'update') => setDrawer({ open: true, track, termId, mode });
  const focusTerm = terms.find((t) => !t.paid_at && t.current && !t.scheduled) || terms.find((t) => !t.paid_at && t.current);
  const drawerTerm = drawer.track === 'pasca' ? terms.find((t) => t.id === drawer.termId) || null : null;
  const pick = (a: Alert) => go(a.target === 'proses' ? 'proses' : a.target === 'dokumen' ? 'dokumen' : a.target === 'pasca' ? 'pasca' : 'ringkasan');

  return (
    <>
      <Head title={p.spp_number || p.code} />
      <PageHeader crumbs={[['Dashboard', '/'], ['Daftar Pengadaan', '/pengadaan'], [p.spp_number || p.code]]}
        title={<span className="block"><span className="mb-1.5 flex flex-wrap items-center gap-2 text-[13px] font-normal"><span className="num rounded-lg bg-navy px-2.5 py-1 text-[14px] font-extrabold text-white" title={p.method === 'SPP' ? 'Nomor SPP' : 'Nomor pengajuan'}>{p.spp_number || '–'}</span><span className="num rounded-md border border-line px-1.5 py-0.5 text-[11.5px] font-semibold text-mute" title="ID monitoring">{p.code}</span>{p.proc_method && <span className="text-mute">{p.proc_method}</span>}<Badge tone={p.method === 'SPP' ? 'navy' : 'teal'}>{p.method}</Badge><PriorityBadge p={p.priority} /><StatusBadge status={p.status} /></span>{p.title}</span>}
        actions={can && <>
          {!p.is_draft && <Button variant="primary" icon={RefreshCw} onClick={() => (approval.current ? openUpdate('pengadaan') : focusTerm ? openUpdate('pasca', focusTerm.id) : openUpdate('pengadaan'))}>Update Proses</Button>}
          <Link href={`/pengadaan/${p.code}/edit`}><Button icon={Pencil}>Edit</Button></Link>
          <Menu items={[
            contract ? { label: `Ajukan amandemen ${contract.kind}`, icon: FileSignature, onClick: () => setModal('amendReq'), disabled: !!amendment } : { label: `Rilis ${p.method === 'SPP' ? 'SP2K' : 'PO'}`, icon: FileSignature, onClick: () => setModal('contract'), disabled: p.is_draft },
            { label: 'Tambah Berita Acara', icon: FilePlus2, onClick: () => router.visit(`/pengadaan/${p.code}/ba/baru`), disabled: !contract },
            p.cancelled ? { label: 'Aktifkan kembali', icon: RotateCcw, onClick: () => router.post(`/pengadaan/${p.code}/aktifkan`) } : { label: 'Batalkan pengadaan', icon: Ban, onClick: () => setModal('cancel'), danger: true },
          ]} />
        </>} />

      <Card className="mb-5 grid grid-cols-2 gap-4 px-5 py-3.5 sm:grid-cols-4 lg:grid-cols-7">
        {([
          ['User Pengaju', p.pengaju_name || '–'],
          [h.track === 'pasca' ? 'Aliran data · Pasca' : h.amendment ? 'Aliran data · Amandemen' : 'Aliran data · Pengadaan', h.stage || '–'],
          ['Aktivitas saat ini', h.activity || '–'],
          ['PIC aliran data', h.pic || '–'],
          ['Lama di tahap', h.days !== null ? (h.track === 'pengadaan' ? <DurationPill days={h.days} level={h.level} /> : `${h.days} hari`) : '–'],
          ['Lama proses', `${p.process_days} hari`],
          ['Last Update', <span key="lu">{fmtDate(p.last_update)}<span className="block text-[11.5px] font-medium text-mute">{p.last_update_days === 0 ? 'hari ini' : `${p.last_update_days} hari lalu`}</span></span>],
        ] as [string, React.ReactNode][]).map(([k, v]) => <div key={k} className="min-w-0"><div className="text-[11.5px] font-medium text-mute">{k}</div><div className="text-[13.5px] font-bold leading-snug">{v}</div></div>)}
      </Card>

      {p.is_draft && <div className="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-info/30 bg-infosoft px-4 py-3 text-[13px]"><Info size={16} className="text-info" />Masih <b>Draft</b>. Monitoring belum berjalan sampai registrasi disimpan.<Link href={`/pengadaan/${p.code}/edit`} className="ml-auto"><Button size="sm" variant="primary" icon={Pencil}>Lengkapi registrasi</Button></Link></div>}
      {p.cancelled && <div className="mb-5 rounded-xl border border-line bg-sunken px-4 py-3 text-[13px]"><b>Dibatalkan.</b> {p.cancel_reason}</div>}

      {!p.is_draft && <ProgressPanel {...props} can={can} onUpdate={openUpdate} onModal={setModal} onTab={go} />}

      <Tabs className="mb-5" value={tab} onChange={go} tabs={[
        { key: 'ringkasan', label: 'Ringkasan', dot: alerts.length > 0 }, { key: 'proses', label: 'Histori Proses Pengadaan', count: approval.history.filter((x) => x.status !== 'Dilewati').length },
        { key: 'dokumen', label: 'Dokumen', count: props.all_documents.length },
        { key: 'kontrak', label: 'Kontrak', count: contract ? 1 : 0 }, { key: 'pasca', label: 'Pasca Pengadaan', count: terms.length }, { key: 'riwayat', label: 'Riwayat Perubahan', count: history.length },
      ]} />

      {tab === 'ringkasan' && (
        <div className="space-y-5">
          {alerts.length > 0 && <Card><CardHead title="Perlu tindak lanjut" /><div className="p-4"><AlertList alerts={alerts} onPick={pick} /></div></Card>}
          <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
            <Card className="xl:col-span-2">
              <CardHead title="Identitas" actions={can && <Link href={`/pengadaan/${p.code}/edit`}><Button size="xs" variant="ghost" icon={Pencil}>Edit</Button></Link>} />
              <div className="p-5"><KV cols={3} items={[
                ['ID monitoring', <span key="c" className="num">{p.code}</span>], [p.method === 'SPP' ? 'Nomor SPP' : 'Nomor pengajuan', p.spp_number || '–'], ['User Pengaju', p.pengaju_name || '–'],
                ['Nilai RAB', <span key="r" className="num">{rp(p.rab_value)}</span>], ['PRK', p.budget_codes.map((b) => `${b.code} · ${b.program}`).join(', ') || '–'], ['Skema / jenis', `${p.method} · ${p.proc_type || '–'}`],
                ...(p.method === 'SPP' ? [['Metode pengadaan', p.proc_method || '–']] as [string, string][] : []), ['Sub bidang', p.sub_bidang || '–'], ['Kategori pengadaan', p.category || '–'], ['Tanggal SPP', fmtDate(p.spp_date)],
                [p.method === 'Kemitraan' ? 'Calon mitra' : 'Calon vendor', <span key="v" className="flex flex-wrap gap-1">{p.candidate_vendors.map((c) => <Badge key={c.id} tone={p.vendor_id === c.id ? 'ok' : 'outline'}>{c.name}{p.vendor_id === c.id ? ' · pemenang' : ''}</Badge>)}</span>],
                ['Detail pengadaan', p.summary || '–'],
              ]} /></div>
            </Card>
            <Card>
              <CardHead title="Posisi saat ini" actions={can && !p.is_draft && <Button size="xs" variant="subtle" icon={RefreshCw} onClick={() => (approval.current ? openUpdate('pengadaan') : focusTerm && openUpdate('pasca', focusTerm.id))}>Update</Button>} />
              <dl className="divide-y divide-line px-5 py-1 text-[13px]">
                {([['Tahap aktif', h.stage || '–'], ['Lama di tahap', h.days !== null ? (h.track === 'pengadaan' ? <DurationPill days={h.days} level={h.level} /> : `${h.days} hari`) : '–'], ['Lama proses', `${p.process_days} hari kerja`], ['Last Update', `${fmtDate(p.last_update)} · ${p.last_update_days === 0 ? 'hari ini' : `${p.last_update_days} hari lalu`}`]] as [string, React.ReactNode][]).map(([k, v]) => <div key={k} className="flex items-center justify-between gap-3 py-2.5"><dt className="text-mute">{k}</dt><dd className="text-right font-bold">{v}</dd></div>)}
              </dl>
            </Card>
          </div>
          <RabCard p={p} />
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <Card>
              <CardHead icon={Handshake} title="Referensi" />
              <div className="space-y-4 p-5 text-[13px]">
                <div><p className="mb-1.5 text-[12px] font-medium text-mute">Perjanjian pelanggan ({p.customer_contracts.length})</p>{p.customer_contracts.length ? p.customer_contracts.map((c) => <Link key={c.id} href={`/bakb/${c.id}`} className="mb-1.5 block rounded-lg border border-line px-3 py-2 hover:border-brand"><span className="flex flex-wrap items-center gap-1.5"><Badge tone={c.kind === 'BAKB' ? 'navy' : c.kind === 'MoM Kesepakatan Harga' ? 'teal' : 'brand'}>{c.kind}</Badge><span className="num font-bold text-brand">{c.number}</span></span><span className="block text-[12px] text-mute">{c.title} · {c.customer}</span></Link>) : <p className="text-warn">Belum ada perjanjian pelanggan.</p>}</div>
                {documents.filter((d) => d.doc_type === 'Surat Penugasan').map((d) => <div key={d.id} className="flex items-center justify-between gap-2 rounded-lg border border-line px-3 py-2"><span><b>{d.doc_type}</b> <span className="num text-mute">{d.number}</span></span><LinkButton url={d.link} label="Buka" /></div>)}
                {p.method === 'Kemitraan' && <div><p className="mb-1.5 text-[12px] font-medium text-mute">PKS & BAK</p>{p.pks ? <Link href={`/pks/${p.pks.id}`} className="block rounded-lg border border-line px-3 py-2 hover:border-brand"><span className="flex items-center gap-2"><ScrollText size={14} className="text-brand" /><span className="num font-bold text-brand">{p.pks.number}</span></span><span className="block text-[12px]">Versi efektif: <b>{p.pks.label}</b></span></Link> : <p className="text-warn">PKS belum dipilih.</p>}<p className="mt-2">BAK: <b className="num">{p.bak_number || '–'}</b> {p.bak_date && <span className="text-mute">· {fmtDate(p.bak_date)}</span>}</p></div>}
              </div>
            </Card>
            <Card>
              <CardHead icon={FileStack} title="Dokumen wajib" actions={<Button size="xs" variant="ghost" onClick={() => go('dokumen')}>Semua dokumen</Button>} />
              <ul className="space-y-1 p-4">{documents.filter((d) => d.required).map((d) => <li key={d.id} className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-[13px]"><StatusBadge status={d.available ? 'Tersedia' : 'Belum Tersedia'} /><span className="flex-1 font-semibold">{d.doc_type}</span><LinkButton url={d.link} label="Buka" /></li>)}</ul>
            </Card>
          </div>
        </div>
      )}

      {tab === 'proses' && (
        <Card>
          <CardHead title="Histori Proses Pengadaan" desc={approval.locked ? `Terkunci sejak ${contract?.kind} ${contract?.number} rilis. Perubahan hanya melalui amandemen.` : undefined}
            actions={can && (approval.current ? <><Button size="sm" variant="primary" icon={RefreshCw} onClick={() => openUpdate('pengadaan')}>Update Proses</Button><Button size="sm" className="!border-warn/50 !text-warn" onClick={() => openUpdate('pengadaan', undefined, 'rev_minor')}>Rev Minor</Button><Button size="sm" className="!border-bad/50 !text-bad" onClick={() => openUpdate('pengadaan', undefined, 'rev_major')}>Rev Mayor</Button></> : approval.can_reopen ? <ReopenControl code={p.code} stages={approval.all_stages} /> : approval.locked && !amendment ? <Button size="sm" icon={FileSignature} onClick={() => setModal('amendReq')}>Ajukan Amandemen</Button> : undefined)} />
          <History visits={approval.history} />
        </Card>
      )}

      {tab === 'dokumen' && (
        <Card>
          <CardHead title="Seluruh dokumen pengadaan" desc="Dokumen pengadaan, perjanjian pelanggan, kontrak, Berita Acara, GR, dan invoice yang terkait pengadaan ini."
            actions={can && <Button size="sm" icon={Plus} onClick={() => setDocModal({ open: true, doc: null })}>Tambah dokumen</Button>} />
          {props.all_documents.length === 0 ? <EmptyState compact icon={FileStack} title="Belum ada dokumen" /> : (
            <TableWrap><thead><tr>{['Sumber', 'Jenis', 'Nomor', 'Tanggal', 'Keterangan', 'Tautan', ''].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
              <tbody>{props.all_documents.map((d, k) => {
                const doc = d.doc_id ? documents.find((x) => x.id === d.doc_id) : null;
                return (
                  <tr key={k}>
                    <td className={cx(td, 'whitespace-nowrap')}><Badge tone={d.source === 'Dokumen pengadaan' ? 'navy' : d.source === 'Berita Acara' ? 'teal' : d.source === 'Kontrak' ? 'brand' : 'outline'}>{d.source}</Badge></td>
                    <td className={cx(td, 'font-semibold')}>{d.type}</td>
                    <td className={cx(td, 'num')}>{d.number || '–'}</td>
                    <td className={cx(td, 'whitespace-nowrap')}>{fmtDate(d.date)}</td>
                    <td className={cx(td, 'text-[12.5px] text-mute')}>{d.info || '–'}</td>
                    <td className={td}><LinkButton url={d.link} label="Buka" /></td>
                    <td className={cx(td, 'whitespace-nowrap')}>{doc && can ? <Button size="xs" variant="ghost" icon={Pencil} onClick={() => setDocModal({ open: true, doc })}>Ubah</Button> : d.href ? <Link href={d.href} className="text-[12.5px] font-semibold text-brand hover:underline">Lihat</Link> : null}</td>
                  </tr>
                );
              })}</tbody>
            </TableWrap>
          )}
        </Card>
      )}

      {tab === 'kontrak' && (contract ? (
        <div className="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_360px]">
          <Card>
            <CardHead icon={FileSignature} title={`${contract.kind} ${contract.number}`} desc={contract.vendor || undefined} actions={<><LinkButton url={contract.link} label={`Buka ${contract.kind}`} size="sm" />{can && <Button size="sm" icon={Pencil} onClick={() => setModal('editContract')}>Koreksi data</Button>}{can && !amendment && <Button size="sm" variant="primary" icon={Plus} onClick={() => setModal('amendReq')}>Ajukan Amandemen</Button>}<Link href={`/kontrak/${contract.id}`}><Button size="sm" variant="ghost">Halaman kontrak</Button></Link></>} />
            <div className="p-5"><KV cols={3} items={[['Judul pekerjaan', contract.title], ['Vendor / mitra', contract.vendor || '–'], ['Tanggal kontrak', fmtDate(contract.date)], ['Nilai sebelum PPN', rp(contract.value_excl)], ['Nilai setelah PPN', rp(contract.value_incl)], ['Mulai – rencana selesai', `${fmtDate(contract.kickoff_date)} – ${fmtDate(contract.end_date)}`], ['Ruang lingkup', contract.scope || '–']]} /></div>
            <div className="border-t border-line p-5"><p className="mb-3 text-[13.5px] font-bold">Amandemen ({contract.amendments.length})</p>
              {contract.amendments.length === 0 ? <p className="text-[13px] text-mute">Belum ada amandemen.</p> : contract.amendments.map((a) => (
                <div key={a.id} className={cx('mb-3 rounded-lg border p-3 text-[13px] last:mb-0', a.status === 'Dalam Proses' ? 'border-warn/40 bg-warnsoft/40' : 'border-line')}>
                  <div className="flex flex-wrap items-center gap-2"><b>Amandemen {a.seq}</b><StatusBadge status={a.status === 'Dalam Proses' ? 'Dalam Proses' : a.status} />{a.number && <span className="num font-semibold">{a.number}</span>}<span className="text-mute">{a.date ? fmtDate(a.date) : `diajukan ${fmtDate(a.requested_at)}`}</span>{a.change_types.map((t) => <Badge key={t} tone="outline">{t}</Badge>)}{a.link && <LinkButton url={a.link} label="Buka" />}</div>
                  <p className="mt-1">{a.description || a.reason}</p>
                  {a.after && Object.keys(a.after).length > 0 && <p className="mt-1 text-[12.5px] text-mute">{Object.entries(a.after).map(([k, v]) => `${k}: ${String((a.before || {})[k] ?? '–')} → ${String(v)}`).join(' · ')}</p>}
                </div>))}
            </div>
          </Card>
          <Card className="self-start">
            <CardHead title="Tahapan pembayaran" desc={`Mulai ${fmtDate(contract.kickoff_date)} · selesai ${fmtDate(contract.end_date)}`} actions={can && <Button size="xs" icon={Plus} onClick={() => setTermModal({ open: true, term: null })}>Tambah</Button>} />
            <ul className="divide-y divide-line">{terms.map((t) => <li key={t.id} className="px-4 py-2.5 text-[13px]"><div className="flex items-start justify-between gap-2"><b>Tahap {t.seq} · {t.name}</b><span className="num whitespace-nowrap font-semibold">{t.percent ? `${t.percent}%` : rp(t.amount)}</span></div><div className="mt-1 flex flex-wrap items-center justify-between gap-2 text-[12px] text-mute"><span className="flex items-center gap-1.5">Rencana selesai {fmtDate(t.planned_end)}{!t.paid_at && <DeadlinePill date={t.planned_end} />}</span>{can && <button className="font-semibold text-brand" onClick={() => setTermModal({ open: true, term: t })}>Ubah</button>}</div></li>)}</ul>
          </Card>
        </div>
      ) : <Card><EmptyState icon={FileSignature} title={`${p.method === 'SPP' ? 'SP2K' : 'PO'} belum dirilis`} desc="Rilis kontrak setelah Proses Pengadaan selesai, beserta tahapan pembayarannya." action={can && !p.is_draft && <Button variant="primary" icon={FileSignature} onClick={() => setModal('contract')}>Rilis {p.method === 'SPP' ? 'SP2K' : 'PO'}</Button>} /></Card>)}

      {tab === 'pasca' && (
        <div className="space-y-5">
          {!contract ? <Card><EmptyState icon={FileSignature} title="Belum ada SP2K/PO" desc="Pasca Pengadaan terbentuk setelah SP2K/PO dirilis beserta tahapan pembayarannya." /></Card> : terms.map((t) => (
            <TermDetail key={t.id} code={p.code} t={t} bas={bas} can={can} onUpdate={(mode) => openUpdate('pasca', t.id, mode)} onGr={() => setGrTerm(t)} onInv={() => setInvTerm(t)} />
          ))}
          <Card>
            <CardHead icon={PenLine} title="Berita Acara" actions={can && contract && <Link href={`/pengadaan/${p.code}/ba/baru`}><Button size="sm" icon={Plus}>Tambah BA</Button></Link>} />
            {bas.length === 0 ? <EmptyState compact icon={PenLine} title="Belum ada Berita Acara" desc="BA dibuat dari halaman ini agar otomatis terhubung ke pengadaan dan tahap pembayaran." /> : (
              <TableWrap><thead><tr>{['Tahap', 'Jenis', 'Nomor', 'Judul', 'Tanggal', 'Status', 'Progres TTD', 'Tautan'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
                <tbody>{bas.map((b) => <tr key={b.id} className="cursor-pointer hover:bg-sunken/60" onClick={() => router.visit(`/ba/${b.id}`)}><td className={cx(td, 'num whitespace-nowrap font-semibold')}>{b.term_seqs.length ? b.term_seqs.map((n) => `Tahap ${n}`).join(', ') : <span className="text-mute">–</span>}</td><td className={td}><Badge tone="navy">{b.type}</Badge></td><td className={cx(td, 'num font-semibold')}>{b.number || '–'}</td><td className={td}>{b.title}</td><td className={td}>{fmtDate(b.date)}</td><td className={td}><StatusBadge status={b.status} /></td><td className={cx(td, 'num')}>{b.signed}/{b.total}{b.round > 1 && <span className="ml-1 text-warn">· putaran {b.round}</span>}</td><td className={td}><LinkButton url={b.link} label="Buka" /></td></tr>)}</tbody>
              </TableWrap>)}
          </Card>
        </div>
      )}

      {tab === 'riwayat' && (
        <Card><CardHead title="Riwayat perubahan" desc="Audit log append-only: siapa, kapan, nilai lama, nilai baru dan alasan." />
          {history.length === 0 ? <EmptyState compact title="Belum ada perubahan" /> : <TableWrap><thead><tr>{['Waktu', 'Pengguna', 'Entitas', 'Aksi', 'Field', 'Lama', 'Baru', 'Alasan'].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
            <tbody>{history.map((a, i) => <tr key={i}><td className={cx(td, 'whitespace-nowrap')}>{fmtDateTime(a.at)}</td><td className={td}>{a.user || 'Sistem'}</td><td className={td}>{a.entity}</td><td className={cx(td, 'font-semibold')}>{a.action}</td><td className={td}>{a.field || '–'}</td><td className={cx(td, 'max-w-[220px] text-mute')}>{a.old || '–'}</td><td className={cx(td, 'max-w-[220px]')}>{a.new || '–'}</td><td className={cx(td, 'max-w-[220px] text-mute')}>{a.reason || '–'}</td></tr>)}</tbody></TableWrap>}
        </Card>
      )}

      <UpdateProcessDrawer open={drawer.open} onClose={() => setDrawer({ ...drawer, open: false })} code={p.code} track={drawer.track} term={drawerTerm}
        current={drawer.track === 'pasca' ? drawerTerm?.current || null : approval.current} stages={drawer.track === 'pasca' ? props.pasca_stages : approval.stages}
        pics={o.pics} activities={o.activities} finalLabel={finalLabel} initialMode={drawer.mode} grTypes={o.gr_types} />
      <ReleaseContractModal open={modal === 'contract'} onClose={() => setModal('')} code={p.code} method={p.method} title={p.title} candidates={p.candidate_vendors} rab={p.rab_value} contractTypes={o.contract_types} runningStage={approval.current && !approval.current.amendment_id ? approval.current.stage : null} />
      {contract && <ContractEditModal open={modal === 'editContract'} onClose={() => setModal('')} contract={contract} />}
      {contract && <AmendmentRequestModal open={modal === 'amendReq'} onClose={() => setModal('')} code={p.code} kind={contract.kind} number={contract.number} stages={approval.all_stages} />}
      <AmendmentReleaseModal open={modal === 'amendRel'} onClose={() => setModal('')} code={p.code} amendment={amendment} contract={contract} />
      <CancelModal open={modal === 'cancel'} onClose={() => setModal('')} code={p.code} />
      <GrDrawer open={!!grTerm} onClose={() => setGrTerm(null)} code={p.code} term={grTerm} bas={bas} pics={o.pics} statuses={o.gr_statuses} />
      <InvoiceDrawer open={!!invTerm} onClose={() => setInvTerm(null)} code={p.code} term={invTerm} pics={o.pics} checklistLabels={o.checklist} />
      <TermModal open={termModal.open} onClose={() => setTermModal({ open: false, term: null })} code={p.code} term={termModal.term} bas={bas} />
      <DocModal open={docModal.open} onClose={() => setDocModal({ open: false, doc: null })} code={p.code} doc={docModal.doc} docTypes={['TOR', 'BCR (Benefit Cost Ratio)', 'BAK', 'SPH', 'Form Justifikasi Anggaran', 'BAKB', 'RKS', 'Lainnya']} />
    </>
  );
}

function ProgressPanel({ procurement: p, approval, amendment, contract, terms, bas, can, onUpdate, onModal, onTab }: Props & { can: boolean; onUpdate: (t: 'pengadaan' | 'pasca', termId?: number) => void; onModal: (m: 'contract' | 'amendReq' | 'amendRel') => void; onTab: (t: string) => void }) {
  const cur = approval.current;
  const orig = cur && !cur.amendment_id ? cur : null;
  const paid = terms.filter((t) => t.paid_at).length;
  const st1 = orig ? `${approval.cycle > 1 ? `Siklus approval ${approval.cycle} · ` : ''}${orig.stage} · ${orig.days ?? 0} hari kerja${approval.resume_stage ? ` · Revisi Minor, kembali ke ${approval.resume_stage}` : ''}` : contract ? `Selesai · ${contract.kind} rilis ${fmtDate(contract.date)} · terkunci` : approval.history.length ? `Approval selesai · ${p.method === 'SPP' ? 'SP2K' : 'PO'} belum dirilis` : 'Belum dimulai';
  const head = (n: number, title: string, status: string, tone: 'ok' | 'run' | 'idle', action: React.ReactNode, tab: string) => (
    <div className="min-w-0">
      <div className="flex items-center gap-2"><span className={cx('num flex h-6 w-6 items-center justify-center rounded-full text-[12px] font-bold', tone === 'ok' ? 'bg-ok text-white' : tone === 'run' ? 'bg-brand text-white' : 'bg-sunken text-mute')}>{n}</span><span className="text-[14px] font-bold">{title}</span></div>
      <p className={cx('mt-1 pl-8 text-[12.5px] font-medium', tone === 'ok' ? 'text-ok' : tone === 'run' ? 'text-brand' : 'text-mute')}>{status}</p>
      <div className="mt-2 flex flex-wrap gap-1.5 pl-8">{action}<Button size="xs" variant="ghost" onClick={() => onTab(tab)}>Histori</Button></div>
    </div>
  );
  return (
    <Card className="mb-5 divide-y divide-line">
      <div className="grid grid-cols-1 gap-4 px-5 py-4 lg:grid-cols-[250px_1fr]">
        {head(1, 'Proses Pengadaan', st1, contract ? 'ok' : orig ? 'run' : 'idle', can && (orig ? <Button size="xs" variant="primary" icon={RefreshCw} onClick={() => onUpdate('pengadaan')}>Update</Button> : !contract && approval.history.length ? <Button size="xs" variant="primary" icon={FileSignature} onClick={() => onModal('contract')}>Rilis {p.method === 'SPP' ? 'SP2K' : 'PO'}</Button> : contract && !amendment ? <Button size="xs" icon={FileSignature} onClick={() => onModal('amendReq')}>Ajukan Amandemen</Button> : null), 'proses')}
        <div className="min-w-0 self-center"><Stepper nodes={approval.nodes} /></div>
      </div>
      {amendment && (
        <div className="grid grid-cols-1 gap-4 bg-warnsoft/40 px-5 py-4 lg:grid-cols-[250px_1fr]">
          {head(1, `Amandemen ${amendment.seq} ${contract?.kind}`, `${cur?.amendment_id === amendment.id ? `${cur.stage} · ${cur.days ?? 0} hari kerja` : 'Menunggu rilis'} · ${amendment.reason}`, 'run', can && (cur?.amendment_id === amendment.id ? <Button size="xs" variant="primary" icon={RefreshCw} onClick={() => onUpdate('pengadaan')}>Update</Button> : <Button size="xs" variant="primary" onClick={() => onModal('amendRel')}>Rilis amandemen</Button>), 'proses')}
          <div className="min-w-0 self-center"><Stepper nodes={amendment.nodes} /></div>
        </div>
      )}
      <div className="px-5 py-4">
        <div className="mb-3">{head(2, 'Proses Pasca Pengadaan', !contract ? 'Terbentuk setelah SP2K/PO rilis' : !terms.length ? 'Tahapan pembayaran belum dibuat' : p.post_state === 'selesai' ? `Selesai · ${terms.length} tahap dibayar` : `${paid} dari ${terms.length} tahap dibayar · mulai ${fmtDate(contract.kickoff_date)}, selesai ${fmtDate(contract.end_date)}`, p.post_state === 'selesai' ? 'ok' : terms.length ? 'run' : 'idle', null, 'pasca')}</div>
        {terms.length > 0 && <div className="space-y-2">{terms.map((t) => <TermCard key={t.id} code={p.code} t={t} bas={bas} can={can} onUpdate={() => onUpdate('pasca', t.id)} onTab={onTab} />)}</div>}
      </div>
    </Card>
  );
}

function TermCard({ code, t, bas, can, onUpdate, onTab }: { code: string; t: Term; bas: BaSummary[]; can: boolean; onUpdate: () => void; onTab: (t: string) => void }) {
  const [open, setOpen] = useState(false);
  const total = t.nodes.length; const done = t.paid_at ? total : t.nodes.filter((n) => n.state === 'done' || n.state === 'skipped').length;
  return (
    <div className={cx('rounded-xl border transition', open ? 'border-brand/40 shadow-card' : 'border-line')}>
      <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex w-full flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 text-left">
        <ChevronDown size={16} className={cx('shrink-0 text-mute transition', open ? '' : '-rotate-90')} />
        <span className="min-w-[200px] flex-1"><span className="block text-[13.5px] font-bold">Tahap {t.seq} · {t.name}</span><span className="num flex flex-wrap items-center gap-1.5 text-[12px] text-mute">{t.percent ? `${t.percent}% · ` : ''}{rp(t.amount)}{t.planned_end ? ` · rencana selesai ${fmtDate(t.planned_end)}` : ''}{!t.paid_at && (!t.current || t.current.stage_key === 'pelaksanaan') && <DeadlinePill date={t.planned_end} />}</span></span>
        <span className="w-[120px]"><span className="block text-[11px] font-semibold text-mute">Progres</span><span className="mt-1 flex items-center gap-2"><span className="h-1.5 w-16 rounded-full bg-sunken"><span className={cx('block h-1.5 rounded-full', t.paid_at ? 'bg-ok' : 'bg-brand')} style={{ width: `${(done / total) * 100}%` }} /></span><span className="num text-[11.5px] text-mute">{done}/{total}</span></span></span>
        <span className="min-w-[170px]">{t.paid_at ? <StatusBadge status="Dibayar" /> : t.scheduled ? <Badge tone="outline">Terjadwal mulai {fmtDate(t.current?.entered_at)}</Badge> : t.current ? <span className="flex flex-wrap items-center gap-1.5"><Badge tone="info">{t.current.stage}</Badge><span className="num text-[12px] font-semibold text-mute">{t.current.days} hari</span>{t.late_days > 0 && <Badge tone="bad">Terlambat {t.late_days} hari</Badge>}</span> : '–'}</span>
      </button>
      {open && (
        <div className="border-t border-line px-4 pb-4 pt-4 fade-in">
          <Stepper nodes={t.nodes} />
          <div className="mt-4 grid grid-cols-2 gap-3 text-[12.5px] sm:grid-cols-4">
            <div><span className="text-mute">Rencana pekerjaan</span><div className="font-semibold">{t.plan ? `${fmtDate(t.plan.start)} – ${fmtDate(t.plan.end)}` : '–'}</div></div>
            <div><span className="text-mute">PIC tahap</span><div className="font-semibold">{t.current?.pic || '–'}</div></div>
            <div><span className="text-mute">Aktivitas terakhir</span><div className="font-semibold">{t.current?.activity || (t.paid_at ? `Dibayar ${fmtDate(t.paid_at)}` : '–')}</div></div>
            <div><span className="text-mute">Dokumen</span><div className="font-semibold">BA {t.ba_ids.length} · GR {t.gr?.status || 'belum ada'} · Invoice {t.invoice?.status || 'belum ada'}</div></div>
          </div>
          <div className="mt-4 -mx-4 border-t border-line"><TermBaList code={code} t={t} bas={bas} can={can} /></div>
          <div className="mt-4 flex flex-wrap gap-2">{can && t.current && !t.paid_at && <Button size="sm" variant="primary" icon={RefreshCw} onClick={onUpdate}>Update tahap</Button>}<Button size="sm" variant="ghost" iconRight={ArrowRight} onClick={() => onTab('pasca')}>Detail tahap</Button></div>
        </div>
      )}
    </div>
  );
}

function TermDetail({ code, t, bas, can, onUpdate, onGr, onInv }: { code: string; t: Term; bas: BaSummary[]; can: boolean; onUpdate: (m: Mode) => void; onGr: () => void; onInv: () => void }) {
  const [open, setOpen] = useState(false);
  return (
    <Card>
      <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex w-full flex-wrap items-center gap-3 px-5 py-3.5 text-left">
        <ChevronDown size={16} className={cx('text-mute transition', open ? '' : '-rotate-90')} />
        <span className="min-w-0 flex-1"><span className="block text-[14.5px] font-bold">Tahap {t.seq} · {t.name}</span><span className="num block text-[12px] text-mute">{t.percent ? `${t.percent}% · ` : ''}{rp(t.amount)}{t.planned_end ? ` · rencana selesai ${fmtDate(t.planned_end)}` : ''}</span></span>
        {t.paid_at ? <StatusBadge status="Dibayar" /> : t.scheduled ? <Badge tone="outline">Terjadwal</Badge> : t.current ? <Badge tone="info">{t.current.stage}</Badge> : null}
      </button>
      {open && (
        <div className="border-t border-line">
          <div className="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">
            {can && t.current && !t.paid_at && <><Button size="sm" variant="primary" icon={RefreshCw} onClick={() => onUpdate('update')}>Update</Button><Button size="sm" onClick={() => onUpdate('return')}>Kembalikan</Button><Button size="sm" onClick={() => onUpdate('skip')}>Lewati</Button></>}
            {can && <><Button size="sm" variant="ghost" onClick={onGr}>{t.gr ? `GR: ${t.gr.status}` : 'Catat GR'}</Button><Button size="sm" variant="ghost" onClick={onInv}>{t.invoice ? `Invoice: ${t.invoice.status}` : 'Catat invoice'}</Button></>}
          </div>
          <div className="px-5 py-5"><Stepper nodes={t.nodes} /></div>
          {(t.bucket_code || t.bucket_number || t.bucket_link) && <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-5 py-3 text-[13px]"><span className="text-mute">Bucket invoicing</span><span>Nomor <b className="num">{t.bucket_number || '–'}</b></span><span>Kode <b className="num">{t.bucket_code || '–'}</b></span><LinkButton url={t.bucket_link} label="Buka" /></div>}
          <TermBaList code={code} t={t} bas={bas} can={can} />
          <History visits={t.history} />
        </div>
      )}
    </Card>
  );
}

/** Daftar BA satu tahap pembayaran beserta progres tanda tangannya. Satu tahap boleh memiliki lebih dari satu BA. */
function TermBaList({ code, t, bas, can }: { code: string; t: Term; bas: BaSummary[]; can: boolean }) {
  const mine = bas.filter((b) => b.term_ids.includes(t.id));
  return (
    <div className="px-5 py-4">
      <div className="mb-2 flex items-center justify-between gap-2">
        <p className="text-[13.5px] font-bold">Berita Acara tahap {t.seq} <span className="num font-medium text-mute">({mine.length})</span></p>
        {can && <Link href={`/pengadaan/${code}/ba/baru?tahap=${t.id}`}><Button size="xs" icon={Plus}>Tambah BA</Button></Link>}
      </div>
      {mine.length === 0 ? <p className="text-[12.5px] text-mute">Belum ada BA untuk tahap ini.</p> : (
        <ul className="space-y-2">{mine.map((b) => (
          <li key={b.id}>
            <Link href={`/ba/${b.id}`} className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-line px-3 py-2 text-[13px] hover:border-brand">
              <Badge tone="navy">{b.type}</Badge>
              <span className="num font-semibold">{b.number || 'Tanpa nomor'}</span>
              <span className="min-w-0 flex-1 truncate text-mute">{b.title}</span>
              <span className="flex items-center gap-2"><span className="h-1.5 w-16 rounded-full bg-sunken"><span className={cx('block h-1.5 rounded-full', b.status === 'TTD Selesai' ? 'bg-ok' : 'bg-brand')} style={{ width: `${b.total ? (b.signed / b.total) * 100 : 0}%` }} /></span><span className="num text-[12px] text-mute">{b.signed}/{b.total} TTD</span></span>
              <StatusBadge status={b.status} />
            </Link>
          </li>))}</ul>
      )}
    </div>
  );
}

function History({ visits }: { visits: HistoryVisit[] }) {
  if (!visits.length) return <EmptyState compact title="Belum ada histori" />;
  return (
    <ol className="space-y-3 px-5 pb-5 pt-2">
      {visits.map((v) => (
        <li key={v.id} className="rounded-xl border border-line p-4">
          <div className="flex flex-wrap items-center gap-2"><b className="text-[14px]">{v.stage}</b>{v.cycle > 1 && <Badge tone="warn">siklus {v.cycle}</Badge>}{v.amendment_id && <Badge tone="warn">amandemen</Badge>}<StatusBadge status={v.status} /><span className="ml-auto text-[12px] text-mute">{fmtDate(v.entered_at)} → {v.completed_at ? fmtDate(v.completed_at) : 'sekarang'}</span></div>
          <div className="mt-2 grid grid-cols-3 gap-3 text-[12.5px]"><div><span className="text-mute">PIC</span><div className="font-semibold">{v.pic || '–'}</div></div><div><span className="text-mute">Aktivitas terakhir</span><div className="font-semibold">{v.activity || '–'}</div></div><div><span className="text-mute">Durasi</span><div className="num font-semibold">{v.days !== null ? `${v.days} hari kerja` : 'terjadwal'}</div></div></div>
          {v.return_reason && <p className={cx('mt-2 rounded-lg px-3 py-1.5 text-[12.5px]', v.return_type === 'mayor' ? 'bg-badsoft text-bad' : 'bg-warnsoft text-warn')}>{v.return_type === 'mayor' ? 'Revisi Mayor — approval diulang dari awal. ' : v.return_type === 'minor' ? 'Revisi Minor — kembali ke tahap ini setelah pengaju memperbaiki. ' : ''}Alasan: {v.return_reason}</p>}
          {v.activities.length > 0 && <ul className="mt-3 space-y-1 border-t border-line pt-2 text-[12.5px]">{v.activities.map((a, i) => <li key={i} className="flex gap-2"><span className="num w-[86px] shrink-0 text-mute">{fmtDate(a.date)}</span><Badge tone="outline">{a.category}</Badge><span className="min-w-0">{a.note}</span></li>)}</ul>}
        </li>
      ))}
    </ol>
  );
}

function ReopenControl({ code, stages }: { code: string; stages: StageDef[] }) {
  const [stage, setStage] = useState('');
  return <div className="flex items-center gap-2"><Select className="h-8 w-[200px]" value={stage} onChange={(e) => setStage(e.target.value)}><option value="">Buka kembali tahap…</option>{stages.filter((s) => s.enabled).map((s) => <option key={s.key} value={s.key}>{s.name}</option>)}</Select><Button size="sm" disabled={!stage} icon={PlayCircle} onClick={() => router.post(`/pengadaan/${code}/proses/buka`, { stage }, { preserveScroll: true })}>Buka</Button></div>;
}

function RabCard({ p }: { p: Props['procurement'] }) {
  const [open, setOpen] = useState(true);
  if (!p.rab_items.length) return null;
  return (
    <Card>
      <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex w-full items-center justify-between gap-3 px-5 py-3.5 text-left"><span><span className="block text-[15px] font-bold">Informasi RAB</span><span className="num block text-[12.5px] text-mute">{p.rab_items.length} baris · total {rp(p.rab_value)}</span></span><ChevronDown size={18} className={cx('text-mute transition', open ? 'rotate-180' : '')} /></button>
      {open && <div className="border-t border-line"><TableWrap>
        <thead><tr>{['Deskripsi', 'Tipe Anggaran', 'PRK', 'Jumlah', 'Satuan', 'Harga', 'Total'].map((x) => <th key={x} className={cx(th, ['Jumlah', 'Harga', 'Total'].includes(x) && 'text-right')}>{x}</th>)}</tr></thead>
        <tbody>{p.rab_items.map((r) => <tr key={r.id}><td className={cx(td, 'min-w-[260px] font-medium')}>{r.description}</td><td className={td}><Badge tone={r.budget_type === 'CAPEX' ? 'teal' : 'navy'}>{r.budget_type}</Badge></td><td className={cx(td, 'whitespace-nowrap')}><span className="num font-semibold">{r.budget_code}</span><span className="block max-w-[220px] truncate text-[11.5px] text-mute">{r.program}</span></td><td className={cx(td, 'num text-right')}>{r.qty.toLocaleString('id-ID')}</td><td className={td}>{r.unit}</td><td className={cx(td, 'num whitespace-nowrap text-right')}>{rp(r.price)}</td><td className={cx(td, 'num whitespace-nowrap text-right font-semibold')}>{rp(r.total)}</td></tr>)}</tbody>
        <tfoot><tr className="border-t-2 border-ink/70"><td colSpan={6} className="px-4 py-2.5 text-[13px] font-bold">Total</td><td className="num px-4 py-2.5 text-right text-[13.5px] font-extrabold">{rp(p.rab_value)}</td></tr></tfoot>
      </TableWrap></div>}
    </Card>
  );
}
