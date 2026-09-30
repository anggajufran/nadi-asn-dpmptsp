# Akses dan pengelolaan VPS privat

NADI ASN dipasang di `/home/anggauser/nadi-asn` pada VPS kantor, dalam project Compose `nadi-asn`. Enam service: web, app, worker, scheduler, db dan antivirus. Database serta berkas privat disimpan dalam volume khusus NADI ASN.

## Akses utama

Gunakan `https://nadiasndpmptspsulteng.online`. Database VPS adalah sumber utama sejak 30 September 2026. Database pengembangan lokal telah diatur read-only untuk koneksi baru dan backend lokal dihentikan. Data lokal tidak tersinkron otomatis; jangan menimpa akun, audit atau data produksi dengan dump lama.

## Jalur privat cadangan lewat komputer pengelola

Jalankan terminal berikut dan biarkan tetap terbuka:

```sh
ssh -N -L 18443:127.0.0.1:18443 anggauser@187.127.119.238
```

Buka `https://localhost:18443`. Hanya komputer dengan akses SSH yang dapat menggunakan jalur ini. Login menggunakan NIP, password dan OTP yang sudah ada. Deployment membawa salinan akun/data, bukan akun simulasi.

Sertifikat jalur privat adalah sertifikat internal untuk localhost, sehingga browser belum otomatis mempercayainya. Pengelola dapat memverifikasi sertifikat melalui SSH sebelum memberi pengecualian khusus localhost; jangan mematikan pemeriksaan TLS browser secara global. Untuk peluncuran umum gunakan domain kantor dan sertifikat yang dipercaya browser.

Pemeriksaan TLS dari VPS tanpa menonaktifkan validasi:

```sh
cd /home/anggauser/nadi-asn
curl --cacert .deploy/tls/server.crt https://localhost:18443/api/session
openssl x509 -in .deploy/tls/server.crt -noout -fingerprint -sha256 -dates
```

Alamat IP publik port 18443 tidak dibuka. Sejak 30 September 2026, Nginx Proxy Manager melayani aplikasi publik melalui HTTPS domain NADI ASN. Lihat [konfigurasi domain](DOMAIN_2026-09-30.md) untuk status dan perpanjangan sertifikat.

## Status dan restart

```sh
cd /home/anggauser/nadi-asn
docker compose -f infra/compose.yaml ps
docker compose -f infra/compose.yaml exec app php artisan nadi:preflight
docker compose -f infra/compose.yaml logs --tail=50 app worker scheduler
```

Jika diperlukan, restart service NADI ASN tertentu, misalnya `docker compose -f infra/compose.yaml restart worker`. Jangan memakai perintah yang menghentikan semua container VPS. Jangan memakai `down -v` karena menghapus volume data.

## Data dan konfigurasi

`.deploy/app.env`, `.deploy/db-owner-password`, `.deploy/tls/server.key`, dump dan arsip berkas privat hanya boleh diakses pengelola tepercaya. Jangan commit atau mengirimkannya melalui chat. Operator dengan akses Docker/root memiliki kewenangan membaca container; batasi keanggotaan grup Docker.

Data sumber lokal tidak dihapus. Salinan VPS dibuat 28 September 2026; sesi login, cache, antrean lama dan token aktivasi lama tidak dipindahkan sebagai sesi aktif. Akun, hash password, kunci autentikator terenkripsi, pegawai dan audit dipertahankan. Jika memakai lokal dan VPS bersamaan, perubahan tidak tersinkron otomatis.

Email pemulihan menggunakan SMTP Gmail yang dikonfigurasi privat oleh pengelola. Pada 30 September 2026, pengiriman nyata berhasil dan pemilik akun mengonfirmasi pemulihan berhasil. Backup otomatis terenkripsi Google Drive telah aktif setiap 14 hari sesuai permintaan pengelola; lihat [backup Google Drive](BACKUP_GOOGLE_DRIVE.md).

## Riwayat prasyarat peluncuran (telah diselesaikan)

Tetapkan domain dan sertifikat HTTPS; arahkan proxy khusus ke web NADI ASN; tetapkan `APP_URL` dan `NADI_FRONTEND_URL` ke URL publik yang sama. Jika memakai forwarded headers, isi hanya IP proxy yang tepat dalam `NADI_TRUSTED_PROXIES`, bukan `*`. Uji origin/CSRF/rate limit dari luar.

SMTP dan backup Google Drive telah disiapkan. Tetapkan monitor, amankan salinan kunci dekripsi di luar VPS, rekonsiliasi perubahan data sejak snapshot, lakukan uji login dan fitur oleh pengelola, lalu set gate kesiapan dan jalankan preflight kembali. Jangan membuka akses internet sementara gate tersebut belum selesai.

Lihat [catatan peluncuran](LAUNCH_2026-09-30.md) dan [runbook pemantauan](MONITORING.md) untuk status terkini. Gunakan kedua file Compose (`infra/compose.yaml` dan `infra/domain/compose.proxy.yaml`) saat membuat ulang service web agar jaringan proxy tetap tersambung.
