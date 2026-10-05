from pathlib import Path
import json, re, os
from xml.sax.saxutils import escape
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor, Color, white
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import Paragraph, Table, TableStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.utils import ImageReader
from PIL import Image

ROOT=Path(__file__).resolve().parents[3]; TMP=ROOT/'tmp/pdfs/nadi-handbook'; ASSETS=Path(__file__).resolve().parent/'assets'; OUT=ROOT/'output/pdf/Panduan-Lengkap-NADI-ASN.pdf'
TMP.mkdir(parents=True,exist_ok=True); OUT.parent.mkdir(parents=True,exist_ok=True)
font_candidates=[Path(os.environ.get('NADI_DOC_FONTS','/nonexistent')),Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/native/libreoffice-headless/libreoffice/LibreOfficeDev.app/Contents/Resources/fonts/truetype',Path('/usr/share/fonts/truetype/noto')]
FONT=next((p for p in font_candidates if (p/'NotoSans-Regular.ttf').exists()),None)
if not FONT:raise RuntimeError('Set NADI_DOC_FONTS to a font directory containing NotoSans-Regular.ttf and NotoSans-Bold.ttf')
for name,file in [('Body','NotoSans-Regular.ttf'),('Bold','NotoSans-Bold.ttf'),('Display','Rubik-Bold.ttf')]: pdfmetrics.registerFont(TTFont(name,str(FONT/(file if (FONT/file).exists() else 'NotoSans-Bold.ttf'))))
pdfmetrics.registerFontFamily('Body',normal='Body',bold='Bold',italic='Body',boldItalic='Bold')
NAVY=HexColor('#15324D'); TEAL=HexColor('#078B91'); SKY=HexColor('#EAF4F7'); INK=HexColor('#263E50'); MUTED=HexColor('#637B8D'); LINE=HexColor('#DCE7EC'); GOLD=HexColor('#C58A36'); PALE=HexColor('#FBF3E5')
W,H=595.276,841.89; M=46; CW=W-2*M; TOTAL=38
c=canvas.Canvas(str(OUT),pagesize=(W,H),pageCompression=1)
c.setTitle('Buku Panduan Penggunaan NADI ASN'); c.setAuthor('DPMPTSP Prov. Sulawesi Tengah'); c.setSubject('Panduan operasional publik, Admin Kepegawaian dan Super Admin. Edisi 5 Oktober 2026.')
PAGES=[]; y=0; num=0; section=''; checks=[]

def para(text,size=10.15,leading=15,color=INK,bold=False,width=None,x=M,gap=10):
 global y
 style=ParagraphStyle('p',fontName='Bold' if bold else 'Body',fontSize=size,leading=leading,textColor=color,spaceAfter=0)
 p=Paragraph(text,style);ww,hh=p.wrap(width or CW,1000);p.drawOn(c,x,y-hh);y-=hh+gap

def start(title,sec,tag=None,deck=''):
 global y,num,section
 if tag is None:
  tag='ADMIN KEPEGAWAIAN & SUPER ADMIN' if sec[:2] in {'03','04','05','07','08','09','13','15'} else 'SEMUA PENGGUNA'
 if num: end()
 num+=1;section=sec;PAGES.append((num,title));c.setFillColor(white);c.rect(0,0,W,H,fill=1,stroke=0)
 c.setFillColor(TEAL);c.rect(0,H-8,W,8,fill=1,stroke=0)
 c.setFont('Bold',8);c.setFillColor(NAVY);c.drawString(M,H-37,'NADI ASN  /  BUKU PANDUAN')
 c.setFont('Body',7.6);c.setFillColor(MUTED);c.drawRightString(W-M,H-37,sec.upper())
 c.setStrokeColor(LINE);c.line(M,H-48,W-M,H-48)
 c.bookmarkPage(f'p{num}');c.addOutlineEntry(title,f'p{num}',0)
 y=H-78;para(tag,8,10,TEAL,bold=True,gap=12)
 para(title,25,30,NAVY,bold=True,gap=11)
 if deck:para(deck,10.5,15.5,MUTED,gap=17)
 else:y-=6

def end():
 checks.append({'page':num,'bottom':round(y,1),'title':PAGES[-1][1]})
 if y<66:raise RuntimeError(f'Overflow page {num}: y={y:.1f} {PAGES[-1][1]}')
 c.setStrokeColor(LINE);c.line(M,48,W-M,48)
 c.setFont('Body',7.4);c.setFillColor(MUTED);c.drawString(M,32,'DPMPTSP Prov. Sulawesi Tengah')
 c.drawRightString(W-M,32,f'{num:02d} / {TOTAL:02d}')
 c.showPage()

def h(text):
 global y
 y-=4;para(text,12.5,17,NAVY,bold=True,gap=7)

def bullet(text): para('<font color="#078B91">•</font>  '+text,gap=8)

def steps(items):
 global y
 for i,(title,text) in enumerate(items,1):
  c.setFillColor(SKY);c.circle(M+10,y-10,10,fill=1,stroke=0);c.setFillColor(TEAL);c.setFont('Bold',9);c.drawCentredString(M+10,y-13,str(i))
  para('<b>'+title+'</b><br/>'+text,x=M+31,width=CW-31,gap=12)

def box(title,text,tone='teal'):
 global y
 bg=SKY if tone=='teal' else PALE;accent=TEAL if tone=='teal' else GOLD
 ps=ParagraphStyle('box',fontName='Body',fontSize=9.7,leading=14,textColor=INK)
 p=Paragraph('<b>'+title+'</b><br/>'+text,ps);_,ph=p.wrap(CW-30,1000);hh=ph+25
 c.setFillColor(bg);c.roundRect(M,y-hh,CW,hh,7,fill=1,stroke=0);c.setFillColor(accent);c.roundRect(M,y-hh,3,hh,1.5,fill=1,stroke=0)
 p.drawOn(c,M+15,y-13-ph);y-=hh+14

def table(headers,rows,widths=None,size=9.4):
 global y
 widths=widths or [CW/len(headers)]*len(headers)
 st=ParagraphStyle('cell',fontName='Body',fontSize=size,leading=size+4,textColor=INK)
 sh=ParagraphStyle('head',fontName='Bold',fontSize=size-0.2,leading=size+3,textColor=white)
 data=[[Paragraph(str(v),sh) for v in headers]]+[[Paragraph(str(v),st) for v in row] for row in rows]
 t=Table(data,colWidths=widths,hAlign='LEFT');t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),10),('RIGHTPADDING',(0,0),(-1,-1),10),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8),('ROWBACKGROUNDS',(0,1),(-1,-1),[HexColor('#F3F7F9'),white]),('LINEBELOW',(0,1),(-1,-1),0.4,LINE)]))
 tw,th=t.wrap(CW,1000);t.drawOn(c,M,y-th);y-=th+14

def shot(name,caption,height=260):
 global y
 path=next(p for p in [ASSETS/(name+'.png'),ASSETS/(name+'.jpg')] if p.exists());iw,ih=Image.open(path).size;dw=min(CW,height*iw/ih);dh=dw*ih/iw;x=M+(CW-dw)/2
 c.setStrokeColor(LINE);c.setFillColor(white);c.roundRect(x-3,y-dh-3,dw+6,dh+6,5,fill=1,stroke=1)
 c.drawImage(str(path),x,y-dh,width=dw,height=dh);y-=dh+11
 para(caption,8.2,11,MUTED,gap=14)

def flow(items):
 global y
 gap=12;bw=(CW-gap*(len(items)-1))/len(items);hh=76
 for i,(title,text) in enumerate(items):
  x=M+i*(bw+gap);c.setFillColor(SKY if i%2==0 else HexColor('#F2F5F8'));c.roundRect(x,y-hh,bw,hh,7,fill=1,stroke=0)
  p=Paragraph('<b>'+title+'</b><br/>'+text,ParagraphStyle('flow',fontName='Body',fontSize=9,leading=13,textColor=NAVY));_,ph=p.wrap(bw-18,1000);p.drawOn(c,x+9,y-12-ph)
  if i<len(items)-1:
   c.setStrokeColor(TEAL);c.line(x+bw+2,y-hh/2,x+bw+gap-2,y-hh/2)
 y-=hh+18

# 01 - cover
num=1;section='Panduan';PAGES.append((1,'Buku panduan penggunaan'));c.bookmarkPage('p1');c.addOutlineEntry('Buku panduan penggunaan','p1',0)
c.setFillColor(NAVY);c.rect(0,0,W,H,fill=1,stroke=0)
c.setFillColor(HexColor('#184860'));c.circle(W+15,150,244,fill=1,stroke=0)
c.setFillColor(HexColor('#116774'));c.circle(W+27,140,166,fill=1,stroke=0)
c.setStrokeColor(Color(1,1,1,alpha=.12))
for dy in [0,24,48,72]:
 p=c.beginPath();p.moveTo(-40,210+dy);p.curveTo(180,420+dy,290,20+dy,650,290+dy);c.drawPath(p)
