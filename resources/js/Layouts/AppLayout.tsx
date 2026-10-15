import React, { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Landmark, LayoutDashboard, ListChecks, Handshake, ScrollText, FileSignature, FileStack, PenLine, Receipt, Settings2, Moon, Sun, Menu as MenuIcon, X, LogOut, PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cx } from '@/Components/ui';
import { Toaster } from '@/Components/common';
import type { PageProps } from '@/types';

const NAV: { group: string; admin?: boolean; items: { href: string; label: string; icon: LucideIcon }[] }[] = [
  { group: 'Monitoring', items: [{ href: '/', label: 'Dashboard', icon: LayoutDashboard }, { href: '/pengadaan', label: 'Daftar Pengadaan', icon: ListChecks }, { href: '/rkap', label: 'RKAP', icon: Landmark }] },
  { group: 'Daftar Dokumen', items: [{ href: '/bakb', label: 'Perjanjian Pelanggan', icon: Handshake }, { href: '/pks', label: 'PKS & Amandemen', icon: ScrollText }, { href: '/kontrak', label: 'SP2K/PO & Kontrak', icon: FileSignature }] },
  { group: 'Register', items: [{ href: '/dokumen', label: 'Dokumen', icon: FileStack }, { href: '/ba', label: 'Berita Acara', icon: PenLine }, { href: '/tahapan', label: 'Tahapan, GR & Invoice', icon: Receipt }] },
  { group: 'Pengaturan', admin: true, items: [{ href: '/master', label: 'Master & Konfigurasi', icon: Settings2 }] },
];

