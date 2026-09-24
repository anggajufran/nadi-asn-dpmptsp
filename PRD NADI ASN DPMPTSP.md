# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## NADI ASN DPMPTSP
**Navigasi dan Analitik Data Internal ASN**  
**Tagline:** *Data ASN dalam Satu Pandangan*

**Dokumen:** Product Requirements Document  
**Versi:** 1.0  
**Status:** Baseline Produk / Siap Dilanjutkan ke System Design  
**Target MVP:** 1–3 bulan  
**Lingkup Organisasi:** Satu instansi DPMPTSP  
**Bahasa Aplikasi:** Bahasa Indonesia  
**Platform:** Web Application  
**Deployment:** VPS Hostinger, Docker Compose

---

# 1. Ringkasan Eksekutif

NADI ASN DPMPTSP adalah aplikasi web pengelolaan dan analitik data Aparatur Sipil Negara yang dirancang sebagai **Command Center Kepegawaian DPMPTSP**.

NADI ASN bukan sekadar database pegawai dan bukan sekadar dashboard statistik. Sistem harus mampu menjawab tiga pertanyaan utama:

1. **Apa kondisi ASN sekarang?**
2. **Apa yang harus segera dikerjakan?**
3. **Apa yang akan terjadi berikutnya?**

NADI ASN tidak dimaksudkan untuk menggantikan SIASN, ASN Digital, e-Kinerja, maupun sistem resmi pemerintah lainnya. NADI ASN berfungsi sebagai **single source of truth internal DPMPTSP** untuk konsolidasi data, monitoring, analitik, early warning, kualitas data, DUK Digital, dan dukungan pengambilan keputusan.

Prinsip utama produk:

> **INPUT SEKALI — DIGUNAKAN BANYAK KALI**

Satu data ASN yang telah diverifikasi harus dapat digunakan kembali untuk dashboard, DUK, KP/KGB Radar, laporan, analitik, dan kebutuhan administratif tanpa penginputan berulang.

---

# 2. Latar Belakang dan Masalah

Pengelolaan data ASN saat ini sangat bergantung pada berbagai file Excel. Informasi yang sama dapat muncul berulang pada file atau sheet berbeda sehingga menyebabkan beberapa masalah.

Data sulit dipastikan sebagai versi terbaru. Pembaruan harus dilakukan berulang. DUK masih membutuhkan pengolahan manual. Jadwal KP dan KGB berpotensi terlewat. Permintaan statistik dari pimpinan memerlukan formula dan chart Excel secara manual. Format laporan sulit dibuat konsisten dan fleksibel.

Selain itu, struktur Excel cenderung mengikuti kebutuhan laporan tertentu, bukan model data kepegawaian yang terintegrasi.

NADI ASN akan mengubah pola tersebut menjadi:

**Data ASN → Master Database → Calculation Engine → Analytics → Dashboard / Early Warning / Reports / Executive Insight**

---

# 3. Visi Produk

Menjadikan NADI ASN sebagai pusat navigasi data kepegawaian DPMPTSP yang aman, akurat, mudah digunakan, dapat diaudit, dan mampu memberikan gambaran kondisi ASN serta pekerjaan yang memerlukan perhatian secara cepat.

---

# 4. Tujuan Produk

NADI ASN bertujuan untuk:

- membangun satu sumber data internal ASN yang terpercaya;
- mengurangi ketergantungan terhadap banyak file Excel;
- mengurangi penginputan data berulang;
- mengotomatisasi penyusunan DUK;
- memberikan early warning KP dan KGB;
- meningkatkan kualitas dan konsistensi data;
- mempercepat pembuatan laporan;
- memberikan dashboard statistik kepada pimpinan;
- menyediakan statistik agregat yang aman kepada publik;
- mempertahankan histori perubahan data;
- menyediakan audit trail yang kuat;
- membangun fondasi untuk analitik dan proyeksi kepegawaian lanjutan.

---

# 5. Pengguna

## 5.1 Pengguna Utama

**Staf/Admin Subbag Kepegawaian**

Merupakan pengguna operasional utama yang mengelola Master Pegawai, melakukan import, memperbarui data, memeriksa KP/KGB Radar, mengelola DUK, membuat laporan, dan melakukan pekerjaan administratif lainnya.

## 5.2 Super Admin

Bertanggung jawab atas:

- user management;
- role dan permission;
- konfigurasi keamanan;
- reset password dan 2FA;
- ruleset;
- rollback import;
- konfigurasi sistem;
- audit keamanan;
- operasi administratif tingkat tinggi.

Sistem harus memiliki minimal **dua akun Super Admin aktif**.

## 5.3 Pimpinan / Internal Viewer

Memiliki akses read-only terhadap dashboard eksekutif dan informasi ASN yang diizinkan.

Data sensitif tertentu serta link Google Drive tidak ditampilkan kepada role ini.

## 5.4 Publik

Tidak memerlukan akun.

Publik hanya dapat mengakses statistik agregat melalui Public Dashboard.

---

# 6. Prinsip Arsitektur Produk

NADI ASN menggunakan prinsip:

- single source of truth;
- normalized relational database;
- history-first;
- effective-date aware;
- configuration-driven business rules;
- explainable calculations;
- maker-checker untuk perubahan kritis;
- immutable published snapshots;
- privacy by design;
- security by design;
- auditability by default;
- modular monolith.

NIP merupakan **business identifier**, bukan primary key database.

Setiap pegawai menggunakan internal UUID sebagai identitas teknis permanen.

---

# 7. Ruang Lingkup MVP

