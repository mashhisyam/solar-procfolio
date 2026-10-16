import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Plus, Save, Pencil, Trash2, ArrowUp, ArrowDown } from 'lucide-react';
import { Button, Card, CardHead, Field, Input, Modal, Select, Switch, TableWrap, Tabs, cx, td, th } from '@/Components/ui';
import { PageHeader } from '@/Components/common';
import { fmtDate } from '@/lib/format';
import { Pager, usePaged } from '@/Components/Pagination';
import { useAccess } from '@/lib/access';

type Company = { id: number; name: string; type: string; active: boolean };
type PicRow = { id: number; name: string; title: string | null; company_id: number | null; company?: { id: number; name: string } | null; role: string | null; sub_bidang: string | null; email: string | null; phone: string | null; active: boolean };
type Item = { id: number; group: string; label: string; active: boolean; locked: boolean };
type Tpl = { id: number; scheme: string; key: string; name: string; group?: string | null; enabled: boolean; optional: boolean; default_activities: string[] | null; pic_id: number | null };
interface Props { tab: string; companies: Company[]; pics: PicRow[]; items: Record<string, Item[]>; groups: Record<string, string>; templates: Record<string, Tpl[]>; settings: Record<string, number>; holidays: { id: number; date: string; name: string; type: string }[]; picRoles: string[] }

const TABS = [{ key: 'organisasi', label: 'Organisasi & PIC' }, { key: 'daftar', label: 'Master Daftar' }, { key: 'workflow', label: 'Workflow' }, { key: 'alert', label: 'Alert & Aturan' }, { key: 'kalender', label: 'Kalender' }];
const post = (url: string, data: Record<string, unknown>, done?: () => void) => router.post(url, data as never, { preserveScroll: true, onSuccess: done });

export default function MasterIndex(props: Props) {
  const access = useAccess();
  const [tab, setTab] = useState(TABS.some((t) => t.key === props.tab) ? props.tab : 'organisasi');
  return (
    <>
      <Head title="Master & Konfigurasi" />
      <PageHeader title="Master & Konfigurasi" desc="Referensi dropdown, template alur, alert dan kalender kerja. Data yang sudah dipakai dinonaktifkan, bukan dihapus." />
      <Tabs className="mb-5" value={tab} onChange={(t) => { setTab(t); window.history.replaceState({}, '', `?tab=${t}`); }} tabs={TABS} />
      {!access.canEdit('master') && <p className="mb-4 rounded-lg border border-info/30 bg-infosoft px-3 py-2 text-[13px]">Mode lihat: Anda hanya memiliki akses Lihat ke Master & Konfigurasi.</p>}
      {/* Tanpa akses Ubah, seluruh isian dan tombol simpan dinonaktifkan; server tetap menolak perubahan. */}
      <fieldset disabled={!access.canEdit('master')} className="min-w-0">
      {tab === 'organisasi' && <Org {...props} />}
      {tab === 'daftar' && <Lists items={props.items} groups={props.groups} />}
      {tab === 'workflow' && <Workflows templates={props.templates} />}
      {tab === 'alert' && <Settings settings={props.settings} />}
      {tab === 'kalender' && <Calendar holidays={props.holidays} />}
      </fieldset>
    </>
  );
}

