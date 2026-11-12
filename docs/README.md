# Dokumentasi NADI ASN

**Edisi 2.0 | 5 Oktober 2026 | Produksi publik aktif.** Pemilik sistem: DPMPTSP Prov. Sulawesi Tengah. Baseline aplikasi `f03ce2f9581af83325d8b9fb2bbc99b45983ab0c`. Dokumen pengguna dan teknis telah disesuaikan dengan domain, pemulihan email, sumber data VPS, backup, monitoring serta statistik pengunjung.

## Mulai sesuai kebutuhan

| Pembaca | Mulai di sini | Tujuan |
|---|---|---|
| Publik dan petugas kepegawaian | [Buku pedoman PDF](../output/pdf/Panduan-Lengkap-NADI-ASN.pdf) | Tutorial, ilustrasi dan pemecahan masalah |
| Admin dan Super Admin | [Panduan penggunaan](user/PANDUAN.md) | Ringkasan langkah kerja sehari-hari |
| Tim pengembang dan IT | [Buku teknis PDF](../output/pdf/Dokumentasi-Teknis-NADI-ASN.pdf) | Arsitektur, data, API, keamanan dan serah terima |
| Pengembang yang mencari sumber | [Buku teknis Markdown](architecture/DOKUMENTASI_TEKNIS_NADI_ASN.md) | Isi yang sama dengan PDF beserta rujukan file |
| Operator VPS | [Ringkasan operasi terkini](operations/STATUS_OPERASIONAL.md) | Domain, layanan, jadwal, bukti dan eskalasi |

## Panduan pengguna

- [Aktivasi, akun dan pemulihan](user/MANAJEMEN_AKUN.md)
- [DUK manual](user/DUK_RESMI.md)
- [KP Radar dan KGB Radar](user/RADAR.md)
- [Statistik pengunjung](operations/VISITOR_STATISTICS.md)

## Operasi dan pengembangan

- [Setup lingkungan baru](operations/SETUP.md), [akses VPS](operations/VPS_PRIVATE.md) dan [otomatisasi deployment CI/CD](operations/DEPLOYMENT_AUTOMATION.md)
- [Domain dan HTTPS](operations/DOMAIN_2026-09-30.md)
- [Pemantauan dan gangguan](operations/MONITORING.md)
- [Backup data dan pemulihan](operations/BACKUP_GOOGLE_DRIVE.md)
- [Backup seluruh project](operations/BACKUP_PROJECT.md)
- [Ringkasan arsitektur](architecture/IMPLEMENTASI.md)
- [Status implementasi](STATUS_IMPLEMENTASI.md) dan [bukti validasi](VALIDASI.md)
- [Sumber buku pengguna](user/handbook/README.md) dan [sumber buku teknis](architecture/technical-handbook/README.md)

## Histori dan prioritas acuan

[Peluncuran 30 September](operations/LAUNCH_2026-09-30.md), [audit keamanan 28 September](operations/SECURITY_REVIEW_2026-09-28.md), serta [arsip pengembangan September](history/PROGRES_PENGEMBANGAN_SEPTEMBER_2026.md) adalah bukti bertanggal, bukan klaim audit baru. PRD dan Stage 1 tetap disimpan sebagai desain awal; revisi kebutuhan pengguna dan implementasi aktif dapat menggantikannya. Dokumen aturan KP/KGB tetap menjadi acuan kalkulator versi 2026-09-22.1.

Gunakan edisi terbaru untuk operasi. Angka pegawai, backup sukses dan status layanan merupakan snapshot bertanggal; selalu periksa aplikasi dan marker operasional sebelum mengambil keputusan.

Repository berisi kode, fixture sintetis, aset dan dokumentasi. Database pegawai asli, konfigurasi privat, token, kunci dan backup tidak masuk Git. GitHub tidak menggantikan backup database.