Target MVP adalah **1–3 bulan**.

Delapan kemampuan wajib MVP:

1. **Master Pegawai**
2. **Import Excel**
3. **Public Dashboard**
4. **Admin Dashboard / Command Center**
5. **DUK Digital**
6. **KP Radar**
7. **KGB Radar**
8. **Export Laporan**

Database dan arsitektur harus tetap menyediakan fondasi untuk modul lanjutan tanpa memaksakan seluruh fitur tersebut masuk MVP.

---

# 8. Master Pegawai

Master Pegawai menjadi sumber utama data ASN.

Jenis ASN:

- PNS
- PPPK

Kategori PPPK:

- Full Time
- Part Time

Data utama mencakup setidaknya:

- UUID internal;
- nama lengkap termasuk gelar;
- NIP;
- jenis ASN;
- kategori PPPK;
- jenis kelamin;
- tempat lahir;
- tanggal lahir;
- status kepegawaian;
- unit kerja;
- jabatan;
- jenis jabatan;
- pangkat/golongan;
- pendidikan terakhir/tertinggi;
- TMT relevan;
- data kontrak PPPK;
- tanggal/data dasar KP;
- tanggal/data dasar KGB;
- link folder Google Drive;
- status kelengkapan data.

Tidak ada foto pegawai.

Data pribadi yang tidak dibutuhkan seperti alamat rumah, nomor telepon, agama, dan informasi keluarga tidak dikumpulkan pada MVP.

---

# 9. Nama Pegawai

Nama beserta gelar disimpan dalam **satu field nama lengkap**.

Perubahan nama atau penambahan gelar memperbarui nilai tersebut secara langsung.

Riwayat perubahan tidak memerlukan tabel histori khusus tetapi tetap tercatat pada Audit Log.

---

# 10. Ringkasan ASN 360

MVP menyediakan **Ringkasan ASN 360**, bukan ASN 360 lengkap.

Ringkasan dapat menampilkan:

- identitas;
- status ASN;
- unit;
- jabatan;
- pangkat/golongan;
- pendidikan;
- tanggal penting;
- KP/KGB;
- kontrak PPPK;
- kelengkapan data;
- link Google Drive untuk role yang diizinkan;
- histori penting yang telah tersedia.

Halaman ini bersifat **view-only**.

Perubahan data dilakukan melalui Master Pegawai.

---

# 11. Link Google Drive

NADI ASN tidak menyimpan dokumen pegawai.

Dokumen tetap disimpan pada Google Drive kantor.

MVP hanya menyimpan **URL folder Google Drive secara manual** dan tidak menggunakan Google Drive API.

Link hanya dapat dilihat oleh:

- Super Admin;
- Admin Kepegawaian.

Pimpinan dan publik tidak dapat melihat link tersebut.

---

# 12. Model Histori Kepegawaian

Pangkat, jabatan, dan unit kerja menggunakan model historis.

Record lama **tidak ditimpa**.

UI dapat menggunakan tindakan sederhana seperti:

**Perbarui Pangkat**

tetapi backend membuat record histori baru.

Record memiliki effective date/TMT.

Perubahan dengan TMT masa depan dapat dimasukkan sebelum tanggal berlaku dan akan menjadi efektif otomatis saat TMT tercapai.

Record future-effective yang dibatalkan tidak dihapus, tetapi diberi status:

- Dibatalkan; atau
- Diganti.

TMT wajib.

Nomor SK dan tanggal SK bersifat opsional.

---

# 13. Perubahan Retroaktif

Perubahan dengan TMT masa lalu diperbolehkan tetapi dianggap kritis.

Perubahan harus:

- melewati maker-checker;
- memiliki audit trail;
- menunjukkan analisis dampak;
- memperbarui effective truth setelah approval.

Published snapshot lama tidak berubah.

Jika dokumen resmi lama perlu diperbaiki, sistem membuat **versi revisi**.

---

# 14. Effective Truth vs Published Truth

NADI ASN membedakan:

**Effective Truth**  
Kondisi terbaik berdasarkan data yang saat ini diketahui dan effective date-nya.

**Published Truth**  
Snapshot resmi yang pernah diterbitkan pada suatu waktu.

Koreksi retroaktif dapat mengubah effective truth tetapi tidak mengubah published snapshot yang sudah ada.

---

# 15. Pendidikan

MVP menyimpan **pendidikan formal terakhir/tertinggi**, bukan histori pendidikan penuh.

Data dapat mencakup:

- jenjang;
- program/jurusan;
- institusi;
- tahun lulus.

Model database tidak boleh menghalangi pengembangan histori pendidikan pada versi berikutnya.

---

# 16. PPPK

Data kontrak PPPK sudah harus terstruktur sejak MVP.

Minimal:

- TMT PPPK;
- tanggal mulai kontrak;
- tanggal akhir kontrak;
- periode kontrak;
- nomor perjanjian/SK jika tersedia;
- status kontrak.

Contract Radar dapat dikembangkan kemudian.

---

# 17. Import Excel

Excel merupakan **sumber migrasi dan media pertukaran data**, bukan database paralel.

Alur import:

**Upload → Mapping → Validation → Matching → Diff Detection → Preview → Confirmation → Verification bila diperlukan → Transactional Import → Audit**

Matching pegawai hanya menggunakan **NIP exact**.

Tidak diperbolehkan fuzzy matching berdasarkan nama.

NIP kosong/tidak valid harus masuk daftar perbaikan manual.

---

# 18. Import Add dan Update

Import dapat:

