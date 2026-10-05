# Menyiapkan lingkungan Laravel dan VPS

**Edisi 5 Oktober 2026. Produksi aktif** di https://nadiasndpmptspsulteng.online. Domain/TLS, SMTP, backup Drive dan monitoring sudah tersedia; statistik pengunjung aktif. [Status operasi](STATUS_OPERASIONAL.md) adalah acuan terkini.

Panduan di bawah berlaku untuk clone/instalasi baru yang sengaja disiapkan, bukan instruksi mereset produksi. Lokal lama read-only dan tidak tersinkron otomatis. Jangan menimpa `.env` atau membuat kunci baru pada instalasi yang sudah mempunyai data terenkripsi.

## Pratinjau yang tersedia

Gunakan Node 24 dan `npm ci`, kemudian `npm run dev`. Jangan membuka Vite ke jaringan publik. Mode default menghubungkan Laravel 8000. Untuk fixture sintetis gunakan `npm run dev:demo` di 5176.

## Laravel lokal

1. Sediakan PHP 8.4 (runtime pengujian 8.4.25), Composer 2, PostgreSQL 16. Aktifkan `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `intl`, `zip`, `gd`, dan `pcntl` untuk worker.
2. Hanya bila belum ada `.env`, salin `.env.example` ke `.env`; isi koneksi database development dan timezone. Jangan commit `.env`.
3. Jalankan `composer install` menggunakan `composer.lock` yang sudah tersedia. Instalasi dan registrasi paket sudah diuji.
4. Jalankan `php artisan key:generate`. Buat kunci acak 32 byte **berbeda** untuk `NADI_TOTP_KEY`, dalam base64, simpan di secret store instansi.
5. Gunakan database owner/migration role khusus untuk `php artisan migrate`. Setelah itu gunakan role runtime yang memiliki DML terbatas pada tabel yang diperlukan, tanpa CREATE/ALTER/DROP atau ownership.
6. Verifikasi referensi unit, golongan, jenis/status dan klasifikasi bersama pengelola sebelum memasukkannya ke `reference_values`. Service menolak referensi unit yang belum diverifikasi.
7. Bootstrap akun Super Admin pertama dengan `php artisan nadi:create-admin`. Proses ini interaktif, tidak menyediakan kata sandi default. Provisioning secret TOTP harus melalui prosedur enrollment instansi; enrollment melalui kunci autentikator tersedia pada halaman aktivasi. Pemulihan email dijelaskan di bawah.
8. Untuk pengembangan, jalankan `php artisan serve --host=127.0.0.1 --port=8000`, `php artisan queue:work --tries=3 --timeout=90`, dan `npm run dev:backend` pada terminal terpisah. Pilihan `-- --port 5175` dapat ditambahkan ke perintah frontend bila mode simulasi masih memakai 5173. Jalankan `npm run build` untuk build. Untuk integrasi penuh, gunakan Nginx yang melayani `dist/` dan meneruskan `/api/` ke `public/index.php` Laravel.
9. Uji login → TOTP → sesi penuh, logout, CSRF, role, perubahan, transaksi, constraint dan retry concurrency pada PostgreSQL nyata.

## Docker Compose

`infra/compose.yaml` mendefinisikan web, app, worker, scheduler, database dan antivirus. Database/ClamAV/PHP-FPM tidak membuka port ke host. Web hanya terikat pada `127.0.0.1:18443` dan menggunakan TLS.

- Buat `.deploy/` mode 0700 di root project. Konfigurasi runtime berada di `.deploy/app.env` mode 0600; salin struktur `.env.example` dan isi secara privat.
- `.deploy/db-owner-password` adalah secret bootstrap PostgreSQL yang hanya dipasang pada container DB. Jangan memasukkan password owner ke `app.env`.
- `.deploy/tls/server.crt` dan `server.key` menyediakan TLS; key mode 0600. Sertifikat localhost sementara hanya untuk pengujian privat.
- Buat `nadi_runtime` dengan password berbeda. Jalankan migrasi/pemulihan sebagai owner, kemudian terapkan `infra/docker/runtime-grants.sql`. Ulangi grant setelah migrasi berikutnya, bukan memberi owner kepada runtime.
- Aplikasi produksi wajib `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `NADI_UPLOAD_SCAN_ENABLED=true`. ClamAV gagal/tidak aktif menyebabkan unggahan ditolak.
- Image dasar dikunci digest; evaluasi pembaruan keamanan sebelum mengganti digest dan membangun rilis baru.
- Jangan menjalankan `down -v`, reset database, atau restore menimpa instalasi yang sudah berisi data. Gunakan backup dan prosedur cutover.
- `NADI_PRODUCTION_READY` adalah catatan gate preflight, bukan firewall atau pengganti uji penerimaan.

