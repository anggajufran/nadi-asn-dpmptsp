# Panduan NADI ASN — 14 September 2026

> **Pembaruan 15 September 2026:** DUK kembali mengikuti data sumber dan diatur manual oleh Super Admin. Sidebar dapat diminimalkan; tabel memiliki 10/30/50/All; urutan Excel mengikuti DUK; kategori Unit kerja berada di atas tabel PDF. Manajemen akun Admin tersedia di Pengaturan. Super Admin dapat menyetujui usulan sendiri. Kebijakan ini menggantikan hierarki otomatis dan syarat pemeriksa berbeda sebelumnya.

## Aktivasi dan masuk

Buka berkas pribadi `.runtime/aktivasi-super-admin.html`, lalu **Buka aktivasi akun**. Halaman menampilkan identitas Restu Anggara Pratama Jufran. Tentukan kata sandi minimal 12 karakter, ulangi, dan tambahkan kunci yang ditampilkan ke aplikasi autentikator berbasis waktu. Masukkan kode enam digit, lalu **Aktifkan akun**. Masuk berikutnya memakai NIP, kata sandi dan kode autentikator yang baru. Jangan mengirim kata sandi/kunci melalui chat atau membagikan berkas aktivasi.

Jika tautan kedaluwarsa sebelum digunakan, pengelola lokal dapat membuat ulang tautan untuk akun nonaktif dengan `php artisan nadi:activation-link NIP_AKUN`. Pembuatan ulang membatalkan tautan/kunci sebelumnya. Akun aktif tidak bisa direset melalui perintah ini.

## Statistik publik

Buka http://127.0.0.1:5173. Pilih unit kerja dan jenis pegawai, kemudian **Terapkan**. Angka kelompok kecil tetap ditampilkan; nama, NIP, tanggal lahir dan dokumen tidak tersedia di publik. Total ASN hanya PNS + PPPK Penuh Waktu. Kartu PPPK Paruh Waktu selalu terpisah. Bila jenis Paruh Waktu dipilih, grafik pendidikan/unit/gender/jabatan menampilkan kelompok tersebut dengan total ASN tetap nol.

Reset menghapus filter; Bagikan menyalin tautan filter; Unduh Ringkasan menghasilkan PDF agregat. Tabel statistik di bagian bawah menyajikan angka yang sama dengan grafik.

## Master Pegawai dan kelengkapan 100%

1. Masuk, buka **Master Pegawai**, cari nama atau NIP.
2. Buka profil untuk melihat 18 isian, histori, catatan sumber, dan daftar isian yang belum lengkap.
3. Pilih **Usulkan perubahan**, isi kekurangannya dan alasan. Persentase pratinjau dihitung langsung saat mengisi.
4. Simpan pengajuan. Super Admin (termasuk pengaju sendiri) membuka **Persetujuan**, memeriksa perbedaan dan menyetujui.
5. Buka kembali profil. Persentase resmi dihitung ulang berdasarkan isian yang disetujui, menjadi 100% bila seluruh isian yang relevan telah terisi.

Usia otomatis dari tanggal lahir dan tidak memengaruhi skor. Pada PPPK Paruh Waktu, pangkat/golongan serta masa kerja tahun/bulan dikecualikan. Angka nol sah; kosong dan tanda minus berarti belum diisi. Tanggal kontrak dan tautan folder bersifat tambahan. Kelengkapan adalah ukuran keterisian, bukan pengesahan kebenaran data. Catatan sumber tetap menjadi bukti historis, bukan pengurang skor.

Super Admin dapat menyetujui usulannya sendiri. Admin Kepegawaian mengajukan perubahan dan menunggu persetujuan Super Admin. Pengelolaan akun tambahan tersedia di **Pengaturan → Manajemen Akun**; lihat [panduan akun](MANAJEMEN_AKUN.md).

## Edit DUK seperti lembar Excel

Buka **DUK Digital**, atur filter, lalu klik **Edit DUK**. Ubah langsung sel yang diperlukan; gunakan Tab untuk berpindah. Sel kuning menunjukkan perubahan yang belum disimpan. Isi alasan kemudian **Simpan DUK**. Semua baris disimpan sebagai usulan dalam satu transaksi, lalu diperiksa di Persetujuan. Bila ada konflik revisi, tidak ada baris batch yang diajukan: muat ulang dan periksa perubahan.

Batal edit membuang draf setelah konfirmasi. Kolom No. sumber sudah dihapus. Nomor DUK mengikuti urutan manual. Urutan DUK dapat dinaikkan/diturunkan Super Admin lalu disimpan langsung. Radar KP/KGB mengikuti aturan instansi; lihat [panduan Radar](RADAR.md).

## Unit kerja — Super Admin

