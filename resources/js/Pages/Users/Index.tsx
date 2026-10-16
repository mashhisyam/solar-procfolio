import React, { useEffect, useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Pencil, Save, ShieldCheck, KeyRound, UsersRound } from 'lucide-react';
import { Badge, Button, Card, CardHead, EmptyState, Field, Input, Modal, SearchInput, Segmented, Select, Switch, TableWrap, cx, td, th } from '@/Components/ui';
import { PageHeader } from '@/Components/common';
import { Pager, usePaged } from '@/Components/Pagination';

type Level = 'none' | 'view' | 'edit';
interface UserRow { id: number; name: string; email: string; role: 'super_admin' | 'admin'; active: boolean; pic_id: number | null; pic: string | null; sso: boolean; owner: boolean; permissions: Record<string, Level>; created_at: string | null }
interface MenuDef { key: string; label: string; levels: Level[] }
interface Props {
  users: UserRow[]; menus: MenuDef[]; roles: { value: string; label: string }[];
  defaults: { admin: Record<string, Level>; view_only: Record<string, Level>; full: Record<string, Level> };
  pics: { id: number; name: string; role: string | null }[];
}

const LEVEL_LABEL: Record<Level, string> = { none: 'Tidak ada', view: 'Lihat', edit: 'Ubah' };
const LEVEL_TONE: Record<Level, string> = { none: 'bg-sunken text-mute', view: 'bg-infosoft text-info', edit: 'bg-oksoft text-ok' };

/** Kelola Pengguna (Super Admin): akun, peran, hak akses per menu, status, kata sandi. */
export default function UsersIndex({ users, menus, roles, defaults, pics }: Props) {
  const [q, setQ] = useState('');
  const [edit, setEdit] = useState<UserRow | 'new' | null>(null);
  const rows = useMemo(() => users.filter((u) => !q.trim() || `${u.name} ${u.email}`.toLowerCase().includes(q.trim().toLowerCase())), [users, q]);
  const pg = usePaged(rows, q);

  return (
    <>
      <Head title="Kelola Pengguna" />
      <PageHeader title="Kelola Pengguna" desc="Hanya Super Admin. Buat akun Procfolio dan atur menu yang dapat diakses setiap pengguna."
        actions={<Button variant="primary" icon={Plus} onClick={() => setEdit('new')}>Tambah Akun</Button>} />

      <Card className="mb-5">
        <CardHead icon={ShieldCheck} title="Peran dan hak akses" />
        <div className="grid grid-cols-1 gap-4 px-5 pb-5 text-[13px] md:grid-cols-2">
          <div className="rounded-lg border border-line p-3"><Badge tone="navy">Super Admin</Badge><p className="mt-2 text-ink/80">Seluruh menu tingkat Ubah, Kelola Pengguna, dan hapus pengadaan. Hak aksesnya tidak dapat dibatasi.</p></div>
          <div className="rounded-lg border border-line p-3"><Badge tone="brand">Admin</Badge><p className="mt-2 text-ink/80">Hak akses per menu diatur di sini: <b>Tidak ada</b> (menu disembunyikan), <b>Lihat</b>, atau <b>Ubah</b>. Ubah di Daftar Pengadaan mencakup semua aksi di halaman detail pengadaan.</p></div>
        </div>
      </Card>

      <Card>
        <div className="flex flex-wrap items-center gap-2 border-b border-line p-3">
          <SearchInput className="min-w-[240px] flex-1" value={q} onChange={setQ} placeholder="Cari nama atau email" />
          <span className="num text-[12.5px] text-mute">{rows.length} akun</span>
        </div>
        {pg.view.length === 0 ? <EmptyState icon={UsersRound} title="Tidak ada akun yang cocok" /> : (
          <TableWrap><thead><tr><th className={cx(th, 'w-12')}>No</th>{['Nama', 'Email', 'Peran', 'Status', 'Hak akses', ''].map((x) => <th key={x} className={th}>{x}</th>)}</tr></thead>
            <tbody>{pg.view.map((u, rowIdx) => (
              <tr key={u.id} className={cx(!u.active && 'opacity-60')}><td className={cx(td, 'num w-12 text-mute')}>{pg.start + rowIdx + 1}</td>
                <td className={td}><b>{u.name}</b>{u.owner && <Badge tone="outline" className="ml-1.5">Pemilik</Badge>}{u.pic && <div className="text-[12px] text-mute">PIC: {u.pic}</div>}</td>
                <td className={cx(td, 'num')}>{u.email}{u.sso && <div className="text-[11.5px] text-mute">Terhubung SSO</div>}</td>
                <td className={td}><Badge tone={u.role === 'super_admin' ? 'navy' : 'brand'}>{u.role === 'super_admin' ? 'Super Admin' : 'Admin'}</Badge></td>
                <td className={td}><Badge tone={u.active ? 'ok' : 'neutral'}>{u.active ? 'Aktif' : 'Nonaktif'}</Badge></td>
                <td className={cx(td, 'min-w-[260px]')}>{u.role === 'super_admin' ? <span className="text-[12px] text-mute">Seluruh menu</span> : (
                  <div className="flex flex-wrap gap-1">{menus.filter((m) => u.permissions[m.key] !== 'none').map((m) => <span key={m.key} className={cx('rounded px-1.5 py-0.5 text-[11px] font-semibold', LEVEL_TONE[u.permissions[m.key]])} title={LEVEL_LABEL[u.permissions[m.key]]}>{m.label}{u.permissions[m.key] === 'edit' ? ' ✎' : ''}</span>)}</div>
                )}</td>
                <td className={cx(td, 'text-right')}><Button size="xs" variant="ghost" icon={Pencil} onClick={() => setEdit(u)}>Ubah</Button></td>
              </tr>
            ))}</tbody>
          </TableWrap>
        )}
        <Pager p={pg} label="akun" />
      </Card>

      {edit && <UserModal user={edit === 'new' ? null : edit} onClose={() => setEdit(null)} menus={menus} roles={roles} defaults={defaults} pics={pics} />}
    </>
  );
}

