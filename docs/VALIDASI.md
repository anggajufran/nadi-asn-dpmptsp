# Hasil pemeriksaan — 14 September 2026

> **Status terkini — 30 September 2026:** produksi publik aktif di https://nadiasndpmptspsulteng.online. Domain/TLS, SMTP, backup Google Drive, restore terisolasi, pemantauan lima menit dan UAT pemilik akun telah diselesaikan. VPS menjadi sumber utama. Lihat [catatan rilis](operations/LAUNCH_2026-09-30.md) dan [monitor](operations/MONITORING.md). Bagian bertanggal September sebelumnya adalah histori, bukan status operasional terbaru. Monitor eksternal untuk VPS mati total masih perlu ditambahkan.

> **Pembaruan 15 September 2026:** DUK kembali mengikuti data sumber dan diatur manual oleh Super Admin. Sidebar dapat diminimalkan; tabel memiliki 10/30/50/All; urutan Excel mengikuti DUK; kategori Unit kerja berada di atas tabel PDF. Manajemen akun Admin tersedia di Pengaturan. Super Admin dapat menyetujui usulan sendiri. Kebijakan ini menggantikan hierarki otomatis dan syarat pemeriksa berbeda sebelumnya.

| Pemeriksaan | Hasil |
| --- | --- |
| Build Vue/TypeScript | Berhasil |
| Vitest unit + API simulasi terisolasi | 17 test lulus |
| PHPUnit pada PostgreSQL testing | 27 test, 105 assertion lulus |
| Parser PHP tambahan | 63 file, nol kesalahan sintaks |
| Migrasi development/testing | Enam migrasi telah diterapkan |
| Rekonsiliasi data sumber | 168 record × 19 kolom = 3.192 nilai proyeksi cocok dengan staging PDF |
| Rekap backend aktual | 55 PNS + 91 PPPK Penuh Waktu = 146 ASN; 22 Paruh Waktu |
| Filter Paruh Waktu aktual | Total ASN 0; Paruh Waktu 22; jumlah grafik unit 22 |
| Filter publik kelompok satu pegawai | Angka 1 tampil pada browser, tanpa penanda penyembunyian |
| Edit DUK pada browser sintetis | Ubah sel → Simpan → usulan tampil di Persetujuan; data resmi belum berubah |
| Pengelolaan unit pada browser sintetis | Super Admin menambah unit; unit baru muncul pada daftar |
| Layout desktop | Dashboard 1.280 px tanpa overflow halaman; pengaturan unit diperiksa visual |
| Pemeriksaan bundle | Tidak ada 168 NIP sumber atau nama lengkap panjang sumber di bundle frontend |

Seluruh tujuh halaman PDF telah diperiksa secara visual. Dua unit hasil ekstraksi yang keliru karena teks baris 60 meluber ke baris 61 dikoreksi berdasarkan halaman 3. Font khusus pada nama baris 4 turut ditangani. Tidak ada nama/NIP kosong atau duplikat NIP setelah ekstraksi. Delapan anomali isi sumber tetap ditandai, tidak diperbaiki dengan perkiraan. Jumlah unit aktif 12.

## Cakupan pengujian backend

Password + TOTP, replay protection, sesi parsial/versi lama, penolakan peran lama, CSRF; Super Admin menyetujui usulan sendiri; Admin tidak dapat memutuskan; revision/hash/transaksi/history/audit; impor XLSX, NIP teks, formula/duplikasi, mapping, konfirmasi idempotent; ekspor input beku, hak pemilik/role/expiry; agregat tanpa identitas; kelengkapan berubah setelah approval; kelola unit hanya Super Admin; rename mempertahankan keanggotaan; penonaktifan unit berisi pegawai ditolak; batch DUK rollback saat baris lain konflik; roundtrip ekspor 18 kolom → impor tanpa perubahan; aktivasi token/TOTP dan penolakan pemakaian ulang.

## Mengulang pengujian

Pengujian Laravel memaksa lingkungan testing dan database `nadi_testing`, kemudian menggunakan rollback transaksi. Jangan mengarahkannya ke database development/produksi. Migrasi uji hanya diperlukan ketika skema berubah.

