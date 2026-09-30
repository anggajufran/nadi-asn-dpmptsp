# Sumber Dokumentasi Teknis NADI ASN

Dokumen ini menjelaskan implementasi pada commit `5b1b70ae786ec4384614cebd41b50041cc1979df` dan bukti operasional bertanggal 28 September 2026. Ia tidak mengubah aplikasi, database, atau VPS.

## Mengubah isi

Edit `content.py`, lalu jalankan generator dari direktori mana saja:

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