```sh
docker compose -f infra/compose.yaml build
docker compose -f infra/compose.yaml up -d
docker compose -f infra/compose.yaml exec app php artisan nadi:preflight
```

Compose memerlukan seluruh secret dan sertifikat sebelum dijalankan. Domain produksi sudah dilayani konfigurasi khusus Nginx Proxy Manager. Gunakan overlay `infra/domain/compose.proxy.yaml` bersama Compose utama saat membuat ulang web; jangan mengganti konfigurasi aplikasi kantor lain.

## Rancangan deployment VPS (acuan awal; domain dikelola Hostinger)

1. Tentukan domain, TLS, firewall, SSH keys, secret custody, log rotation dan monitor eksternal.
2. Hanya Nginx yang menerima trafik pengguna. App, PostgreSQL, worker, dan scheduler tidak diekspos langsung.
3. Konfigurasikan trusted TLS termination/HTTPS forwarding sesuai proxy aktual; aktifkan `SESSION_SECURE_COOKIE=true`, pastikan origin dan CSRF konsisten.
4. `APP_DEBUG=false`. Pisahkan dev, test, staging dan production. Jangan seed fixture ke production.
5. Sesuai revisi 14 September, publik menampilkan agregat tepat tanpa identitas. Flag rilis lama tidak mengendalikan dashboard ini; pastikan perilaku tersebut diterima pada UAT sebelum deployment.
6. Selesaikan seluruh gap dalam `docs/STATUS_IMPLEMENTASI.md`, lalu `php artisan nadi:preflight` dan UAT.

## Backup dan disaster recovery

Contoh `infra/backup/backup.sh` menjalankan dump PostgreSQL custom, enkripsi `age`, checksum dan pengiriman `rclone`. Service/timer contoh menjadwalkan tiap empat jam dan **tidak digunakan pada VPS aktif**. Jadwal aktif memakai vps-backup.sh (14 hari) dan project-backup.sh (tiga bulan kalender) melalui cron.

Konfigurasi `PGPASSFILE`, role backup, public key recipient age, rclone remote institusi, folder lokal, timeout, kapasitas disk, retention dan notifikasi kegagalan harus disiapkan terpisah. Secret tidak disimpan pada script atau source control. Private key decrypt disimpan terpisah dari server produksi.

Verifikasi upload saja bukan restore drill. Uji decrypt, checksum, `pg_restore` ke lingkungan terisolasi, row count, constraint, login, snapshot, dan laporan; ukur RPO/RTO aktual dan bandingkan dengan kebijakan 14 hari serta kebutuhan organisasi, bukan mengklaim target PRD enam jam sudah tercapai. Cadangan lokal sebelum migrasi tersedia di `.runtime/before-directory-import.dump`; pada saat awal penyiapan belum ada backup offsite atau restore drill. Keduanya telah diselesaikan pada 30 September 2026; gunakan runbook BACKUP_GOOGLE_DRIVE.md dan BACKUP_PROJECT.md.

## Lingkungan lokal yang sudah disiapkan

Database development dan test pada mesin ini berada di cluster `.runtime/pgsql`, melalui UNIX socket privat `/private/tmp/nadi-asn-pg-20260912` port 55432. PostgreSQL ini tidak menerima koneksi TCP. Koneksi development dan kunci acak aplikasi tersimpan pada `.env` berizin baca/tulis pemilik saja; jangan membagikan file tersebut. Cluster ini bukan service PostgreSQL produksi.

Untuk menyalakan kembali cluster yang sudah ada (jangan menjalankan `initdb` ulang):