c.setFillColor(white);c.roundRect(M,H-171,115,120,13,fill=1,stroke=0)
c.drawImage(str(ROOT/'public/logo-nadi-asn.png'),M+8,H-166,100,110,preserveAspectRatio=True,anchor='c',mask='auto')
c.setFont('Bold',9);c.setFillColor(HexColor('#7FD0D2'));c.drawString(M,594,'PANDUAN OPERASIONAL  /  EDISI 02')
c.setFont('Display',47);c.setFillColor(white);c.drawString(M,532,'NADI ASN')
c.setFont('Display',30);c.drawString(M,483,'Buku panduan')
c.drawString(M,444,'penggunaan')
y=408;para('Dari membaca statistik hingga mengelola<br/>data kepegawaian dengan tertib.',12.5,19,HexColor('#C4D9E3'),gap=20)
c.setStrokeColor(HexColor('#62BEC3'));c.line(M,325,M+72,325)
y=302;para('DPMPTSP Prov. Sulawesi Tengah',12,18,white,bold=True)
para('Publik  •  Admin Kepegawaian  •  Super Admin',10,15,HexColor('#C4D9E3'))
c.setFont('Body',9);c.setFillColor(white);c.drawString(M,106,'nadiasndpmptspsulteng.online');c.linkURL('https://nadiasndpmptspsulteng.online',(M,102,M+310,119),relative=0,thickness=0)
c.drawString(M,87,'EDISI 2.0  /  5 Oktober 2026')
c.setFont('Body',8);c.setFillColor(HexColor('#B5D2DD'));c.drawString(M,73,'Tutorial bertahap, contoh, dan pemecahan masalah')
y=68

start('Sebelum mulai','Orientasi',deck='Baca sesuai peran Anda. Simpan buku ini sebagai rujukan saat bekerja sehari-hari.')
h('Tentang buku ini')
para('Panduan ini menjelaskan perilaku NADI ASN yang sudah diimplementasikan pada rilis <b>f03ce2f</b>. Nama tombol mengacu pada antarmuka Bahasa Indonesia. Petunjuk KP/KGB menerangkan aturan aplikasi versi <b>2026-09-22.1</b> yang diberikan instansi, bukan pengganti keputusan atau pemeriksaan dokumen oleh pejabat berwenang.')
flow([('01  Temukan','Cari menu atau masalah melalui daftar isi.'),('02  Praktikkan','Ikuti langkah sesuai level akses akun.'),('03  Periksa','Pastikan hasil tersimpan dan statusnya sesuai.')])
h('Cara membaca ilustrasi')
para('Gambar layar dalam buku memakai <b>ruang simulasi dengan data sintetis</b>. Nama dan NIP pada ilustrasi internal adalah data buatan. Gambar statistik pengunjung memakai agregat produksi 5 Oktober tanpa identitas; angkanya adalah snapshot, bukan nilai tetap. Tata letak dapat sedikit berubah mengikuti ukuran browser. Tahapan akun, keamanan dan persetujuan dijelaskan berdasarkan backend data asli.')
box('Produksi aktif','Gunakan <b>https://nadiasndpmptspsulteng.online</b>. Domain HTTPS, email pemulihan, backup terenkripsi dan pemantauan aktif. VPS menjadi sumber data utama. Statistik pengunjung mulai dicatat 5 Oktober 2026; simulasi tetap terpisah.')
h('Tiga hasil yang perlu dibedakan')
bullet('<b>Draf:</b> perubahan yang masih Anda isi dan belum disimpan.')
bullet('<b>Usulan:</b> perubahan data pegawai yang menunggu keputusan Super Admin.')
bullet('<b>Data berlaku:</b> nilai yang sudah disetujui, atau perubahan yang memang disimpan langsung sesuai kewenangan fitur.')

start('Daftar isi','Navigasi buku',deck='Klik judul untuk menuju halaman. Panel bookmark PDF juga dapat digunakan.')
contents=[('Mengenal peran dan alur kerja', 4), ('Mulai cepat sesuai peran', 5), ('Portal publik dan statistik', 6), ('Statistik pengunjung dan privasi', 8), ('Aktivasi, login dan pemulihan', 10), ('Navigasi dan Command Center', 12), ('Master Pegawai dan kelengkapan data', 13), ('Masa kerja dan usia otomatis', 17), ('Persetujuan perubahan', 18), ('DUK Digital dan urutan manual', 19), ('Import Excel', 21), ('KP Radar dan KGB Radar', 23), ('Laporan & Export', 29), ('Manajemen akun', 30), ('Unit kerja dan pengaturan', 32), ('Audit Log dan keamanan kerja', 33), ('Pemecahan masalah', 34), ('Latihan dan rutinitas kerja', 36), ('Glosarium', 37), ('Catatan pengelola dan versi', 38)]
for title,page in contents:
 c.setFont('Body',10.2);c.setFillColor(INK);c.drawString(M+4,y-10,title);c.setFont('Bold',10.2);c.setFillColor(TEAL);c.drawRightString(W-M-4,y-10,f'{page:02d}');c.linkRect('',f'p{page}',(M,y-19,W-M,y+2),relative=0,thickness=0);c.setStrokeColor(LINE);c.line(M,y-21,W-M,y-21);y-=27

start('Siapa melakukan apa?','01 / Peran',deck='Ada dua jenis akun internal. Pengunjung publik tidak memerlukan akun.')
table(['Kegiatan','Publik','Admin','Super Admin'],[
['Melihat statistik agregat','Ya','Ya','Ya'],['Membaca data individu','Tidak','Ya','Ya'],['Mengajukan data baru/perubahan','Tidak','Ya','Ya'],['Mengimpor Excel sebagai usulan','Tidak','Ya','Ya'],['Menyetujui/menolak usulan','Tidak','Tidak','Ya'],['Mengedit isi sel DUK','Tidak','Usulan','Usulan'],['Mengatur urutan DUK','Tidak','Tidak','Ya'],['Memperbarui pemeriksaan Radar','Tidak','Ya','Ya'],['Ekspor data individu/laporan internal','Tidak','Tidak','Ya'],['Mengelola akun dan unit kerja','Tidak','Tidak','Ya'],['Membaca Audit Log','Tidak','Ya','Ya']],widths=[CW*.46,CW*.16,CW*.17,CW*.21],size=8.8)
box('Pelaporan kepada pimpinan','Tidak ada akun atau halaman khusus Pimpinan. Super Admin menyiapkan laporan PDF/Excel untuk disampaikan melalui saluran kerja yang ditetapkan instansi.')
para('Admin Kepegawaian boleh lebih dari satu orang. Hak akses melekat pada akun yang dibuat dari Master Pegawai, bukan pada pilihan tampilan atau perubahan URL.',9.8,14)

start('Mulai cepat','01 / Peran',deck='Urutan sederhana untuk penggunaan pertama dan pekerjaan rutin.')
h('Pengunjung publik')
steps([('Buka portal','Gunakan <b>https://nadiasndpmptspsulteng.online</b>. Tidak perlu menyalakan aplikasi lokal.'),('Pilih cakupan','Pilih unit kerja dan jenis pegawai, lalu klik <b>Terapkan</b>.'),('Baca dan bagikan','Periksa kartu/grafik, gunakan <b>Bagikan</b> atau <b>Unduh Ringkasan</b> bila diperlukan.')])
h('Admin Kepegawaian')
para('<b>Aktivasi akun  &gt;  login NIP + OTP  &gt;  Master Pegawai  &gt;  usulkan perubahan  &gt;  pantau Persetujuan.</b> Untuk KP/KGB, perbarui pemeriksaan Radar berdasarkan dokumen yang telah diperiksa. Gunakan Import Excel saat banyak baris perlu diperbarui.')
h('Super Admin')
para('<b>Login  &gt;  Command Center  &gt;  periksa usulan  &gt;  atur DUK  &gt;  buat laporan.</b> Siapkan akun Admin melalui Pengaturan, pelihara daftar unit kerja, dan periksa Audit Log bila ada perbedaan data.')
box('Tiga kebiasaan yang mencegah salah data','Periksa filter sebelum membaca jumlah. Tulis alasan perubahan yang spesifik. Setelah menyimpan, cek apakah hasilnya sudah berlaku atau masih berupa usulan.')

