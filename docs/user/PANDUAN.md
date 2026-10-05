# Panduan penggunaan NADI ASN

Edisi 2.0 - 5 Oktober 2026. DPMPTSP Prov. Sulawesi Tengah. Buku tutorial lengkap beserta ilustrasi tersedia pada [PDF pedoman](../../output/pdf/Panduan-Lengkap-NADI-ASN.pdf).

## Membuka aplikasi

Buka **https://nadiasndpmptspsulteng.online** dari browser. Portal publik tidak memerlukan akun. Gunakan **Login Internal** untuk pekerjaan kepegawaian; masuk memakai NIP 18 digit, kata sandi dan OTP. Tema awal light, tombol bulan/matahari mengganti tema dan menyimpan pilihan pada browser. Localhost bukan alamat layanan kantor.

Produksi menggunakan database VPS sebagai sumber utama sejak 30 September 2026. Simulasi lokal port 5176 adalah ruang latihan dengan data buatan; tidak dapat dijadikan sakelar untuk membaca data asli. Lokal lama merupakan arsip read-only dan tidak tersinkron otomatis.

## Peran dan kewenangan

| Peran | Kewenangan |
|---|---|
| Publik | Statistik agregat, filter, ringkasan publik dan statistik pengunjung |
| Admin Kepegawaian | Data individu, pengajuan perubahan, edit isi DUK, impor, pemeriksaan Radar dan audit |
| Super Admin | Seluruh kegiatan Admin, keputusan usulan termasuk usulan sendiri, urutan DUK, akun, unit dan laporan internal |

Akun Admin dapat lebih dari satu orang. Tidak ada akun atau halaman Pimpinan; pelaporan melalui berkas yang ditarik Super Admin. Akses SSH/Docker adalah kewenangan pengelola teknis, terpisah dari peran aplikasi.

## Portal statistik pegawai

1. Pilih unit kerja dan jenis pegawai, lalu **Terapkan**.
2. Angka agregat kelompok kecil tetap tampil. Nama, NIP, tanggal lahir dan dokumen individu tidak tersedia di portal.
3. **Reset** menghapus filter; **Bagikan** menyalin tautan dengan filter; **Unduh Ringkasan** menghasilkan PDF agregat.
4. Buka tabel statistik alternatif jika lebih mudah membaca angka dalam tabel.

Total ASN pada rekap aplikasi = PNS + PPPK Penuh Waktu. PPPK Paruh Waktu direkap terpisah. Jika Paruh Waktu dipilih, grafik mengikuti kelompok itu, sedangkan total ASN tetap nol. Urutan unit: Kepala Dinas, Sekretaris, TIM 1-7, lalu tiga tim Sekretariat; unit tambahan tampil setelah acuan.

## Statistik pengunjung

Panel **Statistik pengunjung** berada di bagian bawah portal dan Command Center. Ini statistik aktivitas website, berbeda dari jumlah pegawai dan tidak mengikuti filter unit/jenis pegawai.

| Angka | Cara membaca |
|---|---|
| Sedang online | Perkiraan browser aktif dalam lima menit terakhir |
| Hari ini | Browser unik hari ini menurut WITA |
| Kemarin | Browser unik hari kemarin menurut WITA |
| Minggu ini | Akumulasi kunjungan unik harian sejak Senin |
| Bulan ini | Akumulasi harian sejak tanggal 1 bulan berjalan |
| Tahun ini | Akumulasi harian sejak 1 Januari |
| Total kunjungan | Akumulasi harian sejak pencatatan dimulai |

Pencatatan produksi mulai 5 Oktober 2026, tanpa membuat angka sebelum aktivasi. Satu browser dihitung sekali per hari, termasuk ketika memuat ulang atau membuka beberapa tab. Browser yang sama dapat dihitung lagi pada hari berikutnya. Angka periode bukan jumlah individu unik sepanjang periode. Menghapus cookie atau memakai perangkat lain dapat menambah hitungan.

Pembaruan otomatis setiap menit selama halaman terlihat. Saat halaman tidak terlihat, pengiriman aktivitas berhenti; angka online berkurang setelah jendela lima menit terlewati. Cookie acak terenkripsi tidak dikaitkan dengan akun; tabel penghitung tidak menyimpan IP, NIP, nama atau riwayat halaman. Jika muncul **Pembaruan tertunda**, periksa koneksi dan muat ulang. Jangan menganggap angka lama masih real-time. Rincian: [definisi dan privasi](../operations/VISITOR_STATISTICS.md).