- menambahkan pegawai baru;
- memperbarui pegawai yang sudah ada.

Setiap perubahan harus memperlihatkan:

**Current Value → Imported Value**

Jika satu baris memiliki perubahan kritis, seluruh perubahan untuk pegawai tersebut ditahan sampai approval selesai.

---

# 19. Import Duplicate Detection

Sistem menghitung hash/checksum file.

Jika file identik pernah diproses, sistem memberikan peringatan dan menampilkan informasi batch sebelumnya.

Admin tetap dapat melanjutkan setelah konfirmasi eksplisit.

---

# 20. Retensi File Import

File Excel sumber import disimpan sementara.

Baseline MVP:

**14 hari, configurable.**

Setelah itu file sumber dihapus.

Metadata batch, diff, keputusan rekonsiliasi, approval dan Audit Log tetap dipertahankan.

---

# 21. Rollback Import

Setiap import memiliki Batch ID.

Rollback satu batch hanya dapat dilakukan oleh **Super Admin**.

Sebelum rollback, sistem harus melakukan impact warning jika data hasil import sudah memiliki perubahan lanjutan.

Rollback tidak menghapus jejak import sebelumnya.

---

# 22. Migrasi Data Awal

Data awal berasal dari workbook DUK dan rekap pegawai yang telah tersedia.

Struktur Excel tidak boleh disalin langsung menjadi desain database.

Migrasi menggunakan pendekatan hybrid:

- data jelas dan valid → migrasi;
- data ambigu → `Perlu Verifikasi`;
- konflik antar sumber → Admin memilih nilai yang benar;
- keputusan rekonsiliasi dicatat.

Setelah go-live, NADI ASN menjadi **single source of truth internal**.

---

# 23. DUK Digital

DUK Digital menggunakan UI tabel web modern.

Kemampuan minimal:

- search;
- filter;
- sorting;
- pagination;
- sticky header;
- export Excel;
- export PDF;
- print;
- detail dasar perhitungan.

Format export dapat mengikuti kebutuhan DUK resmi yang digunakan DPMPTSP/BKD.

---

# 24. DUK Rules Engine

Logika pengurutan DUK tidak ditanam langsung pada UI/query.

Ruleset dikelola pada service/rules engine yang:

- versioned;
- configurable;
- memiliki effective date;
- memiliki dasar regulasi;
- dapat diuji;
- dapat disimulasikan.

Ruleset tidak boleh aktif sebelum diverifikasi.

Jika belum terverifikasi:

> **Perhitungan belum tersedia karena ruleset belum terverifikasi.**

---

# 25. Ruleset Governance

Ruleset menggunakan status:

**Draft → Pending Approval → Active → Superseded/Inactive**

Maker tidak boleh menjadi checker.

Setiap ruleset penting menyimpan:

- versi;
- tanggal efektif;
- nama/nomor regulasi;
- referensi;
- parameter;
- status;
- catatan;
- pembuat;
- approver;
- timestamps.

---

# 26. Ruleset Impact Simulation

Sebelum ruleset baru diaktifkan, sistem harus dapat menunjukkan dampaknya.

Contoh:

- jumlah pegawai terdampak;
- hasil lama vs hasil baru;
- perubahan urutan DUK.

Setelah activation, perhitungan aktif direkalkulasi.

Ruleset lama tidak dihapus.

---

# 27. Data Tidak Lengkap dalam DUK

Pegawai aktif tetap muncul di DUK.

Jika data penentu urutan tidak lengkap:

> **Urutan Belum Dapat Dipastikan — Data Belum Lengkap**

Sistem tidak boleh menebak nilai.

---

# 28. DUK Live dan DUK Published

Terdapat dua konsep:

**DUK Live/Draft**  
Mengikuti effective official data terbaru.

**DUK Published**  
Snapshot resmi immutable.

DUK tidak dapat dipublikasikan jika terdapat data penentu urutan yang belum lengkap/valid.

---

# 29. Pre-Publish Review

Sebelum publish, sistem menampilkan:

- tanggal acuan;
- jumlah pegawai;
- ruleset/version;
- dasar regulasi;
- validation status;
- perubahan dari published DUK sebelumnya;
- catatan publikasi.

Publish DUK menggunakan maker-checker.

**Pengaju ≠ Publisher.**

---

# 30. Revisi DUK

DUK Published tidak diedit.

Jika terjadi koreksi:

**DUK per [tanggal] — Revisi 1**

Versi sebelumnya tetap tersedia.

Sistem menyimpan:

- alasan revisi;
- perubahan;
- maker;
- approver;
- publication timestamp;
- hubungan dengan versi sebelumnya.

---

# 31. KP Radar

MVP KP Radar merupakan **early warning/reminder**, bukan workflow pengurusan KP.

Kategori dapat mencakup:

- segera;
- ≤3 bulan;
- 3–6 bulan;
- 6–12 bulan.

Threshold harus configurable.

Jika ruleset belum terverifikasi:

> **Perhitungan Belum Tersedia — Ruleset Belum Terverifikasi**

Jika data tidak lengkap:

> **Tidak Dapat Dihitung — Data Belum Lengkap**

---

# 32. KGB Radar

MVP KGB Radar juga merupakan reminder/early warning.

Kategori dapat mencakup:

- bulan ini;
- bulan berikutnya;
- ≤3 bulan;
- ≤6 bulan;
- overdue.

Tersedia countdown.

Ruleset mengikuti governance yang sama dengan KP.

---

# 33. Explainable Calculation

Perhitungan penting menyediakan:

**Lihat Dasar Perhitungan**

