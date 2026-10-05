# Sumber Dokumentasi Teknis NADI ASN

Edisi 2.0, 5 Oktober 2026. Dokumen menjelaskan baseline aplikasi `f03ce2f9581af83325d8b9fb2bbc99b45983ab0c`, domain publik, SMTP, backup, monitoring dan statistik pengunjung. PDF 50 halaman dan Markdown memakai isi terstruktur yang sama. Pembaruan dokumentasi tidak mengubah aplikasi atau database.

## Mengubah isi

Edit metadata EDITION/PUBLISHED/BASELINE dan daftar PAGES pada `content.py`, lalu jalankan generator dari direktori mana saja:

```sh
python3 docs/architecture/technical-handbook/build.py
```

Python memerlukan `reportlab` dan `Pillow`. Font yang diperlukan adalah `NotoSans-Regular.ttf` dan `NotoSans-Bold.ttf`. Opsional: `Rubik-Bold.ttf` dan `DejaVuSansMono.ttf`. Jika font tidak ditemukan otomatis, tetapkan `NADI_DOC_FONTS` ke folder font tersebut. Jangan menaruh rahasia atau data identitas pegawai dalam sumber dokumentasi.

Keluaran:

- `output/pdf/Dokumentasi-Teknis-NADI-ASN.pdf`
- `docs/architecture/DOKUMENTASI_TEKNIS_NADI_ASN.md`
- `tmp/pdfs/nadi-technical/layout.json` untuk pemeriksaan tata letak.

Markdown adalah keluaran generator; untuk perubahan yang harus konsisten dengan PDF, edit `content.py`. PDF memakai diagram vektor; Markdown menyediakan diagram Mermaid.

## Verifikasi penerbitan

1. Perbarui versi/tanggal/baseline dan bukti sesuai perubahan nyata.
2. Cocokkan route, field, izin, konfigurasi dan status layanan dengan source.
3. Jalankan generator; pastikan tidak ada `layout_issues`.
4. Render PDF menggunakan `pdftoppm`, periksa seluruh halaman, bookmark dan tautan daftar isi.
5. Pastikan tidak ada password, token, secret, identitas pegawai atau klaim pengujian yang belum dilakukan.

Tidak diperlukan migrasi atau pengujian aplikasi hanya untuk membangun dokumen. Jika aturan/kode berubah bersamaan, jalankan pengujian aplikasi yang relevan secara terpisah.
