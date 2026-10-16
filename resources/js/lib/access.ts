import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

export type MenuKey = 'dashboard' | 'pengadaan' | 'rkap' | 'bakb' | 'pks' | 'kontrak' | 'dokumen' | 'ba' | 'tahapan' | 'master';

/** Hak akses pengguna yang sedang login. Pemeriksaan sebenarnya tetap di server (middleware menu). */
export function useAccess() {
  const { auth } = usePage<PageProps>().props;
  const level = (m: MenuKey) => auth.access?.[m] ?? 'none';
  return {
    canView: (m: MenuKey) => level(m) !== 'none',
    canEdit: (m: MenuKey) => level(m) === 'edit',
    isSuper: !!auth.can.super,
  };
}