function useTheme() {
  const [theme, setTheme] = useState<'light' | 'dark'>(() => (document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'));
  useEffect(() => { document.documentElement.setAttribute('data-theme', theme); try { localStorage.setItem('procfolio-theme', theme); } catch { /* */ } }, [theme]);
  return { theme, toggle: () => setTheme(theme === 'dark' ? 'light' : 'dark') };
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const { url, props } = usePage<PageProps>();
  const { auth, app } = props;
  const { theme, toggle } = useTheme();
  const [mobile, setMobile] = useState(false);
  const [collapsed, setCollapsed] = useState(() => { try { return localStorage.getItem('procfolio-side') === '1'; } catch { return false; } });
  useEffect(() => { try { localStorage.setItem('procfolio-side', collapsed ? '1' : '0'); } catch { /* */ } }, [collapsed]);
  useEffect(() => setMobile(false), [url]);
  const path = url.split('?')[0];
  const isActive = (href: string) => (href === '/' ? path === '/' : path === href || path.startsWith(href + '/'));

  const side = (mini: boolean) => (
    <div className="flex h-full flex-col border-r border-line bg-side text-sideink">
      <Link href="/" className={cx('flex items-center gap-3 pb-4 pt-5', mini ? 'justify-center px-2' : 'px-5')} aria-label="Procfolio, ke Dashboard">
        <img src="/images/procfolio-icon.png" alt="" className={mini ? 'h-9 w-auto' : 'h-10 w-auto'} />
        {!mini && <span><span className="grad-text block pb-0.5 font-display text-[23px] font-extrabold leading-none tracking-[-0.015em]">Procfolio</span><span className="mt-1 block text-[11px] leading-tight text-mute">Procurement Portfolio Monitoring</span></span>}
      </Link>
      <nav className={cx('flex-1 overflow-y-auto pb-3', mini ? 'px-2' : 'px-3')} aria-label="Menu utama">
        {NAV.filter((g) => !g.admin || auth.can.admin).map((g) => (
          <div key={g.group} className="mt-3">
            {mini ? <div className="mx-2 mb-1 border-t border-line" /> : <div className="px-2.5 pb-1 text-[11.5px] font-semibold text-mute">{g.group}</div>}
            {g.items.map((it) => {
              const on = isActive(it.href);
              return (
                <Link key={it.href} href={it.href} aria-current={on ? 'page' : undefined} title={mini ? it.label : undefined}
                  className={cx('mb-0.5 flex items-center rounded-lg py-2 text-[13.5px] font-semibold transition', mini ? 'justify-center' : 'gap-2.5 px-2.5', on ? 'bg-brandsoft text-brand shadow-[inset_3px_0_0_rgb(var(--brand))]' : 'text-ink/80 hover:bg-sunken hover:text-ink')}>
                  <it.icon size={mini ? 19 : 17} className={on ? 'text-brand' : 'text-mute'} />{!mini && it.label}
                </Link>
              );
            })}
          </div>
        ))}
      </nav>
      <div className={cx('space-y-1 border-t border-line py-3', mini ? 'px-2' : 'px-3')}>
        <button onClick={() => setCollapsed(!collapsed)} title={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'} className={cx('hidden w-full items-center rounded-lg py-2 text-[13px] font-semibold text-ink/80 hover:bg-sunken lg:flex', mini ? 'justify-center' : 'gap-2.5 px-2.5')}>{collapsed ? <PanelLeftOpen size={16} /> : <PanelLeftClose size={16} />}{!mini && 'Ciutkan sidebar'}</button>
        <button onClick={() => router.post('/logout')} title={mini ? 'Keluar' : undefined} className={cx('flex w-full items-center rounded-lg py-2 text-[13px] font-semibold text-ink/80 hover:bg-sunken', mini ? 'justify-center' : 'gap-2.5 px-2.5')}><LogOut size={16} />{!mini && 'Keluar'}</button>
      </div>
    </div>
  );

  return (
    <div className="flex h-full">
      <aside className={cx('hidden shrink-0 transition-[width] lg:block', collapsed ? 'w-[72px]' : 'w-[252px]')}>{side(collapsed)}</aside>
      {mobile && (
        <div className="fixed inset-0 z-50 flex lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/40" onClick={() => setMobile(false)} />
          <aside className="relative w-[268px] slide-in">{side(false)}<button className="absolute right-3 top-4 text-mute" aria-label="Tutup menu" onClick={() => setMobile(false)}><X size={18} /></button></aside>
        </div>
      )}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-line bg-surface px-4 lg:px-6">
          <button className="flex h-9 w-9 items-center justify-center rounded-lg text-mute hover:bg-sunken lg:hidden" aria-label="Buka menu" onClick={() => setMobile(true)}><MenuIcon size={19} /></button>
          {path !== '/' && <button onClick={() => window.history.back()} className="flex h-9 items-center gap-1.5 rounded-lg border border-line px-3 text-[13px] font-semibold hover:bg-sunken" aria-label="Kembali ke halaman sebelumnya"><ArrowLeft size={16} />Kembali</button>}
          <div className="flex-1" />
          <button onClick={toggle} className="flex h-9 w-9 items-center justify-center rounded-lg text-mute hover:bg-sunken" aria-label={theme === 'dark' ? 'Mode terang' : 'Mode gelap'}>{theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}</button>
          {auth.user && (
            <div className="ml-1 flex items-center gap-2.5 border-l border-line pl-3">
              <span className="grad-brand flex h-9 w-9 items-center justify-center rounded-full text-[13px] font-bold text-white">{auth.user.name.split(' ').map((w) => w[0]).slice(0, 2).join('')}</span>
              <span className="hidden text-[12.5px] leading-tight sm:block"><b className="block text-ink">{auth.user.name}</b><span className="text-mute">{{ admin: 'Admin', pengaju: 'User Pengaju', viewer: 'Pemantau' }[auth.user.role] || auth.user.role}</span></span>
            </div>
          )}
        </header>
        <main className="min-w-0 flex-1 overflow-y-auto px-4 py-6 lg:px-8" id="main">{children}</main>
      </div>
      <Toaster />
      <span className="sr-only">{app.name}</span>
    </div>
  );
}
