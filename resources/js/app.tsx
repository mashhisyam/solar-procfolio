import '../css/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

type PageModule = { default: React.ComponentType & { layout?: ((page: React.ReactNode) => React.ReactNode) | null } };

createInertiaApp({
  title: (title) => (title ? `${title} · Procfolio` : 'Procfolio'),
  resolve: (name) => {
    const pages = import.meta.glob<PageModule>('./Pages/**/*.tsx', { eager: true });
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) throw new Error(`Halaman ${name} tidak ditemukan.`);
    // Semua halaman memakai AppLayout kecuali yang menyatakan layout = null (mis. Login).
    if (page.default.layout === undefined) page.default.layout = (p: React.ReactNode) => <AppLayout>{p}</AppLayout>;
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
  progress: { color: '#1663CC' },
});