start('Membaca portal publik','02 / Portal publik',deck='Statistik kepegawaian dapat dilihat tanpa membuka identitas individu.')
shot('public','Gambar 1. Portal publik pada mode simulasi; angka adalah contoh.',235)
steps([('Pilih filter','Pilih unit kerja dan jenis pegawai pada panel filter.'),('Terapkan pilihan','Klik <b>Terapkan</b>. Periksa angka dan grafik sesuai cakupan terpilih.'),('Kembali atau bagikan','Klik <b>Reset</b> untuk menghapus filter, <b>Bagikan</b> untuk menyalin tautan filter, atau <b>Unduh Ringkasan</b> untuk PDF agregat.')])
box('Data yang tetap dilindungi','Portal publik tidak menampilkan nama, NIP, tanggal lahir, atau dokumen pegawai. Angka kelompok kecil tetap ditampilkan sesuai kebutuhan statistik instansi.')

start('Memahami angka & grafik','02 / Portal publik',deck='Cakupan filter harus sama sebelum dua angka atau laporan dibandingkan.')
flow([('PNS','Masuk Total ASN.'),('PPPK Penuh Waktu','Masuk Total ASN.'),('PPPK Paruh Waktu','Rekap terpisah dalam aplikasi.')])
para('<b>Dalam pelaporan NADI ASN:</b> Total ASN = PNS + PPPK Penuh Waktu. PPPK Paruh Waktu ditampilkan terpisah dan tidak dijumlahkan ke kartu Total ASN. Ini menjelaskan pengelompokan aplikasi, bukan penetapan status hukum kepegawaian.')
bullet('Jika filter PPPK Paruh Waktu dipilih, grafik menampilkan kelompok tersebut, sementara Total ASN dapat bernilai nol.')
bullet('Grafik pendidikan, gender, unit dan jabatan dibaca bersama filter aktif. Tabel statistik di bagian bawah membantu melihat angka rinci.')
h('Urutan unit kerja')
para('Kepala Dinas, Sekretaris, TIM 1 sampai TIM 7, kemudian tiga unit Sekretariat: Kepegawaian dan Umum; Keuangan dan Aset; Program. Unit tambahan berada setelah daftar tersebut menurut nama.')
box('Contoh membaca filter','Pilih TIM 2 dan PNS, lalu Terapkan. Angka yang muncul adalah cakupan TIM 2 untuk PNS, bukan seluruh pegawai instansi. Reset sebelum membandingkannya dengan keseluruhan organisasi.')
h('Jika angka terlihat berbeda')
para('Samakan unit, jenis pegawai, status dan waktu pengambilan data. Laporan internal rekap pegawai aktif berbeda cakupannya dari ekspor Master yang dapat memuat status lain.')

start('Melihat statistik pengunjung','02 / Portal publik',deck='Aktivitas website berbeda dari jumlah pegawai, dan tidak mengikuti filter unit kerja.')
shot('visitors','Gambar 2. Panel pengunjung di domain produksi pada 5 Oktober 2026. Angka berubah mengikuti aktivitas.',180)
steps([('Temukan panel','Gulir ke bagian bawah portal. Admin dan Super Admin juga melihat panel yang sama pada Command Center.'),('Baca waktu dan status','Lihat Diperbarui otomatis serta waktu WITA. Pembaruan tiap menit hanya berjalan ketika halaman terlihat.'),('Kenali angka online','Online adalah perkiraan browser aktif lima menit terakhir. Ketika tab tidak terlihat atau ditutup, aktivitas berhenti; angka berkurang setelah jendela tersebut terlewati.')])
box('Tanggal awal pencatatan','Produksi mulai mencatat 5 Oktober 2026. Angka kemarin dan periode sebelumnya dapat nol karena fitur belum aktif; sistem tidak mengarang riwayat sebelum pencatatan.')
para('Muat ulang atau membuka tab tambahan pada browser yang sama tidak menambah pengunjung harian. Browser yang sama akan dihitung lagi ketika tanggal WITA berganti. Detail ketujuh angka dijelaskan pada halaman berikut.',9.8,14)

start('Arti angka dan privasi pengunjung','02 / Portal publik',deck='Gunakan angka sebagai statistik aktivitas browser, bukan identitas orang.')
table(['Angka','Makna'],[['Sedang online','Browser aktif dalam lima menit terakhir, termasuk deduplikasi beberapa tab.'],['Hari ini','Browser unik pada tanggal hari ini menurut WITA.'],['Kemarin','Browser unik pada tanggal kemarin menurut WITA.'],['Minggu ini','Akumulasi kunjungan unik harian mulai Senin.'],['Bulan ini','Akumulasi harian mulai tanggal 1 bulan berjalan.'],['Tahun ini','Akumulasi harian mulai 1 Januari tahun berjalan.'],['Total kunjungan','Seluruh akumulasi harian sejak pencatatan dimulai.']],widths=[CW*.29,CW*.71],size=9.3)
box('Contoh membaca total','Satu browser membuka aplikasi pada Senin dan Selasa. Hitungan harian masing-masing bertambah satu; total bertambah dua. Angka total bukan jumlah orang unik sepanjang waktu.')
h('Apa yang dilindungi')
para('Cookie acak dienkripsi dan tidak dikaitkan dengan akun. Tabel penghitung tidak menyimpan nama, NIP, alamat IP, riwayat halaman atau identitas pegawai. Server membersihkan hash sementara dan mempertahankan angka agregat.')
h('Jika angka tampak berbeda atau tertunda')
para('Cookie terhapus, perangkat/browser lain atau cookie diblokir dapat menyebabkan hitungan baru. Online bersifat perkiraan; bot umum disaring tetapi tidak dijamin semua bot terdeteksi. Pembaruan tertunda menandakan koneksi/perubahan data belum berhasil: periksa jaringan dan muat ulang, tanpa menganggap angka lama masih real-time.',9.5,14)

start('Aktivasi dan login pertama','03 / Akses akun',tag='ADMIN KEPEGAWAIAN & SUPER ADMIN',deck='Kata sandi dan autentikator diatur sendiri oleh pemilik akun.')
steps([('Terima tautan pribadi','Admin menerima tautan dari Super Admin. Untuk akun pertama, pengelola aplikasi memberikan tautan/proses aktivasi yang sesuai. Jangan membagikan tautan ke grup umum.'),('Periksa identitas','Buka tautan, pastikan nama dan NIP milik Anda. Jika tidak sesuai, hentikan dan hubungi pemberi tautan.'),('Atur kata sandi','Isi kata sandi minimal 12 karakter dan ulangi dengan nilai yang sama. Gunakan kata sandi unik.'),('Hubungkan autentikator','Tambahkan kunci yang tampil ke aplikasi autentikator berbasis waktu. Simpan kunci di tempat aman; jangan memasukkannya ke email atau chat.'),('Konfirmasi aktivasi','Masukkan kode enam digit yang sedang berlaku, lalu klik <b>Aktifkan akun</b>.'),('Masuk ke ruang internal','Pada Login, isi <b>NIP 18 digit</b> dan kata sandi. Klik <b>Masuk ke ruang internal</b>, lalu isi kode OTP dan klik <b>Verifikasi & masuk</b>.')])
box('Email bukan identitas login','Login hanya menggunakan NIP. Email dipakai untuk pemulihan Super Admin; layanan produksi sudah aktif. Tautan Admin berlaku 24 jam dan sekali pakai; pemulihan Super Admin melalui email berlaku 30 menit.','amber')

start('OTP dan pemulihan akses','03 / Akses akun',deck='Pilih penanganan sesuai peran; reset Admin dan pemulihan Super Admin memiliki dampak berbeda.')
h('Jika kode OTP ditolak')
bullet('Pastikan tanggal, jam dan zona waktu ponsel mengikuti pengaturan otomatis.')
bullet('Pilih entri autentikator NADI ASN milik akun yang benar. Jika kode hampir berganti, tunggu kode berikutnya.')
bullet('Kode yang sudah berhasil dipakai tidak boleh digunakan ulang. Jika langkah verifikasi kedaluwarsa, mulai login dari NIP dan kata sandi.')
table(['Kondisi','Tindakan'],[
['Admin lupa kata sandi / OTP hilang','Hubungi Super Admin. Akun direset melalui Manajemen Akun dan pemilik melakukan aktivasi ulang.'],
['Super Admin lupa kata sandi / OTP hilang','Klik <b>Lupa kata sandi atau kehilangan OTP?</b>, pilih Super Admin, isi email pemulihan terdaftar, lalu kirim permintaan.'],
['Tautan tidak berlaku','Gunakan tautan terbaru dan pastikan belum kedaluwarsa. Admin meminta tautan baru kepada Super Admin.']
],widths=[CW*.35,CW*.65])
box('Pemulihan produksi sudah aktif','Periksa inbox dan spam pada email terdaftar. Buka tautan terbaru melalui domain HTTPS, buat kata sandi dan autentikator baru, lalu selesaikan aktivasi. Jika pengiriman gagal, hubungi pengelola; tidak perlu mengganti akun pengirim sendiri.')
para('Pada pemulihan Super Admin, permintaan email saja tidak menonaktifkan akun. Kata sandi, OTP dan sesi lama dibatalkan setelah aktivasi pemulihan berhasil. Hindari percobaan berulang cepat karena login dan pemulihan dibatasi.',9.7,14)

