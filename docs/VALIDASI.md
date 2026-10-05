# Bukti validasi NADI ASN

Ringkasan terbaru 5 Oktober 2026, baseline aplikasi `f03ce2f9581af83325d8b9fb2bbc99b45983ab0c`. Hasil berikut berasal dari pemeriksaan rilis dan catatan operasional; pembaruan buku tidak dianggap sebagai audit ulang aplikasi. [Bukti September](history/VALIDASI_SEPTEMBER_2026.md) tetap tersedia dengan tanggal aslinya.

| Pemeriksaan | Hasil terverifikasi | Tanggal |
|---|---|---|
| Backend PostgreSQL testing | 85 tes, 424 assertion lulus | 5 Oktober |
| Fitur statistik pengunjung | 8 tes, 49 assertion lulus dalam suite backend | 5 Oktober |
| Vitest | 17 tes lulus pada data sintetis terisolasi | 5 Oktober |
| Build produksi frontend | Lulus; chunk ExcelJS besar tetap menjadi catatan | 5 Oktober |
| Sintaks PHP | 95 file, nol kesalahan | 5 Oktober |
| Diff dan scan secret | Diff bersih; tidak menemukan secret yang dikenali/pola umum pada cakupan scan | 5 Oktober |
| Endpoint pengunjung produksi | 11 pemeriksaan lulus | 5 Oktober |
| Portal/domain setelah rilis | 26 pemeriksaan anonim lulus | 5 Oktober |
| Preflight produksi | 13 pemeriksaan lulus | 5 Oktober |
| Pemantauan | 21 pemeriksaan sehat | 5 Oktober |
| Integritas deployment | Fingerprint inti pegawai/akun/audit sama sebelum-sesudah | 5 Oktober |
| Isolasi aplikasi kantor | 19 container kantor serta DB/ClamAV tidak dibuat ulang | 5 Oktober |
| Browser produksi | Tujuh metrik tampil; reload tidak menambah hitungan harian | 5 Oktober |
| Backup data Google Drive | Lima file cocok pada baca ulang, nol perbedaan | 5 Oktober |
| Backup project Google Drive | Tiga file cocok pada baca ulang, nol perbedaan | 5 Oktober |
| Restore terisolasi | DB dari Drive, dekripsi/struktur/hash arsip project tervalidasi | 30 September |
| Pemulihan akun | SMTP nyata diterima; pemilik mengonfirmasi pemulihan berhasil | 30 September |

## Cakupan pengunjung

Deduplikasi cookie harian, WITA/tengah malam, awal minggu/bulan/tahun, online lintas hari, kedaluwarsa aktivitas, pruning presence, API agregat, bot umum, Secure/HttpOnly/SameSite, payload pribadi ditolak, Origin asing/hilang ditolak dan heartbeat tidak memperpanjang sesi login. GET/health probe tidak menambah kunjungan.

## Mengulang tes dengan aman

Gunakan lingkungan testing dan database `nadi_testing` terpisah; jangan arahkan fixture atau suite ke produksi. Migrasi testing diperlukan hanya bila skema berubah, menggunakan role owner khusus testing.

```sh
npm test
npm run build
npm run check:php
APP_ENV=testing DB_DATABASE=nadi_testing php artisan migrate --force
php vendor/bin/phpunit
```

`phpunit.xml` memaksa APP_ENV testing dan DB_DATABASE nadi_testing. Pengujian menjalankan transaksi rollback. Baca konfigurasi privat sebelum menjalankan perintah pada mesin baru.

Pemeriksaan anonim rilis melalui `python3 infra/monitor/public-check.py`; opsi rate-limit tidak diperlukan untuk pemeriksaan rutin. Status langsung VPS tidak sama dengan bukti load test, pentest penuh, accessibility audit, SLA atau RTO resmi.

## Pemeriksaan dokumen edisi 2

PDF dibangun dari sumber versi yang tercatat, dicek tata letak, daftar isi/bookmark, teks, privasi dan render seluruh halaman. Hasil QA penerbitan disimpan sementara di `tmp/pdfs/`; artefak final berada di `output/pdf/`. Pembaruan dokumentasi tidak menjalankan migrasi, reset atau perubahan data pegawai.
