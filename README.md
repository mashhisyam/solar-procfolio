# Procfolio

Aplikasi web internal untuk memantau siklus pengadaan di PLN Icon Plus, dari pengajuan sampai pembayaran vendor.

Procfolio tidak menjalankan approval atau transaksi pengadaan. Proses resmi tetap berada di aplikasi pengadaan, SAP dan sistem keuangan. Procfolio mencatat status yang diperbarui manual, lalu menghitung durasi, alert, kelengkapan dokumen dan jadwal pekerjaan secara otomatis.

## Dua progres per pengadaan

Setiap pengadaan dipantau lewat dua progres yang tampil di satu halaman detail.

**Progres 1 · Proses Pengadaan** berakhir saat SP2K/PO rilis.

| Skema / metode | Alur |
| --- | --- |
| SPP Pusat, Pengadaan Langsung | Pengaju (User → Manager → L1/VP → Direktur*) → Sales (Manager → L1/VP) → Anggaran (Staff → Manager → L1/VP → Direktur*) → Manager Pengadaan → Pelaksana Pengadaan (SPV Lakdan → Staff Lakdan) → SP2K Rilis |
| SPP Pusat, metode lain | Pengaju → Sales → Anggaran → Manager Pengadaan → Perencana Pengadaan (SPV Rendan → Staff Rendan) → Pelaksana Pengadaan (SPV Lakdan → Staff Lakdan) → SP2K Rilis |
| SPP Kemitraan | Pengaju → Sales → Anggaran → Partnership → PO Rilis |

\* Tahap Direktur bersifat opsional. Tahap pada Proses Pengadaan tidak dapat dilewati.

Pengembalian di Proses Pengadaan ada dua jenis:

| Jenis | Perilaku |
| --- | --- |
| Revisi Minor | Kembali ke User Pengaju; setelah diperbaiki, proses langsung kembali ke tahap yang mengembalikan tanpa mengulang approval. |
| Revisi Mayor | Kembali ke User Pengaju dan seluruh approval diulang dari awal; siklus approval bertambah satu. |

Setelah kontrak rilis, Progres 1 terkunci. Satu-satunya cara membukanya kembali adalah mengajukan amandemen, yang punya alur approval sendiri sampai Amandemen Rilis.

**Progres 2 · Proses Pasca Pengadaan** berjalan **satu alur untuk setiap tahap pembayaran**:

Pelaksanaan Pekerjaan → BA Penutupan → Proses GR → Invoicing → Verifikasi Invoice → Antri Bayar → Dibayar

Jadwal tahap dihitung dari tanggal kick off dan rencana tanggal selesai tiap tahap. Satu tahap boleh memiliki lebih dari satu BA. Proses GR hanya bisa diselesaikan setelah detail GR (nomor, jenis, nominal, tautan) diisi; invoice boleh dicatat atau tidak.

## Aturan utama

- Nomor SPP wajib diisi sejak registrasi, termasuk saat menyimpan draft.
- Setiap pengadaan wajib punya dasar pelanggan: minimal satu perjanjian pelanggan (Berita Acara Kesepakatan Biaya, Kontrak Perjanjian, Surat Penugasan) atau nomor PA (Project Activation). PA tidak memiliki detail; cukup nomornya di pengadaan.
- Setiap pengadaan memakai satu PRK. RAB diisi per baris (deskripsi, tipe anggaran, jumlah, satuan, harga) dan nilainya dihitung otomatis.
- Setiap pengadaan memiliki kategori (Lisensi/Non Lisensi, dari master).
- Dashboard tidak memuat daftar pengadaan. Isinya: jumlah pengadaan aktif/Urgent/Butuh Perhatian/Selesai, RAB Usulan Pengadaan, RAB Realisasi (nilai SP2K/PO setelah PPN), Revenue, Revenue vs Cost per perjanjian pelanggan (Revenue, RAB Pengadaan, Nilai SP2K), persebaran pengadaan aktif per posisi, dan Top 5 (waktu proses, nilai SP2K/PO, proses approval, vendor pemenang).
- Filter periode registrasi pengadaan: 2 bulan terakhir (bawaan), 6 bulan, 1 tahun, atau semua. Berlaku di Dashboard, Daftar Pengadaan, SP2K/PO & Kontrak Pengadaan, Dokumen, Berita Acara, Tahapan, dan ekspor Excel; pilihan disimpan di sesi.
- Setiap daftar memakai paginasi dengan pilihan 10/25/50/100 baris per halaman.
- Menu RKAP memantau penetapan anggaran tiap PRK per sub bidang: Nilai Pengajuan, Nilai Digunakan, dan Sisa Anggaran. Sisa boleh minus dan hanya diinformasikan.
- PRK yang dapat dipilih saat registrasi mengikuti sub bidang pengadaan.
- Pekerjaan dapat memiliki Kontrak Pengadaan di samping SP2K/PO. Perlakuannya sama dengan SP2K/PO (koreksi data, amandemen, halaman kontrak); vendornya mengikuti pemenang. Hanya SP2K/PO yang mengunci Proses Pengadaan, membentuk tahapan pembayaran, dan menjadi Cost. Satu pengadaan hanya boleh memiliki satu amandemen berjalan.
- Calon vendor diisi saat registrasi. Pemenang dipilih dari daftar itu saat rilis SP2K/PO, lalu otomatis menjadi Pihak Kedua di setiap BA.
- Tahapan pembayaran diisi saat rilis SP2K/PO: syarat penyerahan, rencana tanggal selesai, persentase, dan nilai bayar.
- Dokumen tidak diunggah. Setiap isian dokumen berupa tautan (SharePoint atau sharefolder).
- Isian teks bebas otomatis ditulis dengan huruf kapital.
- Alert durasi Proses Pengadaan: hijau 0–4 hari kerja, kuning 5–6, merah 7 ke atas. Ambangnya diatur di Master.
- Perubahan penting tercatat di audit log yang bersifat append-only.