start('Ruang kerja internal','04 / Navigasi',deck='Mulai dari Command Center untuk melihat pekerjaan yang perlu ditindaklanjuti.')
shot('dashboard','Gambar 3. Command Center, menu samping dan ringkasan pada mode simulasi.',260)
steps([('Baca ringkasan','Periksa Total ASN, PNS, PPPK Penuh Waktu, PPPK Paruh Waktu dan kelengkapan. Kartu Paruh Waktu tetap terpisah.'),('Buka pekerjaan terkait','Gunakan <b>Perlu perhatian Anda</b>, akses cepat, atau menu samping untuk menuju data, usulan dan Radar.'),('Sesuaikan ruang layar','Tombol menu dapat meminimalkan sidebar menjadi ikon. Tombol bulan/matahari mengganti light/dark; light menjadi tampilan awal dan pilihan disimpan di browser.')])
para('Pencarian atas membantu menuju Master Pegawai. Pada layar kecil, buka/tutup menu melalui tombol navigasi. Gunakan tombol keluar setelah bekerja di perangkat bersama.',9.8,14)

start('Master Pegawai: cari & baca','05 / Master Pegawai',tag='ADMIN KEPEGAWAIAN & SUPER ADMIN',deck='Cari satu pegawai atau persempit daftar sebelum melakukan perubahan.')
shot('pegawai','Gambar 4. Master Pegawai pada mode simulasi. Geser tabel untuk kolom di sebelah kanan.',235)
steps([('Cari nama atau NIP','Gunakan kotak pencarian. Gabungkan dengan filter jenis pegawai, jenis jabatan, unit kerja dan status.'),('Atur jumlah baris','Pilih <b>10, 30, 50, All</b>. Perubahan filter/jumlah baris membawa Anda ke halaman pertama.'),('Buka profil','Klik nama pegawai atau tombol lihat. Periksa isian, riwayat, catatan sumber dan daftar data yang belum lengkap.')])
box('Ekspor oleh Super Admin','<b>Export Excel</b> mengambil seluruh hasil filter, bukan hanya halaman yang terlihat. Baris mengikuti urutan DUK tersimpan. Kosongkan filter yang tidak diperlukan sebelum mengekspor.')

start('Menambah & mengubah pegawai','05 / Master Pegawai',deck='Semua perubahan isi data diajukan terlebih dahulu untuk pemeriksaan.')
steps([('Pilih tindakan','Klik <b>Tambah pegawai</b> untuk data baru, atau buka profil lalu <b>Usulkan perubahan</b> untuk pegawai yang sudah ada.'),('Isi sesuai dokumen','Masukkan nama, NIP, tanggal, jabatan, jenis pegawai dan unit yang benar. Jangan membuat data pengganti hanya untuk menaikkan skor kelengkapan.'),('Periksa data tambahan','Atur status Aktif/Pensiun/Mutasi/Meninggal. Folder Google Drive dan tanggal kontrak bersifat tambahan; akhir kontrak tidak boleh sebelum mulai kontrak.'),('Tulis alasan','Jelaskan apa yang berubah dan sumber pemeriksaannya. Contoh: “Koreksi TMT jabatan sesuai SK yang telah diverifikasi.”'),('Ajukan untuk pemeriksaan','Klik <b>Ajukan untuk pemeriksaan</b>. Tunggu pemberitahuan keberhasilan dan cek usulannya di Persetujuan.'),('Pastikan hasil berlaku','Setelah disetujui Super Admin, buka kembali profil dan periksa data resmi serta riwayatnya.')])
box('Jangan menggandakan pegawai','Cari NIP terlebih dahulu sebelum menambah. NIP adalah teks 18 digit dan harus unik. Pemilihan unit menggunakan referensi yang sudah dikelola Super Admin.')

start('Mencapai kelengkapan 100%','05 / Master Pegawai',deck='Kelengkapan mengukur isian yang tersedia, bukan validitas hukum atau kebenaran dokumen.')
flow([('Temukan','Buka profil dan daftar isian yang kurang.'),('Lengkapi','Isi berdasarkan sumber yang benar.'),('Setujui','Usulan diperiksa Super Admin.'),('Periksa','Muat ulang persentase resmi.')])
steps([('Lihat rincian yang kurang','Klik indikator kelengkapan atau buka profil. Gunakan daftar tersebut sebagai daftar kerja.'),('Isi dan ajukan','Lengkapi isian relevan pada form. Persentase di form adalah pratinjau; klik Ajukan untuk pemeriksaan.'),('Selesaikan persetujuan','Super Admin dapat menyetujui usulan sendiri. Admin Kepegawaian menunggu keputusan Super Admin.'),('Muat ulang profil','Jika seluruh isian relevan terisi pada data yang disetujui, persentase resmi menjadi 100%.')])
table(['Aturan skor','Maknanya'],[
['Usia saat ini','Dihitung otomatis dan tidak menjadi isian wajib skor.'],
['PPPK Paruh Waktu','Pangkat/golongan serta masa kerja tahun/bulan dikecualikan dari skor.'],
['Nol, kosong, tanda minus','Angka 0 sah. Kosong atau “-” dibaca belum lengkap.'],
['Catatan sumber','Tetap menjadi jejak historis dan tidak otomatis mengurangi skor.']
],widths=[CW*.34,CW*.66],size=9)

start('Kamus isian pegawai','05 / Master Pegawai',deck='Format utama terdiri dari 18 kolom, termasuk usia otomatis. Template impor berisi 17 kolom isian.')
table(['Kelompok / isian','Cara mengisi atau membaca'],[
['Nama lengkap; NIP','Ikuti dokumen identitas kepegawaian. NIP disimpan sebagai teks 18 digit.'],
['Pangkat / golongan; TMT pangkat','Isi pangkat sesuai SK dan tanggal mulai berlakunya. Gunakan format pangkat/golongan yang dikenali aplikasi.'],
['Nama jabatan; TMT jabatan','Jabatan yang sedang berlaku dan tanggal mulai jabatan. Bukan tanggal penerbitan dokumen.'],
['Masa kerja tahun; masa kerja bulan','Masa kerja keseluruhan pada bulan pengisian. Bulan normal 0-11; lihat contoh di halaman 17.'],
['Nama pendidikan; tanggal lulus','Nama institusi/pendidikan dan tanggal kelulusan sesuai dokumen sumber.'],
['Tingkat ijazah','Pilih tingkat pendidikan formal yang telah diperiksa.'],
['Tempat lahir; tanggal lahir','Tanggal lahir tidak boleh berada di masa depan.'],
['Usia saat ini','Otomatis dari tanggal lahir; tidak diedit manual.'],
['Jenis kelamin; jenis pegawai','Pilih kategori yang tersedia pada aplikasi.'],
['Jenis jabatan; unit kerja organisasi','Pilih Struktural/Fungsional/Pelaksana serta unit yang terdaftar.']
],widths=[CW*.41,CW*.59],size=9)
box('Data pendukung di luar 18 kolom','Status, folder Google Drive, serta tanggal mulai/akhir kontrak disediakan di form. Tautan dokumen berupa folder Google Drive HTTPS; akses folder tetap ditentukan oleh pemilik dokumen.')

start('Masa kerja & usia otomatis','05 / Master Pegawai',deck='Tidak perlu memperbarui setiap pegawai secara manual setiap pergantian bulan atau tahun.')
h('Masa kerja keseluruhan')
para('Data awal masa kerja beracuan <b>September 2026</b>. Sistem menambahkan selisih bulan saat data dibuka atau diunduh. Jika Anda memperbaiki masa kerja sekarang, isikan jumlah yang benar untuk bulan pengisian tersebut.')
flow([('Sep 2026','13 tahun 1 bulan'),('Okt 2026','13 tahun 2 bulan'),('Jul 2029','15 tahun 11 bulan')])
box('Bedakan masa kerja dan TMT pangkat','Kolom masa kerja pada daftar adalah masa kerja keseluruhan sejak pengangkatan ASN sesuai sumber. Pemeriksaan masa dalam pangkat untuk KP memakai <b>TMT pangkat</b>. Mengubah salah satunya tidak berarti yang lain ikut benar.','amber')
h('Usia mengikuti ulang tahun')
para('Usia dihitung dari tanggal lahir dan bertambah ketika ulang tahun terlewati, bukan serentak pada 1 Januari. Jika tanggal lahir kosong, usia juga kosong. Perbaiki tanggal lahir melalui usulan bila hasil usia tidak sesuai.')
h('Saat angka belum terlihat berubah')
steps([('Muat ulang halaman','Halaman yang terbuka melewati pergantian bulan/tanggal perlu dimuat ulang.'),('Periksa sumber','Pastikan tahun/bulan masa kerja serta tanggal lahir terisi benar. Catatan sumber dengan bulan masa kerja di luar 0-11 perlu diperiksa berdasarkan dokumen.'),('Bandingkan keluaran baru','Buat ulang laporan; file lama di perangkat tidak diperbarui otomatis.')])