Informasi dapat mencakup:

- data sumber;
- parameter;
- ruleset;
- versi;
- dasar regulasi;
- tanggal kalkulasi;
- alasan hasil.

---

# 34. Acknowledgement Peringatan

Admin dapat menandai KP/KGB sebagai:

**Sudah Dilihat**

Ini tidak mengubah status resmi atau hasil perhitungan.

Jika tingkat urgensi meningkat, acknowledgement otomatis kembali menjadi:

**Belum Dilihat**

---

# 35. Notification Center

MVP menggunakan notifikasi **in-app only** untuk operasional kepegawaian.

Notifikasi muncul melalui:

- icon lonceng + unread badge;
- bagian Perlu Perhatian pada Admin Dashboard.

Email/WhatsApp untuk notifikasi kepegawaian dapat dikembangkan kemudian.

---

# 36. Admin Command Center

Halaman pertama Admin menggunakan konsep **Hybrid Command Center**.

Mencakup:

- kondisi ASN saat ini;
- statistik utama;
- Perlu Perhatian;
- KP Radar;
- KGB Radar;
- data quality summary;
- pending verification;
- import issues;
- aktivitas terbaru.

---

# 37. Prioritas Peringatan

Peringatan menggunakan kombinasi:

**Urgency Level + Deadline**

Contoh:

- Merah — kritis/overdue;
- Oranye — sangat dekat;
- Kuning — perlu persiapan;
- Hijau — aman.

Threshold configurable.

Dalam level yang sama, deadline terdekat muncul terlebih dahulu.

---

# 38. Data Quality Summary

MVP tidak memerlukan Data Quality Center penuh tetapi wajib memiliki validasi dasar.

Command Center menampilkan:

- persentase kelengkapan;
- jumlah pegawai belum lengkap;
- masalah NIP;
- invalid references;
- data yang tidak dapat dihitung KP/KGB.

---

# 39. Completeness Score

Skor kelengkapan menggunakan konfigurasi dan bobot.

Field dapat diklasifikasikan sebagai:

- kritis/wajib;
- pendukung.

Perhitungan kontekstual berdasarkan jenis ASN.

Field PPPK tidak menurunkan skor PNS dan sebaliknya.

Ringkasan ASN 360 menampilkan:

**Kelengkapan Data: XX%**

beserta field yang perlu dilengkapi.

---

# 40. Public Dashboard

Public Dashboard **tidak membutuhkan login**.

Domain utama langsung membuka Public Dashboard.

Tersedia tombol:

**Login Internal**

Public Dashboard dan internal area harus memiliki separation of concern yang jelas pada API dan authorization.

Public API hanya boleh mengeluarkan data agregat.

---

# 41. Konten Public Dashboard

Public Dashboard menampilkan statistik agregat seperti:

- Total ASN;
- PNS;
- PPPK;
- PPPK Full Time;
- PPPK Part Time;
- gender;
- kelompok jabatan;
- pendidikan;
- pangkat/golongan PNS;
- golongan PPPK;
- jabatan fungsional;
- unit kerja.

Dashboard hanya menggunakan **official verified effective data**.

Draft/Pending Verification tidak masuk statistik.

---

# 42. Public Dashboard Header

Bagian atas menampilkan:

- identitas DPMPTSP;
- wordmark NADI ASN;
- tagline;
- deskripsi singkat;
- informasi statistik agregat;
- `Data per [tanggal]`.

MVP belum memerlukan logo khusus NADI ASN.

---

# 43. Public Privacy Engine

Public Dashboard menggunakan privacy threshold configurable.

Multi-filter diperbolehkan secara terbatas.

Jika hasil terlalu kecil:

1. agregasikan ke kategori “Lainnya” bila secara statistik tepat;
2. jika tidak memungkinkan, tampilkan `<N`.

Tidak pernah menampilkan identitas individu.

---

# 44. Public Drill-Down

Grafik dapat di-drill-down sampai statistik agregat unit/kategori.

Tidak pernah sampai daftar pegawai.

Setiap drill-down tetap melewati privacy engine.

---

# 45. Public Filter Sharing

Kondisi filter dapat disimpan pada URL.

URL hanya memuat kode filter publik.

Saat dibuka kembali, statistik dihitung berdasarkan data resmi terbaru.

URL tidak membekukan snapshot.

---

# 46. Public PDF

Publik dapat mengunduh **PDF ringkasan dashboard**.

Tidak tersedia Excel/CSV publik pada MVP.

PDF hanya berisi statistik agregat yang telah melewati privacy threshold.

---

# 47. Search Engine Indexing

Halaman utama Public Dashboard boleh diindeks mesin pencari.

URL hasil filter/drill-down diarahkan sebagai **noindex**.

---

# 48. Public Historical Analytics

MVP hanya menampilkan kondisi terkini.

Tren historis publik dan perbandingan periode masuk V2.

---

# 49. Dashboard Pimpinan

Pimpinan memperoleh Executive Dashboard read-only yang menggabungkan:

- kondisi ASN;
- attention items;
- statistik strategis;
- proyeksi yang tersedia.

Tampilan dibuat lebih ringkas daripada Admin Command Center.

Field sensitif dibatasi melalui field/category-level permission.

---

# 50. Global Search

Global Search mencari seluruh pegawai, termasuk nonaktif.

Search mendukung:

- nama partial/fuzzy ringan;
- NIP exact;
- NIP partial.

Exact NIP diprioritaskan.

Fuzzy search tidak pernah digunakan untuk import matching.

---

# 51. Status Pegawai

