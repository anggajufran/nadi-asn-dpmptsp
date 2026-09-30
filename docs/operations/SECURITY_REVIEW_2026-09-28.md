# Pemeriksaan keamanan pra-peluncuran NADI ASN

Tanggal: 28 September 2026. Status: **pemasangan privat di VPS; belum diluncurkan untuk umum**.

Audit ini memeriksa source, riwayat Git, dependensi, pengujian aplikasi, dan konfigurasi runtime NADI ASN. Hasil lulus berlaku pada ruang lingkup pengujian di bawah, bukan jaminan bebas seluruh kerentanan atau pengganti pentest independen.

> Pembaruan 30 September: prasyarat domain/TLS, SMTP, backup dan rekonsiliasi sudah dipenuhi; akses publik aktif. Laporan ini mempertahankan hasil audit tanggal 28 September. Status terbaru ada di [catatan peluncuran](LAUNCH_2026-09-30.md); monitor eksternal masih perlu ditambahkan.

## 20 pemeriksaan

| No. | Pemeriksaan | Hasil dan bukti |
| --- | --- | --- |
| 1 | API key aman | Tidak ditemukan kunci nyata pada source/riwayat yang dipindai. Kunci aplikasi dan TOTP berada di konfigurasi privat backend; tidak dikirim ke frontend. |
| 2 | ENV tidak publik | `.env`, `.deploy`, backup dan data operasional dikecualikan dari Git/build. HTTP `/.env` dan `/.deploy/app.env` menghasilkan 404. Berkas konfigurasi server mode 0600 dalam direktori 0700. |
| 3 | Tidak ada hardcoded secret | Gitleaks dan pembandingan terhadap kunci lokal tidak menemukan kredensial nyata. Nilai sintetis pengujian bukan akun produksi. |
| 4 | Secret di Git | Gitleaks v8.30.1 memeriksa dua commit awal dan source deployment tanpa temuan. Pemeriksaan seluruh blob Git juga tidak menemukan kunci lokal. Tidak ada database pegawai di Git. |
| 5 | Debug OFF | Runtime `APP_ENV=production`, `APP_DEBUG=false`; PHP tidak menampilkan error dan tidak mengekspos versi melalui X-Powered-By. |
| 6 | Error tidak bocor | Error API 500 generik; pengujian memastikan pesan rahasia/path tidak muncul. Error API memakai `no-store`; respons 404 tidak memuat stack trace. |
| 7 | Validasi input | Allowlist field, enum, tipe, panjang, NIP, referensi unit dan revisi; input role tak sah serta URL JavaScript ditolak dalam tes. |
| 8 | Sanitasi input | Teks dinormalisasi, output HTML di-escape, URL dokumen dibatasi; XML aktif, external relationship, makro, embedded object, arsip terenkripsi/traversal ditolak. Formula impor tidak dieksekusi dan cell ekspor ditulis sebagai string. |
| 9 | SQL injection | Eloquent/binding parameter; raw SQL aplikasi hanya ekspresi tetap atau placeholder. Payload SQL pada pencarian diperlakukan sebagai teks dan tidak mengubah tabel. |
| 10 | XSS | Interpolasi Vue/Blade ter-escape; tidak ditemukan v-html/innerHTML pada source aplikasi. CSP, nosniff, frame DENY, dan pembatasan URL diterapkan. Inline style masih diperlukan UI; inline script tidak diizinkan CSP. |
| 11 | Server-side auth | Endpoint internal menolak anonim dan sesi yang baru lolos password. Pengujian HTTP VPS menghasilkan 401 untuk daftar pegawai tanpa login. |
| 12 | Akses pengguna | ADMIN/SUPER_ADMIN diperiksa di backend, kepemilikan impor/ekspor dibatasi, akun nonaktif/role lama/sesi dicabut ditolak. |
| 13 | Role admin | Pembuatan/pemulihan/penonaktifan Admin Kepegawaian hanya oleh Super Admin; tidak menerima role arbitrer dari client. Challenge OTP terikat versi keamanan akun sehingga reset membatalkan challenge lama. |
| 14 | DB tidak publik | PostgreSQL NADI ASN tidak memiliki published port dan hanya berada di jaringan internal NADI ASN. Port database aplikasi kantor lain tidak diubah oleh pekerjaan ini. |
| 15 | Izin DB ketat | Runtime `nadi_runtime` bukan owner/superuser, tidak dapat membuat schema/DB/role atau bypass RLS. Tidak memiliki UPDATE/DELETE/TRUNCATE pada audit dan snapshot. Owner password tidak dimuat ke container aplikasi. |
| 16 | Hash password | Hash Laravel; runtime memverifikasi seluruh password tersimpan sebagai hash yang dikenal. Kunci autentikator terenkripsi dan dapat dibaca dengan kunci lama yang dipertahankan saat migrasi. |
| 17 | Session aman | Cookie Secure, HttpOnly, SameSite=Strict, sesi terenkripsi, idle 30 menit, batas absolut 8 jam, regenerasi sesi, CSRF dan rate limit. Uji HTTPS privat lulus. **HTTPS domain publik masih menunggu domain dan sertifikat tepercaya.** |
| 18 | Reset password | Token acak disimpan sebagai hash, kedaluwarsa, sekali pakai, terikat versi akun, membatalkan sesi setelah aktivasi; OTP baru wajib diverifikasi. **Pengiriman email belum aktif karena layanan SMTP belum tersedia.** Alamat Gmail penerima yang sudah ditetapkan dipertahankan di DB privat. |
| 19 | Batas unggahan | XLSX saja, maksimum 5 MiB, satu worksheet, 1.000 baris, 50 kolom, 2.000 entry ZIP, 25 MiB terurai. Nginx menolak request lebih dari 6 MiB; berkas tersimpan privat dengan nama acak. |
| 20 | Scan unggahan | ClamAV privat memindai INSTREAM sebelum parsing/penyimpanan. Tes VPS: berkas bersih diterima, EICAR ditolak 422, scanner tidak tersedia ditolak 503. Mode production tidak dapat melewati scan dengan menonaktifkan flag. |