start('Memeriksa & memutus usulan','06 / Persetujuan',tag='SUPER ADMIN MEMUTUSKAN',deck='Usulan dapat berasal dari Master Pegawai, edit isi DUK, atau Import Excel.')
flow([('Menunggu','Nilai resmi belum berubah.'),('Disetujui','Perubahan diterapkan dan dicatat.'),('Ditolak','Nilai resmi tetap seperti sebelumnya.')])
steps([('Buka antrean','Masuk ke <b>Persetujuan  &gt;  Menunggu pemeriksaan</b>. Pilih <b>Periksa perubahan</b> pada usulan yang akan ditinjau.'),('Bandingkan nilai','Periksa nilai sebelumnya, nilai yang diajukan, identitas pengaju dan alasan. Cocokkan dengan dokumen pendukung.'),('Tulis catatan keputusan','Isi alasan yang dapat ditelusuri. Jika menolak, jelaskan isian atau bukti yang harus diperbaiki.'),('Putuskan','Pilih setuju atau tolak sesuai hasil pemeriksaan. Tunggu konfirmasi; jangan menekan tombol berulang.'),('Verifikasi hasil','Lihat tab Disetujui/Ditolak. Untuk usulan yang disetujui, cek kembali Master atau DUK.')])
box('Super Admin boleh menyetujui usulan sendiri','Tidak wajib menggunakan akun kedua. Catatan keputusan dan audit tetap harus jelas. Admin Kepegawaian dapat memantau usulannya tetapi tidak dapat memutuskan persetujuan.')
para('Jika muncul konflik revisi, data telah berubah sejak usulan disusun. Muat ulang, bandingkan data terbaru, lalu siapkan usulan yang sesuai. Usulan yang ditolak diperbaiki melalui pengajuan baru.',9.8,14)

start('DUK Digital: edit isi sel','07 / DUK Digital',tag='ADMIN KEPEGAWAIAN & SUPER ADMIN',deck='Lembar DUK dapat diedit langsung seperti tabel spreadsheet.')
shot('duk-edit','Gambar 5. Mode Edit DUK pada simulasi; panah urutan hanya tersedia bagi Super Admin.',225)
steps([('Pilih cakupan lalu Edit DUK','Gunakan pencarian serta filter jenis pegawai, jenis jabatan dan unit. Tentukan 10/30/50/All bila diperlukan.'),('Ubah sel','Edit isian yang diperlukan. Gunakan Tab untuk berpindah. Sel yang berubah diberi penanda; usia tetap dihitung otomatis.'),('Isi alasan dan simpan','Tulis alasan penyimpanan, lalu klik <b>Simpan DUK</b>. Perubahan isi data menjadi usulan yang diperiksa di Persetujuan.')])
box('Draf belum tersimpan','<b>Batal edit</b> atau keluar saat ada perubahan akan meminta konfirmasi. Jika penyimpanan gagal karena konflik, muat ulang dan susun kembali draf; jangan menganggap sebagian baris sudah diterapkan.','amber')

start('DUK: mengatur urutan manual','07 / DUK Digital',tag='KHUSUS SUPER ADMIN',deck='Urutan awal mengikuti daftar sumber; sesudahnya ditentukan secara manual oleh Super Admin.')
steps([('Masuk ke Edit DUK','Buka DUK Digital, lalu klik Edit DUK.'),('Naikkan atau turunkan','Gunakan panah pada kolom <b>Atur urutan</b>. Panah menukar posisi dengan pegawai sebelumnya/berikutnya dalam hasil filter, termasuk lintas halaman.'),('Isi alasan dan Simpan DUK','Urutan langsung berlaku setelah penyimpanan berhasil dan dicatat pada Audit Log. Perubahan isi sel yang ikut disimpan tetap berupa usulan.')])
h('Contoh pemindahan dalam hasil filter')
table(['Posisi global','Sebelum','Sesudah C dinaikkan'],[['1','Pegawai A (unit X)','Pegawai C (unit X)'],['2','Pegawai B (unit Y)','Pegawai B (unit Y)'],['3','Pegawai C (unit X)','Pegawai A (unit X)']],widths=[100,200,CW-300])
para('Contoh ini memakai filter unit X. A dan C bertukar tempat, sedangkan pegawai B di luar filter tetap di tempatnya. Nomor DUK dapat melompat ketika daftar difilter.')
box('Tidak memakai hierarki otomatis','Pangkat, jabatan, pendidikan dan usia tidak otomatis menyusun ulang DUK. Pegawai baru berada setelah daftar yang sudah ada sampai diatur. Master dan ekspor Excel memakai urutan tersimpan yang sama.')

start('Import Excel: siapkan berkas','08 / Import Excel',tag='ADMIN KEPEGAWAIAN & SUPER ADMIN',deck='Gunakan template dari aplikasi agar nama kolom dan format lebih mudah dicocokkan.')
shot('import','Gambar 6. Halaman Import Excel pada mode simulasi.',200)
steps([('Unduh template','Buka <b>Import Excel</b> dan unduh template terbaru. Template memiliki 17 kolom isian; usia dihitung otomatis.'),('Siapkan satu worksheet','Isi data sebagai nilai biasa. NIP harus teks 18 digit; jangan memakai format angka atau notasi ilmiah. Tanggal memakai YYYY-MM-DD atau sel tanggal Excel.'),('Periksa referensi','Nama unit harus sudah terdaftar. Isikan jenis pegawai dan jenis jabatan sesuai pilihan aplikasi. Masa kerja mengikuti bulan impor.')])
box('Batas berkas data asli','Hanya XLSX, maksimum 5 MiB, satu worksheet, 1.000 baris data dan 50 kolom. Formula, makro, tautan eksternal aktif, objek tertanam dan arsip berbahaya ditolak. Antivirus harus tersedia sebelum berkas diproses.','amber')

start('Import Excel: validasi & ajukan','08 / Import Excel',deck='Unggahan tidak langsung mengganti Master Pegawai.')
flow([('Unggah','Pilih XLSX.'),('Mapping','Cocokkan kolom.'),('Validasi','Baca preview.'),('Konfirmasi','Buat usulan.')])
steps([('Unggah berkas','Pilih berkas yang telah diperiksa. Jika antivirus/validasi menolak, perbaiki berkas atau hubungi pengelola.'),('Cocokkan kolom','Pilih kolom sumber untuk setiap field. Mapping NIP wajib; satu kolom tidak boleh dipakai untuk beberapa field.'),('Baca preview','Periksa setiap baris: kecocokan pegawai, perubahan dan kesalahan. NIP sama mengarah ke pembaruan pegawai yang ada; NIP baru menjadi usulan pegawai baru.'),('Konfirmasi baris valid','Isi alasan, baca pernyataan konfirmasi, lalu ajukan. Periksa peringatan unggahan duplikat jika muncul. Baris tidak valid perlu diperbaiki dan diunggah kembali.'),('Pantau persetujuan','Buka Persetujuan. Data baru berlaku setelah disetujui. Pada data asli, riwayat pemeriksaan berkas dapat digunakan untuk melanjutkan batch.')])
box('Sel kosong bukan perintah menghapus','Pada impor pembaruan, sel kosong tidak menghapus nilai lama. Gunakan form pegawai atau edit DUK bila perlu mengosongkan isian opsional. Duplikasi NIP dalam satu berkas dan NIP yang tersimpan numerik ditolak.','amber')

