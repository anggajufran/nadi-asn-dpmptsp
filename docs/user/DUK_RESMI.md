# DUK Digital — urutan manual, 15 September 2026

Edisi 5 Oktober 2026. Gunakan domain produksi https://nadiasndpmptspsulteng.online. Panduan ini mengikuti implementasi aktif.

Kebijakan terbaru menggantikan hierarki otomatis, MKG hasil perhitungan, kandidat, dan pengesahan DUK. Urutan awal mengikuti No. sumber pada DATA PEGAWAI 2026.pdf. Masa kerja pada lembar tetap merupakan masa kerja keseluruhan sesuai PDF. Tidak ada pengurutan ulang otomatis berdasarkan pangkat, jabatan, diklat, pendidikan, atau usia.

## Mengatur urutan

1. Masuk sebagai **Super Admin**, buka **DUK Digital → Edit DUK**.
2. Gunakan panah naik/turun di kolom **Atur urutan**. Panah menukar posisi dengan pegawai sebelumnya/berikutnya dalam hasil filter, termasuk lintas halaman. Pegawai di luar filter tidak dipindahkan.
3. Isi alasan, lalu **Simpan DUK**. Urutan langsung berlaku dan dicatat pada Audit Log. Tidak perlu akun lain.
4. Master Pegawai, Export Excel di Master, DUK, dan Laporan menggunakan urutan tersimpan yang sama. Filter hanya menyaring baris, bukan mengurutkan ulang.

Nomor DUK adalah posisi pada seluruh daftar tersimpan. Ketika difilter, nomornya dapat melompat. Kolom No. sumber tidak ditampilkan lagi pada tabel, profil, template impor, atau Excel. Format data pegawai berisi 18 kolom. Nomor sumber asli tetap disimpan secara internal sebagai jejak dokumen. Pegawai baru ditempatkan setelah daftar yang sudah ada, sampai Super Admin mengaturnya.

## Mengubah isi sel

Admin Kepegawaian maupun Super Admin dapat mengedit isi sel melalui Edit DUK. Perubahan isi disimpan sebagai usulan, lalu diperiksa pada **Persetujuan**. Super Admin dapat menyetujui usulan sendiri maupun usulan Admin. Admin tidak dapat memutuskan persetujuan atau mengubah urutan.

Urutan dan pengajuan isi sel disimpan dalam satu transaksi: konflik revisi atau urutan akan membatalkan seluruh penyimpanan. Keluar atau Batal edit meminta konfirmasi bila ada perubahan. Muat ulang halaman bila terjadi konflik, kemudian sesuaikan kembali draf.

## Jumlah baris

Master Pegawai dan DUK menyediakan **10, 30, 50, All**. Mengubah filter atau jumlah baris kembali ke halaman pertama. Export mengambil seluruh hasil filter, bukan hanya halaman yang terlihat.

Riwayat kandidat/publikasi lama tetap disimpan di database untuk jejak historis; jalur pembuatan dan pengesahan otomatis sudah ditutup. KP/KGB mengikuti aturan instansi secara terpisah dari urutan DUK; lihat [panduan Radar](RADAR.md).