Buka **Pengaturan → Unit kerja**. Tambahkan nama baru atau pilih Ubah nama pada unit yang ada. Nama baru langsung dipakai pada tabel dan filter. Unit yang kosong dapat dinonaktifkan. Bila masih berisi pegawai, pindahkan pegawainya melalui pengajuan/persetujuan sebelum menonaktifkannya.

## Import dan export Excel

Unduh template pada **Import Excel**. Gunakan satu worksheet dengan 17 kolom isian. NIP harus teks 18 digit; tanggal menggunakan YYYY-MM-DD atau sel tanggal Excel. Tahun/bulan masa kerja diisi sesuai bulan impor. Usia dihitung otomatis dari tanggal lahir; ekspor memiliki 18 kolom termasuk usia, dan tetap dapat diimpor kembali. Nama unit harus sudah tersedia.

Unggah XLSX, cocokkan kolom, validasi, baca perbedaan, lalu konfirmasi baris valid. Formula dan NIP numerik ditolak. Baris dengan NIP sama memperbarui pegawai tersebut melalui usulan; sel kosong pada impor tidak menghapus isian lama. Gunakan form/DUK bila ingin mengosongkan nilai opsional. Batch backend dapat dilanjutkan dari riwayat dan konfirmasinya atomik.

Super Admin memakai **Laporan & Export** untuk Excel pegawai atau PDF statistik. Unduhan memakai data saat permintaan dibuat dan tersimpan tujuh hari. Template ekspor dapat diimpor kembali dengan mapping yang sama. Pelaporan kepada pimpinan dilakukan menggunakan berkas tersebut; tidak ada akun/halaman Pimpinan.

## Sumber dan ruang uji

168 pegawai dimuat dari PDF yang diberikan, dengan 12 unit kerja. Kolom kosong dipertahankan. Delapan record memiliki anomali seperti tanggal tidak valid, tanggal lahir berbeda dari bagian NIP, atau bulan masa kerja melebihi 11; periksa dokumen pendukung sebelum memperbaikinya. Nilai bulan lama dipertahankan; perubahan baru dibatasi 0–11.

Port 5176 adalah ruang simulasi dengan data buatan; port 5173 memakai backend lokal. Data nyata dan cadangan tidak boleh ditempatkan pada ruang simulasi/aset publik. Aplikasi belum dipublikasikan ke internet.

## Pembaruan 16 September 2026

- Tombol bulan/matahari mengganti mode terang dan gelap di portal publik, ruang internal, serta halaman login/aktivasi. Pilihan disimpan di browser. Padding portal dibuat lebih ringkas dengan jarak kiri-kanan tetap tersedia.
- Nama instansi pada tampilan dan laporan: **DPMPTSP Prov. Sulawesi Tengah**. Footer aplikasi mencantumkan **Angga Jufran**. Sejak pembaruan 22–23 September, kredit developer tidak dicantumkan pada laporan unduhan.
- Grafik, pilihan unit, dan laporan mengikuti Kepala Dinas, Sekretaris, TIM 1–7, lalu tiga unit Sekretariat (Kepegawaian dan Umum, Keuangan dan Aset, Program). Unit baru di luar daftar tampil sesudahnya menurut nama. Urutan ini tidak mengubah urutan DUK pegawai.
- Login memakai **NIP 18 digit**, kata sandi dan autentikator. Email hanya untuk pemulihan Super Admin.
- Masa kerja sumber beracuan September 2026 dan bertambah setiap pergantian bulan. Contoh 13 tahun 1 bulan menjadi 13 tahun 2 bulan pada Oktober 2026 dan 15 tahun 11 bulan pada Juli 2029. Nilai terbaru dihitung saat data dibuka/diunduh; muat ulang halaman yang masih terbuka setelah pergantian tanggal. Perubahan data lain tidak menghitung pertambahan dua kali.
- Usia mengikuti tanggal lahir dan bertambah pada ulang tahun. Kolom usia tidak diedit manual. Tanggal lahir kosong menghasilkan usia kosong; bulan masa kerja sumber di luar 0–11 tetap ditandai untuk diperbaiki dari dokumen pendukung.
- Untuk lupa kata sandi/OTP, lihat [panduan pemulihan akun](MANAJEMEN_AKUN.md). Pengiriman email perlu konfigurasi SMTP pengelola.

## Pembaruan 22–23 September 2026

- Tema awal adalah light; pilihan dark pengguna tetap disimpan.
- Master Pegawai dan DUK memiliki filter jenis jabatan Struktural/Fungsional/Pelaksana, termasuk saat ekspor.
- KP Radar dan KGB Radar sudah aktif bagi PNS aktif. Admin dan Super Admin mengisi data pemeriksaan melalui **Perbarui**, lalu **Simpan pemeriksaan**. Lihat [panduan Radar](RADAR.md).
- PDF statistik publik/internal yang dibuat baru tidak memuat kredit developer. Berkas lama yang sudah diunduh tidak berubah; buat laporan baru untuk memakai format terbaru.
