# Status implementasi — 14 September 2026

> **Pembaruan 15 September 2026:** DUK kembali mengikuti data sumber dan diatur manual oleh Super Admin. Sidebar dapat diminimalkan; tabel memiliki 10/30/50/All; urutan Excel mengikuti DUK; kategori Unit kerja berada di atas tabel PDF. Manajemen akun Admin tersedia di Pengaturan. Super Admin dapat menyetujui usulan sendiri. Kebijakan ini menggantikan hierarki otomatis dan syarat pemeriksa berbeda sebelumnya.

Permintaan revisi pengguna 14 September menggantikan kebutuhan lama untuk peran Pimpinan dan penyembunyian kelompok agregat kecil. Dokumen PRD dan Stage 1 asli tidak diubah.

| Area | Status lokal saat ini |
| --- | --- |
| Data pegawai | 168 record PDF, 19 kolom, 12 unit, sumber mentah privat dan catatan anomali |
| Rekap | 55 PNS + 91 PPPK Penuh Waktu = 146 ASN; 22 PPPK Paruh Waktu terpisah |
| Akses | Admin/Super Admin saja, password + TOTP, session version, CSRF; peran lama tidak mendapat akses |
| Akun pertama | Restu Anggara Pratama Jufran; NIP login, aktivasi pribadi satu kali dengan sandi pilihan pengguna |
| Publik | Agregat langsung dari data aktif, angka kelompok kecil tersedia; identitas tidak diserialisasi ke publik |
| Master Pegawai | Tabel 18 kolom, profil, filter, histori, formulir pengajuan dan persentase keterisian dinamis |
| Unit kerja | Super Admin menambah/mengganti nama/menonaktifkan unit kosong |
| DUK Live | Edit sel, draf lintas halaman, penanda perubahan, penyimpanan usulan batch atomik |
| Excel | Template/ekspor 18 kolom, NIP teks, tanggal, validasi, preview, konfirmasi dan antrean privat |
| Laporan | Super Admin, salinan data saat permintaan, riwayat, unduhan terbatas pemilik, masa berlaku 7 hari |
| Audit/persetujuan | Super Admin dapat menyetujui usulan sendiri, lock/revision, histori, hash, trigger append-only |

## Batas yang masih berlaku

- Kelengkapan dihitung dari keterisian; bukan validasi kebenaran dokumen. Delapan record sumber masih memiliki anomali untuk diperiksa petugas. Data kosong tidak ditebak.
- Urutan DUK ditentukan manual oleh Super Admin. KP/KGB memakai aturan instansi versi 2026-09-22.1; dokumen wajib diperiksa pengelola. Pengeditan aturan melalui UI belum disediakan.
- Manajemen akun Admin, aktivasi/reset dengan TOTP, status, penonaktifan, dan pencabutan sesi tersedia. QR enrollment, recovery codes dan reauth khusus belum tersedia; kunci autentikator dimasukkan manual.
- Backend impor belum memiliki rekonsiliasi/rollback kompensasi dan purge retensi. Worker produksi, stress concurrency dan outbox consumer belum lengkap.
- Identitas future-effective serta model histori Stage 1 lanjutan belum lengkap; perubahan TMT masa depan ditahan saat persetujuan.
- Statistik publik hanya mengeluarkan agregat; angka tepat kelompok kecil sesuai permintaan pengguna bukan jaminan anonimitas terhadap pengetahuan eksternal.
- Docker/Hostinger, TLS produksi, monitoring, restore drill dan UAT organisasi belum dijalankan. Versi ini berjalan lokal.

Simulasi sintetis disimpan terpisah dan bukan backend alternatif untuk data nyata. Referensi unit dari PDF digunakan sesuai penulisan sumber; salah ketik dapat diperbaiki Super Admin dengan bukti yang sesuai.

## Pembaruan 22–23 September 2026

- KP Radar dan KGB Radar aktif untuk PNS aktif, dengan input SK/TMT KGB, AK/PAK, kinerja, pemeriksaan dokumen, tenggat layanan dan proses usulan. Data disimpan dalam `radar_records`, revisi dan audit.
- Perhitungan membedakan Pelaksana, Fungsional dan Struktural; pengingat masuk Command Center. Ketika Master berubah, pemeriksaan Radar perlu diperbarui.
- Kredit developer dihapus dari seluruh generator laporan unduhan; footer aplikasi tetap.
- Migrasi Radar telah diterapkan di development dan testing. Pemeriksaan read-only menghasilkan 55 PNS aktif; data SK/PAK/kinerja asli belum diinput otomatis.
- Tema awal light dan filter jenis jabatan Master/DUK tersedia, termasuk filter ekspor.

Petunjuk: [Radar](user/RADAR.md). Bukti pengujian: [Validasi](VALIDASI.md).