Pegawai nonaktif tidak dihapus.

Contoh status:

- Aktif;
- Pensiun;
- Mutasi Keluar;
- Meninggal;
- Berhenti;
- Tugas Belajar;
- status lain sesuai kebutuhan.

Dashboard aktif secara default hanya menghitung ASN aktif.

Data historis/nonaktif dapat difilter.

---

# 52. Laporan Per Tanggal

MVP menggunakan pendekatan hybrid.

Laporan dapat memiliki tanggal acuan dan snapshot resmi.

Rekonstruksi arbitrer kondisi historis penuh atau **Time Machine** masuk V2.

Laporan baru untuk tanggal masa lalu menggunakan effective truth terbaru.

Published snapshot lama tidak berubah.

---

# 53. Export Internal

Format wajib:

- `.xlsx`
- `.pdf`

Terdapat:

- export data operasional;
- format laporan kedinasan resmi.

Hak melihat dan hak export dipisahkan menggunakan permission khusus.

---

# 54. Watermark/Traceability Export

Export internal mengandung penanda seperti:

**Dihasilkan oleh NADI ASN DPMPTSP**

serta:

- tanggal/waktu;
- identitas pengguna yang melakukan export.

Aktivitas export dicatat pada Audit Log.

---

# 55. File Export

File export dibuat on-demand dan tidak disimpan permanen.

File sementara dibersihkan otomatis.

Audit hanya menyimpan metadata export.

Published snapshot disimpan sebagai data/metadata di database sehingga dokumen dapat diregenerasi.

---

# 56. Laporan Resmi

Laporan resmi dapat memuat:

- identitas instansi;
- judul;
- tanggal acuan;
- tanggal cetak;
- data/tabel;
- ruang pengesahan.

Pejabat penandatangan diisi **manual setiap kali laporan dibuat**.

MVP tidak menyimpan gambar tanda tangan/stempel dan belum menggunakan TTE.

---

# 57. Authentication

Area internal menggunakan:

**Username/Email + Password + TOTP 2FA**

Username dan email unik.

NIP bukan kredensial login.

Public Dashboard tidak menggunakan authentication.

---

# 58. User Account

Akun dan Master Pegawai merupakan entitas terpisah.

Akun menggunakan internal UUID.

Username/email hanya dapat diubah oleh Super Admin.

Perubahan tidak memutus histori audit.

---

# 59. Password Policy

Password minimal:

**12 karakter**

Mendukung passphrase.

Password lemah/umum ditolak.

Tidak ada mandatory periodic password rotation tanpa alasan.

Password wajib diganti setelah reset administratif atau indikasi compromise.

Password disimpan menggunakan secure password hashing.

---

# 60. Password Reset

MVP tidak menggunakan self-service reset.

Reset dilakukan oleh Super Admin.

Pengguna diwajibkan membuat password baru setelah login berikutnya.

---

# 61. TOTP 2FA

TOTP menggunakan aplikasi Authenticator standar.

Recovery code berupa beberapa kode **single-use**.

Membuat recovery code baru membatalkan seluruh kode lama.

Recovery code tidak disimpan plaintext.

---

# 62. 2FA Recovery

Jika pengguna kehilangan 2FA dan recovery code, hanya Super Admin yang dapat melakukan reset setelah verifikasi identitas.

Reset dicatat pada Security Audit.

TOTP dan recovery code lama dibatalkan.

---

# 63. Super Admin Recovery

Minimal terdapat dua Super Admin.

Sebagai opsi terakhir tersedia **break-glass recovery** melalui server/CLI.

Penggunaannya:

- dibatasi;
- terdokumentasi;
- hanya personel teknis berwenang;
- wajib diaudit.

---

# 64. Session Management

Session menggunakan pendekatan balanced.

Target inactivity timeout sekitar **1–2 jam**.

Aktivitas memperpanjang sesi.

Sensitive actions dapat meminta re-authentication.

Perubahan permission dapat mencabut session/token lama secara otomatis.

---

# 65. User Deactivation

Akun tidak dihapus permanen.

Ketika pengguna tidak lagi berwenang:

- akun disabled;
- sesi aktif dicabut;
- login ditolak;
- histori audit tetap dipertahankan.

Pimpinan tidak memiliki automatic expiry pada MVP; deaktivasi dilakukan manual oleh Super Admin.

---

# 66. Roles dan Permission

Role baseline:

- Super Admin;
- Admin Kepegawaian;
- Pimpinan/Internal Viewer;
- Public.

Permission granular harus mendukung setidaknya:

- view;
- create;
- update;
- approve;
- import;
- export;
- publish;
- configure;
- audit access;
- rollback.

Admin Kepegawaian secara umum dapat mengelola seluruh pegawai, bukan hanya unit tertentu.

---

# 67. Maker-Checker

Maker-checker berlaku pada perubahan kritis.

Daftar critical fields harus configurable.

Verifier:

- Admin Kepegawaian yang diberi permission;
- Super Admin.

**Maker ≠ Checker.**

Pegawai baru yang dibuat manual berstatus Draft/Pending Verification dan belum masuk statistik resmi sampai disetujui.

---

# 68. Audit Trail

Audit harus mencakup:

- create/update/delete;
- nilai sebelum/sesudah;
- import;
- export;
- approval/rejection;
- publish;
- revision;
- rollback;
- ruleset;
- configuration;
- login/security events;
- account/permission changes;
- aktivitas sensitif tertentu.

Audit dapat menyimpan IP dan user agent jika sesuai.

---

# 69. Audit Access

