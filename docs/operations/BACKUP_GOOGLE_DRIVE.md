# Backup Google Drive NADI ASN

## Status terkini - 5 Oktober 2026

Backup data terbaru `nadi-20261005T002934Z` berhasil diunggah dan lima file cocok pada baca ulang. Dump mencakup dua tabel statistik pengunjung. Percobaan awal tertahan kuota API Drive; backup lama dipertahankan sampai retry berhasil. Monitor memeriksa umur backup, bukan setiap kode kegagalan unggahan.

## Bukti penyiapan 30 September 2026

Tujuan dipilih pengelola: Google Drive akun kantor. Alat age dan rclone dipasang tanpa root dari paket Ubuntu 24.04 yang menerima pembaruan keamanan, di `.deploy/backup/tools/usr/bin/`. Bukan instalasi paket global.

Snapshot terenkripsi pertama berhasil dibuat, diunduh ke komputer pengelola, diperiksa checksum, didekripsi, dan dipulihkan ke PostgreSQL 16 sementara tanpa jaringan. Jumlah pegawai (168), penugasan (169), dan akun (1) cocok. Struktur arsip aman serta keberadaan APP_KEY dan NADI_TOTP_KEY dalam recovery kit telah diverifikasi tanpa mencetak nilainya.

Otorisasi Google Drive selesai oleh pengelola. Backup pertama `nadi-20260930T024747Z` berhasil diunggah dan seluruh lima berkas cocok saat dibaca kembali dari Drive. Cron aktif. Permintaan pengelola mengganti jadwal menjadi setiap 14 hari dengan satu backup aktif; backup lama baru dihapus setelah backup baru diverifikasi. Kunci dekripsi dibuat hanya pada komputer pengelola; kunci publik saja dikirim ke VPS.

Dump database juga diunduh langsung dari Google Drive, diverifikasi SHA-256, didekripsi, dan dipulihkan ke container PostgreSQL 16 tanpa jaringan. Jumlah pegawai, penugasan, akun, dan audit cocok dengan produksi; seluruh foreign key tervalidasi. Container uji dan dump plaintext kemudian dihapus. Backup awal di Drive berisi data hingga 30 September 2026 pukul 10:47:47 WITA.

## Menghubungkan Google Drive

Pada komputer pengelola, buka Terminal dan jalankan:

```sh
ssh -L 127.0.0.1:53682:127.0.0.1:53682 anggauser@187.127.119.238 \
  'bash /home/anggauser/nadi-asn/infra/backup/authorize-drive.sh'
```

Biarkan Terminal terbuka. Salin URL `http://127.0.0.1:53682/auth?...` yang muncul ke browser pada komputer yang sama. Login menggunakan akun kantor dan periksa permintaan izin rclone. Scope `drive.file` membatasi akses pada berkas yang dibuat/dibuka melalui aplikasi ini, bukan seluruh dokumen yang sudah ada di Drive. Pilih akun dengan ruang penyimpanan cukup. Jika kebijakan Workspace memblokir aplikasi, admin Workspace perlu mengizinkannya; jangan mematikan kebijakan organisasi secara global.

Token disimpan pada `.deploy/backup/rclone.conf` (0600). Jangan mengirim token, isi config, atau screenshot berisi token ke chat. Tidak perlu sandi Gmail atau Sandi Aplikasi SMTP. OAuth Google Drive dan SMTP merupakan kredensial terpisah.

Rclone membuat folder baru `NADI-ASN-Backup` saat unggahan pertama. Jangan membagikan folder backup secara publik.

Setelah pengelola menyelesaikan login Google, jalankan melalui operator:

```sh
ssh anggauser@187.127.119.238 \
  'bash /home/anggauser/nadi-asn/infra/backup/enable-vps-backup.sh'
```

Script baru memasang jadwal setelah satu backup utuh berhasil diunggah dan dibaca ulang untuk verifikasi. Tidak ada jadwal aktif jika otorisasi/upload gagal.

## Isi dan jadwal

Setiap snapshot memiliki folder `nadi-YYYYMMDDTHHMMSSZ` dengan:

| Berkas | Isi |
|---|---|
| `database.dump.age` | Dump custom seluruh database PostgreSQL, termasuk pegawai, akun, DUK, radar dan audit |
| `private-files.tar.age` | Berkas impor, ekspor dan berkas aplikasi privat |
| `recovery-kit.tar.age` | Konfigurasi privat aplikasi, kredensial DB, kunci OTP/aplikasi, sertifikat privat upstream, kode backend/frontend serta konfigurasi infrastruktur |
| `manifest.json` | Metadata waktu/format backup tanpa rahasia/identitas pegawai |
| `SHA256SUMS` | Checksum komponen terenkripsi dan manifest |

Plaintext dump/arsip tidak ditulis ke disk VPS; pipeline langsung dienkripsi. Kunci dekripsi tidak masuk ke arsip atau VPS. Snapshot PostgreSQL konsisten secara transaksi. Arsip berkas diambil setelah dump dari volume aktif, sehingga bukan snapshot atomik lintas database/volume; jangan mengklaim titik waktu yang identik. Kegagalan dump, tar, upload atau pembacaan ulang menggagalkan backup.

Jadwal: setiap 14 hari pada 02:00 WITA. Cron VPS menjalankan pemeriksaan harian pukul 18:00 UTC, kemudian `policy.py` membandingkan tanggal WITA dengan backup sukses terakhir; hari yang belum mencapai 14 hari dilewati. Ini tidak memakai `*/14` pada hari dalam bulan, yang tidak memberikan interval dua minggu konsisten. Berdasarkan backup sukses 5 Oktober, berikutnya 19 Oktober 2026 pukul 02:00 WITA bila tidak ada backup manual sukses baru. Marker `last-success` selalu menjadi acuan. Ada lock agar proses tidak tumpang tindih dan timeout 30 menit. Jadwal mempertahankan crontab perpanjangan TLS. Jika backup gagal, backup lama dipertahankan dan pemeriksaan berikutnya mencoba lagi keesokan hari.

Upload menggunakan copy, tidak sync. Sesuai instruksi pengelola, backup baru diunggah dan seluruh komponen dibaca kembali sebelum penghapusan lama. Penghapusan cloud hanya berlaku untuk direktori bertimestamp `nadi-YYYYMMDDTHHMMSSZ` yang lebih lama dalam namespace tepat `nadibackup:NADI-ASN-Backup`; direktori terbaru, nama asing, dan nama tidak valid dipertahankan. Google Drive memindahkan backup lama ke Sampah sesuai perilaku default, tanpa mengosongkan Sampah akun. Salinan lokal VPS yang lebih lama juga dibersihkan, dengan pengecualian symlink. Direktori/file lain pada Drive dan VPS tidak ikut dihapus. Google Drive dengan akses tulis bukan penyimpanan immutable; operator atau pencuri token dapat menghapus berkas.

Konfigurasi privat: `BACKUP_INTERVAL_DAYS=14`, `BACKUP_REPLACE_OLD=true`. Kebijakan ini menggantikan usulan awal setiap empat jam; jarak pemulihan data mengikuti snapshot 14 harian dan bukan target RPO enam jam PRD awal. Perubahan manual yang berhasil melakukan backup juga menggeser tanggal backup berikutnya.

Backup seluruh folder project memiliki jadwal dan folder terpisah setiap tiga bulan pada 03:00 WITA; lihat [backup project](BACKUP_PROJECT.md). Penggantian backup operasional tidak mencakup folder `NADI-ASN-Project-Backup`.

Enam pengujian kebijakan lolos: interval lintas bulan, jadwal awal/jam mundur, pembatasan nama folder, perlindungan symlink/direktori asing, marker rusak, serta kegagalan verifikasi cloud yang mempertahankan backup sebelumnya dan tidak memanggil purge. Pemeriksaan `--scheduled` setelah backup pertama juga terbukti melewati hari yang belum jatuh tempo.

## Kunci dekripsi