start('Mengenal Radar kepegawaian','09 / KP & KGB Radar',deck='Radar hanya menampilkan PNS aktif. Kesiapan administrasi dan status proses dibaca terpisah.')
shot('kp','Gambar 7. KP Radar pada simulasi. Hasilnya bukan keputusan kenaikan pangkat.',215)
table(['Status kesiapan','Makna'],[['Data belum lengkap','Data, referensi atau verifikasi yang wajib belum tersedia.'],['Belum memenuhi','Ada syarat yang terbukti tidak terpenuhi. Periksa juga kekurangan lain pada catatan.'],['Siap diusulkan','Seluruh pemeriksaan yang relevan telah terpenuhi.']],widths=[160,CW-160],size=9.1)
para('Cari nama/NIP, gunakan filter <b>kesiapan</b> dan <b>proses</b>, lalu buka catatan hasil pemeriksaan. Jumlah baris tersedia 10/30/50/All. Klik <b>Perbarui</b> untuk melengkapi dokumen dan hasil verifikasi.')
box('Perubahan Radar disimpan langsung','Admin dan Super Admin dapat menekan <b>Simpan pemeriksaan</b>. Perubahannya dicatat pada audit, tanpa antrean persetujuan Master. Koreksi pangkat/jabatan sumber tetap melalui Master dan Persetujuan.')

start('KP Radar: jabatan Pelaksana','09 / KP & KGB Radar',deck='Periksa masa dalam pangkat, kinerja, ijazah dan persyaratan yang relevan.')
steps([('Periksa Master','Jenis jabatan Pelaksana, TMT pangkat, golongan dan pendidikan harus sesuai dokumen.'),('Tentukan target','Gunakan TMT target tanggal 1. Target harus memenuhi empat tahun dalam pangkat. Perkiraan otomatis mempertimbangkan periode dan tenggat.'),('Lengkapi pemeriksaan','Isi tahun/predikat kinerja dua tahun sebelum tahun target, minimal Baik. Verifikasi ijazah, ujian dinas/pembebasan jika relevan, serta kelengkapan dokumen.'),('Simpan dan baca hasil','Isi referensi dokumen, status proses dan alasan, lalu Simpan pemeriksaan. Buka catatan untuk syarat yang masih gagal/belum diperiksa.')])
h('Batas pendidikan yang digunakan aplikasi')
table(['Pendidikan pada Master','Pangkat maksimum pemeriksaan'],[['SD','II/a'],['SMP','II/c'],['SMA/SMK, D1, D2','III/b'],['D3','III/c'],['S1, D4','III/d'],['S2','IV/a'],['S3','IV/b']],widths=[CW*.52,CW*.48],size=8.8)
para('Tabel ini menjelaskan konfigurasi aplikasi. Pangkat atasan tidak menjadi batas KP reguler dalam perhitungan ini. Pada lintas II/d atau III/d, periksa ujian dinas atau pembebasan yang sah.',9.3,13.5)

start('KP Radar: jabatan Fungsional','09 / KP & KGB Radar',deck='AK pangkat dan AK kumulatif naik jenjang adalah dua isian yang berbeda.')
bullet('Masa dalam pangkat pada TMT target minimal dua tahun; kinerja dua tahun sebelum tahun target minimal Baik.')
bullet('Isi <b>AK untuk naik pangkat (PAK terakhir)</b>, <b>AK kumulatif untuk naik jenjang</b>, serta nomor/tanggal PAK yang sah.')
bullet('Jenjang dibaca dari Nama Jabatan di Master: Ahli Pertama, Muda, Madya atau Utama. Jika belum dikenali/tidak sesuai, perbaiki sumber melalui Master berdasarkan dokumen.')
table(['Golongan terakhir','AK pangkat yang diperiksa'],[['III/a - III/b','50'],['III/c - III/d','100'],['IV/a - IV/c','150'],['IV/d - IV/e','200']],widths=[CW*.56,CW*.44])
h('Pemeriksaan tambahan saat pindah jenjang')
table(['Peralihan','Jenjang tujuan','AK kumulatif'],[['III/b ke III/c','Ahli Muda','100'],['III/d ke IV/a','Ahli Madya','200'],['IV/c ke IV/d','Ahli Utama','450']],widths=[175,190,CW-365],size=9)
box('Memenuhi angka saja belum cukup','Jenjang harus sesuai pangkat tujuan, PAK tersedia, persyaratan fungsional diverifikasi dan dokumen lengkap. Sistem juga memeriksa ketersediaan pangkat berikutnya. Jangan mengubah Nama Jabatan hanya untuk melewati pemeriksaan.','amber')

start('KP Struktural & tenggat berkas','09 / KP & KGB Radar',deck='Jalur struktural memerlukan penetapan target dan pemeriksaan oleh pengelola.')
h('Jabatan Struktural')
steps([('Tentukan TMT target','Isi tanggal 1 setelah memeriksa rentang pangkat, jalur struktural dan riwayat pelantikan. Sistem tidak menerapkan rumus empat tahun Pelaksana pada jalur ini.'),('Catat dasar pemeriksaan','Isi riwayat pelantikan/dasar jalur struktural, kinerja dua tahun minimal Baik, referensi dokumen serta hasil verifikasi.'),('Simpan hasil','Lengkapi status proses dan alasan. Periksa kembali catatan Radar setelah Simpan pemeriksaan.')])
h('Contoh periode target 1 Januari 2027')
flow([('16 Nov 2026','Awal baseline berkas.'),('15 Des 2026','Akhir baseline berkas.'),('1 Jan 2027','TMT target KP.')])
para('Untuk target pada bulan M, baseline berkas adalah tanggal <b>16 bulan M-2 sampai 15 bulan M-1</b>. Jika ada jadwal khusus, isi <b>Tenggat khusus berkas</b> beserta <b>Surat layanan acuan tenggat</b>. Tenggat harus sebelum TMT target.')
box('Target tersimpan tidak bergeser diam-diam','Setelah pemeriksaan disimpan, target tetap sampai diubah pengelola. Saat target berubah, periksa kembali tahun kinerja, tenggat dan dokumennya. Pengingat muncul di aplikasi; tidak ada pengiriman email/WhatsApp otomatis.')

start('KGB Radar: acuan TMT terakhir','09 / KP & KGB Radar',deck='Pisahkan tanggal penerbitan SK dengan tanggal mulai berlakunya KGB.')
shot('kgb','Gambar 8. KGB Radar pada mode simulasi.',195)
steps([('Isi dua tanggal dari SK','Klik Perbarui. Isi <b>Tanggal penerbitan SK KGB terakhir</b> dan <b>TMT KGB terakhir pada SK</b> pada kolom yang berbeda.'),('Lengkapi penilaian','Isi kinerja dua tahun sebelum tahun TMT target, minimal Cukup; masukkan referensi dokumen dan hasil verifikasi.'),('Simpan pemeriksaan','Lengkapi status proses/catatan dan alasan. Sistem menghitung perkiraan dari <b>TMT terakhir + 2 tahun</b>.')])
box('Contoh perhitungan','SK terbit 15 Agustus 2024, TMT 1 September 2024  &gt;  perkiraan 1 September 2026. Pengingat mulai 1 Juli 2026. Sebelum dua tahun tercapai, syarat waktu masih Belum memenuhi.')
para('AK dan jenjang fungsional tidak digunakan untuk KGB. Jangan mengganti tanggal SK/TMT terakhir dengan tanggal perkiraan apabila SK baru belum diterima.',9.7,14)

start('Menindaklanjuti hasil Radar','09 / KP & KGB Radar',deck='Status proses mencatat pekerjaan yang dilakukan setelah hasil pemeriksaan dibaca.')
table(['Status proses','Yang dicatat/dilakukan'],[['Belum diusulkan','Persiapan data dan dokumen; belum ada pengajuan.'],['Diusulkan','Catat nomor/tanggal usulan dan perkembangan berkas.'],['Perbaikan','Catat kekurangan dan tenggat perbaikan sesuai surat.'],['Keputusan terbit','Catat nomor/tanggal keputusan; pengingat catatan ini berhenti.'],['Ditolak','Catat dasar/nomor keputusan; pengingat catatan ini berhenti.']],widths=[150,CW-150])
h('Sesudah SK atau keputusan baru diterima')
steps([('Perbarui sumber','Untuk KP, ajukan perubahan pangkat/TMT di Master dan selesaikan persetujuannya. Untuk KGB, masukkan SK/TMT terakhir yang benar pada pemeriksaan berikutnya.'),('Periksa siklus baru','Perbarui kinerja, PAK bila relevan, referensi dokumen dan target. Jangan menggunakan verifikasi lama tanpa memeriksa ulang.'),('Atur status proses','Pilih status sesuai kondisi siklus baru dan tulis alasan yang jelas.')])
box('Keputusan terbit tidak mengubah Master otomatis','Radar adalah alat pemeriksaan dan pemantauan. Perubahan Master setelah pemeriksaan membuat Radar memerlukan verifikasi ulang. Jika revisi berbenturan dengan pengelola lain, muat ulang sebelum mengedit lagi.','amber')

