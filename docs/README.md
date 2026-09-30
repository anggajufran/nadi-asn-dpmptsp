# Indeks dokumentasi NADI ASN

Status operasi terbaru: **30 September 2026, produksi publik aktif**. Dokumen ini menjadi pintu masuk tim pengelola dan pengembang. Dokumen bertanggal lebih lama mempertahankan kondisi saat disusun; utamakan runbook operasi terbaru untuk domain, SMTP, backup dan monitoring.

## Pengguna

- [Panduan penggunaan](user/PANDUAN.md)
- [KP Radar dan KGB Radar](user/RADAR.md)
- [Buku panduan PDF — edisi 29 September 2026](../output/pdf/Panduan-Lengkap-NADI-ASN.pdf)

## Tim IT dan operator

- [Catatan peluncuran dan rollback maintenance](operations/LAUNCH_2026-09-30.md)
- [Pemantauan dan gangguan](operations/MONITORING.md)
- [Domain, HTTPS dan perpanjangan sertifikat](operations/DOMAIN_2026-09-30.md)
- [Akses dan pengelolaan VPS](operations/VPS_PRIVATE.md)
- [Backup data dan restore](operations/BACKUP_GOOGLE_DRIVE.md)
- [Backup project lengkap](operations/BACKUP_PROJECT.md)
- [Setup pengembangan/instalasi baru](operations/SETUP.md)
- [Pemeriksaan keamanan — 28 September](operations/SECURITY_REVIEW_2026-09-28.md)

## Pengembang

- [Dokumentasi teknis Markdown — edisi 29 September](architecture/DOKUMENTASI_TEKNIS_NADI_ASN.md)
- [Dokumentasi teknis PDF — edisi 29 September](../output/pdf/Dokumentasi-Teknis-NADI-ASN.pdf)
- [Sumber generator dokumen teknis](architecture/technical-handbook/README.md)
- [Status implementasi dan histori revisi](STATUS_IMPLEMENTASI.md)
- [Bukti pengujian](VALIDASI.md)

PDF dan buku teknis edisi 29 September menjelaskan arsitektur dan penggunaan pada baseline sebelum peluncuran. Untuk status operasional, jadwal backup dan prosedur domain/monitor gunakan dokumen operasi 30 September. Jangan menganggap status domain/SMTP yang belum aktif dalam buku lama masih berlaku.

Repository tidak menyimpan konfigurasi rahasia, database pegawai nyata atau kunci backup. Pemulihan produksi memerlukan arsip offsite, kunci dekripsi dan konfigurasi privat yang diamankan pengelola. GitHub menyimpan kode dan dokumentasi, bukan pengganti backup database.