## Aktivasi dan pemulihan akses

Pemilik Admin menerima tautan pribadi dari Super Admin. Periksa identitas, buat kata sandi minimal 12 karakter, tambahkan kunci ke aplikasi autentikator berbasis waktu, masukkan kode enam digit lalu **Aktifkan akun**. Tautan Admin berlaku 24 jam dan sekali pakai.

Admin yang lupa sandi atau kehilangan OTP menghubungi Super Admin. Super Admin mereset melalui Manajemen Akun dan membagikan tautan baru; akses lama segera dibatalkan. Super Admin menggunakan **Lupa kata sandi atau kehilangan OTP?**, memilih Super Admin, lalu mengisi email pemulihan terdaftar. SMTP produksi aktif dan pemulihan telah dikonfirmasi pemilik akun. Tautan pemulihan berlaku 30 menit, sekali pakai; sandi/OTP/sesi lama dibatalkan setelah aktivasi pemulihan berhasil. Periksa inbox/spam. Jangan membagikan sandi, OTP, kunci atau tautan melalui grup umum.

## Navigasi dan Command Center

Tombol menu menyembunyikan atau meminimalkan sidebar menjadi ikon. Baca ringkasan jenis pegawai, kelengkapan, usulan dan Radar. Gunakan menu atau akses cepat menuju pekerjaan terkait. Panel pengunjung menggunakan statistik website yang sama dengan portal; tidak mengidentifikasi pengunjung.

## Master Pegawai dan kelengkapan 100 persen

1. Cari nama/NIP dan gunakan filter jenis pegawai, **jenis jabatan Struktural/Fungsional/Pelaksana**, unit dan status.
2. Pilih jumlah baris **10, 30, 50, All**; filter/jumlah baris mengembalikan tabel ke halaman pertama.
3. Buka profil, daftar isian yang kurang dan histori. Pilih **Usulkan perubahan**, atau **Tambah pegawai** untuk pegawai baru yang belum ada.
4. Isi berdasarkan dokumen pendukung dan alasan, kemudian ajukan.
5. Super Admin membuka **Persetujuan**, memeriksa perbedaan dan menyetujui/menolak. Pengaju Super Admin dapat menyetujui sendiri.
6. Buka kembali profil setelah persetujuan. Persentase resmi menjadi 100 persen apabila semua isian relevan terisi.

Usia dihitung otomatis dan tidak memengaruhi kelengkapan. PPPK Paruh Waktu mengecualikan pangkat serta masa kerja tahun/bulan. Nol adalah nilai sah; kosong atau tanda minus menandakan belum terisi. Folder Drive dan tanggal kontrak adalah tambahan. Kelengkapan tidak membuktikan kebenaran dokumen. Tidak ada penghapusan pegawai; perubahan status tetap menjaga histori.

## Masa kerja dan usia

Masa kerja sumber merupakan masa kerja keseluruhan sampai September 2026. Sistem menghitung pertambahan per bulan dari acuan, tanpa menulis ulang sumber atau menghitung ganda. Contoh 13 tahun 1 bulan pada September 2026 menjadi 13 tahun 2 bulan pada Oktober 2026 dan 15 tahun 11 bulan pada Juli 2029. Saat mengubah masa kerja atau impor, gunakan nilai sesuai bulan saat pekerjaan dilakukan. MKG/lama dalam pangkat berbeda dari masa kerja keseluruhan.

Usia mengikuti tanggal lahir dan bertambah pada ulang tahun. Tanggal kosong/tidak valid perlu diperbaiki melalui usulan. Muat ulang halaman setelah tanggal berganti bila halaman tetap terbuka.

## Persetujuan

Hanya Super Admin memutuskan usulan. Periksa nilai sebelum/sesudah, alasan dan dokumen. Menyetujui menerapkan data resmi, menaikkan revisi, menambah histori dan audit. Menolak tidak menerapkan perubahan. Bila muncul konflik, muat ulang lalu ajukan ulang berdasarkan data terbaru; jangan memaksa revisi lama.

## DUK Digital

Buka **DUK Digital**, atur filter dan jumlah baris, kemudian **Edit DUK**. Edit langsung sel, gunakan Tab untuk berpindah, baca penanda perubahan, tulis alasan lalu **Simpan DUK**. Isi yang berubah menjadi usulan untuk Persetujuan. Batal edit membuang draf setelah konfirmasi.

