# NADI ASN — DPMPTSP Prov. Sulawesi Tengah

Navigasi dan Analitik Data Internal ASN: portal statistik publik dan aplikasi pengelolaan kepegawaian dengan Vue 3, TypeScript, Laravel 13 dan PostgreSQL 16.

**Status produksi: produksi aktif dan siap digunakan.**

**Website:** https://nadiasndpmptspsulteng.online

Database VPS menjadi sumber utama. Repository memuat kode, migrasi, fixture simulasi, desain dan dokumentasi; repository tidak berisi database pegawai nyata, password, secret OTP/SMTP, konfigurasi privat atau backup operasional. Clone repository tidak memulihkan data produksi.

## Pengguna dan akses

- **Publik:** statistik agregat, filter unit/jenis pegawai, serta ringkasan; nama, NIP, tanggal lahir dan dokumen individual tidak ditampilkan.
- **Admin Kepegawaian:** akses internal sesuai izin backend, pengelolaan/pengajuan data dan Radar. Akun dapat lebih dari satu.
- **Super Admin:** manajemen akun dan unit kerja, persetujuan, pengaturan urutan DUK dan laporan operasional. Super Admin dapat menyetujui usulan sendiri.

Login internal menggunakan **NIP, password dan OTP**. Akun Admin dipilih dari Master Pegawai melalui Pengaturan → Manajemen Akun. Tautan aktivasi memungkinkan pengguna menentukan password dan autentikator sendiri. Admin yang lupa akses menghubungi Super Admin; pemulihan Super Admin menggunakan email terdaftar. Tidak tersedia password bawaan atau peran Pimpinan.

## Pembaruan 5 Oktober 2026

Statistik pengunjung tersedia pada portal dan Command Center: hari ini, kemarin, minggu ini, bulan ini, tahun ini, total serta online (aktif lima menit terakhir). Satu browser dihitung sekali per hari; angka periode/total merupakan akumulasi harian sejak fitur aktif. Lihat [definisi, privasi dan operasi statistik pengunjung](docs/operations/VISITOR_STATISTICS.md).

## Fitur utama

- Master Pegawai: pencarian, filter unit/jenis pegawai/jenis jabatan, profil, histori dan kelengkapan data dinamis.
- DUK Digital: edit langsung dalam sel, simpan usulan batch, serta naik/turun urutan manual oleh Super Admin; tanpa hierarki otomatis.
- Tabel menyediakan pilihan 10/30/50/All. Excel mengikuti urutan DUK; template/ekspor menggunakan skema bersama 18 kolom, NIP teks dan tanggal yang tervalidasi.
- KP Radar dan KGB Radar mengikuti aturan instansi versi `2026-09-22.1`; dokumen pendukung dan hasil pemeriksaan perlu diisi petugas.
- Laporan, riwayat ekspor privat, audit, konflik revisi dan persetujuan perubahan tersedia. Kredit developer hanya pada footer aplikasi, bukan laporan.
- Tema awal light; pengguna dapat mengganti dark/light. Masa kerja keseluruhan bergerak bulanan dari acuan September 2026; usia dihitung berdasarkan tanggal lahir. MKG berbeda dari masa kerja keseluruhan.
- Rekap pada peluncuran: **168 pegawai**, terdiri dari **55 PNS + 91 PPPK penuh waktu = 146 ASN**, dan **22 PPPK paruh waktu** direkap terpisah; 12 unit kerja. Angka dapat berubah melalui pengelolaan data produksi.

## Operasional produksi

Enam service terisolasi: web, app, worker, scheduler, PostgreSQL dan ClamAV. Nginx Proxy Manager melayani domain HTTPS; hanya web terhubung ke jaringan proxy kantor. Database tidak membuka port publik. Konfigurasi khusus tidak tampil sebagai Proxy Host pada UI NPM.

| Komponen | Kondisi saat peluncuran |
| --- | --- |
| Domain / TLS | Aktif, redirect HTTP/www, perpanjangan sertifikat otomatis |
| Email pemulihan | SMTP aktif; pemilik akun mengonfirmasi pemulihan berhasil |
| Sumber data | VPS utama; database pengembangan lokal diatur read-only untuk koneksi baru |
| Monitor | 21 pemeriksaan setiap 5 menit; email saat gangguan dan pulih |
| Backup data | Google Drive terenkripsi setiap 14 hari, pukul 02.00 WITA |
| Backup seluruh project | Google Drive terenkripsi setiap 3 bulan kalender, pukul 03.00 WITA |
| Retensi | Backup lama diganti setelah backup baru terunggah dan terverifikasi |
| Bukti rilis | 13 preflight lulus; 27 pemeriksaan domain sebelum pembukaan dan 26 setelah pembukaan lulus |

