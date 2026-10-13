<?php

return [
    /*
    | Login lokal (email + password) untuk pengembangan/UAT bila SSO Entra ID belum siap.
    | Di produksi set PROCFOLIO_LOCAL_LOGIN=false.
    */
    'local_login' => (bool) env('PROCFOLIO_LOCAL_LOGIN', true),

    /* Domain tautan dokumen yang diterima (dokumen tidak diunggah, hanya ditautkan). */
    'link_domains' => array_filter(array_map('trim', explode(',', (string) env('PROCFOLIO_LINK_DOMAINS', 'sharepoint.com')))),

    /* Email yang otomatis mendapat peran admin saat login pertama. */
    'admin_emails' => array_filter(array_map('trim', explode(',', (string) env('PROCFOLIO_ADMIN_EMAILS', '')))),

    /* Prefix ID monitoring internal: PRC-{tahun}-{urut 4 digit}. */
    'code_prefix' => 'PRC',
];