Super Admin dapat melihat audit penuh.

Admin Kepegawaian hanya melihat audit operasional yang relevan.

Security Audit sensitif hanya untuk Super Admin.

---

# 70. Audit Retention

Audit penting dipertahankan sepanjang umur sistem.

Audit terbaru berada pada active storage.

Audit lama dapat diarsipkan tetapi tetap dapat ditelusuri.

Login/logout rutin memiliki retensi lebih pendek.

Security events penting disimpan lebih lama.

---

# 71. Aktivitas Terbaru

Admin Dashboard menampilkan aktivitas operasional seluruh Admin secara ringkas:

- jenis aktivitas;
- objek/pegawai;
- pelaku;
- waktu.

Detail before/after dibuka melalui Audit Log.

Security activity tidak ditampilkan pada widget ini.

---

# 72. UI/UX

Arah desain:

- modern;
- premium;
- profesional;
- government-grade;
- bersih;
- data-centric;
- desktop-first untuk internal;
- fully responsive untuk publik.

MVP menggunakan **Light Mode**.

Dark Mode masuk versi berikutnya.

---

# 73. Branding

NADI ASN menjadi identitas aplikasi utama tetapi tetap menunjukkan bahwa aplikasi merupakan sistem resmi DPMPTSP.

MVP menggunakan wordmark:

**NADI ASN**

Logo khusus dapat dibuat kemudian.

---

# 74. Accessibility

Target komponen utama:

**WCAG 2.1 AA**

Termasuk:

- contrast;
- keyboard navigation;
- visible focus;
- proper form labels;
- readable typography;
- accessible validation;
- status tidak hanya mengandalkan warna.

---

# 75. Responsive Design

Public Dashboard memiliki pengalaman penuh di smartphone dengan UX adaptif.

Filter kompleks menggunakan drawer.

Chart/tabel menyesuaikan layar.

Area internal tetap desktop-first tetapi responsive untuk akses ringan melalui smartphone.

---

# 76. Browser Support

Mendukung browser modern yang masih didukung vendor:

- Chrome;
- Edge;
- Firefox;
- Safari.

QA Admin diprioritaskan pada Chrome dan Edge.

Legacy browser tidak menjadi target MVP.

---

# 77. Visualisasi

Dashboard menggunakan visualisasi modern dan interaktif dengan:

- tooltip;
- hover;
- legend interaction;
- filtering;
- animasi ringan;
- drill-down aman.

Visualisasi tidak boleh dekoratif berlebihan.

---

# 78. Bahasa dan Locale

UI menggunakan Bahasa Indonesia.

Tanggal, angka, desimal, istilah, dan timezone mengikuti konfigurasi lokal kantor.

MVP tidak memerlukan sistem i18n kompleks.

---

# 79. Security Requirements

Minimal:

- HTTPS;
- RBAC;
- TOTP 2FA;
- CSRF protection;
- XSS mitigation;
- SQL injection protection;
- password hashing;
- login rate limiting;
- secure session management;
- secure upload;
- server-side validation;
- authorization pada setiap endpoint;
- audit logging;
- secrets management;
- least privilege;
- security headers.

Public/private API harus dipisahkan secara logis dan authorization tidak boleh hanya bergantung pada UI.

---

# 80. Data Privacy

Public API hanya mengeluarkan aggregate allowlisted data.

Data sensitif tidak boleh sekadar dikirim lalu disembunyikan menggunakan frontend.

Public privacy engine harus berjalan di server.

---

# 81. Database

Database utama:

**PostgreSQL**

Menggunakan relational normalized schema.

Prinsip:

- UUID primary keys;
- foreign keys;
- indexes;
- unique constraints;
- referential integrity;
- history tables;
- effective dates;
- soft delete/status untuk official data;
- transactional operations.

---

# 82. Stack Teknologi MVP

Stack utama yang direkomendasikan:

**Backend:** Laravel (PHP)  
**Frontend:** Vue 3 + TypeScript  
**UI:** Tailwind CSS + component layer yang konsisten  
**Database:** PostgreSQL  
**Cache/Queue:** Redis bila dibutuhkan  
**Web Server / Reverse Proxy:** Nginx  
**Containerization:** Docker + Docker Compose  
**API:** RESTful API  
**Authentication:** server-side secure authentication + TOTP  
**Excel:** library mature untuk XLSX import/export  
**PDF:** server-side PDF generation  
**Charts:** library chart modern yang mendukung responsive/interactivity  
**Version Control:** Git private repository

Alasan pemilihan Laravel sebagai backend utama adalah ekosistemnya matang untuk aplikasi CRUD/data-heavy, authentication/authorization, validation, queues, database migration, testing, scheduler, file handling, serta relatif mudah dipelajari dan dipelihara oleh tim internal kecil.

Arsitektur menggunakan **modular monolith**, bukan microservices.

---

# 83. Deployment

Production berjalan pada VPS Hostinger.

Docker Compose minimal mengelola:

- application;
- Nginx;
- PostgreSQL;
- Redis bila digunakan;
- worker/scheduler bila diperlukan.

PostgreSQL menggunakan persistent volume.

Lifecycle database tidak bergantung pada lifecycle container.

---

# 84. Environment Separation

Minimal:

- development/test;
- production.

Data ASN asli hanya berada di production.

Development/test menggunakan dummy atau anonymized data.

Secrets antar-environment tidak boleh sama.

---

# 85. Deployment Workflow

Deployment menggunakan model **semi-otomatis dan terkontrol**.

Source code berada pada Git private repository.

