# Sumber buku pedoman NADI ASN

Edisi 2.0, 5 Oktober 2026. Baseline aplikasi `f03ce2f9581af83325d8b9fb2bbc99b45983ab0c`.

`build.py` menghasilkan PDF tutorial 38 halaman. Ilustrasi internal dalam `assets/` memakai data sintetis; ilustrasi pengunjung memakai agregat produksi pada tanggal edisi dan tidak memuat identitas individu. Simpan aset beserta source agar buku dapat dibangun kembali setelah clone.

## Membangun

Gunakan Python dengan `reportlab` dan `Pillow`:

```sh
python3 docs/user/handbook/build.py
```

Font `NotoSans-Regular.ttf` dan `NotoSans-Bold.ttf` wajib; `Rubik-Bold.ttf` opsional. Tetapkan `NADI_DOC_FONTS` ke direktori font apabila autodiscovery tidak menemukan font. Generator tidak membutuhkan database, OTP, SMTP atau koneksi VPS.

Keluaran: `output/pdf/Panduan-Lengkap-NADI-ASN.pdf`. Metadata layout dan daftar halaman ditulis ke `tmp/pdfs/nadi-handbook/`, bukan repository. Panduan Markdown ringkas berada di `docs/user/PANDUAN.md`.

## Memperbarui edisi

Cocokkan nama tombol, peran, aturan, tanggal dan URL dengan aplikasi. Perbarui TOTAL dan tautan daftar isi jika jumlah/urutan halaman berubah. Gunakan ilustrasi sintetis untuk ruang internal; jangan memasukkan NIP, akun, token atau dokumen pegawai asli ke aset buku.

Bangun ulang, render semua halaman memakai Poppler, periksa setiap halaman, daftar isi/bookmark, teks dan privacy sebelum penerbitan. Jangan menyebut bukti uji lama sebagai pengujian baru. Revisi dokumentasi tidak perlu migrasi atau reset data.