```sh
mkdir -p /private/tmp/nadi-asn-pg-20260912
chmod 700 /private/tmp/nadi-asn-pg-20260912
/opt/homebrew/opt/postgresql@16/bin/pg_ctl -D .runtime/pgsql -l .runtime/pgsql.log -o "-k /private/tmp/nadi-asn-pg-20260912 -h '' -p 55432" start
```

Bila cluster masih aktif, tidak perlu menyalakannya lagi. Database development berisi 168 pegawai dan akun pertama untuk aktivasi; jangan seed/reset. Data test berada pada database terpisah dan di-rollback setelah pengujian. Data demo Vue/Vite tetap terpisah pada `.runtime/demo-state.json`.

## Email pemulihan Super Admin

SMTP produksi aktif dan pemulihan nyata dikonfirmasi pemilik akun pada 30 September 2026. Contoh berikut untuk instalasi baru; gunakan `.env` atau `.deploy/app.env` privat yang sesuai lingkungan. Jangan menimpa konfigurasi VPS aktif dan jangan mengirim kredensial melalui chat:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
NADI_FRONTEND_URL=http://127.0.0.1:5173
NADI_RECOVERY_MAIL_ENABLED=false
```

Gunakan host, port, skema TLS, akun, dan kredensial SMTP resmi penyedia email yang dipilih. Alamat pengirim harus diizinkan oleh penyedia; alamat penerima pemulihan bukan otomatis akun pengirim. `smtp` memakai STARTTLS saat tersedia, sedangkan penyedia yang meminta TLS langsung umumnya memakai `smtps` beserta port yang diberikannya. Jangan memakai transport `array` untuk pengiriman nyata; transport itu hanya menyimpan email dalam memori untuk pengujian.

Setelah nilai lengkap, set `NADI_RECOVERY_MAIL_ENABLED=true`, jalankan `php artisan config:clear`, dan mulai ulang proses aplikasi yang menyimpan konfigurasi lama. Bila aplikasi dihosting, ganti `NADI_FRONTEND_URL` dengan URL HTTPS frontend yang dapat diakses pemilik akun. Link 127.0.0.1 hanya bekerja pada komputer ini.

Uji melalui Login → lupa kata sandi/OTP → Super Admin → email terdaftar. Pemilik akun membuka email dan menyelesaikan kata sandi serta autentikator sendiri. Periksa kotak masuk/spam. Jangan menaruh tautan atau kunci autentikator di log. Pengujian otomatis menggunakan mail fake; itu tidak membuktikan SMTP mengirim email nyata.

Permintaan dibatasi per IP dan email; jawaban sukses bersifat umum agar tidak mengungkap akun yang terdaftar. Kegagalan SMTP membatalkan token yang baru dibuat dan mengembalikan pesan kegagalan, tanpa membatalkan kredensial aktif.

## Penyimpanan kode di GitHub

Repositori memuat kode, migrasi, dokumentasi, desain, logo, dan data simulasi. Data pegawai nyata, `.env`, `.runtime`, seluruh isi operasional `storage`, token aktivasi, dan cadangan database tidak disertakan. Kloning kode tidak memulihkan database/data pegawai.

Direktori runtime Laravel disertakan sebagai folder kosong dengan `.gitignore`. Setelah clone, ikuti langkah instalasi di atas dan siapkan database secara terpisah. Jangan menyalin `.env.example` menimpa `.env` instalasi yang sudah berjalan.

Migrasi pemulihan akun tidak menyimpan NIP/email nyata dalam kode. Jika sudah ada akun Super Admin sebelum migrasi tersebut dijalankan, isi `NADI_INITIAL_RECOVERY_NIP` dan `NADI_INITIAL_RECOVERY_EMAIL` secara lokal untuk provisioning satu kali. Untuk akun yang dibuat sesudah migrasi, tetapkan `users.recovery_email` melalui administrasi backend oleh pengelola tepercaya. Instalasi lokal yang migrasinya sudah diterapkan tetap memakai alamat pemulihan yang telah tersimpan; tidak perlu menjalankan ulang migrasi atau mereset data.