Deployment production hanya dilakukan secara eksplisit oleh personel berwenang.

Pipeline/script dapat menjalankan:

1. pre-check;
2. backup;
3. mengambil approved version;
4. dependency build;
5. database migration;
6. Docker update;
7. health check;
8. rollback bila gagal.

---

# 86. Source Code Governance

Repository Git privat harus berada di bawah kontrol instansi.

Tidak boleh bergantung pada akun pribadi developer.

Workflow Git dibuat sederhana untuk tim pemula.

Dokumentasi wajib menjelaskan Git dari dasar.

---

# 87. Maintenance Mode

Aplikasi memiliki Maintenance Mode.

Saat aktif:

- pengguna biasa tidak dapat melakukan transaksi;
- halaman informasi ditampilkan;
- akses teknis tertentu dapat dipertahankan;
- activation/deactivation dicatat.

---

# 88. Availability

Target:

**24/7 secara wajar**

MVP tidak membutuhkan High Availability multi-server.

Prioritas:

- stability;
- monitoring;
- backup;
- recovery;
- security.

---

# 89. Backup

Target:

**RPO ≤ 6 jam**

Backup database dilakukan beberapa kali per hari.

Rolling retention operasional:

**±7 hari**

Backup tidak hanya berada pada VPS produksi.

---

# 90. Off-Server Backup

Google Drive kantor digunakan sebagai backup off-server MVP.

Backup harus:

- dienkripsi sebelum upload;
- memiliki restricted access;
- menggunakan folder khusus teknis;
- tidak bercampur dengan folder dokumen ASN.

Selain rolling 7 hari, satu backup bulanan disimpan lebih lama.

Baseline:

**12 bulan, configurable.**

---

# 91. Disaster Recovery

Target:

**RTO ≤ 8 jam**

Restore backup diuji minimal:

**setiap 3 bulan.**

Uji dilakukan pada environment terpisah.

Hasil uji mencatat:

- tanggal;
- backup;
- success/failure;
- waktu restore;
- masalah;
- tindak lanjut.

---

# 92. Monitoring

Monitoring otomatis minimal mencakup:

- application/API health;
- database;
- CPU;
- RAM;
- disk;
- backup failures;
- critical errors;
- SSL/TLS expiry.

Alert teknis dikirim melalui **email tim teknis**.

Alamat penerima configurable.

---

# 93. Performance

Sistem harus menggunakan:

- indexing;
- pagination;
- optimized queries;
- caching bila sesuai;
- eager loading/batching;
- pencegahan N+1;
- async/background processing untuk pekerjaan berat bila diperlukan.

Sistem tidak boleh didesain hanya untuk jumlah awal sekitar 170 pegawai.

---

# 94. Testing

Automated testing diprioritaskan untuk business-critical logic.

Minimal:

- DUK ordering;
- usia;
- masa kerja;
- effective-date logic;
- KP;
- KGB;
- import;
- maker-checker;
- retroactive correction;
- future-effective record;
- ruleset version;
- privacy threshold;
- permission;
- published snapshot;
- rollback import.

Selain unit test, diperlukan integration/feature testing untuk workflow utama.

---

# 95. Dokumentasi Wajib

Dokumentasi merupakan deliverable produk, bukan pekerjaan tambahan opsional.

## 95.1 User Guide
Panduan penggunaan untuk Admin Kepegawaian dan Pimpinan.

## 95.2 Super Admin Guide
Akun, role, permission, reset password, reset 2FA, ruleset, audit, rollback, konfigurasi.

## 95.3 Technical/Developer Guide
Arsitektur, modul, coding convention, dependencies, struktur source code, service layer, testing.

## 95.4 Database & Data Dictionary
Tabel, field, tipe data, PK/FK, constraint, index, enum/reference, histori, effective date.

## 95.5 API Documentation
Endpoint, authentication, permission, request, response, validation dan error.

## 95.6 Deployment Guide
Docker, Docker Compose, environment, secrets, migration, deployment, rollback.

## 95.7 Git Guide untuk Pemula
Konsep repository, clone, branch, commit, pull/push, merge, tag/release, workflow tim, recovery kesalahan umum.

## 95.8 Backup & Disaster Recovery Guide
Backup schedule, encryption, Google Drive, retention, restore, RPO/RTO, quarterly recovery test.

## 95.9 Security Operations Guide
User lifecycle, 2FA recovery, break-glass, incident response dasar, permission review, security audit.

## 95.10 Import & Migration Guide
Format data, mapping, validation, reconciliation, Batch ID, rollback, troubleshooting.

## 95.11 Ruleset Administration Guide
Pembuatan ruleset, dasar regulasi, simulation, maker-checker, activation, superseded rules.

## 95.12 Maintenance & Troubleshooting Guide
Health checks, logs, disk, database, backup failure, SSL, deployment failure, rollback, maintenance mode.

## 95.13 Release Notes / Changelog
Setiap release production memiliki versi dan ringkasan perubahan.

---

# 96. Out of Scope MVP

Tidak menjadi blocker MVP:

- ASN 360 lengkap;
- histori pendidikan lengkap;
- training/competency management lengkap;
- Satyalancana Radar;
- Retirement Radar;
- regeneration map;
- Contract Radar;
- Data Quality Center lengkap;
- Reconciliation Center lengkap;
- DUK Time Machine;
- historical public analytics;
- ad-hoc report builder;
- WhatsApp notification;
- email notification kepegawaian;
- Google Drive API integration;
- TTE;
- Dark Mode;
- advanced executive projections;
- microservices;
- High Availability cluster.

