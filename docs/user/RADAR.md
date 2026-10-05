# Panduan KP Radar dan KGB Radar

Edisi 5 Oktober 2026. Gunakan domain produksi https://nadiasndpmptspsulteng.online. Panduan ini mengikuti implementasi aktif.

Pembaruan 22–23 September 2026 · versi aturan `2026-09-22.1`, berdasarkan `NADI-ASN-Rules-KP dan KGB.md` dari instansi. Hanya **PNS aktif** masuk Radar. Hasil membantu pemeriksaan administrasi; keputusan tetap mengikuti dokumen dan proses yang berlaku.

## Menggunakan Radar

1. Masuk sebagai Admin Kepegawaian atau Super Admin, lalu buka **KP Radar** atau **KGB Radar**.
2. Cari pegawai, klik **Perbarui**, isi data berdasarkan dokumen sah dan alasan perubahan.
3. Klik **Simpan pemeriksaan**. Hasil dihitung ulang dan perubahan dicatat pada Audit Log.
4. Buka **Lihat catatan/hasil pemeriksaan** untuk melihat syarat yang terpenuhi, belum terpenuhi, atau belum diperiksa.

Pencarian mendukung nama/NIP. Filter kesiapan dan proses berdiri sendiri; jumlah baris tersedia 10/30/50/All. Perubahan pemeriksaan Radar disimpan langsung oleh kedua peran, sedangkan perubahan pangkat/jabatan pada Master tetap mengikuti alur persetujuan yang ada. Pengingat ditampilkan di Radar dan Command Center saat halaman dimuat; tidak mengirim email atau WhatsApp otomatis.

## KGB

Isi **tanggal penerbitan SK KGB terakhir** dan **TMT KGB terakhir pada SK**. Kedua tanggal disimpan terpisah: perkiraan berikutnya dihitung dari **TMT + 2 tahun**, bukan tanggal penerbitan SK. Pengingat mulai dua bulan sebelum TMT perkiraan, walaupun dokumen belum lengkap. Sebelum dua tahun tercapai, status waktu masih Belum memenuhi.

Lengkapi predikat kinerja dua tahun sebelum tahun TMT target, minimal **Cukup**, referensi berkas, dan hasil pemeriksaan dokumen. AK maupun jenjang fungsional tidak digunakan untuk KGB. Jangan mengisi tanggal perkiraan sebagai tanggal terakhir bila SK-nya belum ada.

Contoh: SK diterbitkan 15 Agustus 2024 dengan TMT 1 September 2024 menghasilkan perkiraan 1 September 2026 dan pengingat sejak 1 Juli 2026. Setelah waktu tercapai dan seluruh syarat relevan lengkap, hasil menjadi Siap diusulkan.

## KP

| Jenis jabatan | Pemeriksaan |
| --- | --- |
| Pelaksana | Empat tahun dalam pangkat pada TMT target; kinerja dua tahun minimal Baik; ijazah diakui; pangkat tujuan dalam batas pendidikan; ujian dinas/pembebasan bila relevan. |
| Fungsional | Dua tahun dalam pangkat pada TMT target; kinerja dua tahun minimal Baik; PAK sah; AK pangkat dan kesesuaian jenjang; AK kumulatif pada perpindahan jenjang. |
| Struktural | Pengelola menetapkan TMT target setelah memeriksa rentang pangkat dan riwayat pelantikan. Tidak memakai rumus empat tahun Pelaksana. |

TMT pangkat, pendidikan, golongan, jenis jabatan, dan Nama Jabatan dibaca dari Master. Koreksi melalui Master jika sumbernya belum tepat. Jenjang Ahli Pertama/Muda/Madya/Utama dibaca dari Nama Jabatan; jenjang yang tidak dikenali membutuhkan pelengkapan data.

**AK pangkat** dan **AK kumulatif naik jenjang** diisi terpisah sesuai PAK. AK pangkat mengikuti kelompok pangkat terakhir: 50, 100, 150, atau 200. Peralihan III/b→III/c, III/d→IV/a, dan IV/c→IV/d memeriksa jenjang tujuan serta AK kumulatif 100, 200, atau 450. Pelaksana/Struktural menampilkan garis pada kolom AK.

Periode KP selalu tanggal 1. Perkiraan otomatis Pelaksana/Fungsional memilih periode yang memenuhi masa pangkat dan tenggat awalnya belum lewat. Sesudah pemeriksaan disimpan, target tersebut tetap sampai diubah pengelola; tidak bergeser diam-diam ke siklus lain. Pangkat atasan tidak menjadi batas KP reguler.

Baseline berkas untuk bulan M: **16 bulan M−2 hingga 15 bulan M−1**. Contoh target 1 Januari 2027: 16 November–15 Desember 2026. Tenggat khusus dapat diisi berdasarkan surat layanan, wajib mencantumkan surat acuan dan bertanggal sebelum TMT target. Jika target berubah, periksa kembali tahun penilaian kinerja.

## Status dan tindak lanjut

- **Data belum lengkap:** data/referensi/verifikasi wajib belum diisi.
- **Belum memenuhi:** ada syarat yang terbukti gagal. Kekurangan data lain tetap tampil dalam catatan.
- **Siap diusulkan:** seluruh pemeriksaan relevan terpenuhi.

Status tersebut terpisah dari proses **Belum diusulkan, Diusulkan, Perbaikan, Keputusan terbit, Ditolak**. Isikan nomor/tanggal usulan atau keputusan pada catatan proses. Perbaikan wajib memiliki catatan dan tenggat sesuai surat. Keputusan terbit/Ditolak menghentikan pengingat untuk catatan itu.

Keputusan terbit tidak otomatis mengubah Master maupun memulai siklus berikutnya. Sesudah SK sah diterima, perbarui Master untuk KP atau TMT/SK terakhir untuk KGB, periksa ulang dokumen dan kinerja siklus baru, lalu atur prosesnya kembali sesuai kondisi.

Jika Master berubah setelah pemeriksaan, Radar meminta verifikasi ulang. Jika pengelola lain menyimpan terlebih dahulu, penyimpanan lama ditolak agar tidak menimpa revisi terbaru; muat ulang dan periksa kembali. Data Radar disimpan di backend, bukan hanya pada browser. Data SK, PAK, kinerja, dan hasil pemeriksaan asli tidak diisi otomatis dari perkiraan.
