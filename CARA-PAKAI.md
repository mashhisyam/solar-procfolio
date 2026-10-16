# Patch 16 — 23 Okt 2026

Dipasang di atas patch 15. Timpa file ke repositori (branch `develop`).

## Perintah

```powershell
$env:Path = "C:\php84;$env:Path"
php artisan migrate          # prk_katalog: memuat seluruh PRK dari database/data/prk.csv (PLN 1 + PLN 2)
php artisan config:clear
php artisan test             # 47 tes
npm run build
```

## Isi

1. **Master Daftar tidak bisa menambah.** Di server, penambahan oleh Super Admin berhasil, baik di SQLite maupun PostgreSQL. Yang bermasalah ada di tampilan:
   - Bila penyimpanan gagal (mis. pilihan sudah ada), pesan error sebelumnya tidak ditampilkan dan input tidak dikosongkan, sehingga terlihat seperti tidak terjadi apa-apa. Pilihan ganda sebelumnya bahkan memicu error database.
   - Sekarang pesan muncul di kartu daftar tersebut, pilihan ganda ditolak dengan pesan yang jelas (tanpa membedakan huruf besar/kecil), dan Enter juga bisa dipakai untuk menambah.
2. **Peran PIC:** isian teks dengan saran (datalist) diganti dropdown berisi semua nama tahap di template alur (termasuk Manager Sales, L1/VP Sales, tahap SPR, Pasca), ditambah Finance dan Penandatangan. Peran lama di luar daftar tetap ditampilkan.
3. **Nomor urut** di semua daftar: Daftar Pengadaan, PRK, perjanjian, PKS, SP2K/PO, dokumen, BA, tahapan, Kelola Pengguna, Master, dan tab Dokumen/Riwayat di detail. Nomor berlanjut antarhalaman.
5. **Tambah PIC penandatangan** di detail BA menampilkan "Nama — Jabatan".
6. **Urutan kolom Daftar Pengadaan:** No · Prioritas · No SPP/ID · Judul · **Status** · RAB · Skema · User Pengaju · Aliran Data · Aktivitas Saat Ini · **Durasi Tahap** · PIC · Last Update.
7. **Menu "Daftar TOR, BCR, BAK"** (dulu Dokumen): hanya memuat TOR, BCR, dan BAK. Dokumen lain tetap ada di tab Dokumen pada detail pengadaan.
8. **Menu "Berita Acara Penyelesaian"** (dulu Berita Acara).
9. **PRK PLN 1** dari capture ditambahkan ke `database/data/prk.csv`:
   - Lima kode: IC01062023, IC00462022, IC00892023, IC01362022, IC00472022 (cost center I0326 - SUB BID SOL ARCH PLN 1).
   - Migrasi `prk_katalog` dan `ReferenceSeeder` keduanya memuat CSV ini, sehingga daftar PRK selalu terbawa setelah `migrate`, `migrate:fresh`, atau seeding.
   - Nilai Pengajuan tidak terlihat di capture, jadi masih 0. Isi di menu RKAP, atau lengkapi kolom `nilai_pengajuan` di CSV.
   - Data demo kini memakai PRK PLN 1 yang asli (IC00892023 dan IC00472022), bukan lagi PRK PLN 2 yang dipaksa menjadi PLN 1.

Menambah PRK berikutnya: tambahkan baris di `database/data/prk.csv`, lalu jalankan `php artisan db:seed --class=PrkSeeder`.

## Pengujian

- 47 tes lulus di SQLite **dan di PostgreSQL 16**. Ini pertama kalinya seluruh migrasi, seeder, dan tes dijalankan di PostgreSQL.
- `migrate:fresh --seed` + DemoSeeder berjalan di PostgreSQL 16.
- Pint, PHPStan (0 error), `tsc`, `vite build` bersih.
- Belum diuji di peramban.
