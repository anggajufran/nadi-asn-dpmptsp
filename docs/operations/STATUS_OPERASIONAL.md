# Status operasional NADI ASN

Snapshot 5 Oktober 2026. DPMPTSP Prov. Sulawesi Tengah. Baseline aplikasi `f03ce2f9581af83325d8b9fb2bbc99b45983ab0c`. Produksi aktif sejak 30 September; fitur statistik pengunjung aktif sejak 5 Oktober.

## Identitas layanan

| Komponen | Kondisi dan acuan |
|---|---|
| Domain | https://nadiasndpmptspsulteng.online; HTTP/www dialihkan ke HTTPS utama |
| Sistem | Ubuntu 24.04 LTS; `/home/anggauser/nadi-asn`; Compose `nadi-asn` |
| Service | Web, app, worker, scheduler, PostgreSQL 16, ClamAV |
| Data utama | Database VPS; lokal lama read-only, tidak tersinkron otomatis |
| Proxy | Konfigurasi khusus NADI ASN dalam container NPM; hanya web berada di jaringan edge |
| Email | SMTP Gmail privat; pemulihan Super Admin terkonfirmasi |
| Pengunjung | API agregat dan heartbeat stateless, WITA; tanpa identitas dalam tabel penghitung |
| Monitor | 21 pemeriksaan setiap lima menit; email gangguan/pulih ke penerima yang disetujui |
| Backup data | Drive terenkripsi setiap 14 hari, 02.00 WITA; seluruh DB termasuk statistik pengunjung, private files, recovery kit |
| Backup project | Drive terenkripsi setiap tiga bulan kalender, 03.00 WITA; kode/dokumen/aset/config dan history bundle |
| Retensi | Ganti backup lama hanya setelah upload dan baca ulang cocok; Drive Trash tidak dikosongkan |

## Bukti terbaru

Pada 5 Oktober: 13 preflight lulus, 21 monitor sehat, 26 pemeriksaan portal dan 11 pemeriksaan pengunjung lulus. Fingerprint inti pegawai/akun/audit tidak berubah pada rilis statistik; 19 container aplikasi kantor lain, database dan antivirus tidak dibuat ulang.

Bukti pada awal verifikasi 5 Oktober: backup data `nadi-20261005T002934Z` terverifikasi lima file. Backup project `nadi-project-20261005T002819Z` terverifikasi tiga file. Batas permintaan Google Drive sempat menggagalkan unggahan, kemudian percobaan ulang berhasil. Salinan terenkripsi tambahan diamankan di komputer pengelola. Jadwal berikutnya mengikuti marker sukses aktual, bukan tanggal yang dicetak dalam buku: data 19 Oktober 2026 dan project 5 Januari 2027 bila tidak ada backup manual baru yang sukses.

Penerbitan dokumentasi edisi 2 diikuti backup manual project agar PDF dan source terbaru ikut tersimpan. Marker otomatis bergeser setelah backup manual berhasil. Marker `last-success` dan `project-last-success` adalah acuan tanggal yang berlaku.

## Pemeriksaan harian operator

```sh
cd /home/anggauser/nadi-asn
python3 -B infra/monitor/check.py
docker compose -f infra/compose.yaml -f infra/domain/compose.proxy.yaml ps
cat .deploy/backup/last-success .deploy/backup/project-last-success
```

Periksa disk, log error, antrean, sertifikat dan waktu backup terakhir. Jangan membuka `.deploy/app.env`, token OAuth atau dump melalui halaman publik. Jangan menganggap flag preflight menggantikan pemeriksaan domain atau uji pemilik akun.

## Penanganan gangguan

Identifikasi pemeriksaan gagal, catat waktu/objek, lalu periksa service terkait. Jangan menghentikan seluruh container kantor. Jika perlu membatasi akses, gunakan prosedur maintenance pada [catatan peluncuran](LAUNCH_2026-09-30.md). Pulihkan komponen dan jalankan pemeriksaan ulang. Tidak ada auto-restart oleh monitor.

Monitor host tidak mendeteksi VPS mati total dari luar. Backup data 14 hari berarti transaksi sesudah snapshot terakhir berisiko hilang saat bencana; project tiga bulan tidak menggantikan backup data. Restore DB terisolasi telah dilakukan, tetapi RTO pemulihan aplikasi penuh dan SLA organisasi belum disahkan.

## Acuan operator

[Domain/TLS](DOMAIN_2026-09-30.md), [VPS](VPS_PRIVATE.md), [monitor](MONITORING.md), [data/restore](BACKUP_GOOGLE_DRIVE.md), [project](BACKUP_PROJECT.md), [statistik pengunjung](VISITOR_STATISTICS.md). Kunci dekripsi disimpan di luar VPS dan akun backup; akses harus diteruskan melalui prosedur privat kepada pengelola pengganti.
