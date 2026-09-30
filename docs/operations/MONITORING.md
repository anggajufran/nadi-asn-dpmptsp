# Pemantauan NADI ASN

## Operasi aktif

Cron pengguna anggauser menjalankan `infra/monitor/check.py` setiap lima menit, dengan lock untuk mencegah proses tumpang tindih. Konfigurasi privat berada di `.deploy/monitor/config.json`, status terakhir di `status.json`, log berotasi di `monitor.log`. Email gangguan dan pemulihan dikirim ke alamat yang disetujui pengelola: restu.angga.jf@gmail.com. Uji SMTP telah diterima layanan pengiriman.

Ada 21 pemeriksaan: enam container, HTTPS health dan session, mode data asli, koneksi database, heartbeat scheduler, umur antrean, kegagalan jobs, gate produksi, debug nonaktif, cookie secure, kapasitas disk, masa berlaku sertifikat publik/internal, serta umur backup data/project.

Dua pemeriksaan gagal berturut-turut memicu email gangguan (umumnya 5–10 menit); satu hasil sehat memicu email pulih. Kondisi sama tidak mengirim pesan berulang. Email gagal dicoba kembali pada pemeriksaan berikutnya. SMTP dijalankan dari host sehingga masih dapat melapor ketika aplikasi berhenti. Monitor tidak me-restart service secara otomatis.

**Batas cakupan:** monitor berada pada VPS yang sama. Jika VPS mati total, cron berhenti, atau akses jaringan/SMTP terputus, email tidak dapat dikirim. Deteksi dari luar saat VPS mati total memerlukan monitor eksternal terpisah; layanan tersebut belum dipasang. Backup dua minggu dan tiga bulan mengikuti kebijakan pengelola, bukan backup perubahan harian.

## Pemeriksaan operator

```sh
cd /home/anggauser/nadi-asn
python3 -B infra/monitor/check.py
cat .deploy/monitor/status.json
tail -n 30 .deploy/monitor/monitor.log
crontab -l
docker compose -f infra/compose.yaml -f infra/domain/compose.proxy.yaml ps
```

Uji email secara manual hanya saat diperlukan: `python3 -B infra/monitor/check.py --test-email`. Tidak ada password, token atau identitas pegawai dalam pesan/log monitor. Jangan membagikan `.deploy/app.env` atau konfigurasi SMTP.

## Tindakan gangguan

1. Periksa nama pemeriksaan gagal pada status/log, lalu status container NADI ASN. Jangan me-restart seluruh container kantor.
2. Untuk database: periksa volume, kapasitas disk dan koneksi; jangan memakai `down -v` atau mengimpor dump tanpa rencana pemulihan.
3. Untuk scheduler/antrean: periksa log service terkait. Restart service tertentu bila penyebab telah diketahui; failed jobs perlu ditinjau sebelum retry.
4. Untuk sertifikat: periksa cron perpanjangan dan `nginx -t`. Jangan menonaktifkan verifikasi TLS.
5. Untuk backup: periksa `last-failure`/`project-last-failure` serta log backup. Backup lama dipertahankan bila unggahan/verifikasi gagal.
6. Jalankan pemeriksaan lagi setelah perbaikan. Satu hasil sehat mengirim email pulih jika insiden sebelumnya tercatat.

## Verifikasi sebelum rilis berikutnya

`python3 infra/monitor/public-check.py` memeriksa endpoint anonim tanpa menampilkan data pegawai atau cookie. Opsi `--rate-limit` melakukan percobaan login NIP dummy dan dapat membatasi IP penguji sementara. Jalankan sesuai kebutuhan setelah perubahan, bukan setiap lima menit. Tests transisi monitor: `python3 -m unittest discover -s infra/monitor -p 'test_*.py'`.