Super Admin juga menggunakan panah naik/turun pada kolom **Atur urutan**. Urutan disimpan langsung dan dicatat pada audit. Tidak ada hierarki pangkat/jabatan otomatis. Panah menukar tetangga dalam hasil filter, termasuk lintas halaman; pegawai di luar filter tetap pada posisi globalnya. Nomor DUK bisa melompat saat difilter. Kolom No. Sumber tidak ditampilkan. Konflik satu baris/order membatalkan transaksi batch. [Panduan DUK](DUK_RESMI.md).

## Impor Excel

Unduh template terbaru di **Import Excel**. Satu worksheet, 17 kolom isian tanpa usia otomatis; ekspor memiliki 18 kolom termasuk usia dan dapat diimpor kembali. NIP harus teks 18 digit, tanggal YYYY-MM-DD atau sel tanggal Excel, bulan masa kerja 0-11, unit sudah tersedia. Batas unggahan XLSX 5 MiB dan 1.000 baris data. Formula, makro, tautan eksternal dan NIP numerik ditolak; antivirus wajib tersedia.

Unggah, cocokkan kolom, validasi, periksa preview/error, lalu konfirmasi baris valid. NIP sama memperbarui melalui usulan, bukan membuat duplikat. Sel kosong tidak menghapus data lama; gunakan form/DUK untuk mengosongkan isian opsional. Konfirmasi berhasil bukan persetujuan resmi. Jangan mengulangi batch sebelum membaca status/riwayatnya.

## KP Radar dan KGB Radar

Radar aktif untuk PNS aktif berdasarkan aturan instansi versi 2026-09-22.1. Buka **Perbarui**, isi SK/TMT, kinerja, AK/PAK jika relevan, verifikasi dokumen serta catatan proses, lalu **Simpan pemeriksaan**. Baca syarat yang kurang/gagal. Data belum lengkap, Belum memenuhi dan Siap diusulkan berbeda dari status proses administrasi.

Master yang berubah membutuhkan verifikasi ulang. Keputusan terbit tidak otomatis memperbarui Master atau memulai siklus berikutnya. Sesudah menerima SK sah, perbarui sumber dan pemeriksaan siklus berikutnya. [Petunjuk aturan dan tenggat](RADAR.md).

## Laporan dan ekspor

Super Admin membuka **Laporan & Export** untuk XLSX pegawai atau PDF statistik. Filter menentukan seluruh hasil, bukan hanya halaman tabel. Urutan Excel mengikuti DUK tersimpan. Unit kerja berada di awal kategori PDF; laporan baru tidak memuat kredit developer.

Input laporan dibekukan saat permintaan. Pantau riwayat hingga selesai, kemudian unduh. Masa berlaku unduhan tujuh hari; bila kedaluwarsa atau sumber berubah, buat laporan baru. Laporan individu hanya dibagikan kepada penerima berwenang. Footer aplikasi tetap mencantumkan identitas developer.

## Akun, unit dan audit

Super Admin memilih pegawai aktif di **Pengaturan → Manajemen Akun**, memberi akses Admin, membuat tautan, melihat status dan menonaktifkan/reset akses. Menu ini tidak membuat Super Admin tambahan. [Panduan akun](MANAJEMEN_AKUN.md).

Di **Pengaturan → Unit kerja**, tambah nama, ubah nama, atau nonaktifkan unit yang sudah tidak dipakai. Backend juga memeriksa penugasan OFFICIAL historis; bila penonaktifan tetap ditolak setelah pemindahan pegawai, hubungi pengelola dan pertahankan histori.

**Audit Log** membantu menelusuri pelaku, waktu, aktivitas dan ID kejadian. Catat ID, menu, waktu dan pesan saat meminta bantuan tanpa mengirim rahasia atau screenshot identitas yang tidak diperlukan.

## Layanan dan kebiasaan kerja

Logout pada perangkat bersama. Sesi memiliki batas idle sekitar 30 menit dan maksimum delapan jam; pembaruan statistik pengunjung tidak memperpanjang sesi. Tidak perlu menjalankan server lokal untuk menggunakan produksi. Bila layanan bermasalah, hubungi pengelola; jangan reset database untuk memperbaiki tampilan.

Backup data otomatis setiap 14 hari pukul 02.00 WITA; seluruh project setiap tiga bulan kalender pukul 03.00 WITA. Backup lama diganti sesudah unggahan baru terverifikasi. Pemantauan VPS tiap lima menit mengirim email gangguan dan pulih, tetapi tidak mendeteksi dari luar jika VPS mati total. [Panduan operator](../operations/STATUS_OPERASIONAL.md).
