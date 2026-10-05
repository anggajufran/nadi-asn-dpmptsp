# Manajemen Akun — Super Admin

Buka **Pengaturan → Manajemen Akun** pada https://nadiasndpmptspsulteng.online. Edisi 5 Oktober 2026.

1. Cari dan pilih pegawai aktif dari Master Pegawai.
2. Level akses ditetapkan sebagai **Admin Kepegawaian**. Klik **Buat akun & tautan**.
3. Salin tautan aktivasi yang ditampilkan, lalu bagikan secara pribadi kepada pegawai tersebut. Tautan berlaku 24 jam, sekali pakai. Tautan tidak disimpan di riwayat browser setelah dibuka dan tidak ditampilkan ulang dari database.
4. Pegawai membuka tautan, membuat kata sandi minimal 12 karakter, memasukkan kunci ke aplikasi autentikator berbasis waktu, dan mengonfirmasi kode enam digit.
5. Akun menjadi Aktif. Identitas masuk menggunakan NIP saat akun dibuat. Kata sandi dan autentikator ditetapkan sendiri oleh pemilik akun.

Tidak ada batas satu Admin: ulangi langkah tersebut untuk pegawai lain. Pegawai yang sudah memiliki akun tidak dapat dibuatkan akun duplikat. Akun Super Admin tetap dilindungi dari penonaktifan atau pengubahan melalui fitur ini.

## Reset, tautan kedaluwarsa, dan penonaktifan

- **Reset kata sandi / Buat tautan aktivasi** membuat tautan baru. Kata sandi, autentikator, sesi, dan tautan lama dibatalkan. Pegawai harus melakukan aktivasi ulang sebelum masuk. Dialog menjelaskan dampaknya sebelum tindakan dijalankan.
- **Nonaktifkan** segera membatalkan akses dan tautan aktivasi. Data pegawai serta riwayat kerja tidak dihapus. Untuk memberi akses kembali, buat tautan aktivasi baru pada akun yang sama.
- Status: **Aktif**, **Menunggu aktivasi**, **Tautan kedaluwarsa**, atau **Nonaktif**.

Manajemen akun hanya tersedia bagi Super Admin di backend utama. Simulasi tidak membuat akun yang dapat membaca data asli. Tautan produksi menggunakan domain HTTPS utama. Tautan 127.0.0.1 dari lingkungan latihan bukan tautan akun produksi.

## Pemulihan Super Admin melalui email

Pada Login, klik **Lupa kata sandi atau kehilangan OTP?**, pilih **Super Admin**, lalu isi email pemulihan. Gunakan alamat pemulihan yang tersimpan pada akun Super Admin instansi. Tautan berlaku 30 menit dan sekali pakai. Tautan baru menggantikan tautan pemulihan sebelumnya. Permintaan email tidak langsung mengunci akun; kata sandi dan OTP lama baru dibatalkan setelah pemilik berhasil menetapkan kata sandi serta autentikator baru. Sesi lama ikut dibatalkan saat pemulihan selesai.

Untuk **Admin Kepegawaian**, halaman hanya mengarahkan agar menghubungi Super Admin. Reset dilakukan melalui Manajemen Akun seperti langkah di atas.

SMTP produksi aktif sejak 30 September 2026 dan pemulihan telah dikonfirmasi pemilik akun. Bila pengiriman gagal, periksa inbox/spam dan hubungi pengelola untuk memeriksa SMTP; kredensial aktif tidak dibatalkan oleh kegagalan kirim. Lihat [konfigurasi email](../operations/SETUP.md#email-pemulihan-super-admin).
