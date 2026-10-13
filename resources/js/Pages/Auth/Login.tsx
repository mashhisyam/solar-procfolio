import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button, Field, Input } from '@/Components/ui';
import { Toaster } from '@/Components/common';
import type { PageProps } from '@/types';

export default function Login({ ssoEnabled, localLogin }: { ssoEnabled: boolean; localLogin: boolean }) {
  const { props } = usePage<PageProps>();
  const form = useForm({ email: '', password: '', remember: true });
  return (
    <div className="flex min-h-full items-center justify-center bg-bg px-4 py-10">
      <Head title="Masuk" />
      <div className="w-full max-w-[400px] rounded-2xl border border-line bg-surface p-7 shadow-card">
        <img src="/images/procfolio-logo.png" alt="Procfolio" className="mb-6 h-12 w-auto" />
        <h1 className="text-[20px] font-extrabold">Masuk ke Procfolio</h1>
        <p className="mt-1 text-[13px] text-mute">Monitoring pengadaan dari approval hingga pembayaran vendor.</p>
        {props.flash.error && <p role="alert" className="mt-4 rounded-lg bg-badsoft px-3 py-2 text-[13px] text-bad">{props.flash.error}</p>}
        {ssoEnabled && <a href="/auth/microsoft" className="btn-primary mt-6 flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-brand text-[14px] font-semibold text-white hover:bg-brand/90"><LogIn size={17} />Masuk dengan akun Microsoft</a>}
        {localLogin && (
          <form className="mt-6 space-y-4" onSubmit={(e) => { e.preventDefault(); form.post('/login'); }}>
            {ssoEnabled && <p className="text-center text-[12px] text-mute">atau login lokal (pengembangan)</p>}
            <Field label="Email" error={form.errors.email}><Input type="email" autoComplete="username" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} invalid={!!form.errors.email} /></Field>
            <Field label="Kata sandi"><Input type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></Field>
            <Button type="submit" variant={ssoEnabled ? 'secondary' : 'primary'} className="w-full" disabled={form.processing}>Masuk</Button>
          </form>
        )}
        {!ssoEnabled && !localLogin && <p className="mt-6 text-[13px] text-bad">SSO belum dikonfigurasi. Hubungi admin aplikasi.</p>}
      </div>
      <Toaster />
    </div>
  );
}
Login.layout = null;