function Org({ companies, pics, picRoles }: Props) {
  const [c, setC] = useState<Partial<Company> | null>(null); const [p, setP] = useState<Partial<PicRow> | null>(null);
  const cPg = usePaged(companies, '', 10); const pPg = usePaged(pics, '', 10);
  return (
    <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
      <Card><CardHead title={`Perusahaan (${companies.length})`} actions={<Button size="sm" icon={Plus} onClick={() => setC({ name: '', type: 'Vendor', active: true })}>Tambah</Button>} />
        <TableWrap><thead><tr><th className={cx(th, 'w-12')}>No</th>{['Nama', 'Tipe', 'Aktif', ''].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead><tbody>{cPg.view.map((x, rowIdx) => <tr key={x.id} className={cx(!x.active && 'opacity-60')}><td className={cx(td, 'num w-12 text-mute')}>{cPg.start + rowIdx + 1}</td><td className={td}>{x.name}</td><td className={td}>{x.type}</td><td className={td}>{x.active ? 'Ya' : 'Tidak'}</td><td className={td}><Button size="xs" variant="ghost" icon={Pencil} onClick={() => setC(x)}>Ubah</Button></td></tr>)}</tbody></TableWrap><Pager p={cPg} label="perusahaan" /></Card>
      <Card><CardHead title={`PIC (${pics.length})`} actions={<Button size="sm" icon={Plus} onClick={() => setP({ name: '', active: true })}>Tambah</Button>} />
        <TableWrap><thead><tr><th className={cx(th, 'w-12')}>No</th>{['Nama', 'Jabatan', 'Peran', 'Perusahaan', ''].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead><tbody>{pPg.view.map((x, rowIdx) => <tr key={x.id} className={cx(!x.active && 'opacity-60')}><td className={cx(td, 'num w-12 text-mute')}>{pPg.start + rowIdx + 1}</td><td className={td}>{x.name}</td><td className={td}>{x.title}</td><td className={td}>{x.role}</td><td className={td}>{x.company?.name}</td><td className={td}><Button size="xs" variant="ghost" icon={Pencil} onClick={() => setP(x)}>Ubah</Button></td></tr>)}</tbody></TableWrap><Pager p={pPg} label="PIC" /></Card>
      <Modal open={!!c} onClose={() => setC(null)} title={c?.id ? 'Ubah perusahaan' : 'Tambah perusahaan'} footer={<><Button onClick={() => setC(null)}>Batal</Button><Button variant="primary" icon={Save} onClick={() => post(`/master/perusahaan${c?.id ? `/${c.id}` : ''}`, c!, () => setC(null))}>Simpan</Button></>}>
        {c && <div className="space-y-4"><Field label="Nama" required><Input value={c.name || ''} onChange={(e) => setC({ ...c, name: e.target.value })} /></Field><Field label="Tipe" required><Select value={c.type} onChange={(e) => setC({ ...c, type: e.target.value })}>{['Internal', 'Pelanggan', 'Mitra', 'Vendor'].map((t) => <option key={t}>{t}</option>)}</Select></Field><Switch checked={!!c.active} onChange={(v) => setC({ ...c, active: v })} label="Aktif" /></div>}
      </Modal>
      <Modal open={!!p} onClose={() => setP(null)} title={p?.id ? 'Ubah PIC' : 'Tambah PIC'} footer={<><Button onClick={() => setP(null)}>Batal</Button><Button variant="primary" icon={Save} onClick={() => post(`/master/pic${p?.id ? `/${p.id}` : ''}`, p!, () => setP(null))}>Simpan</Button></>}>
        {p && <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Nama" required><Input value={p.name || ''} onChange={(e) => setP({ ...p, name: e.target.value })} /></Field>
          <Field label="Jabatan"><Input value={p.title || ''} onChange={(e) => setP({ ...p, title: e.target.value })} /></Field>
          <Field label="Peran" hint="Dipakai untuk menyarankan PIC di setiap tahap."><Select value={p.role || ''} onChange={(e) => setP({ ...p, role: e.target.value || null })}><option value="">– Tanpa peran –</option>{[...(p.role && !picRoles.includes(p.role) ? [p.role] : []), ...picRoles].map((r) => <option key={r} value={r}>{r}</option>)}</Select></Field>
          <Field label="Perusahaan"><Select value={p.company_id ?? ''} onChange={(e) => setP({ ...p, company_id: Number(e.target.value) || null })}><option value="">–</option>{companies.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</Select></Field>
          <Field label="Sub bidang"><Select value={p.sub_bidang || ''} onChange={(e) => setP({ ...p, sub_bidang: e.target.value })}><option value="">–</option><option>PLN 1</option><option>PLN 2</option></Select></Field>
          <Field label="Email"><Input value={p.email || ''} onChange={(e) => setP({ ...p, email: e.target.value })} /></Field>
          <Field label="Telepon"><Input value={p.phone || ''} onChange={(e) => setP({ ...p, phone: e.target.value })} /></Field>
          <Switch checked={!!p.active} onChange={(v) => setP({ ...p, active: v })} label="Aktif" />
        </div>}
      </Modal>
    </div>
  );
}

function Lists({ items, groups }: { items: Record<string, Item[]>; groups: Record<string, string> }) {
  const [add, setAdd] = useState<Record<string, string>>({});
  // Pesan gagal ditampilkan di kartu daftar yang sedang ditambah (sebelumnya gagal tanpa pesan).
  const [err, setErr] = useState<{ group: string; message: string } | null>(null);
  const addItem = (g: string) => router.post('/master/daftar', { group: g, label: add[g], active: true }, {
    preserveScroll: true, preserveState: true,
    onSuccess: () => { setAdd((a) => ({ ...a, [g]: '' })); setErr(null); },
    onError: (e) => setErr({ group: g, message: Object.values(e)[0] || 'Gagal menyimpan.' }),
  });
  return (
    <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">{Object.entries(groups).map(([g, label]) => (
      <Card key={g}><CardHead title={label} />
        <ul className="divide-y divide-line">{(items[g] || []).map((it) => <li key={it.id} className="flex items-center justify-between gap-2 px-4 py-2 text-[13px]"><span className={cx(!it.active && 'text-mute line-through')}>{it.label}{it.locked && <span className="ml-2 text-[11px] text-mute">inti</span>}</span><Switch checked={it.active} onChange={(v) => post(`/master/daftar/${it.id}`, { group: g, label: it.label, active: v })} label={it.active ? 'Aktif' : 'Nonaktif'} /></li>)}</ul>
        <div className="flex gap-2 border-t border-line p-3"><Input value={add[g] || ''} onChange={(e) => setAdd({ ...add, [g]: e.target.value })} onKeyDown={(e) => { if (e.key === 'Enter' && add[g]?.trim()) addItem(g); }} placeholder="Tambah pilihan" /><Button icon={Plus} disabled={!add[g]?.trim()} onClick={() => addItem(g)}>Tambah</Button></div>
        {err?.group === g && <p role="alert" className="px-3 pb-3 text-[12px] font-medium text-bad">{err.message}</p>}
      </Card>))}</div>
  );
}

