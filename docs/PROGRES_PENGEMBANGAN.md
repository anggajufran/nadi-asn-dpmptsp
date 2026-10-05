# Catatan perkembangan NADI ASN

Diperbarui 5 Oktober 2026. Ringkasan ini menjelaskan keputusan akhir, bukan daftar langkah aktivasi instalasi lama. Detail pekerjaan September disimpan pada [arsip perkembangan](history/PROGRES_PENGEMBANGAN_SEPTEMBER_2026.md).

## Keputusan yang berlaku

Pimpinan tidak memiliki akun; pelaporan menggunakan berkas dari Super Admin. Data pegawai operasional berada di VPS. DUK mengikuti urutan manual, bukan comparator pangkat/jabatan. Masa kerja PDF merupakan masa kerja keseluruhan dengan acuan September 2026. Login NIP/password/OTP, Super Admin dapat menyetujui usulan sendiri, dan Admin dapat lebih dari satu orang.

## Tonggak terverifikasi

| Tanggal | Hasil |
|---|---|
| 14-23 September | Master PDF, unit, kelengkapan, DUK manual, akun, tema, waktu otomatis dan Radar instansi |
| 28 September | Audit keamanan bertanggal dan deployment privat enam service |
| 29 September | Edisi awal buku pengguna dan buku teknis |
| 30 September | Domain/HTTPS, pemulihan email, sumber utama VPS, backup Drive dan pemantauan; publik dibuka |
| 5 Oktober | Statistik pengunjung produksi, 85 tes backend/17 frontend dan verifikasi domain; kode dan backup diperbarui |
| 5 Oktober | Dokumentasi edisi 2, panduan produksi dan tutorial statistik, arsitektur/operasi terbaru |

Baseline aplikasi `f03ce2f9581af83325d8b9fb2bbc99b45983ab0c`. Fixture demo tetap terpisah; clone GitHub tidak memulihkan data asli. Sesi lokal lama dan staging privat tidak dijadikan petunjuk pengoperasian kantor.

## Lanjutan pemeliharaan

Pemilik operasional, jadwal patch, latihan restore aplikasi penuh, rotasi/retensi log, monitor eksternal dan tata kelola kunci perlu dipelihara organisasi. Lihat [status implementasi](STATUS_IMPLEMENTASI.md), [validasi](VALIDASI.md) dan [ringkasan operasi](operations/STATUS_OPERASIONAL.md).
