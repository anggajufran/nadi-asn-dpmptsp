# Menyiapkan lingkungan Laravel dan VPS

**Laravel lokal, migrasi, dan tes PostgreSQL sudah dijalankan pada 12 September 2026. Bagian Docker/VPS/backup tetap merupakan runbook yang belum diuji, bukan konfirmasi siap produksi.**

## Pratinjau yang tersedia

Gunakan Node 24 dan `npm ci`, kemudian `npm run dev`. Jangan membuka Vite ke jaringan publik. Mode default menghubungkan Laravel 8000. Untuk fixture sintetis gunakan `npm run dev:demo` di 5176.

## Laravel lokal

1. Sediakan PHP 8.4 (runtime pengujian 8.4.25), Composer 2, PostgreSQL 16. Aktifkan `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `intl`, `zip`, `gd`, dan `pcntl` untuk worker.
2. Salin `.env.example` ke `.env`; isi koneksi database development dan timezone. Jangan commit `.env`.
3. Jalankan `composer install` menggunakan `composer.lock` yang sudah tersedia. Instalasi dan registrasi paket sudah diuji.
4. Jalankan `php artisan key:generate`. Buat kunci acak 32 byte **berbeda** untuk `NADI_TOTP_KEY`, dalam base64, simpan di secret store instansi.
5. Gunakan database owner/migration role khusus untuk `php artisan migrate`. Setelah itu gunakan role runtime yang memiliki DML terbatas pada tabel yang diperlukan, tanpa CREATE/ALTER/DROP atau ownership.
6. Verifikasi referensi unit, golongan, jenis/status dan klasifikasi bersama pengelola sebelum memasukkannya ke `reference_values`. Service menolak referensi unit yang belum diverifikasi.
7. Bootstrap akun Super Admin pertama dengan `php artisan nadi:create-admin`. Proses ini interaktif, tidak menyediakan kata sandi default. Provisioning secret TOTP harus melalui prosedur enrollment instansi; enrollment melalui kunci autentikator tersedia pada halaman aktivasi. Pemulihan email dijelaskan di bawah.
8. Untuk pengembangan, jalankan `php artisan serve --host=127.0.0.1 --port=8000`, `php artisan queue:work --tries=3 --timeout=90`, dan `npm run dev:backend` pada terminal terpisah. Pilihan `-- --port 5175` dapat ditambahkan ke perintah frontend bila mode simulasi masih memakai 5173. Jalankan `npm run build` untuk build. Untuk integrasi penuh, gunakan Nginx yang melayani `dist/` dan meneruskan `/api/` ke `public/index.php` Laravel.
9. Uji login → TOTP → sesi penuh, logout, CSRF, role, perubahan, transaksi, constraint dan retry concurrency pada PostgreSQL nyata.

## Docker Compose

`infra/compose.yaml` mendefinisikan web, app, worker, scheduler dan database. Database tidak membuka port ke host. Nginx terikat ke loopback 8080 untuk reverse proxy TLS di host.

- `DB_OWNER_PASSWORD` diperlukan saat provisioning container PostgreSQL.
- `POSTGRES_USER=nadi_owner` adalah owner bootstrap; **bukan** role runtime.
- Buat role `nadi_runtime` dan password yang sesuai `.env` setelah provisioning, dengan hak minimum. Jalankan migrasi menggunakan role migrator/owner yang terpisah.
- File Compose membaca konfigurasi interpolasi dari `--env-file .env`; app sendiri membaca file `.env` yang sama.
- Build image harus sukses dan lulus pengujian sebelum menjalankan production services.
- Dockerfile menggunakan tag baseline eksplisit. Validasi patch yang masih didukung, perbarui bila diperlukan, dan kunci digest sebelum production.
- Jangan langsung set `NADI_PRODUCTION_READY=true`; flag ini adalah catatan gate preflight, bukan pengganti penyelesaian fitur dan UAT.

Contoh build integrasi setelah seluruh prasyarat terpenuhi:

```sh
docker compose --env-file .env -f infra/compose.yaml build
```

## Target Hostinger VPS

1. Tentukan domain, TLS, firewall, SSH keys, secret custody, log rotation dan monitor eksternal.
2. Hanya Nginx yang menerima trafik pengguna. App, PostgreSQL, worker, dan scheduler tidak diekspos langsung.
3. Konfigurasikan trusted TLS termination/HTTPS forwarding sesuai proxy aktual; aktifkan `SESSION_SECURE_COOKIE=true`, pastikan origin dan CSRF konsisten.
4. `APP_DEBUG=false`. Pisahkan dev, test, staging dan production. Jangan seed fixture ke production.
5. Sesuai revisi 14 September, publik menampilkan agregat tepat tanpa identitas. Flag rilis lama tidak mengendalikan dashboard ini; pastikan perilaku tersebut diterima pada UAT sebelum deployment.
6. Selesaikan seluruh gap dalam `docs/STATUS_IMPLEMENTASI.md`, lalu `php artisan nadi:preflight` dan UAT.

## Backup dan disaster recovery

Contoh `infra/backup/backup.sh` menjalankan dump PostgreSQL custom, enkripsi `age`, checksum dan pengiriman `rclone`. Service/timer contoh menjadwalkan tiap empat jam.

Konfigurasi `PGPASSFILE`, role backup, public key recipient age, rclone remote institusi, folder lokal, timeout, kapasitas disk, retention dan notifikasi kegagalan harus disiapkan terpisah. Secret tidak disimpan pada script atau source control. Private key decrypt disimpan terpisah dari server produksi.

Verifikasi upload saja bukan restore drill. Uji decrypt, checksum, `pg_restore` ke lingkungan terisolasi, row count, constraint, login, snapshot, dan laporan; ukur RPO/RTO sesuai PRD. Cadangan lokal sebelum migrasi tersedia di `.runtime/before-directory-import.dump`; belum ada backup offsite atau restore drill.

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

Fitur aplikasi sudah tersedia, tetapi pengiriman nyata belum diuji dan belum diaktifkan pada lingkungan lokal per 16 September 2026. Isi langsung pada `.env` privat, jangan kirim kredensial melalui chat:

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
