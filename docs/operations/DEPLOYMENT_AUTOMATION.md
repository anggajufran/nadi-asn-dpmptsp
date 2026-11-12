# Panduan Otomatisasi Deployment NADI ASN (CI/CD GitHub Actions)

**Dokumen Operasional Resmi | NADI ASN DPMPTSP Prov. Sulawesi Tengah**  
*Dibuat untuk mencatat alur dan prosedur deployment otomatis agar mudah diingat dan dirujuk kapan saja.*

---

## 1. Ringkasan Arsitektur & Alur Deployment

Alur kerja pembaruan aplikasi dirancang agar otomatis, cepat, dan aman:

```
MacBook (Pengembangan Lokal)
  │
  ├─ 1. Koding, pengetesan (type check, PHP check), dan git commit
  │
  ▼
GitHub Repository (https://github.com/anggajufran/nadi-asn-dpmptsp)
  │
  ├─ 2. Jalankan `git push origin main`
  │
  ▼
GitHub Actions (Cloud Runner)
  │
  ├─ 3. Verifikasi otomatis kualitas kode (Syntax & TypeScript Check)
  ├─ 4. Login ke VPS via SSH (menggunakan kredensial dari GitHub Secrets)
  │
  ▼
Server Produksi (VPS: 187.127.119.238 | User: anggauser)
  │
  ├─ 5. Menarik kode baru (`git pull origin main` di `/home/anggauser/nadi-asn`)
  ├─ 6. Rebuild image Docker `app` dan `web`
  ├─ 7. Restart service `app`, `web`, `worker`, `scheduler` (database tetap menyala)
  ├─ 8. Jalankan migrasi database (`php artisan migrate --force`) & optimize cache
  ├─ 9. Menjalankan verifikasi preflight (`php artisan nadi:preflight`)
  │
  ▼
Live Website (https://nadiasndpmptspsulteng.online) TERBARUI OTOMATIS!
```

---

## 2. Struktur Layanan di Server VPS

Di VPS kantor, sistem berjalan di bawah manajemen Docker / 1Panel:
* **Folder Proyek:** `/home/anggauser/nadi-asn`
* **Nama Compose Project:** `nadi-asn`
* **6 Container Terisolasi:**
  1. `nadi-asn-app-1` (PHP-FPM Laravel 13)
  2. `nadi-asn-web-1` (Nginx, port 18443:8443)
  3. `nadi-asn-db-1` (PostgreSQL 16) — *Disimpan di Docker Volume aman*
  4. `nadi-asn-worker-1` (Queue Worker)
  5. `nadi-asn-scheduler-1` (Task Scheduler)
  6. `nadi-asn-antivirus-1` (ClamAV Scanner)
* Proyek lain di VPS (`berinvest`, `dashboard`, `jdih_dpmptsp`, `n8n`, `simapan`, `wordpress-kantor`, dll.) berjalan di jalurnya masing-masing dan **tidak disentuh sama sekali** oleh proses deployment ini.

---

## 3. Konfigurasi GitHub Repository Secrets

Kredensial disimpan secara terenkripsi di:  
👉 **Repository GitHub -> Settings -> Secrets and variables -> Actions**

| Nama Secret | Nilai / Keterangan |
|---|---|
| `VPS_HOST` | `187.127.119.238` |
| `VPS_USERNAME` | `anggauser` |
| `VPS_SSH_KEY` | Private SSH Key (diawali `-----BEGIN ... PRIVATE KEY-----`) |
| `VPS_PASSWORD` | Password akun `anggauser` (opsional jika menggunakan SSH key) |
| `VPS_PORT` | `22` (atau nomor port SSH custom jika diubah di panel VPS) |

---

## 4. Inisialisasi Awal Git di VPS (Cukup Dijalankan 1 Kali)

Jika folder `/home/anggauser/nadi-asn` di VPS belum terhubung ke Git, jalankan perintah ini di Terminal VPS (via 1Panel atau SSH):

```bash
cd /home/anggauser/nadi-asn
git init
git remote add origin https://github.com/anggajufran/nadi-asn-dpmptsp.git
git fetch origin main
git branch -M main
git reset --mixed origin/main
```

*Perintah di atas 100% aman: hanya menghubungkan folder dengan riwayat GitHub tanpa menyentuh database atau konfigurasi container yang sedang berjalan.*

---

## 5. Cara Kerja Rutin Sehari-hari Saat Ada Update

Setiap kali ada penambahan fitur baru atau perbaikan kode:

1. **Di MacBook:**
   Koding bersama asisten AI, lalu buat commit:
   ```bash
   git add .
   git commit -m "feat/fix: deskripsi perubahan"
   ```
2. **Kirim ke GitHub:**
   ```bash
   git push origin main
   ```
   *(Jika diminta password di Terminal, masukkan Personal Access Token GitHub yang memiliki centang `repo` dan `workflow`).*

3. **Selesai!**
   GitHub Actions akan otomatis memproses dan memperbarui aplikasi di **https://nadiasndpmptspsulteng.online**. Anda bisa memantau jalannya proses di menu **Actions** di web GitHub.

---

## 6. Jaminan Keamanan Data Pegawai

* **Database Terlindungi:** Seluruh data pegawai (PNS, PPPK, riwayat jabatan, KGB, KP, akun login, dan audit log) tersimpan di dalam Docker Volume PostgreSQL (`database:/var/lib/postgresql/data`).
* Operasi Git (`pull`, `checkout`, `commit`) hanya mengelola file kode teks dan tidak memiliki akses ke database internal PostgreSQL.
* Opsi `--no-deps` pada perintah Docker memastikan database tidak di-restart atau di-reset saat pembaruan aplikasi berlangsung.