Namun database dan architecture tidak boleh sengaja menutup jalan bagi fitur tersebut.

---

# 97. Roadmap

## Phase 0 — Foundation & Verification

- verifikasi sumber data;
- data profiling Excel;
- verifikasi regulasi DUK/KP/KGB;
- repository Git;
- development environment;
- database foundation;
- authentication/RBAC;
- audit foundation.

## Phase 1 — Core Data

- Master Pegawai;
- master references;
- history model;
- Ringkasan ASN 360;
- basic data quality;
- maker-checker.

## Phase 2 — Migration & Import

- Excel mapping;
- validation;
- reconciliation;
- initial migration;
- ongoing import;
- batch/audit/rollback.

## Phase 3 — Command Center

- Admin Dashboard;
- Public Dashboard;
- privacy engine;
- Global Search;
- Notification Center.

## Phase 4 — Employment Intelligence

- ruleset engine;
- DUK Digital;
- DUK publication;
- KP Radar;
- KGB Radar;
- explainable calculation.

## Phase 5 — Reporting

- Excel export;
- PDF;
- official templates;
- public PDF;
- watermark/traceability.

## Phase 6 — Hardening & Go-Live

- security review;
- performance testing;
- backup/restore;
- monitoring;
- documentation;
- UAT;
- production migration;
- training;
- go-live.

---

# 98. Success Metrics MVP

MVP dianggap berhasil jika:

- seluruh data ASN aktif dapat dikelola dari satu sistem;
- Excel tidak lagi menjadi database paralel utama;
- DUK dapat dihasilkan dari data resmi tanpa penyusunan ulang manual;
- KP/KGB yang dapat dihitung dapat dipantau otomatis;
- data tidak lengkap terdeteksi tanpa sistem membuat asumsi;
- laporan utama dapat dibuat dalam Excel/PDF;
- pimpinan dapat memperoleh gambaran ASN tanpa meminta rekap Excel manual;
- publik dapat melihat statistik agregat tanpa mengekspos individu;
- perubahan kritis dapat ditelusuri;
- published DUK tidak dapat berubah diam-diam;
- backup dapat direstore;
- sistem dapat dipelihara oleh tim selain developer awal melalui dokumentasi.

---

# 99. Acceptance Criteria Tingkat Produk

MVP tidak boleh dinyatakan siap production sebelum:

1. authentication + TOTP berjalan;
2. authorization diuji;
3. Public API dipastikan tidak mengeluarkan personal data;
4. privacy threshold diuji;
5. migrasi data awal direkonsiliasi;
6. critical field maker-checker berjalan;
7. audit trail berjalan;
8. DUK ruleset telah diverifikasi sebelum menghasilkan hasil resmi;
9. KP/KGB tidak menghasilkan hasil jika ruleset belum terverifikasi;
10. DUK Published immutable;
11. import preview/diff berjalan;
12. rollback import dapat diuji;
13. Excel/PDF export berjalan;
14. backup otomatis berjalan;
15. off-server backup berjalan;
16. restore berhasil diuji;
17. monitoring dan email alert berjalan;
18. browser utama diuji;
19. responsive Public Dashboard diuji;
20. dokumentasi operasional dan teknis tersedia.

---

# 100. Prinsip Keputusan Produk

Jika terdapat kebutuhan yang belum secara eksplisit tercantum dalam PRD, keputusan implementasi harus mengikuti urutan prioritas:

**Keakuratan Data → Keamanan & Privasi → Auditability → Kepatuhan Ruleset → Kemudahan Operasional → Maintainability → Performance → Visual Polish**

Sistem tidak boleh menghasilkan angka atau status yang terlihat resmi ketika dasar datanya belum valid atau aturan belum terverifikasi.

---

# 101. Definisi Produk Akhir

NADI ASN bukan aplikasi Excel yang dipindahkan ke web.

NADI ASN adalah **platform data kepegawaian internal DPMPTSP** yang memiliki satu model data terintegrasi, histori yang dapat ditelusuri, aturan yang versioned, perhitungan yang dapat dijelaskan, kontrol akses yang ketat, dan kemampuan mengubah data ASN menjadi informasi yang dapat ditindaklanjuti.

Target pengalaman akhirnya:

> **Admin membuka NADI ASN dan dalam satu pandangan mengetahui kondisi ASN, pekerjaan yang perlu segera diperhatikan, kualitas data yang perlu diperbaiki, serta laporan yang dapat dihasilkan dari sumber data yang sama.**

Sedangkan masyarakat dapat membuka NADI ASN tanpa login dan memperoleh statistik ASN DPMPTSP secara informatif tanpa mengorbankan privasi pegawai.

---

# 102. Status Setelah PRD

Dengan disetujuinya PRD ini, pekerjaan berikutnya adalah **Stage 1 — System Design**, yang harus menerjemahkan requirement produk ini menjadi:

- arsitektur sistem final;
- module boundaries;
- folder/project structure;
- ERD lengkap;
- tabel dan field;
- tipe data;
- PK/FK;
- indexes;
- constraints;
- enums/master references;
- permission matrix;
- critical-field matrix;
- ruleset model;
- workflow state machines;
- import architecture;
- audit architecture;
- snapshot architecture;
- API design;
- authentication/security design;
- backup architecture;
- Docker topology;
- deployment architecture.

Setelah System Design disetujui, dilanjutkan dengan:

**Stage 2 — UI/UX Blueprint**

kemudian:

**Stage 3 — Development Plan**

dan baru setelah rancangan tersebut disetujui:

**Implementation Module by Module.**