Komputer pengelola menyimpan `.runtime/backup-keys/nadi-backup-identity.txt` dengan izin 0600 dalam direktori 0700. Direktori `.runtime` dikecualikan dari Git. Salin kunci ini ke penyimpanan aman milik kantor yang terpisah dari VPS, repository, dan akun Drive backup, misalnya password manager organisasi atau media offline terenkripsi. Jika kunci hilang, backup tidak dapat didekripsi. Jika kunci bocor, penerima enkripsi baru dan prosedur pergantian kunci diperlukan.

Binary age lokal berasal dari rilis resmi v1.3.2 darwin/arm64 dengan digest aset SHA-256 diverifikasi melalui metadata GitHub. Binary lokal berada di `.runtime/backup-tools/age/`.

## Pemeriksaan operasi

```sh
cd /home/anggauser/nadi-asn
crontab -l
cat .deploy/backup/last-success
tail -n 20 .deploy/backup/status.log
tail -n 40 .deploy/backup/cron.log
.deploy/backup/tools/usr/bin/rclone --config .deploy/backup/rclone.conf \
  lsd nadibackup:NADI-ASN-Backup
```

`last-success` hanya diperbarui setelah unggahan dan check --download berhasil. `COLLECTED` berarti salinan lokal terenkripsi, bukan sukses offsite. `FAILED` merekam tahap kegagalan. Monitor host memberi alarm bila usia backup melewati ambang. Kegagalan setiap percobaan tidak langsung menghasilkan email tersendiri; pengelola tetap memeriksa status, `last-failure` dan usia `last-success`. Pantau juga kapasitas Drive dan disk VPS. Jangan menampilkan rclone.conf/app.env untuk troubleshooting.

## Pemulihan

1. Unduh satu folder snapshot lengkap dari Drive ke lingkungan pemulihan privat.
2. Jalankan `sha256sum -c SHA256SUMS` (atau `shasum -a 256 -c` pada macOS).
3. Dekripsi komponen dengan `age -d -i /lokasi/kunci -o database.dump database.dump.age`; lakukan serupa untuk kedua arsip.
4. Pulihkan dump memakai PostgreSQL 16 di lingkungan terisolasi; jangan menimpa produksi sebagai uji. Terapkan kembali role owner/runtime dan grants dari `infra/docker/runtime-grants.sql` setelah provisioning role.
5. Pulihkan berkas privat, APP_KEY, NADI_TOTP_KEY dan konfigurasi melalui jalur privat. Sesuaikan kredensial/host DB ke lingkungan tujuan. Jangan membuat ulang kunci OTP yang terenkripsi.
6. Sebelum membuka jaringan, batalkan sesi, challenge, token aktivasi/pemulihan lama; tinjau antrean agar job/email lama tidak terkirim ulang. Atur ulang jadwal dan sertifikat domain melalui prosedur deployment.
7. Verifikasi jumlah baris, constraint, audit, login pemilik akun dengan OTP, akses peran serta satu laporan. Catat lama pemulihan dan waktu snapshot. Uji awal hanya membuktikan restore DB, dekripsi dan kelengkapan arsip; belum membuktikan pemulihan aplikasi penuh atau RTO resmi.
8. Hapus berkas plaintext uji setelah pemeriksaan dan simpan kunci dekripsi secara aman.

Sumber: [rclone Google Drive](https://rclone.org/drive/), [otorisasi VPS melalui SSH tunnel](https://rclone.org/remote_setup/), [age](https://github.com/FiloSottile/age).

## Koneksi Drive dan batas permintaan

Koneksi aktif belum memakai OAuth client khusus kantor. Pada 5 Oktober API bersama mengembalikan `rateLimitExceeded`; retry berikutnya berhasil. Jika berulang, periksa log tanpa mencetak token, pertahankan cadangan lama dan buat koneksi OAuth khusus organisasi melalui otorisasi pemilik akun. Dokumentasi resmi rclone menyatakan client bersama akan dihentikan pada 2026: [panduan client milik sendiri](https://rclone.org/drive/#making-your-own-client-id). Jangan mengganti token/client atau menjalankan script otorisasi ulang pada koneksi aktif tanpa rencana dan persetujuan pemilik akun.
