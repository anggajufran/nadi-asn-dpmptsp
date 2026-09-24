# Rules Sederhana NADI ASN

## KGB dan Kenaikan Pangkat — Dinas Penanaman Modal

### 1. Tujuan fitur

Fitur membantu pengelola kepegawaian memantau jadwal, memeriksa serta agar admin dapat menyiapkan berkas usulan dari jauh jauh hari sebelumnya. Hanya **PNS** yang masuk radar. Gunakan field jenis pegawai pada data aplikasi untuk menyaring pegawai.

Masukkan di Fitur : **KP RADAR** dan **KGB RADAR**.

### 2. Rules KGB RADAR

Dasar KGB adalah tercapainya masa kerja golongan (MKG) untuk kenaikan gaji serta penilaian pekerjaan minimal cukup. Tidak menggunakan AK atau masa pangkat empat tahun. Dasar: PP 7/1977 Pasal 11–12, ditegaskan dalam [penjelasan resmi Kementerian Kesehatan](https://ropeg.kemkes.go.id/osdm/pengumuman/Surat_Edaran_KGB_Kemenkes.pdf).

Rulesnya sederhana.

- Pada fitur KGB Radar nanti yang tampil hanya list pegawai yang PNS.
- KGB umumnya setiap dua tahun. Perhitungannya kita ambil data awal dari SK KGB yang terakhir. Jadi pada tampilan fitur KGB Radar nantinya Admin maupun Super Admin menambahkan dulu kapan tanggal terakhir KGB maka akan baru bisa terlihat per kiraan tanggal KGB selanjutnya dan status perhitungannya bisa dihitung.
- Periksa syarat kinerja KGB, yaitu minimal cukup 2 tahun dari KGB terakhir. Jika belum diisi tanggal KGB terakhir maka status belum bisa dihitung.
- Angka kredit dan jenjang Fungsional tidak menjadi syarat KGB. Sama dengan Struktural, Pelaksana, dan Fungsional yang penting PNS.
- Ingatkan pengelola dua bulan sebelum TMT KGB. TMT adalah tanggal mulai berlakunya keputusan.

Dasar: [PP 7/1977](https://peraturan.bpk.go.id/Details/67316/pp-no-7-tahun-1977) dan [PP 5/2024 tentang tabel gaji PNS](https://apps-denpasar.bkn.go.id/kms/law%3App_nomor_5_tahun_2024).

### 3. Rules kenaikan pangkat menurut jabatan

- Kenaikan Pangkat hanya berlaku juga untuk PNS. Jadi tampilan pegawai pada halaman **KP RADAR** hanya PNS. Pada tabel data pegawai yang ada pada halaman **KP RADAR** tambahkan satu kolom untuk menambahkan angka kredit terakhir yang bisa ditambahkan secara manual oleh admin atau super admin. Jika PNS nya Pelaksana atau Struktural maka isinya 0 atau garis mendatar saja sedangkan jika PNS nya fungsional maka akan diisi angka misalnya 150 atau 250 dll.

| Jenis jabatan                            | Pemeriksaan utama                                                                                                                                             |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Pelaksana                                | Minimal empat tahun dalam pangkat terakhir, kinerja minimal Baik dua tahun terakhir, batas pendidikan, serta ujian dinas atau pembebasannya jika diperlukan.  |
| Fungsional                               | Minimal dua tahun dalam pangkat terakhir, kinerja minimal Baik dua tahun terakhir, AK cukup, dan kesesuaian jenjang jabatan.                                  |
| Manajerial: Pengawas, Administrator, JPT | Periksa rentang pangkat jabatan dan riwayat pelantikan. Karena ada jalur khusus, hasil akhir diverifikasi admin; jangan langsung menggunakan rumus Pelaksana. |

Dasar: [SE BKN 16/2023, bagian persyaratan](https://www.bkn.go.id/wp-content/uploads/2023/05/SE-16-TAHUN-2023.pdf), [Peraturan BKN 3/2023](https://www.bkn.go.id/wp-content/uploads/2023/07/Peraturan-BKN-Nomor-3-Tahun-2023-tentang-AK-Kenaikan-Pagkat-Jenjang-JF.pdf), dan [panduan BKN untuk jabatan struktural](https://www.bkn.go.id/storage/2025/10/Buku-Saku-Kenaikan-Pangkat-Jabatan-Struktural-dan-Pangkatnya-masih-satu-tingkat-dibawah-Jenjang-Pangkat-Terendah.pdf).

**Catatan Pelaksana:** batas reguler S1/D-IV adalah III/d, S2 IV/a, dan S3 IV/b. Gunakan pendidikan yang sudah diakui secara administrasi. Pendidikan lainnya mengikuti tabel referensi resmi. Lulus ujian dinas tidak menghapus batas pendidikan. [Rujukan batas pendidikan BKN](https://apps-denpasar.bkn.go.id/kms/ensiklopedia%3Apangkat_pns).

### 4. Jenjang Fungsional dan angka kredit

AK adalah angka kredit yang tercantum dalam PAK (Penetapan Angka Kredit) yang sah. Bedakan **AK untuk naik pangkat** dan **AK kumulatif untuk naik jenjang**. Nantinya ini akan diinput manual oleh admin maupun super admin.

| Jenjang      | Rentang pangkat umum | AK untuk satu kenaikan pangkat pada jalur umum\* | AK kumulatif menuju jenjang berikutnya |
| ------------ | -------------------- | -----------------------------------------------: | -------------------------------------: |
| Ahli Pertama | III/a–III/b          |                                               50 |                   100 menuju Ahli Muda |
| Ahli Muda    | III/c–III/d          |                                              100 |                  200 menuju Ahli Madya |
| Ahli Madya   | IV/a–IV/c            |                                              150 |                  450 menuju Ahli Utama |
| Ahli Utama   | IV/d–IV/e            |                            200 untuk IV/d → IV/e |           Tidak ada jenjang berikutnya |

Dasar tabel: [Lampiran PermenPANRB 1/2023](https://dispakati.bkn.go.id/assets/download/new/permenpan_1_2023.pdf).

Rules wajib untuk Codex:

1. Baca jenjang dari field Nama Jabatan dan lihat dua kata diakhir Nama Jabatan ada jenjang yaitu Ahli Pertama, Ahli Muda, dan Ahli Madya.
2. III/b → III/c perlu memeriksa jenjang Ahli Muda; III/d → IV/a jenjang Ahli Madya; IV/c → IV/d jenjang Ahli Utama.

Dasar: [Peraturan BKN 3/2023, Pasal 19 dan 21](https://www.bkn.go.id/wp-content/uploads/2023/07/Peraturan-BKN-Nomor-3-Tahun-2023-tentang-AK-Kenaikan-Pagkat-Jenjang-JF.pdf).

### 5. Jadwal kenaikan pangkat

- Periode KP tersedia tanggal 1 setiap bulan sejak Oktober 2025. Ini jadwal pengusulan/penetapan, bukan hak naik pangkat setiap bulan. [Peraturan BKN 4/2025](https://peraturan.bpk.go.id/Details/330957/peraturan-bkn-no-4-tahun-2025).
- Hitung masa pangkat terhadap TMT target. Jadwal batas berkas mengikuti surat layanan BKN/BKPSDM yang dipakai dinas dan dapat diperbarui admin.
- KP reguler boleh melampaui pangkat atasan langsung; jangan jadikan pangkat atasan sebagai penghalang. [Peraturan BKN 2/2025](https://apps-denpasar.bkn.go.id/kms/law%3A2025peraturanlembagapemerintahnonkementerian2_2611).

Baseline usul BKN untuk periode bulan M: tanggal 16 bulan M−2 sampai tanggal 15 bulan M−1. Contoh periode 1 April: 16 Februari–15 Maret; periode 1 Januari: 16 November–15 Desember tahun sebelumnya. Surat penjelasan juga menyediakan kesempatan perbaikan usul yang dinyatakan tidak sesuai; modelkan status perbaikan terpisah dan ikuti tenggat surat.

### 6. Data dan tampilan minimum

Tampilkan pada KGB Radar: **nama pegawai, jenis ASN, golongan, tanggal SK KGB terakhir, perkiraan tanggal, status**.
Tampilkan pada KP Radar: **nama pegawai, jenis ASN, golongan, angka kredit, perkiraan tanggal, status**.

Gunakan tiga status:

- **Data belum lengkap:** data atau dokumen wajib belum tersedia yaitu seperti tanggal SK KGB terakhir atau jumlah angka kredit.
- **Belum memenuhi:** ada syarat yang terbukti belum terpenuhi; tampilkan alasannya misalnya belum cukup 2 tahun dari KGB terakhir, belum cukup 4 tahun, angka kredit belum cukup.
- **Siap diusulkan:** seluruh pemeriksaan relevan serta syarat terpenuhi.

Pisahkan status pemeriksaan dari proses: **Belum diusulkan → Diusulkan → Keputusan terbit**. Catat penolakan jika ada keputusan penolakan.