```sh
APP_ENV=testing DB_DATABASE=nadi_testing /opt/homebrew/opt/php@8.4/bin/php artisan migrate --force
/opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit
npm test
npm run build
```

Runtime: PHP 8.4.25, Laravel 13.31.0, PHPUnit 12.5.35, PostgreSQL 16.15. Vitest memakai state sintetis sementara pada server 5174, bukan database atau state pengguna.

## Batas bukti

Audit dependency npm/Composer sebelumnya lulus pada pemeriksaan 12 September; tidak diklaim sebagai audit ulang tanggal ini. Build masih memperingatkan chunk ExcelJS sekitar 937 kB yang dimuat dinamis. Pengujian handler antrean tidak menggantikan stress concurrency/supervisor produksi. Docker/Hostinger, restore drill, performance, accessibility menyeluruh dan UAT organisasi belum dilakukan. Tata letak PDF laporan panjang belum divalidasi secara visual. Aktivasi akun nyata diserahkan kepada pengguna; agen tidak menentukan atau memasukkan kata sandinya.

## Bukti tambahan DUK 15 September

37 test backend (147 assertion) lulus setelah penambahan perhitungan dan alur publikasi; 17 test Vitest lulus. Build TypeScript/frontend berhasil. Kasus mencakup prioritas kepala/sekretaris, ketiga contoh Kasubag, MKG dari TMT termasuk selisih hari, urutan diklat/pendidikan/tahun/usia, data kosong, seri, hash stabil, pemisahan PPPK, konflik kandidat, hak pengesahan dan ekspor XLSX. Pengujian DUK terarah terakhir lulus 12 test (47 assertion), termasuk dua tambahan: kandidat immutable dan penyimpanan jenjang/diklat melalui persetujuan. Pemeriksaan sintaks 68 file PHP lulus. Tampilan edit, dropdown diklat dan pilihan 25 kolom diperiksa di browser dengan data sintetis.

Pemeriksaan data asli bersifat read-only: 55 PNS, kepala/sekretaris di urutan 1/2, 55 kekurangan diklat dan 8 kekurangan tanggal lulus. Tidak ada kandidat atau publikasi data instansi yang dibuat otomatis.

## Hasil revisi 15 September 2026

- Laravel: 42 pengujian, 191 assertions lulus, memakai database nadi_testing.
- Vitest: 17 pengujian lulus. Build Vue/TypeScript berhasil.
- Urutan manual: izin Super Admin, seluruh ID tepat satu kali, konflik urutan, rollback perubahan sel, persistensi, dan urutan Excel sama dengan Master/DUK.
- Akun: multi Admin, duplikat ditolak, role dibatasi, aktivasi sekali pakai, reset sandi/TOTP, kedaluwarsa, penonaktifan, dan akun Super Admin dilindungi.
- Excel 18 kolom tanpa No. sumber: NIP teks dan ekspor–impor ulang tidak mengubah nilai. PDF menempatkan Unit kerja pertama.
- Browser simulasi: sidebar ringkas, edit urutan tersimpan dan tercermin di Master, pilihan 30/50/All, serta kolom No. sumber tidak tampil.
- Database utama: 168 pegawai, 168 urutan unik sesuai sumber, satu Super Admin tertaut; tidak membuat akun Admin nyata selama pengujian.

## Hasil penyelesaian 21 September 2026

