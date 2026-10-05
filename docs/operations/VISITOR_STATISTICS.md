# Statistik pengunjung NADI ASN

Edisi 5 Oktober 2026. Fitur aktif pada domain produksi; baca [tutorial pengguna](../user/PANDUAN.md#statistik-pengunjung).

Ditampilkan pada portal publik dan Command Center untuk Admin/Super Admin. Endpoint agregat GET /api/public/visitors dan heartbeat POST /api/public/visitors/heartbeat. POST bersifat stateless, mewajibkan Origin yang tepat sama dengan URL frontend dan dibatasi 120 permintaan/menit per IP; GET dibatasi seperti statistik publik lainnya.

## Definisi angka

- Hari ini/kemarin: jumlah browser unik masing-masing hari WITA.
- Minggu ini: akumulasi kunjungan unik harian dari Senin hingga hari ini.
- Bulan/tahun: akumulasi harian sejak tanggal 1/bulan Januari hingga hari ini.
- Total: seluruh kunjungan unik harian sejak pencatatan dimulai, bukan jumlah individu unik sepanjang masa.
- Online: perkiraan browser yang mengirim heartbeat dalam lima menit terakhir; lintas tengah malam tidak dihitung ganda.

Heartbeat setiap 60 detik selama halaman terlihat, termasuk portal, login dan ruang internal. Memuat ulang atau membuka tab tambahan dengan cookie yang sama tidak menambah hitungan harian. Cookie terhapus, browser/perangkat berbeda atau cookie diblokir bisa menyebabkan hitungan berbeda. Bot umum dikenali dari User-Agent dan tidak dihitung; deteksi ini bukan jaminan semua bot terhindar. Membaca API dengan GET serta health probe tidak menambah kunjungan.

Tidak ada rekonstruksi statistik sebelum fitur aktif. trackingSince menunjukkan tanggal pertama kunjungan yang tercatat. Simulasi memakai contoh angka terpisah berlabel Data simulasi dan tidak mengubah VPS.

## Privasi dan penyimpanan

Cookie pihak pertama nadi_visitor berisi UUID acak, dienkripsi Laravel, Secure pada produksi, HttpOnly, SameSite=Strict, maksimum satu tahun. Cookie tidak dikaitkan dengan akun. Database hanya menyimpan hash HMAC harian, hash online untuk deduplikasi jangka pendek, waktu aktivitas terakhir, tanggal serta jumlah agregat. Tidak ada nama, NIP, alamat IP, User-Agent atau URL halaman dalam tabel penghitung. API hanya mengeluarkan agregat.

visitor_presences dibersihkan tiap jam, mempertahankan hari ini dan kemarin agar deduplikasi aman. visitor_daily_totals disimpan untuk statistik kumulatif; tidak berisi penanda browser. Data ikut backup database reguler; salinan dalam backup dapat memiliki retensi berbeda. Log HTTP/rate-limit server yang sudah ada merupakan mekanisme terpisah dari tabel pengunjung.

Endpoint pengunjung tidak membaca/menulis sesi autentikasi dan tidak memperpanjang idle login. Middleware session/CSRF Laravel dikecualikan khusus pada dua endpoint agregat ini; perlindungan pemalsuan permintaan memakai Origin wajib dan cookie SameSite=Strict. Seluruh endpoint mutasi akun/pegawai tetap memakai proteksi Laravel semula.

## Deploy dan verifikasi

Migrasi 2026_10_01_000012_create_visitor_statistics menambah dua tabel; tidak mengubah tabel pegawai. Migrasi dijalankan dengan role owner lalu grant runtime diterapkan ulang. Image app/web, worker dan scheduler diperbarui. Scheduler menjalankan pembersihan hourly; PostgreSQL/ClamAV serta aplikasi kantor lain tidak perlu dibuat ulang.

Tes mencakup browser berulang, batas WITA tengah malam, kalender minggu/bulan/tahun, deduplikasi online, kedaluwarsa aktivitas, retensi, API agregat, cookie dan payload/bot. Seluruh suite backend lokal: 85 tes/424 assertion lulus; 17 tes frontend dan build produksi lulus.

Aktif pada domain produksi 5 Oktober 2026 sekitar 08.23 WITA. Verifikasi langsung: 11 pemeriksaan endpoint pengunjung dan 26 pemeriksaan portal publik lulus, 13 preflight produksi lulus, serta 21 pemeriksaan pemantauan sehat. Tampilan tujuh metrik telah diperiksa melalui browser; muat ulang mempertahankan hitungan harian. Fingerprint tabel inti pegawai/akun/audit sebelum dan sesudah rilis identik. Database, antivirus dan 19 container aplikasi kantor lain tidak dibuat ulang. Statistik pertama tercatat pada 5 Oktober 2026.