## Teknologi

Laravel 12 · Inertia.js · React 18 · TypeScript · Tailwind CSS (Noto Sans) · PostgreSQL 16 · SSO Microsoft Entra ID

## Menjalankan di komputer sendiri

Prasyarat: PHP 8.2+, Composer, Node.js 20+, dan PostgreSQL (atau pakai Docker untuk databasenya saja).

```bash
git clone <url-repo> procfolio && cd procfolio
cp .env.example .env
composer install
npm install
php artisan key:generate
# Sesuaikan DB_* di .env, lalu:
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # opsional: data contoh untuk UAT
composer run dev                         # server + queue + vite
```

Buka `http://localhost:8000`. Selama SSO belum aktif, login lokal tersedia:

| Email | Kata sandi |
| --- | --- |
| admin@procfolio.local | procfolio123 |

Ganti kata sandi ini sebelum dipakai bersama, dan set `PROCFOLIO_LOCAL_LOGIN=false` di produksi.

## Menjalankan dengan Docker

```bash
cp .env.example .env
# Set APP_KEY (php artisan key:generate), DB_PASSWORD dan APP_URL
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

Aplikasi berjalan di `http://localhost:8080` (ubah lewat `APP_PORT`). Container `worker` menjalankan queue dan scheduler, termasuk email ringkasan harian pada hari kerja pukul 07:30.

## SSO Microsoft Entra ID

1. Daftarkan aplikasi di Microsoft Entra ID, lalu catat Client ID, Client Secret dan Tenant ID.
2. Tambahkan redirect URI: `https://<domain-procfolio>/auth/microsoft/callback`.
3. Isi `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID` dan `AZURE_REDIRECT_URI` di `.env`.
4. Isi `PROCFOLIO_ADMIN_EMAILS` dengan email yang otomatis menjadi admin saat login pertama.

Akun dibuat otomatis saat login SSO pertama dengan peran User Pengaju. Admin dapat mengubah peran di Master & Konfigurasi.

| Peran | Hak |
| --- | --- |
| Admin | Semua, termasuk Master & Konfigurasi |
| User Pengaju | Membuat dan memperbarui data pengadaan |
| Pemantau | Hanya membaca |

## Backup

`docker/backup.sh` menjalankan `pg_dump` harian dan menyimpannya 30 hari. Pasang di cron host:

```cron
30 1 * * * /opt/procfolio/docker/backup.sh >> /var/log/procfolio-backup.log 2>&1
```

## Pengujian

```bash
php artisan config:clear && php artisan test   # Pest (SQLite memori): hari kerja, alert, alur proses, amandemen, Kontrak Pengadaan, PA, periode, Dashboard, DemoSeeder
npm run typecheck      # TypeScript
./vendor/bin/pint      # Format kode PHP
./vendor/bin/phpstan analyse   # Analisis statis
```

## Struktur

```
app/
  Enums/            Status invoice, GR, tanda tangan, amandemen, jenis progres
  Http/Controllers/ Pengadaan, proses, kontrak, amandemen, tahapan, BA, GR/invoice, master
  Models/           Entitas Eloquent
  Services/
    WorkflowService.php      Mesin alur dua progres, amandemen, rilis kontrak
    ProcurementInsight.php   Status, durasi, alert, stepper, baris tabel
    ProcurementPresenter.php Data halaman detail
    DashboardStats.php       Angka dan grafik Dashboard
  Support/WorkdayCalendar.php  Hari kerja Indonesia
  Support/Period.php           Filter periode registrasi
database/migrations/  Skema
database/seeders/     ReferenceSeeder (wajib), PrkSeeder (impor PRK dari CSV), DemoSeeder (contoh)
database/data/prk.csv Daftar PRK untuk PrkSeeder
resources/js/
  Pages/            Halaman Inertia
  Components/       UI dasar, komponen bersama, form pengadaan
docker/             Nginx, PHP, skrip backup
```

## Catatan

Angka pada `.env.example` dan seeder adalah data contoh. Daftar PRK diimpor dari `database/data/prk.csv` (pemisah `;` atau `,`, kolom `coa;kode_anggaran;program;klasifikasi;cost_center;sub_bidang;nilai_pengajuan;tahun`) dengan `php artisan db:seed --class=PrkSeeder`. Sub bidang yang kosong ditebak dari nama cost center (PLN 1 / PLN 2). PRK juga dapat ditambah satu per satu di menu RKAP.
