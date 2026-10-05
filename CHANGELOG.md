# Catatan perubahan NADI ASN

## 5 Oktober 2026 - dokumentasi edisi 2

- Buku pedoman dan buku teknis diperbarui ke baseline f03ce2f, domain produksi, pemulihan aktif dan statistik pengunjung.
- Panduan operasi, backup, monitoring, akun, DUK, Radar, status dan validasi diselaraskan; bukti September dipisahkan sebagai histori.
- Sumber pembangun buku pengguna serta ilustrasi sintetis masuk repository agar PDF dapat dibangun ulang.

## 5 Oktober 2026 — statistik pengunjung (dimulai 1 Oktober)

- Statistik hari ini, kemarin, minggu/bulan/tahun, total dan online pada portal publik serta Command Center.
- Browser dihitung sekali per hari WITA; reload/tab tidak menggandakan hitungan. Online adalah aktivitas lima menit terakhir.
- Cookie terenkripsi pihak pertama, hash sementara tanpa identitas/IP/riwayat halaman, retensi presence dua hari kalender dan agregat historis.
- Heartbeat stateless terlihat tiap menit, Origin wajib/rate limit, bot umum tidak dihitung; simulasi berlabel terpisah.
- Delapan tes fitur termasuk isolasi sesi dan penolakan Origin asing; 85 tes backend/424 assertion serta 17 tes frontend lulus. Statistik dimulai saat fitur aktif tanpa mengarang data historis.

## 30 September 2026 — produksi publik dan operasi

- Domain HTTPS nadiasndpmptspsulteng.online aktif; redirect HTTP/www, proxy upstream TLS terverifikasi dan perpanjangan sertifikat otomatis.
- SMTP pemulihan aktif dan pemulihan Super Admin dikonfirmasi pemilik akun.
- VPS ditetapkan sumber data utama setelah rekonsiliasi; database lokal lama read-only.
- Backup terenkripsi Google Drive 14 hari untuk data dan tiga bulan kalender untuk seluruh project. Arsip lama diganti setelah verifikasi; restore DB terisolasi dan verifikasi arsip project berhasil.
- Monitor host dengan 21 pemeriksaan tiap lima menit, email gangguan/pulih dan tes transisi kondisi. Deteksi VPS mati total dari luar masih memerlukan monitor eksternal.
- Script pemeriksaan anonim domain tersedia; 13 preflight, 27 pemeriksaan sebelum pembukaan dan 26 setelah pembukaan lulus pada rilis.
- Dokumentasi peluncuran, rollback maintenance, domain, monitoring dan backup ditambahkan; README/index diperbarui. Buku pengguna dan teknis edisi 29 September disimpan sebagai snapshot historis.
- Data nyata dan konfigurasi privat tetap dikecualikan dari Git; temporary render dan cache Python diabaikan.

## 28 September 2026 — audit dan deployment privat

Baseline 5b1b70a: isolasi enam service VPS, role runtime database terbatas, pemindai ClamAV, perlindungan unggahan XLSX, sesi/OTP dan proxy, error generik serta 20 pemeriksaan keamanan. Lihat laporan audit bertanggal 28 September.

## September 2026 — aplikasi dan revisi kebutuhan

Master Pegawai, DUK manual, akun Admin dari Master, persetujuan Super Admin, statistik publik agregat, laporan/Excel, tema dan filter, pemulihan akun serta KP/KGB Radar. Histori dan batas implementasi ada pada docs/STATUS_IMPLEMENTASI.md dan docs/VALIDASI.md.
