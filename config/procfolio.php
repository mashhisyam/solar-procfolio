<?php

return [
    /*
    | Login lokal (email + password) untuk pengembangan/UAT bila SSO Entra ID belum siap.
    | Di produksi set PROCFOLIO_LOCAL_LOGIN=false.
    */
    'local_login' => (bool) env('PROCFOLIO_LOCAL_LOGIN', true),

    /* Domain tautan dokumen yang diterima (dokumen tidak diunggah, hanya ditautkan). */
    'link_domains' => array_filter(array_map('trim', explode(',', (string) env('PROCFOLIO_LINK_DOMAINS', 'sharepoint.com')))),

    /*
    | Kata sandi awal akun pemilik (superadmin@admin.com dan admin@admin.com). Hanya dipakai saat akun dibuat pertama kali;
    | ganti setelah login pertama. Di produksi isi lewat .env agar tidak memakai nilai bawaan.
    */
    'owner_passwords' => [
        'super_admin' => (string) (env('PROCFOLIO_SUPERADMIN_PASSWORD') ?: '*tr4ns4ct10n#'),
        'admin' => (string) (env('PROCFOLIO_ADMIN_PASSWORD') ?: 'admin123'),
    ],

    /* Email yang otomatis menjadi Super Admin saat login SSO pertama. */
    'admin_emails' => array_filter(array_map('trim', explode(',', (string) env('PROCFOLIO_ADMIN_EMAILS', '')))),

    /* Prefix ID monitoring internal: PRC-{tahun}-{urut 4 digit}. */
    'code_prefix' => 'PRC',
];