function Workflows({ templates }: { templates: Record<string, Tpl[]> }) {
  const [scheme, setScheme] = useState('SPP Pusat');
  const [rows, setRows] = useState<Tpl[]>(templates['SPP Pusat'] || []);
  const pick = (s: string) => { setScheme(s); setRows(templates[s] || []); };
  const move = (i: number, d: -1 | 1) => { const n = [...rows]; [n[i], n[i + d]] = [n[i + d], n[i]]; setRows(n); };
  return (
    <Card><CardHead title="Template alur" desc="Perubahan berlaku untuk pengadaan baru; pengadaan berjalan memakai salinan alurnya sendiri."
      actions={<><Select className="w-[220px]" value={scheme} onChange={(e) => pick(e.target.value)}><option value="SPP Pusat">Proses Pengadaan · SPP Pusat</option><option value="SPP Kemitraan">Proses Pengadaan · SPP Kemitraan</option><option value="SPR">Proses Pengadaan · SPR</option><option value="Pasca">Pasca Pengadaan (per tahap)</option></Select><Button variant="primary" icon={Save} onClick={() => post(`/master/workflow/${scheme}`, { stages: rows.map((r) => ({ key: r.key, name: r.name, group: r.group ?? null, enabled: r.enabled, optional: r.optional, pic_id: r.pic_id, default_activities: r.default_activities || [] })) })}>Simpan</Button></>} />
      <TableWrap><thead><tr>{['Urutan', 'Kunci', 'Nama tahap', 'Aktif default', 'Opsional', 'Aktivitas default', ''].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
        <tbody>{rows.map((r, i) => <tr key={r.key + i}>
          <td className={td}><div className="flex gap-1"><button disabled={i === 0} onClick={() => move(i, -1)} aria-label="Naik" className="text-mute disabled:opacity-30"><ArrowUp size={14} /></button><button disabled={i === rows.length - 1} onClick={() => move(i, 1)} aria-label="Turun" className="text-mute disabled:opacity-30"><ArrowDown size={14} /></button></div></td>
          <td className={cx(td, 'num text-mute')}>{r.key}</td>
          <td className={td}><Input noCaps className="h-8" value={r.name} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)))} /></td>
          <td className={td}><Switch checked={r.enabled} onChange={(v) => setRows(rows.map((x, j) => (j === i ? { ...x, enabled: v } : x)))} label="" /></td>
          <td className={td}><Switch checked={r.optional} onChange={(v) => setRows(rows.map((x, j) => (j === i ? { ...x, optional: v } : x)))} label="" /></td>
          <td className={cx(td, 'min-w-[280px]')}><Input noCaps className="h-8" value={(r.default_activities || []).join(', ')} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, default_activities: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) } : x)))} /></td>
          <td className={td}>{scheme !== 'Pasca' && !['pengaju'].includes(r.key) && <button aria-label="Hapus tahap" className="text-mute hover:text-bad" onClick={() => setRows(rows.filter((_, j) => j !== i))}><Trash2 size={14} /></button>}</td>
        </tr>)}</tbody></TableWrap>
      {scheme !== 'Pasca' && <div className="p-3"><Button size="sm" icon={Plus} onClick={() => setRows([...rows.slice(0, -1), { id: 0, scheme, key: `tahap-${rows.length + 1}`, name: 'Tahap baru', enabled: true, optional: true, default_activities: ['Review Dokumen'], pic_id: null }, ...rows.slice(-1)])}>Tambah tahap</Button></div>}
    </Card>
  );
}