start('Membuat laporan & ekspor','10 / Laporan',tag='KHUSUS SUPER ADMIN',deck='Pilih jenis keluaran sesuai penerima dan tujuan penggunaan.')
shot('laporan','Gambar 9. Laporan & Export pada simulasi; riwayat server tersedia pada data asli.',215)
steps([('Pilih laporan','<b>Rekap pegawai aktif</b> menghasilkan Excel untuk kebutuhan internal. <b>Ringkasan statistik</b> menghasilkan PDF agregat tanpa identitas individu.'),('Tentukan unit','Pilih unit kerja atau seluruh unit kerja, lalu klik <b>Buat & unduh laporan</b>.'),('Periksa hasil','Pastikan cakupan/unit benar. Urutan Excel mengikuti DUK. Kategori Unit kerja berada di bagian atas tabel statistik PDF.')])
box('Unduh kembali','Pada data asli, buka <b>Riwayat laporan Anda</b> dan klik Perbarui. Berkas Siap diunduh tersedia selama 7 hari. Jika kedaluwarsa, buat laporan baru. File lama tidak berubah saat data Master diperbarui.')
para('Ekspor Master/DUK mengikuti seluruh hasil filter, bukan hanya halaman tabel. Hasil laporan baru tidak mencantumkan kredit developer. Kirim data individu hanya kepada pihak yang berwenang.',9.6,14)

start('Membuat akun Admin','11 / Manajemen akun',tag='KHUSUS SUPER ADMIN',deck='Satu akun untuk satu pemilik. Admin Kepegawaian dapat lebih dari satu orang.')
flow([('Pilih pegawai','Dari Master aktif.'),('Buat tautan','Peran Admin.'),('Aktivasi mandiri','Password + OTP.'),('Akun aktif','Login dengan NIP.')])
steps([('Buka Manajemen Akun','Masuk ke <b>Pengaturan  &gt;  Manajemen Akun</b>.'),('Cari dan pilih pegawai','Gunakan Cari pegawai, lalu Pilih pegawai. Daftar berasal dari Master yang aktif; pegawai yang sudah memiliki akun tidak ditawarkan kembali.'),('Buat akun','Level akses ditetapkan Admin Kepegawaian. Klik <b>Buat akun & tautan</b>.'),('Salin secara pribadi','Klik Salin tautan sebelum meninggalkan halaman. Tautan berlaku 24 jam dan sekali pakai; tautan tidak dapat dilihat ulang dari database.'),('Pemilik menyelesaikan aktivasi','Pegawai menetapkan password dan autentikator sendiri. Pantau status akun hingga Aktif; ulangi untuk Admin tambahan.')])
box('Yang tidak dilakukan melalui menu ini','Menu ini tidak membuat akun Super Admin baru dan melindungi akun Super Admin dari penonaktifan. Identitas masuk akun memakai NIP saat akun dibuat. Jika ada koreksi NIP, koordinasikan perubahan identitas akun dengan pengelola.')

start('Status, reset & penonaktifan akun','11 / Manajemen akun',tag='KHUSUS SUPER ADMIN',deck='Pastikan identitas pemohon sebelum memberikan tautan baru.')
table(['Status akun','Arti dan tindak lanjut'],[['Aktif','Pemilik dapat masuk dengan password dan OTP.'],['Menunggu aktivasi','Tautan telah dibuat; pemilik belum selesai mengaktifkan akun.'],['Tautan kedaluwarsa','Buat tautan aktivasi baru setelah memverifikasi pemiliknya.'],['Nonaktif','Akses ditutup. Data pegawai tetap tersimpan.']],widths=[160,CW-160])
h('Jika Admin lupa password atau kehilangan OTP')
steps([('Pilih akun yang benar','Cari akun pada Manajemen Akun dan pastikan NIP/identitasnya.'),('Reset / buat tautan','Pilih <b>Reset kata sandi / Buat tautan aktivasi</b>, baca dialog dampak, lalu lanjutkan jika sesuai.'),('Bagikan tautan baru','Pemilik mengatur ulang password dan autentikator. Tautan lama tidak berlaku.')])
box('Dampak reset Admin segera berlaku','Password, autentikator, sesi dan tautan lama dibatalkan. Pemilik tidak dapat login sampai aktivasi ulang selesai. Reset Admin berbeda dari permintaan email pemulihan Super Admin.','amber')
para('<b>Nonaktifkan</b> segera menutup akses dan membatalkan tautan, tanpa menghapus pegawai atau riwayat. Untuk memberi akses kembali, buat tautan aktivasi baru pada akun yang sama.')

start('Unit kerja & pengaturan','12 / Pengaturan',tag='KHUSUS SUPER ADMIN',deck='Perubahan referensi unit perlu mengikuti struktur organisasi yang sudah ditetapkan.')
steps([('Tambah unit kerja','Buka <b>Pengaturan  &gt;  Unit kerja</b>, masukkan nama unit baru dan simpan. Hindari nama duplikat yang hanya berbeda ejaan.'),('Ubah nama unit','Pilih <b>Ubah nama</b>, isi nama yang benar dan simpan. Nama terbaru digunakan pada tabel serta filter.'),('Nonaktifkan unit kosong','Unit yang masih memiliki pegawai tidak dapat langsung dinonaktifkan. Ajukan pemindahan pegawai, selesaikan persetujuan, lalu coba nonaktifkan unit. Jika histori OFFICIAL masih menghalangi, hubungi pengelola dan pertahankan histori.')])
h('Tab lain yang tersedia')
table(['Tab','Kegunaan'],[['Ruleset & kebijakan','Melihat versi dan status aturan DUK/KP/KGB yang diterapkan. Bukan editor rumus bebas.'],['Akses & keamanan','Membaca pembagian peran dan kewenangan.'],['Informasi sistem','Melihat identitas aplikasi, zona waktu dan lingkungan.']],widths=[CW*.37,CW*.63])
box('Urutan unit bukan urutan DUK','Urutan unit pada grafik/dropdown/laporan mengikuti daftar organisasi. Urutan pegawai di DUK tetap diatur manual. Mengganti nama unit tidak berarti otomatis menaikkan atau menurunkan posisi pegawai.')
para('Jika kebijakan KP/KGB berubah, koordinasikan pembaruan aturan kepada pengelola aplikasi beserta dokumen acuan dan contoh kasus. Jangan mencoba menyiasati hasil dengan mengubah data sumber yang sudah benar.',9.7,14)

start('Audit Log & kebiasaan aman','13 / Audit dan keamanan',deck='Alasan yang jelas membuat perubahan dapat ditelusuri kembali.')
steps([('Buka Audit Log','Admin dan Super Admin dapat melihat jejak aktivitas.'),('Cari peristiwa','Gunakan pencarian pelaku atau aktivitas. Baca waktu, pelaku, aktivitas, objek dan ID peristiwa.'),('Cocokkan dengan pekerjaan','Bandingkan peristiwa dengan usulan, pegawai atau proses yang dipermasalahkan. Catat ID peristiwa saat meminta bantuan.')])
h('Rutinitas keamanan pengguna')
bullet('Jangan berbagi akun, password, kunci autentikator, kode OTP atau tautan aktivasi. Setiap pengelola menggunakan akunnya sendiri.')
bullet('Gunakan dokumen pendukung yang sah. Periksa penerima sebelum membagikan laporan berisi identitas individu.')
bullet('Tautan folder Drive tidak otomatis memberikan akses folder kepada semua orang. Kelola izin dokumen pada layanan penyimpanannya.')
bullet('Akhiri sesi melalui tombol keluar di perangkat bersama. Sesi dibatasi idle sekitar 30 menit dan maksimum 8 jam; heartbeat pengunjung tidak memperpanjangnya. Reset/penonaktifan mencabut sesi.')
box('Minta bantuan tanpa membocorkan data','Sampaikan nama menu, waktu kejadian, langkah yang dilakukan, pesan kesalahan dan ID peristiwa/batch jika ada. Hindari screenshot yang menampilkan seluruh NIP atau dokumen pribadi jika tidak diperlukan.')

start('Kendala akses & data','14 / Pemecahan masalah',deck='Mulai dari pemeriksaan yang aman sebelum meminta bantuan pengelola.')
table(['Gejala','Periksa / lakukan'],[
['NIP/password tidak valid','Gunakan NIP 18 digit, bukan email. Pastikan akun aktif dan password benar. Admin meminta reset kepada Super Admin bila lupa.'],
['OTP selalu ditolak','Sinkronkan jam ponsel, pilih entri akun yang benar, tunggu kode baru. Ulangi login jika challenge sudah kedaluwarsa.'],
['Terlalu banyak percobaan','Hentikan percobaan berulang. Tunggu hingga pembatasan berakhir, lalu coba data yang benar.'],
['Email pemulihan tidak terkirim','Periksa email terdaftar dan folder spam. Gunakan tautan terbaru; minta pengelola memeriksa SMTP bila pengiriman gagal.'],
['Menu tidak terlihat','Periksa peran login. Pengaturan, laporan internal dan ekspor data individu hanya untuk Super Admin.'],
['Kelengkapan belum 100%','Lihat isian yang kurang, pastikan usulan sudah disetujui, lalu muat ulang. Pratinjau form bukan persentase resmi.'],
['Angka/pegawai tampak hilang','Periksa pencarian, semua filter, status, halaman dan jumlah baris. Reset filter sebelum menambah data baru.'],
['Konflik revisi','Muat ulang data, baca perubahan terbaru, lalu susun ulang usulan. Jangan menimpa data tanpa pemeriksaan.']
],widths=[CW*.34,CW*.66],size=9.1)

