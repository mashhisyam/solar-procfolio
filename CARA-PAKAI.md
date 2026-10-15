# Patch 9 — Procfolio (18 Okt 2026)

Berisi hanya file yang berubah, dengan struktur folder sama. Timpa ke repositori (branch `develop`), lalu jalankan perintah di bawah.

## 1. Bersihkan dulu (manual, patch tidak bisa menghapus file)

Hapus empat file nyasar di root repo; isinya duplikat dan membuat `pint --test` di CI gagal:

```
ContractController.php
CustomerContractController.php
ProcurementPresenter.php
web.php
```

Hapus juga folder kosong `resources/js/Pages/{Contracts,CustomerContracts,Pks,BeritaAcara,Documents,Settlement,Master}` bila ada.
`.github/workflows/ci.yml` di working tree Anda terhapus; patch ini memulihkannya.

## 2. Perintah

```powershell
$env:Path = "C:\php84;$env:Path"
php artisan migrate                 # revisi_9: Kontrak Pengadaan, nomor PA, sub bidang PRK
php artisan db:seed --class=ReferenceSeeder   # ikut menjalankan PrkSeeder (database/data/prk.csv)
php artisan config:clear
php artisan test                    # 27 tes, seharusnya lulus semua
npm run build
```

Data demo dari awal (menghapus semua data):

```powershell
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Sebagian besar data demo berumur lebih dari 2 bulan. Pilih periode **6 bulan** atau **Semua waktu** untuk melihat semuanya.

## 3. Ringkasan isi

**Detail Pengadaan**
- Kontrak di samping SP2K/PO bernama **Kontrak Pengadaan**, disimpan di `vendor_contracts` (`is_primary = false`). Kartunya sama dengan SP2K/PO: Koreksi data, Ajukan Amandemen, Halaman kontrak. Tab: "SP2K & Kontrak Pengadaan".
- Vendor otomatis pemenang. Tidak membentuk tahapan pembayaran dan tidak mengubah Cost. Satu amandemen berjalan per pengadaan.
- **PA** bukan lagi perjanjian pelanggan; cukup "Nomor PA" di langkah 2. Dasar pelanggan = minimal satu perjanjian pelanggan **atau** nomor PA. Data PA lama dipindah otomatis ke nomor PA (revenue PA lama tidak ikut).

**Tambah Pengadaan**
- Langkah 2 kosong: controller tidak mengirim `ccKinds`. Diperbaiki. PRK di langkah 3 kini benar-benar tersaring per sub bidang.

**RKAP**
- Hanya 7 PRK yang ada di seeder; empat di antaranya tanpa sub bidang sehingga tidak tampil. Sub bidang kini diisi dari nama cost center, dan ada tab "Tanpa sub bidang".
- Impor daftar PRK: isi `database/data/prk.csv` lalu `php artisan db:seed --class=PrkSeeder`.

**Dashboard** (tanpa daftar pengadaan)
- RAB Usulan Pengadaan, RAB Realisasi (Terkontrak), Revenue, Tahap Belum Dibayar.
- Revenue vs Cost per perjanjian pelanggan: Revenue, RAB Pengadaan, Nilai SP2K (tanpa nama vendor).
- Persebaran pengadaan aktif per posisi; klik batang membuka Daftar Pengadaan tersaring.
- Top 5 dengan kategori pilihan: Waktu (terlama/tercepat), Nilai SP2K atau PO (terbesar/terkecil), Proses Approval (rata-rata per kelompok atau 5 pengadaan per kelompok), Vendor pemenang.

**Umum**
- Wordmark logo bergradien (sidebar dan login).
- Paginasi + pilihan 10/25/50/100 baris di semua daftar.
- Filter periode registrasi: 2 bulan (bawaan), 6 bulan, 1 tahun, Semua waktu.

**Perbaikan lain**
- Alert pengadaan tidak pernah tercatat (arrow function menyalin array). Kartu "Butuh Perhatian" sebelumnya selalu 0.
- Sub bidang Perjanjian Pelanggan tidak tersimpan.
- Peran Manager Sales dan L1/VP Sales di daftar saran PIC.
- Relasi Eloquent diberi tipe generik; baseline PHPStan 334 → 11 catatan.

## 4. Yang sudah dan belum diuji

Diuji di lingkungan saya (PHP 8.3 + SQLite, bukan PHP 8.4 + PostgreSQL):
- `php artisan test`: 27 lulus, termasuk DemoSeeder dan alur Kontrak Pengadaan.
- Migrasi `revisi_9` naik dan rollback pada data lama.
- `pint --test`, `phpstan` (0 error), `tsc --noEmit`, `vite build`.

Belum diuji: migrasi di PostgreSQL, dan tampilan di peramban.