function Settings({ settings }: { settings: Record<string, number> }) {
  const [s, setS] = useState(settings);
  const F = ({ k, label }: { k: string; label: string }) => <Field label={label} required><Input type="number" min={1} className="num text-right" value={s[k] ?? ''} onChange={(e) => setS({ ...s, [k]: Number(e.target.value) })} /></Field>;
  return (
    <Card><CardHead title="Alert & aturan" desc="Durasi dalam hari kerja; batas pekerjaan dalam hari kalender." actions={<Button variant="primary" icon={Save} onClick={() => post('/master/pengaturan', s)}>Simpan</Button>} />
      <div className="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
        <div className="rounded-lg bg-oksoft p-3 text-[13px]"><b className="text-ok">Hijau</b><p className="num">0 – {(s.dur_warn || 5) - 1} hari</p></div>
        {F({ k: 'dur_warn', label: 'Kuning mulai (hari)' })}{F({ k: 'dur_bad', label: 'Merah mulai (hari)' })}
        {F({ k: 'no_update_days', label: 'Batas tanpa update' })}{F({ k: 'ba_stale_days', label: 'Batas BA di sirkuler' })}{F({ k: 'invoice_stale_days', label: 'Batas invoice tidak bergerak' })}{F({ k: 'deadline_warn_days', label: 'Attention batas pekerjaan (hari)' })}
      </div>
    </Card>
  );
}

function Calendar({ holidays }: { holidays: Props['holidays'] }) {
  const [h, setH] = useState({ date: '', name: '', type: 'Libur Nasional' });
  const pg = usePaged(holidays);
  return (
    <Card><CardHead title="Kalender kerja" desc="Libur nasional dan cuti bersama tidak dihitung sebagai hari kerja; hari kerja pengganti dihitung." />
      <div className="flex flex-wrap items-end gap-2 border-b border-line p-4"><Field label="Tanggal"><Input type="date" value={h.date} onChange={(e) => setH({ ...h, date: e.target.value })} /></Field><Field label="Keterangan"><Input value={h.name} onChange={(e) => setH({ ...h, name: e.target.value })} /></Field><Field label="Jenis"><Select value={h.type} onChange={(e) => setH({ ...h, type: e.target.value })}>{['Libur Nasional', 'Cuti Bersama', 'Hari Kerja Pengganti'].map((t) => <option key={t}>{t}</option>)}</Select></Field><Button icon={Plus} disabled={!h.date || !h.name} onClick={() => post('/master/kalender', h, () => setH({ date: '', name: '', type: 'Libur Nasional' }))}>Tambah</Button></div>
      <TableWrap><thead><tr><th className={cx(th, 'w-12')}>No</th>{['Tanggal', 'Keterangan', 'Jenis', ''].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead><tbody>{pg.view.map((x, rowIdx) => <tr key={x.id}><td className={cx(td, 'num w-12 text-mute')}>{pg.start + rowIdx + 1}</td><td className={cx(td, 'num')}>{fmtDate(x.date)}</td><td className={td}>{x.name}</td><td className={td}>{x.type}</td><td className={td}><button aria-label="Hapus" className="text-mute hover:text-bad" onClick={() => router.delete(`/master/kalender/${x.id}`, { preserveScroll: true })}><Trash2 size={14} /></button></td></tr>)}</tbody></TableWrap>
      <Pager p={pg} label="hari" />
    </Card>
  );
}