- PHPUnit: **51 pengujian, 232 assertions lulus** setelah cluster PostgreSQL lokal dinyalakan kembali. Database pengujian terpisah, data pegawai utama tidak direset.
- Vitest: **17 pengujian lulus**. Parser: **82 file PHP, nol kesalahan sintaks**. Build Vue/TypeScript berhasil; peringatan ukuran chunk ExcelJS tetap ada.
- Masa kerja diuji untuk September 2026 → Oktober 2026, Juli 2029, pergantian tahun, koreksi dari formulir bulan sebelumnya, serta persetujuan lintas bulan tanpa perhitungan ganda. Usia diuji sebelum/pada ulang tahun dan tanggal lahir kosong.
- Pemulihan Super Admin diuji memakai mail fake: token 30 menit, sekali pakai, OTP baru, sesi lama batal setelah aktivasi, email tidak dikenal/Admin tidak mendapat tautan, kegagalan email dibatalkan, dan konfigurasi belum aktif menghasilkan pesan jujur. Login email ditolak.
- Browser: portal gelap pada 390 px tidak meluber horizontal; portal desktop dan dropdown unit diperiksa, login NIP serta dialog pemulihan Admin/Super Admin tampil sesuai peran. Kontras angka grafik, kartu akses cepat, tanggal dan kepala dialog diperbaiki.
- PDF backend: seluruh dua halaman laporan semua unit dan satu halaman laporan Tim 2 dirender ke PNG dan diperiksa visual. Nama instansi lengkap, urutan 12 unit sesuai permintaan, Unit kerja pertama, dan kredit Angga Jufran terlihat. Pemeriksaan menggunakan agregat tanpa identitas individu.
- Layanan frontend 5173, backend 8000, worker laporan, dan simulasi 5176 kembali aktif.
- **Batas yang tersisa:** pengiriman email sungguhan belum aktif/diuji karena SMTP pengirim belum diisi. Implementasi dan petunjuk tersedia di `docs/operations/SETUP.md`. Tidak ada email pemulihan nyata dikirim dan akun Super Admin asli tidak direset selama pengujian.

## Penyelesaian Radar dan laporan, 22–23 September 2026

- PHPUnit lengkap: **67 test, 342 assertions lulus**, database testing terpisah. Termasuk 15 test Radar untuk KGB/TMT, batas waktu, tahun kabisat, kinerja, pendidikan/ujian, AK pangkat vs kumulatif, jenjang tujuan, jalur Struktural, revisi, izin akses, audit, validasi dan penghapusan kredit laporan.
- Vitest: **17 test lulus**. Parser: **86 file PHP, nol kesalahan sintaks**. Build Vue/TypeScript berhasil; peringatan ukuran chunk ExcelJS tetap ada.
- Browser simulasi: Admin menyimpan AK 150 dan PAK lalu nilai tampil kembali. SK KGB 15 Agustus 2024 dengan TMT 1 September 2024 menghasilkan perkiraan 1 September 2026. Setelah kinerja dan dokumen lengkap, status berubah menjadi Siap diusulkan serta penghitung pengingat menjadi 1; filter kesiapan menampilkan pegawai tersebut.
- Browser tambahan: hasil pengingat KGB tampil pada Command Center; Super Admin simulasi membuka kembali data tersimpan. Formulir tema gelap diperiksa visual, lalu tema dikembalikan terang dan sesi simulasi ditutup.
- Database utama diperiksa read-only: 168 pegawai, 55 PNS aktif; KP 30 Belum memenuhi dan 25 Data belum lengkap, KGB 55 Data belum lengkap. Tidak ada data SK/PAK/kinerja nyata yang dikarang atau diisi dalam pengujian; `radar_records` utama tetap kosong.
- PDF backend dirender ulang: seluruh 2 halaman laporan semua unit dan 1 halaman Tim 2 diperiksa secara visual. Ekstraksi teks memastikan tidak ada Developer/Angga Jufran. Nama instansi dan Unit kerja pada baris pertama tetap benar. Kredit footer aplikasi tetap tersedia.
- Migrasi Radar diterapkan pada development/testing. Worker laporan dimuat ulang dengan aman. Generator publik/simulasi dan backend tidak lagi memuat kredit developer; berkas laporan yang sudah dibuat sebelumnya tidak dimutasi.

Batas: hasil Radar adalah pemeriksaan administrasi sesuai aturan instansi, bukan penetapan keputusan. Pengingat tampil ketika data halaman dimuat, bukan email otomatis. Pengiriman email pemulihan nyata tetap bergantung pada konfigurasi SMTP yang belum diisi. Pengujian produksi/UAT tidak diklaim.
