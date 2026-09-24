# NADI ASN DPMPTSP

> **Pembaruan 15 September 2026:** DUK kembali mengikuti data sumber dan diatur manual oleh Super Admin. Sidebar dapat diminimalkan; tabel memiliki 10/30/50/All; urutan Excel mengikuti DUK; kategori Unit kerja berada di atas tabel PDF. Manajemen akun Admin tersedia di Pengaturan. Super Admin dapat menyetujui usulan sendiri. Kebijakan ini menggantikan hierarki otomatis dan syarat pemeriksa berbeda sebelumnya.

Navigasi dan Analitik Data Internal ASN. Aplikasi Vue 3 + TypeScript dengan backend Laravel 13 dan PostgreSQL 16, mengikuti desain dashboard serta logo yang disediakan.

Diperbarui 14 September 2026. Revisi mengikuti permintaan terbaru pengguna. Aplikasi lokal sudah memuat 168 pegawai dari PDF: 55 PNS, 91 PPPK Penuh Waktu, dan 22 PPPK Paruh Waktu. Total ASN dalam pelaporan aplikasi adalah 146; PPPK Paruh Waktu direkap terpisah. Terdapat 12 unit kerja sesuai sumber setelah koreksi pembacaan teks yang meluber antarbaris.

## Menjalankan aplikasi

Konfigurasi lokal dan database sudah tersedia. Jangan membuat ulang cluster atau menimpa `.env`. Gunakan PHP 8.4 pada mesin ini.

```sh
/opt/homebrew/opt/php@8.4/bin/php artisan serve --host=127.0.0.1 --port=8000
/opt/homebrew/opt/php@8.4/bin/php artisan queue:work --tries=3 --timeout=90
npm run dev
```

Jalankan ketiganya di terminal terpisah. Buka http://127.0.0.1:5173. Panduan menyalakan database setelah restart tersedia di `docs/operations/SETUP.md`.

Akun Super Admin pertama memakai data Restu Anggara Pratama Jufran dan identitas login NIP. Aktivasi pribadi disediakan di `.runtime/aktivasi-super-admin.html`: pengguna menetapkan sendiri kata sandi dan autentikator. Tidak ada kata sandi bawaan. Tautan berlaku 24 jam dan satu kali.

Untuk evaluasi dengan data buatan: `npm run dev:demo`, lalu buka http://127.0.0.1:5176. Mode ini hanya memiliki pilihan Admin dan Super Admin, dengan state terpisah. Jangan memasukkan data pegawai asli ke ruang simulasi.

## Perubahan yang tersedia

- Master Pegawai dan DUK menggunakan 19 kolom sesuai PDF; pencarian, filter, halaman tabel, histori dan indikator isian yang belum lengkap.
- Kelengkapan dihitung ulang dari isian. Form menampilkan pratinjau persentase; data resmi berubah setelah persetujuan Super Admin.
- DUK dapat diedit langsung dalam sel melalui Edit lembar, kemudian Simpan. Sel berubah ditandai, navigasi Tab tersedia, konflik revisi membatalkan seluruh pengajuan batch.
- Super Admin menambah, mengganti nama, dan menonaktifkan unit kosong pada Pengaturan. Unit yang masih dipakai harus dipindahkan pegawainya terlebih dahulu.
- Template impor dan ekspor XLSX memakai skema 19 kolom yang sama, NIP bertipe teks, tanggal ISO dan validasi sumber. Import backend memiliki staging, preview dan konfirmasi atomik.
- Statistik publik menampilkan angka agregat tepat termasuk kelompok kecil, dengan filter unit/jenis. Identitas pegawai hanya tersedia setelah login dan TOTP.
- Command Center memisahkan PNS, PPPK Penuh Waktu, PPPK Paruh Waktu dan kualitas data. Laporan operasional hanya untuk Super Admin.
- Peran serta fitur Pimpinan dihapus dari aplikasi. Identitas audit lama tetap dipertahankan dengan akses nonaktif.

## Struktur

`app/Modules` memuat identitas, pegawai, referensi unit, tata kelola, analitik, impor, laporan dan audit. `resources/js` memuat aplikasi publik/internal dan modul UI. `resources/js/domain/personnel-schema.json` merupakan skema kolom bersama PHP/TypeScript. `scripts` berisi ekstraksi PDF dan server simulasi. `database/migrations`, `tests`, `infra`, dan `docs` menyediakan skema, pengujian, konfigurasi operasi dan panduan.

Data sumber, cadangan, aktivasi, database serta hasil ekstraksi berada di penyimpanan privat. Data asli tidak dimasukkan ke bundle frontend atau aset publik.

## Verifikasi dan batas versi

```sh
npm test
npm run build
/opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit
npm run check:php
```

Backend diuji pada PHP 8.4.25, Laravel 13.31.0 dan PostgreSQL 16.15. Pengujian menggunakan database terpisah. ExcelJS dimuat hanya saat diperlukan; build masih memperingatkan ukuran chunk XLSX.

Versi lokal ini belum merupakan rilis produksi. KP/KGB mengikuti aturan instansi melalui pemeriksaan dokumen yang tersimpan dan diaudit; lihat [panduan Radar](docs/user/RADAR.md). Manajemen akun dan pemulihan sudah tersedia, tetapi pengiriman email nyata masih memerlukan SMTP. Snapshot lanjutan, deployment Hostinger, pemulihan backup dan UAT instansi masih memerlukan pekerjaan lanjutan. Delapan record mempunyai anomali pada sumber yang ditandai untuk pemeriksaan; nilai kosong tidak ditebak. Lihat [panduan penggunaan](docs/user/PANDUAN.md), [status](docs/STATUS_IMPLEMENTASI.md), dan [validasi](docs/VALIDASI.md).