start('Kendala impor, Radar & laporan','14 / Pemecahan masalah',deck='Jangan memperbaiki hasil dengan mengganti data yang sebenarnya sudah benar.')
table(['Gejala','Periksa / lakukan'],[
['XLSX ditolak','Gunakan template terbaru, satu worksheet, maksimum 5 MiB/1.000 baris. Hapus formula dan konten aktif. Jangan menonaktifkan antivirus.'],
['Pemindai tidak tersedia','Unggahan sengaja ditolak agar aman. Hubungi pengelola untuk memulihkan layanan antivirus, kemudian unggah ulang.'],
['NIP numerik/duplikat','Ubah format NIP menjadi teks sejak sumber dan pastikan semua 18 digit masih benar. Hilangkan baris duplikat berdasarkan dokumen.'],
['Unit tidak dikenal','Minta Super Admin menambahkan atau membetulkan referensi unit, lalu perbaiki mapping/nama pada berkas.'],
['Pegawai tidak muncul di Radar','Periksa jenis PNS dan status Aktif. PPPK tidak masuk KP/KGB Radar versi ini.'],
['Radar Data belum lengkap','Buka rincian syarat. Lengkapi tahun kinerja, tanggal sumber, referensi dan verifikasi yang diminta.'],
['Radar Belum memenuhi','Baca syarat yang gagal; jangan sekadar memilih Memenuhi. Target KGB yang belum tercapai atau masa pangkat yang kurang dapat menjadi penyebab.'],
['Laporan mengantre/gagal','Klik Perbarui pada riwayat. Jika berlarut, hubungi pengelola untuk memeriksa worker. Laporan kedaluwarsa dibuat ulang.'],
['Urutan Excel tidak sesuai harapan','Periksa urutan DUK yang terakhir tersimpan dan filter ekspor. Ekspor mengambil semua hasil filter, bukan hanya baris pada layar.']
],widths=[CW*.34,CW*.66],size=8.9)

start('Latihan & rutinitas kerja','15 / Praktik',deck='Lakukan latihan menggunakan ruang simulasi atau data latihan yang disediakan pengelola.')
h('Latihan 1: satu data belum lengkap')
para('Cari pegawai contoh  &gt;  buka daftar isian kurang  &gt;  usulkan pelengkapan berdasarkan dokumen latihan  &gt;  periksa sebagai Super Admin  &gt;  setujui  &gt;  buka ulang profil. <b>Hasil yang diharapkan:</b> nilai dan persentase resmi berubah setelah persetujuan.')
h('Latihan 2: mengubah urutan DUK')
para('Pilih dua pegawai contoh  &gt;  Edit DUK  &gt;  naikkan/turunkan satu posisi  &gt;  isi alasan  &gt;  Simpan DUK  &gt;  ekspor Excel. <b>Hasil yang diharapkan:</b> urutan tabel dan Excel sama; isi data yang turut diedit tetap menunggu persetujuan.')
h('Latihan 3: memeriksa KGB')
para('Gunakan SK latihan dengan tanggal terbit 15 Agustus 2024 dan TMT 1 September 2024. Lengkapi kinerja/dokumen. <b>Hasil yang diharapkan:</b> perkiraan 1 September 2026; kesiapan mengikuti tanggal pemeriksaan dan seluruh syarat, bukan tanggal cetak SK.')
table(['Waktu','Daftar kerja yang disarankan'],[['Awal hari kerja','Baca Command Center, antrean usulan, pengingat dan perbaikan Radar.'],['Setelah perubahan','Pastikan hasil tersimpan, status persetujuan benar dan catatan audit tersedia.'],['Awal bulan','Muat ulang masa kerja, tinjau target/tenggat dan buat laporan baru bila perlu.'],['Saat pergantian petugas','Tinjau akun aktif, nonaktifkan akses yang tidak diperlukan dan serahkan dokumen kerja melalui saluran resmi.']],widths=[145,CW-145],size=9.2)

start('Glosarium ringkas','16 / Referensi',deck='Istilah yang sering muncul pada layar dan buku ini.')
table(['Istilah','Arti dalam penggunaan NADI ASN'],[['ASN / jenis pegawai','Pengelompokan rekap aplikasi membedakan PNS, PPPK Penuh Waktu dan PPPK Paruh Waktu.'],['DUK','Daftar urut pegawai yang posisi manualnya ditetapkan Super Admin.'],['KP','Kenaikan pangkat; Radar membantu pemeriksaan administrasinya.'],['KGB','Kenaikan gaji berkala; perkiraan bertumpu pada TMT KGB terakhir.'],['TMT','Terhitung mulai tanggal: tanggal suatu keadaan mulai berlaku.'],['Masa kerja / masa dalam pangkat','Masa kerja keseluruhan berbeda dari lama berada pada pangkat tertentu.'],['AK / PAK','Angka kredit / Penetapan Angka Kredit. Isian digunakan pada jalur fungsional.'],['OTP / TOTP','Kode sekali pakai berbasis waktu dari aplikasi autentikator.'],['Usulan / persetujuan','Pengajuan perubahan data dan keputusan yang membuatnya berlaku atau menolaknya.'],['Mapping / batch','Pencocokan kolom impor / satu kelompok unggahan yang diproses.'],['Revisi / konflik','Versi data / kondisi ketika versi yang diedit sudah tidak lagi terbaru.'],['Agregat / snapshot laporan','Ringkasan kelompok / data pada saat permintaan laporan dibuat.'],['SMTP / worker','Layanan pengirim email / proses server yang memproses pekerjaan seperti impor dan ekspor.']],widths=[CW*.34,CW*.66],size=8.9)

start('Layanan aktif dan catatan pengelola','16 / Referensi',tag='PENGELOLA APLIKASI',deck='Produksi dipakai untuk pekerjaan kantor; simulasi disediakan untuk latihan.')
table(['Lingkungan','Cara mengenali'],[['Produksi utama','https://nadiasndpmptspsulteng.online; data asli VPS dan akses internal NIP/password/OTP.'],['Simulasi lokal','Port 5176, banner simulasi, data contoh. Tidak membaca atau mengubah database produksi.'],['Lokal lama','Arsip read-only; tidak tersinkron otomatis dengan VPS. Localhost mengacu pada perangkat sendiri.']],widths=[140,CW-140],size=9.2)
h('Backup dan pemantauan')
para('Backup data terenkripsi di Google Drive setiap <b>14 hari, 02.00 WITA</b>; seluruh project setiap <b>tiga bulan kalender, 03.00 WITA</b>. Backup lama diganti hanya setelah upload baru terverifikasi. Backup manual sukses menggeser acuan jadwal. Kunci dekripsi dijaga di luar VPS.')
para('Monitor VPS memeriksa layanan tiap lima menit dan mengirim email gangguan/pulih. Monitor ini tidak dapat mengirim saat VPS mati total; pemantauan eksternal belum tersedia. Jadwal backup bukan jaminan kehilangan data nol.')
box('Bila layanan bermasalah','Catat menu, waktu, pesan dan ID kejadian, lalu hubungi pengelola. Jangan reset database atau hapus volume untuk memperbaiki tampilan. Jangan berbagi password, OTP, token, kunci atau hasil dekripsi backup.')
h('Edisi dan rujukan')
para('Edisi 2.0, 5 Oktober 2026; baseline aplikasi <b>f03ce2f</b>. Tutorial mengikuti DUK manual, aturan Radar 2026-09-22.1 dan statistik pengunjung aktif. Buku teknis menjelaskan kontrak API, keamanan, deployment dan prosedur pemulihan; indeks dokumentasi tersedia pada repository proyek.',9.6,14)

assert num==TOTAL,(num,TOTAL)
end();c.save();(TMP/'layout-check.json').write_text(json.dumps(checks,indent=2));(TMP/'pages.json').write_text(json.dumps(PAGES,indent=2));print('Created',OUT,'pages:',num,'lowest body bottom:',min(x['bottom'] for x in checks))
