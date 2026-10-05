# Status implementasi NADI ASN

Status per 5 Oktober 2026. Baseline `f03ce2f9581af83325d8b9fb2bbc99b45983ab0c`. Produksi publik aktif di https://nadiasndpmptspsulteng.online. Database VPS adalah sumber utama; dokumen ini menggantikan ringkasan lokal lama. [Arsip September](history/STATUS_IMPLEMENTASI_SEPTEMBER_2026.md) tetap disimpan untuk bukti perubahan.

| Area | Implementasi aktif |
|---|---|
| Portal | Agregat tepat, filter unit/jenis, tabel alternatif dan PDF publik tanpa identitas |
| Akses | Publik tanpa akun; Admin dan Super Admin dengan NIP, password, TOTP dan pemeriksaan server |
| Master | 18 kolom, profil/histori, filter jenis jabatan, kelengkapan dinamis dan usulan |
| DUK | Isi sel sebagai usulan batch; urutan manual langsung oleh Super Admin; 10/30/50/All |
| Persetujuan | Super Admin dapat menyetujui sendiri; lock, revision, histori dan audit |
| Akun dan unit | Admin dari Master, aktivasi/reset/nonaktif; referensi unit dikelola Super Admin |
| Excel dan laporan | Template 17 isian, ekspor 18 kolom, urutan DUK, input beku, file privat dan expiry unduhan |
| Radar | PNS aktif, kalkulator versi 2026-09-22.1, pemeriksaan, konflik revisi dan pengingat |
| Proyeksi waktu | Usia dari tanggal lahir; masa kerja keseluruhan bertambah bulanan dari acuan |
| Pengunjung | Tujuh metrik WITA, heartbeat stateless, hash sementara dan agregat historis sejak 5 Oktober |
| Produksi | Enam service, TLS domain, SMTP pemulihan aktif, DB runtime terbatas dan ClamAV fail-closed |
| Operasi | Monitor 21 pemeriksaan tiap lima menit; backup Drive terenkripsi data 14 hari/project tiga bulan |

Rekap saat peluncuran: 168 pegawai, 55 PNS + 91 PPPK penuh waktu = 146 ASN, 22 PPPK paruh waktu terpisah; 12 unit. Angka operasional dapat berubah. Tidak ada akun Pimpinan dan tidak ada nomor sumber pada tabel/ekspor aktif.

## Batas dan pekerjaan pemeliharaan

- Kelengkapan adalah keterisian. Anomali sumber perlu bukti petugas; data kosong tidak ditebak.
- DUK hierarki/publikasi lama tersimpan sebagai arsip dan bukan jalur API aktif.
- Kunci TOTP dimasukkan manual; belum ada QR enrollment, recovery codes atau break-glass siap pakai.
- Purge fisik imports/exports, enforcement expiry impor, rotasi log Laravel dan consumer outbox belum lengkap. Worker produksi aktif; stress beban/konkurensi menyeluruh belum dibuktikan.
- Future-effective/bitemporal identitas belum penuh; perubahan TMT masa depan ditahan saat penerapan.
- Monitor berjalan di VPS yang sama; monitor eksternal belum tersedia. Tidak ada SLA/throughput atau RTO pemulihan aplikasi penuh yang sudah disahkan.
- Jadwal backup adalah kebijakan 14 hari/tiga bulan, bukan target RPO enam jam PRD awal. Backup terverifikasi dan restore DB terisolasi pernah diuji; latihan pemulihan aplikasi penuh tetap perlu dijadwalkan.
- Statistik pengunjung adalah browser harian/perkiraan aktivitas, bukan identitas individu atau jaminan semua bot tersaring.

Dokumen PRD/Stage 1/rules tidak diubah. [Validasi](VALIDASI.md), [operasi](operations/STATUS_OPERASIONAL.md) dan [catatan perkembangan](PROGRES_PENGEMBANGAN.md) memisahkan implementasi, bukti dan rencana.
