# Role Matrix Procfolio

Dua peran: **Super Admin** dan **Admin**. Hak akses Admin diatur per pengguna dan per menu oleh Super Admin
di menu **Kelola Pengguna**. Pemeriksaan dilakukan di server (`menu:{kunci},{tingkat}`); tampilan hanya menyembunyikan menu dan tombol.

Tingkat akses: **Tidak ada** (menu disembunyikan, akses ditolak) · **Lihat** · **Ubah**.

## Matriks

| Menu / kemampuan | Super Admin | Admin (bawaan baru) | Tingkat yang dapat diatur untuk Admin |
|---|---|---|---|
| Dashboard | Lihat | Lihat | Tidak ada / Lihat |
| Daftar Pengadaan* | Ubah | Ubah | Tidak ada / Lihat / Ubah |
| RKAP | Ubah | Lihat | Tidak ada / Lihat / Ubah |
| Perjanjian Pelanggan | Ubah | Ubah | Tidak ada / Lihat / Ubah |
| PKS & Amandemen | Ubah | Ubah | Tidak ada / Lihat / Ubah |
| SP2K/PO & Kontrak (koreksi data) | Ubah | Ubah | Tidak ada / Lihat / Ubah |
| Dokumen | Lihat | Lihat | Tidak ada / Lihat |
| Berita Acara (ubah BA, sirkuler TTD) | Ubah | Ubah | Tidak ada / Lihat / Ubah |
| Tahapan, GR & Invoice | Lihat | Lihat | Tidak ada / Lihat |
| Master & Konfigurasi | Ubah | Tidak ada | Tidak ada / Lihat / Ubah |
| Kelola Pengguna | Ya | Tidak | Tidak dapat diberikan |
| Hapus pengadaan | Ya | Tidak | Tidak dapat diberikan |

\* **Ubah di Daftar Pengadaan** mencakup semua aksi di halaman detail pengadaan: tambah/edit pengadaan, Update Proses,
Revisi Minor/Mayor, rilis SP2K/PO, Kontrak Pengadaan, amandemen, tahapan pembayaran, GR, invoice, pembayaran, dokumen,
BA baru, batalkan/gagal, aktifkan kembali. Dokumen dan Tahapan karena itu cukup Lihat.

## Preset di Kelola Pengguna

| Preset | Isi |
|---|---|
| Bawaan Admin | Seperti kolom "Admin (bawaan baru)" |
| Hanya lihat | Semua menu Lihat, Master Tidak ada |
| Semua Ubah | Semua menu tingkat tertinggi, termasuk Master Ubah |

## Contoh pemetaan jabatan (saran)

| Jabatan | Preset / pengaturan |
|---|---|
| Tim Solution Architect (pengelola Procfolio) | Super Admin |
| User Pengaju / PIC pengadaan | Bawaan Admin |
| Tim Anggaran | Bawaan Admin + RKAP Ubah |
| Manajemen / Pemantau | Hanya lihat |
| Admin konfigurasi (PIC, kalender, template alur) | Bawaan Admin + Master Ubah |

## Aturan pengaman

- Akun pemilik `superadmin@admin.com` dan `admin@admin.com` selalu dibuat oleh migrasi dan `ReferenceSeeder`; tidak dapat dinonaktifkan.
  Super Admin pemilik tidak dapat diturunkan. Kata sandi yang sudah diganti tidak ditimpa saat migrasi/seeder dijalankan ulang.
- Super Admin tidak dapat menurunkan atau menonaktifkan akunnya sendiri.
- Akun SSO baru masuk sebagai Admin **Hanya lihat**; email di `PROCFOLIO_ADMIN_EMAILS` menjadi Super Admin.
- Setiap pembuatan akun, perubahan peran, status, hak akses, reset kata sandi, dan penghapusan pengadaan tercatat di audit log.
- Hapus pengadaan wajib alasan dan mengetik nomor SPP/ID; riwayat audit pengadaan tetap disimpan.

## Peran lama (sebelum patch 15)

| Peran lama | Menjadi |
|---|---|
| admin | Admin, Semua Ubah |
| pengaju | Admin, Bawaan Admin |
| viewer | Admin, Hanya lihat |
