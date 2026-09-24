# Catatan kelanjutan — 14 September 2026

> **Pembaruan 15 September 2026:** DUK kembali mengikuti data sumber dan diatur manual oleh Super Admin. Sidebar dapat diminimalkan; tabel memiliki 10/30/50/All; urutan Excel mengikuti DUK; kategori Unit kerja berada di atas tabel PDF. Manajemen akun Admin tersedia di Pengaturan. Super Admin dapat menyetujui usulan sendiri. Kebijakan ini menggantikan hierarki otomatis dan syarat pemeriksa berbeda sebelumnya.

Pekerjaan pengguna yang terputus telah dilanjutkan: menghapus fitur Pimpinan, memuat direktori PDF, memisahkan jenis pegawai, membuka angka filter agregat, menyediakan pengelolaan unit, menghitung kelengkapan dinamis, dan edit DUK dalam sel. Akun pertama mengikuti pilihan pengguna: Restu Anggara Pratama Jufran.

Data development sekarang nyata dan privat: 168 record, 12 unit; jangan menjalankan seed/reset. `.runtime/personnel-2026.json` adalah staging privat dari PDF. Pembacaan font memisahkan nama panjang dari NIP; teks unit baris 60 yang meluber ke baris 61 telah dicocokkan dengan gambar halaman 3 dan dikoreksi. Cadangan sebelum migrasi ada di `.runtime/before-directory-import.dump`, izin pemilik saja.

Database `.runtime/pgsql` memakai UNIX socket `/private/tmp/nadi-asn-pg-20260912`, port 55432 tanpa TCP. Enam migrasi diterapkan pada development dan testing. `npm run dev` sekarang frontend backend di 5173, Laravel 8000, worker dibutuhkan untuk impor/laporan. `npm run dev:demo` khusus sintetis di 5176. State UI uji `.runtime/ui-test-state.json` terpisah dari state demo pengguna sebelumnya.

Aktivasi akun pertama tidak dilakukan oleh agen: pengguna menentukan kata sandi dan autentikator melalui berkas pribadi `.runtime/aktivasi-super-admin.html`. Jangan mencetak token/kunci/.env atau memasukkannya ke bundle. Token aktivasi hanya disimpan sebagai hash di database, berlaku 24 jam dan sekali pakai.

Seluruh 3.192 nilai terproyeksi dibandingkan staging 19 kolom dan cocok. Delapan catatan anomali sumber belum diselesaikan karena membutuhkan bukti pegawai. Lihat panduan untuk batas persetujuan dan kelengkapan, status untuk pekerjaan produksi tersisa, serta VALIDASI untuk hasil pemeriksaan.

Frontend lokal dipulihkan sebagai proses mandiri setelah koneksi terputus; log di `.runtime/frontend.log`. Proses uji sintetis memakai `.runtime/ui-test.log`. Jangan menimpa `.env`/data untuk memulihkan tampilan; nyalakan layanan yang berhenti saja.

## Kelanjutan DUK 15 September

Tujuh migrasi sudah diterapkan pada development/testing. DUK menghitung MKG dari TMT golongan, tidak memakai masa kerja keseluruhan PDF (koreksi terakhir pengguna). Tambahan kolom assignment: duk_position_level, training_level, training_date. Comparator dan gate data pada app/Modules/Duk/Services/DukRanking.php; kandidat immutable + pengesahan dua akun memakai published_snapshots, ekspor melalui worker yang sudah dimuat ulang. Seluruh pemeriksaan baru lulus. Tidak mengubah data asli/diklat dan tidak membuat kandidat nyata; hanya read-only mengecek urutan 55 PNS. Belum tersedia data diklat 55 PNS dan tanggal lulus valid 8 PNS.

## Penyelesaian revisi 21 September 2026

Revisi tampilan ringkas, mode terang/gelap, urutan unit, identitas instansi/developer, login NIP, masa kerja bulanan, usia dari tanggal lahir, dan alur pemulihan Super Admin telah diimplementasikan serta diuji. Bukti terbaru ada pada `docs/VALIDASI.md`. Pengiriman email pemulihan masih menunggu konfigurasi SMTP pengirim; jangan menandainya sudah berfungsi mengirim email nyata. Panduan akun dan operasi sudah diperbarui.
