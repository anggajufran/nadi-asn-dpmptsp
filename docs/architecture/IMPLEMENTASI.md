# Arsitektur implementasi 0.1.0

## Dua jalur eksekusi yang terpisah

```text
Simulasi opsional (5176):
Browser → Vue/Vite → API simulasi loopback → .runtime/demo-state.json

Backend lokal (5173 → 8000) dan integrasi target:
Browser → Nginx → Vue build + Laravel → PostgreSQL
                                      ↘ worker / scheduler
```

Fixture tidak diimpor oleh frontend. Komponen meminta data melalui `api/client.ts`. Model identitas, record mentah dan JSON state tidak diperlukan oleh portal publik. Mode simulasi disampaikan server melalui `/api/session` dan bukan ditetapkan pengguna lewat localStorage.

Public client hanya meminta safe DTO. Internal server memeriksa sesi sebelum mengembalikan data; hanya ADMIN dan SUPER_ADMIN diizinkan. Menyembunyikan tombol di UI bukan mekanisme authorization.

## Alur pengajuan

1. Form membaca revision resmi.
2. Client mengirim changes dan alasan.
3. Server memvalidasi allowlist, nilai dan revision.
4. Proposal disimpan terpisah; data resmi belum berubah.
5. Super Admin memeriksa before/after dan menulis keputusan; dapat memeriksa usulan sendiri sesuai kebijakan terbaru.
6. Server memeriksa permission, maker, status proposal, uniqueness dan revision lagi.
7. Penerapan memperbarui official data, revision, histori dan audit; backend Laravel memakai transaksi serta outbox.

Tidak ada endpoint untuk menghapus pegawai. Status nonaktif tetap mempertahankan record.

## Design system

- Navy `#223653`, biru `#2b579a`, teal `#3c8d9c`, latar `#f5f7fb`.
- Font Plus Jakarta Sans dari asset lokal; tidak mengirim request Google Fonts.
- Radius kartu 12px, border tipis, bayangan ringan.
- Layout publik mengikuti referensi: header, panel pengantar, filter, empat KPI, grafik dua kolom, rincian tiga kolom, privacy notice, tabel alternatif.
- Layout internal menggunakan sidebar, breadcrumb, header, penanda simulasi, tabel, dialog dan ringkasan prioritas.
- Label berbahasa Indonesia, status berbentuk teks, bukan warna saja; fokus keyboard tampak.
- Native `<dialog>` menyediakan modal keyboard/focus; tabel data dapat digeser horizontal dalam kontainer pada layar sempit.
- Animasi ringan pada chart dan interaksi menghormati reduced motion.

## Penyimpanan dan data resmi

Laravel menyediakan tabel dasar `employees`, `employee_assignments`, `change_requests`, `audit_events`, `rulesets`, `import_batches`, `import_rows`, `published_snapshots`, `public_releases`, `export_jobs`, `notifications`, sessions/cache/queue/outbox. Tabel Stage 1 lanjutan belum seluruhnya tersedia; lihat matriks status.

Published snapshot dan audit menggunakan trigger PostgreSQL anti-update/delete. Role runtime harus tidak mempunyai hak DDL, owner, atau izin menonaktifkan trigger. Sesuai revisi 14 September, publik menerima agregat tepat dari proyeksi pegawai aktif, dengan allowlist filter dan DTO tanpa identitas. Tabel rilis lama tetap menjadi arsip, tidak menjadi sumber dashboard saat ini.

## Referensi teknis yang diverifikasi

- [Laravel release notes](https://laravel.com/framework/docs/releases): Laravel 13 memerlukan PHP 8.3+.
- [Vue Quick Start](https://vuejs.org/guide/quick-start): Vue 3 dengan Vite dan TypeScript.

Lockfile saat ini diuji pada PHP 8.4.25 dengan Laravel 13.31.0 dan PostgreSQL 16.15. Rincian bukti uji tersedia di `docs/VALIDASI.md`.


## Impor dan laporan pada backend

Upload Excel disimpan di storage privat dan direkam checksum byte berkasnya. Validasi menolak formula, tautan workbook eksternal, makro, NIP numerik, duplikasi NIP, dan referensi yang belum diverifikasi. Worker membuat preview dengan canonical SHA-256. Konfirmasi menggunakan preview yang sama, mengunci batch dan pegawai, lalu membuat semua usulan baris valid dalam satu transaksi. Konflik revisi membatalkan transaksi konfirmasi; pengulangan konfirmasi yang sudah berhasil mengembalikan hasil sebelumnya.

Renderer laporan menerima salinan baris pada saat permintaan, bukan membaca ulang data yang mungkin berubah saat antrean berjalan. Unduhan memeriksa pemilik, role, versi keamanan akun, status berkas dan kedaluwarsa. PDF hanya menerima DTO agregat tanpa identitas; XLSX menggunakan tipe teks eksplisit untuk mencegah konversi NIP dan formula. Riwayat permintaan membuat pekerjaan tetap dapat dilanjutkan setelah berpindah halaman.
