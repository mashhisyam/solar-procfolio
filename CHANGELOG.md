# Catatan Rilis

## Patch 16 — 23 Okt 2026

- PRK PLN 1 (cost center I0326) ditambahkan ke `database/data/prk.csv`; migrasi `prk_katalog` memuat seluruh daftar PRK sehingga ikut terbawa setiap migrate.
- Master Daftar: pesan gagal ditampilkan (sebelumnya diam), pilihan ganda ditolak dengan pesan, Enter untuk menambah.
- Peran PIC di Master berupa dropdown dari nama tahap semua template alur (+ Finance, Penandatangan).
- Nomor urut di setiap daftar. Daftar Pengadaan: Status setelah Judul, Durasi Tahap setelah Aktivitas Saat Ini.
- Dropdown Tambah PIC penandatangan menampilkan jabatan.
- Menu Dokumen menjadi "Daftar TOR, BCR, BAK" (hanya ketiga jenis itu); Berita Acara menjadi "Berita Acara Penyelesaian".
- Seluruh tes lulus juga di PostgreSQL 16.

## Patch 15 — 22 Okt 2026 (RBAC)

- Dua peran: Super Admin dan Admin. Hak akses Admin per menu (Tidak ada / Lihat / Ubah) diatur Super Admin; lihat `docs/ROLE-MATRIX.md`.
- Menu baru **Kelola Pengguna** (Super Admin): buat akun, ubah peran, hak akses, status, kata sandi. Tab Pengguna di Master dihapus.
- Akun pemilik `superadmin@admin.com` dan `admin@admin.com` selalu dibuat oleh migrasi dan ReferenceSeeder (menggantikan `admin@procfolio.local`).
- Super Admin dapat menghapus pengadaan (alasan + konfirmasi nomor SPP/ID); pengguna lain tidak.
- Master & Konfigurasi bermode lihat untuk pengguna tanpa akses Ubah.
- `composer run dev` tidak lagi menjalankan `queue:listen`.

## Patch 14 — 21 Okt 2026

- Skema **SPR**: User Pengaju → Manager Pengaju → Manager Pengadaan → SP2K, lalu langsung Pelaksanaan Pekerjaan. Tanpa Sales, Anggaran, Rendan, Lakdan, dan tanpa metode pengadaan.
- Revisi Mayor (merah) dan Minor (kuning) ditampilkan mencolok: banner di detail, label di Daftar Pengadaan, segmen Menunggu Revisi, strip peringatan di Dashboard, dan alert teratas.
- Jenis pengadaan dihapus; kategori: Barang (Non Lisensi), Barang (Lisensi), Jasa, Barang Jasa (Supply Erect), Sewa.
- Top 5 Dashboard: kategori Kategori Pengadaan (jumlah / total RAB).
- BAK SPP Kemitraan dicatat di Dokumen Referensi (nomor wajib).
- Perjanjian pelanggan wajib; nomor PA opsional dan dapat dilengkapi saat approval Sales (SPP Pusat & Kemitraan).
- Perbaikan: kelompok tahap hilang saat template alur disimpan dari Master.

## Patch 11 — 20 Okt 2026 (performa)

- ProcurementInsight: memo per pengadaan untuk termFlows, alerts dan tahap aktif; status tahap approval dihitung dalam satu lintasan. row() sekitar 2,5x lebih cepat.
- Eager load BA tidak lagi ganda; relasi picPengaju yang tidak dipakai dihapus dari daftar muat.
- Indeks baru untuk kolom filter dan foreign key (PostgreSQL tidak membuatnya otomatis).

## Patch 10 — 19 Okt 2026

- Status Pengadaan Gagal (berhenti setelah masuk unit pengadaan) dan Pengadaan Dibatalkan (sebelumnya). Data tidak dihapus; tidak ada fitur hapus pengadaan.
- Dashboard: kartu Pengadaan Gagal dan Dibatalkan (jumlah dan RAB) serta grafik posisi terakhir saat berhenti. Daftar Pengadaan: segmen Gagal dan Dibatalkan.
- DemoSeeder: 15 pengadaan baru dalam 2 bulan terakhir (Pelaksanaan Pekerjaan, Submit GR, Invoicing, Verifikasi Invoice, posisi approval, Gagal, Dibatalkan).

## Patch 9 — 18 Okt 2026

- Kontrak Pengadaan di samping SP2K/PO dengan perlakuan sama: koreksi data, amandemen, halaman kontrak.
- PA (Project Activation) hanya berupa nomor di pengadaan; dasar pelanggan = perjanjian pelanggan atau nomor PA.
- Dashboard baru tanpa daftar pengadaan: RAB Usulan, RAB Realisasi, Revenue vs Cost per perjanjian, persebaran posisi, Top 5 dengan kategori pilihan.
- Filter periode registrasi (2 bulan bawaan, 6 bulan, 1 tahun, semua) dan paginasi dengan pilihan jumlah baris di semua daftar.
- Wordmark logo bergradien.
- Perbaikan: langkah 2 Tambah Pengadaan kosong; PRK tidak tersaring per sub bidang; alert pengadaan tidak pernah tercatat; sub bidang perjanjian pelanggan tidak tersimpan; PRK tanpa sub bidang tidak tampil di RKAP.
- Impor PRK dari `database/data/prk.csv`. Baseline PHPStan turun dari 334 ke 11 catatan.

## 0.1.0 — rilis awal

Implementasi pertama dari hasil requirement gathering dan prototipe UI/UX.

- Dua progres per pengadaan: Proses Pengadaan (SPP dan Kemitraan) dan Proses Pasca Pengadaan per tahap pembayaran.
- Penguncian Proses Pengadaan setelah SP2K/PO rilis; perubahan hanya lewat amandemen dengan alur approval sendiri.
- Registrasi dengan RAB per baris, kode anggaran, calon vendor dan dasar pelanggan (BAKB atau MoM).
- Rilis SP2K/PO beserta tahapan pembayaran: syarat penyerahan, rencana selesai, persentase dan nilai bayar.
- BA dengan sirkuler paralel dan putaran revisi, Goods Receipt per tahap, invoice dan pembayaran final.
- Alert durasi hijau/kuning/merah, attention batas pekerjaan, dan indikator dokumen serta dokumen tertahan.
- Dokumen berupa tautan SharePoint, tanpa unggahan file.
- Master & Konfigurasi: organisasi, PIC, kode anggaran, daftar pilihan, template alur, ambang alert, kalender kerja dan peran pengguna.
- Login SSO Microsoft Entra ID, audit log append-only, ekspor Excel, dan email ringkasan harian.