function UserModal({ user, onClose, menus, roles, defaults, pics }: { user: UserRow | null; onClose: () => void } & Omit<Props, 'users'>) {
  const form = useForm({
    name: user?.name || '', email: user?.email || '', password: '', role: (user?.role || 'admin') as string,
    pic_id: (user?.pic_id ?? null) as number | null, active: user?.active ?? true,
    permissions: { ...(user && user.role !== 'super_admin' ? user.permissions : defaults.admin) } as Record<string, Level>,
  });
  const d = form.data; const e = form.errors as Record<string, string>;
  const superAdmin = d.role === 'super_admin';
  useEffect(() => { form.clearErrors(); }, []); // eslint-disable-line react-hooks/exhaustive-deps
  const setLevel = (k: string, v: Level) => form.setData('permissions', { ...d.permissions, [k]: v });
  const save = () => (user ? form.put(`/pengguna/${user.id}`, { preserveScroll: true, onSuccess: onClose }) : form.post('/pengguna', { preserveScroll: true, onSuccess: onClose }));

  return (
    <Modal open onClose={onClose} width="max-w-3xl" title={user ? `Ubah akun ${user.email}` : 'Tambah akun Procfolio'}
      desc={user?.owner ? 'Akun pemilik: tidak dapat dinonaktifkan atau diturunkan dari Super Admin.' : undefined}
      footer={<><Button onClick={onClose}>Batal</Button><Button variant="primary" icon={Save} disabled={form.processing} onClick={save}>Simpan</Button></>}>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Nama" required error={e.name}><Input value={d.name} invalid={!!e.name} onChange={(x) => form.setData('name', x.target.value)} /></Field>
        <Field label="Email" required error={e.email}><Input noCaps type="email" value={d.email} invalid={!!e.email} onChange={(x) => form.setData('email', x.target.value)} placeholder="nama@iconpln.co.id" /></Field>
        <Field label={user ? 'Kata sandi baru' : 'Kata sandi'} required={!user} error={e.password} hint={user ? 'Kosongkan bila tidak diubah. Minimal 8 karakter.' : 'Minimal 8 karakter. Sampaikan ke pengguna secara terpisah.'}>
          <Input noCaps type="password" autoComplete="new-password" value={d.password} invalid={!!e.password} onChange={(x) => form.setData('password', x.target.value)} />
        </Field>
        <Field label="Peran" required error={e.role}><Select value={d.role} onChange={(x) => form.setData('role', x.target.value)}>{roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}</Select></Field>
        <Field label="PIC terkait" hint="Opsional. Menghubungkan akun dengan data PIC di Master."><Select value={d.pic_id ?? ''} onChange={(x) => form.setData('pic_id', Number(x.target.value) || null)}><option value="">Tidak dihubungkan</option>{pics.map((p) => <option key={p.id} value={p.id}>{p.name}{p.role ? ` · ${p.role}` : ''}</option>)}</Select></Field>
        <div className="flex items-end pb-2"><Switch checked={d.active} onChange={(v) => form.setData('active', v)} label={d.active ? 'Akun aktif' : 'Akun nonaktif'} /></div>
      </div>

      <div className="mt-5 rounded-xl border border-line">
        <div className="flex flex-wrap items-center gap-2 border-b border-line px-4 py-2.5">
          <KeyRound size={15} className="text-brand" /><b className="text-[13.5px]">Hak akses menu</b>
          {!superAdmin && <div className="ml-auto flex flex-wrap gap-1.5">
            <Button size="xs" variant="ghost" onClick={() => form.setData('permissions', { ...defaults.admin })}>Bawaan Admin</Button>
            <Button size="xs" variant="ghost" onClick={() => form.setData('permissions', { ...defaults.view_only })}>Hanya lihat</Button>
            <Button size="xs" variant="ghost" onClick={() => form.setData('permissions', { ...defaults.full })}>Semua Ubah</Button>
          </div>}
        </div>
        {superAdmin ? <p className="px-4 py-3 text-[13px] text-mute">Super Admin selalu memiliki akses Ubah ke seluruh menu, Kelola Pengguna, dan hapus pengadaan.</p> : (
          <table className="w-full text-[13px]"><tbody>{menus.map((m) => (
            <tr key={m.key} className="border-t border-line first:border-t-0">
              <td className="px-4 py-2 font-semibold">{m.label}</td>
              <td className="px-4 py-2 text-right"><Segmented value={d.permissions[m.key] || 'none'} onChange={(v) => setLevel(m.key, v as Level)} options={m.levels.map((l) => ({ value: l, label: LEVEL_LABEL[l] }))} /></td>
            </tr>
          ))}</tbody></table>
        )}
        {e.permissions && <p className="px-4 pb-3 text-[12px] font-medium text-bad">{e.permissions}</p>}
      </div>
    </Modal>
  );
}