## Validasi

- Backend: 73 tes/361 assertion lulus, ditambah 4 tes/14 assertion untuk error, proxy palsu, SQL dan URL/field berbahaya (77 tes, 375 assertion secara keseluruhan).
- Frontend: 17 tes lulus; build TypeScript/Vite produksi berhasil lokal dan Docker Linux.
- `npm audit`: 0 kerentanan tercatat. `composer audit`: tidak ada advisory atau dependency abandoned pada saat pemeriksaan.
- HTTP langsung di VPS: HTTPS tervalidasi menggunakan sertifikat privat, cookie, CSRF 419, login rate limit 429, batas request 413, penolakan ENV/source dan akses anonim lulus.
- ClamAV menggunakan pembaruan signature otomatis; socket tidak dipublikasikan. Deteksi antivirus tidak menjamin semua ancaman baru dapat dikenali.
- Checksum 168 pegawai, 169 penugasan, referensi, usulan, dan kredensial akun cocok dengan sumber. Login lokal sesudah snapshot menambah audit/penanda OTP lokal; tidak disinkronkan otomatis ke VPS.
- Seluruh 19 container kantor yang sudah ada tetap menggunakan ID container yang sama setelah pemasangan NADI ASN.
- Image dasar dikunci menggunakan digest yang diverifikasi pada tanggal audit. Pemutakhiran image dan audit dependensi harus tetap dilakukan berkala.

## Temuan yang diperbaiki

Pemindaian antivirus yang sebelumnya belum ada; validasi konten ZIP/XLSX; pembatalan challenge OTP ketika akun berubah; respons error API; pemilihan trusted proxy eksplisit; pemisahan kredensial owner/runtime DB; cookie HTTPS dan header keamanan; batas resource container; schema kolom pegawai yang sebelumnya tidak sengaja ikut terabaikan oleh pola `.gitignore`; perintah preflight yang sebelumnya masih meminta dua Super Admin; akun bootstrap sekarang memiliki NIP.

## Batas peluncuran

1. Belum ada domain. Akses hanya melalui SSH tunnel ke `127.0.0.1:18443`; sertifikat privat sementara berlaku 90 hari dan belum dipercayai browser secara umum.
2. Email penerima bukan layanan pengirim. Pilih penyedia SMTP/transaksional, isi kredensial langsung pada konfigurasi privat, lalu uji pengiriman dan aktivasi oleh pemilik akun. Jangan mengirim password SMTP melalui chat.
3. `NADI_PRODUCTION_READY=false` tetap dipertahankan. Preflight belum lulus untuk peluncuran publik sampai domain dan email siap.
4. Salinan VPS bukan sinkronisasi otomatis. Jika data lokal diubah lagi, lakukan rekonsiliasi/cutover terencana sebelum VPS dijadikan sumber utama; jangan menimpa data VPS yang telah diedit.
5. Backup awal berhasil dipulihkan. Backup terjadwal offsite, pemantauan eksternal, dan latihan pemulihan berkala masih perlu ditetapkan pengelola sebelum operasional publik.

## Acuan teknis

- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [ClamAV INSTREAM dan keamanan socket](https://docs.clamav.net/manual/Usage/ClamdProtocol.html)
- [Laravel deployment](https://laravel.com/docs/13.x/deployment)
- [Gitleaks](https://github.com/gitleaks/gitleaks)