Monitor berjalan pada VPS yang sama: VPS mati total memerlukan monitor eksternal yang belum dipasang. Kunci dekripsi backup harus diamankan terpisah di luar VPS. Jadwal backup mengikuti kebijakan pengelola; bukan backup setiap perubahan atau jaminan kehilangan data nol. Restore database terisolasi dan verifikasi arsip project sudah dilakukan.

## Dokumentasi terbaru

Mulai dari [indeks dokumentasi](docs/README.md) atau:

- [Catatan peluncuran 30 September](docs/operations/LAUNCH_2026-09-30.md)
- [Pemantauan dan penanganan gangguan](docs/operations/MONITORING.md)
- [Domain dan sertifikat](docs/operations/DOMAIN_2026-09-30.md)
- [Backup data Google Drive](docs/operations/BACKUP_GOOGLE_DRIVE.md) dan [backup project lengkap](docs/operations/BACKUP_PROJECT.md)
- [Setup pengembangan](docs/operations/SETUP.md), [pengelolaan VPS](docs/operations/VPS_PRIVATE.md), [panduan pengguna](docs/user/PANDUAN.md), [Radar](docs/user/RADAR.md)
- [Status implementasi](docs/STATUS_IMPLEMENTASI.md), [bukti validasi](docs/VALIDASI.md), [audit keamanan 28 September](docs/operations/SECURITY_REVIEW_2026-09-28.md)

Buku pedoman dan dokumentasi teknis **edisi 2.0, 5 Oktober 2026** sudah mencakup kondisi produksi, domain/SMTP, backup, monitoring serta statistik pengunjung. Gunakan [indeks dokumentasi terbaru](docs/README.md). PRD/Stage 1 disimpan sebagai acuan awal; revisi pengguna dan kode aktif dapat menggantikan rancangan awal.

## Pengembangan dan simulasi

Gunakan Node 24, PHP 8.4, Composer 2 dan PostgreSQL 16. Untuk clone baru, ikuti SETUP.md, buat konfigurasi privat sendiri, pasang dependensi dan gunakan database development/test terpisah. Jangan mengarahkan tes atau fixture ke produksi dan jangan menimpa `.env` yang sudah ada.

```sh
composer install
npm ci
# Setelah konfigurasi database dan kunci aplikasi development siap:
php artisan serve --host=127.0.0.1 --port=8000
# Terminal kedua:
php artisan queue:work --tries=3 --timeout=90
# Terminal ketiga:
npm run dev
```

Frontend backend lokal: http://127.0.0.1:5173. Lingkungan lokal lama telah diarsipkan read-only; perintah di atas untuk lingkungan pengembangan yang sengaja disiapkan, bukan instruksi mengaktifkan penulisan pada arsip lokal.

Untuk data sintetis: `npm run dev:demo`, lalu http://127.0.0.1:5176. Mode simulasi terpisah dari backend produksi; jangan memasukkan data nyata ke simulasi.

## Struktur dan pengujian

`app/Modules` berisi modul backend; `resources/js` aplikasi Vue; `resources/js/domain/personnel-schema.json` skema bersama; `database/migrations` skema DB; `tests` pengujian; `infra` deployment/backup/domain/monitor; `docs` panduan dan bukti operasi.

```sh
npm test
npm run build
php vendor/bin/phpunit
npm run check:php
python3 -m unittest discover -s infra/backup -p 'test_*.py'
python3 -m unittest discover -s infra/monitor -p 'test_*.py'
```

PHPUnit wajib memakai database testing terpisah. Baseline audit 28 September: 77 tes backend/375 assertion, 17 tes frontend, build dan audit dependensi lulus. Lihat VALIDASI.md untuk tanggal dan cakupan; hasil tersebut bukan klaim pentest independen atau jaminan bebas kerentanan. Build XLSX masih dapat memberi peringatan ukuran chunk.

Batas yang diketahui: QR enrollment/recovery codes, consumer outbox, purge otomatis impor/berkas laporan dan pengujian beban menyeluruh belum tersedia. Kelengkapan mengukur isian, bukan kebenaran dokumen; anomali sumber harus ditinjau petugas.

Developer: **Angga Jufran**.
