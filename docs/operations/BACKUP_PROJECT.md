# Backup seluruh project NADI ASN

Backup project terpisah dari backup operasional database/berkas privat. Pengelola meminta jadwal tiga bulan sekali pada 30 September 2026.

Backup pertama `nadi-project-20260930T030224Z` sudah diunggah dan seluruh tiga berkas cocok pada verifikasi baca ulang Google Drive. Cron aktif; pemeriksaan ulang pada tanggal yang belum jatuh tempo tidak menghasilkan backup tambahan. Sepuluh pengujian kebijakan lolos, mencakup interval 14 hari, tiga bulan kalender, pergantian tahun/akhir bulan, isolasi namespace, serta kegagalan verifikasi yang mempertahankan backup lama.

Arsip project juga diunduh langsung dari Drive ke komputer pengelola, cocok SHA-256, berhasil didekripsi, dan struktur tar diperiksa tanpa path absolut/traversal. Delapan belas berkas wajib (kode, konfigurasi build/deployment, lockfile, history bundle dan PDF panduan) ditemukan dan hash kontennya identik dengan VPS. Engine backup serta kunci dekripsi tidak masuk ke arsip. Arsip plaintext uji dihapus setelah pemeriksaan; salinan terenkripsi dan laporan pemeriksaan tetap tersedia secara privat di `.runtime/backup-check/project/`.

## Isi

Arsip mengambil seluruh pohon `/home/anggauser/nadi-asn` dengan struktur root `nadi-asn/`, termasuk kode backend/frontend, konfigurasi Vue/Vite/TypeScript, migrasi, tests, scripts, lockfile dependency, Dockerfile/Compose/nginx, dokumen PRD/system design/rules, aset/logo/desain, dokumentasi operasi dan PDF panduan. Konfigurasi privat `.deploy/app.env`, kunci aplikasi, sertifikat internal, arsip deployment dan Git history bundle ikut tersimpan di dalam enkripsi.

Folder engine backup `.deploy/backup` dikecualikan agar arsip tidak memasukkan dirinya sendiri, paket alat backup, token OAuth dan snapshot yang berulang. Kunci dekripsi serta folder uji backup lokal tidak dimasukkan. Log aplikasi dan cache konfigurasi Laravel juga dikecualikan. Ini merupakan backup folder project VPS; direktori di komputer pengelola yang tidak dikirim ke VPS tidak otomatis tersinkron.

Database aktif dan berkas privat berada dalam volume Docker, sehingga dipulihkan dari backup operasional `NADI-ASN-Backup` yang terjadwal 14 hari. Image Docker tidak diekspor; dependensi serta image dibangun/diunduh kembali melalui lockfile dan konfigurasi. Jika `.git` tersedia pada deployment mendatang, pohon tersebut ikut diarsipkan; deployment saat ini memakai `.deploy/history.bundle` untuk riwayat Git sebelumnya.

## Penyimpanan dan jadwal

- Google Drive akun kantor: `NADI-ASN-Project-Backup/nadi-project-YYYYMMDDTHHMMSSZ/`.
- Komponen: `project.tar.gz.age`, `manifest.json`, `SHA256SUMS`.
- Enkripsi age memakai kunci publik yang sama dengan backup operasional; kunci privat tetap terpisah dari VPS.
- Jadwal: setiap tiga bulan kalender pada 03:00 WITA. Jika backup pertama sukses pada 30 September 2026, tanggal berikutnya 30 Desember 2026.
- Cron memeriksa jatuh tempo harian pada 19:00 UTC. Hari lain dilewati. Penambahan bulan memperhitungkan pergantian tahun dan hari terakhir bulan, bukan interval 90 hari.
- Lock terpisah dan timeout satu jam. Jadwal database serta TLS tetap dipertahankan.
- Backup baru diunggah dan dibaca kembali sebelum backup project lama diganti, mengikuti preferensi pengelola sebelumnya. Nama direktori backup operasional tidak cocok dengan pola penghapusan project.
- Backup lama Drive masuk Sampah; tidak ada pengosongan Sampah global. Kegagalan unggah/verifikasi mempertahankan salinan lama.

## Pemeriksaan

```sh
cd /home/anggauser/nadi-asn
crontab -l
cat .deploy/backup/project-last-success
tail -n 20 .deploy/backup/project-status.log
tail -n 40 .deploy/backup/project-cron.log
.deploy/backup/tools/usr/bin/rclone --config .deploy/backup/rclone.conf \
  lsf nadibackup:NADI-ASN-Project-Backup --dirs-only --max-depth 1
```

Backup manual melalui `infra/backup/project-backup.sh` mengganti tanggal acuan tiga bulanan setelah berhasil. `infra/backup/enable-project-backup.sh` mengunggah satu salinan lengkap dan memasang jadwal hanya setelah verifikasi sukses.

## Pemulihan project

Unduh folder snapshot, verifikasi `SHA256SUMS`, lalu dekripsi `project.tar.gz.age` dengan kunci age eksternal ke direktori privat. Ekstrak hanya pada lingkungan baru yang terisolasi, periksa konfigurasi sebelum menjalankannya, instal dependensi memakai lockfile, dan rebuild Docker image. Pulihkan database/volume privat dari backup operasional, sesuaikan host dan proxy, serta jalankan pemeriksaan login/peran/laporan sebelum membuka akses publik.

Arsip berisi secret di dalam enkripsi. Jangan membagikan hasil dekripsi atau commit `.deploy` ke Git. Jangan membuat ulang APP_KEY/NADI_TOTP_KEY saat memulihkan data yang sudah terenkripsi. Pengujian arsip/restore source tidak otomatis membuktikan pemulihan aplikasi penuh atau batas RTO.
