"""Build PDF + Markdown. Requires reportlab and Pillow; NADI_DOC_FONTS may point to fonts."""
from pathlib import Path
from xml.sax.saxutils import escape
import json,re,os,math,html
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor,white
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import Paragraph,Table,TableStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from content import PAGES
ROOT=Path(__file__).resolve().parents[3]
OUT=ROOT/'output/pdf/Dokumentasi-Teknis-NADI-ASN.pdf'
MD=ROOT/'docs/architecture/DOKUMENTASI_TEKNIS_NADI_ASN.md'
TMP=ROOT/'tmp/pdfs/nadi-technical'
for d in [OUT.parent,TMP]: d.mkdir(parents=True,exist_ok=True)
font_candidates=[Path(os.environ.get('NADI_DOC_FONTS','/nonexistent')),Path.home()/'.cache/codex-runtimes/codex-primary-runtime/dependencies/native/libreoffice-headless/libreoffice/LibreOfficeDev.app/Contents/Resources/fonts/truetype',Path('/usr/share/fonts/truetype/noto')]
FONT=next((p for p in font_candidates if (p/'NotoSans-Regular.ttf').exists()),None)
if not FONT:raise RuntimeError('Set NADI_DOC_FONTS to a folder containing NotoSans-Regular.ttf and NotoSans-Bold.ttf')
for name,file in [('Body','NotoSans-Regular.ttf'),('Bold','NotoSans-Bold.ttf')]:pdfmetrics.registerFont(TTFont(name,str(FONT/file)))
pdfmetrics.registerFontFamily('Body',normal='Body',bold='Bold',italic='Body',boldItalic='Bold')
if (FONT/'Rubik-Bold.ttf').exists():pdfmetrics.registerFont(TTFont('Display',str(FONT/'Rubik-Bold.ttf')))
else:pdfmetrics.registerFont(TTFont('Display',str(FONT/'NotoSans-Bold.ttf')))
MONO='Courier'
if (FONT/'DejaVuSansMono.ttf').exists():
 pdfmetrics.registerFont(TTFont('Mono',str(FONT/'DejaVuSansMono.ttf')));MONO='Mono'
NAVY=HexColor('#15324D');TEAL=HexColor('#078B91');INK=HexColor('#263E50');MUTED=HexColor('#627B8C');LINE=HexColor('#DCE7EC');SKY=HexColor('#EAF4F7');GOLD=HexColor('#C58A36')
W,H=595.276,841.89;M=42;CW=W-2*M;TOTAL=len(PAGES)+1
c=canvas.Canvas(str(OUT),pagesize=(W,H),pageCompression=1)
c.setTitle('Dokumentasi Teknis Sistem dan Panduan Serah Terima NADI ASN')
c.setAuthor('DPMPTSP Prov. Sulawesi Tengah');c.setSubject('Arsitektur, data, API, keamanan, deployment dan operasi. Baseline 5b1b70a; edisi 29 September 2026.')
y=0;checks=[]
def clean(s):return s.replace('–','-').replace('—','-').replace('‑','-').replace('→','->').replace('↔','&lt;->').replace('≤','&lt;=').replace('≥','>=').replace('≠','!=')
def para(s,size=9.7,leading=14.2,color=INK,bold=False,x=M,width=CW,gap=9):
 global y
 st=ParagraphStyle('p',fontName='Bold' if bold else 'Body',fontSize=size,leading=leading,textColor=color,splitLongWords=True)
 q=Paragraph(clean(s),st);_,hh=q.wrap(width,2000);q.drawOn(c,x,y-hh);y-=hh+gap
 return hh

def block_table(headers,rows,widths):
 global y
 widths=[CW*v for v in widths] if widths else [CW/len(headers)]*len(headers)
 st=ParagraphStyle('cell',fontName='Body',fontSize=8.7,leading=12.2,textColor=INK,splitLongWords=True)
 sh=ParagraphStyle('head',fontName='Bold',fontSize=8.4,leading=11.5,textColor=white)
 data=[[Paragraph(clean(str(v)),sh) for v in headers]]+[[Paragraph(clean(str(v)),st) for v in row] for row in rows]
 t=Table(data,colWidths=widths);t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('ROWBACKGROUNDS',(0,1),(-1,-1),[HexColor('#F3F7F9'),white]),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),9),('RIGHTPADDING',(0,0),(-1,-1),9),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6),('LINEBELOW',(0,1),(-1,-1),.4,LINE)]))
 _,hh=t.wrap(CW,2000);t.drawOn(c,M,y-hh);y-=hh+12

def callout(title,s):
 global y
 st=ParagraphStyle('box',fontName='Body',fontSize=9.2,leading=13.3,textColor=INK)
 q=Paragraph('<b>'+clean(title)+'</b><br/>'+clean(s),st);_,hh=q.wrap(CW-28,2000);height=hh+22
 c.setFillColor(SKY);c.roundRect(M,y-height,CW,height,6,fill=1,stroke=0);c.setFillColor(TEAL);c.roundRect(M,y-height,3,height,1,fill=1,stroke=0);q.drawOn(c,M+14,y-11-hh);y-=height+12

def draw_code(s):
 global y
 lines=s.splitlines();fontsize=7.4
 longest=max((pdfmetrics.stringWidth(line,MONO,fontsize) for line in lines),default=0)
 if longest>CW-24:fontsize*=((CW-24)/longest)
 if fontsize<6.6:raise RuntimeError('Code line too long: '+s)
 leading=fontsize+4;hh=len(lines)*leading+22
 c.setFillColor(NAVY);c.roundRect(M,y-hh,CW,hh,6,fill=1,stroke=0);c.setFont(MONO,fontsize);c.setFillColor(HexColor('#E5F1F5'))
 for i,line in enumerate(lines):c.drawString(M+12,y-15-i*leading,line)
 y-=hh+12

def draw_flow(items):
 global y
 gap=12;bw=(CW-gap*(len(items)-1))/len(items);hh=62
 for i,(title,s) in enumerate(items):
  x=M+i*(bw+gap);c.setFillColor(SKY);c.roundRect(x,y-hh,bw,hh,6,fill=1,stroke=0)
  q=Paragraph('<b>'+title+'</b><br/>'+s,ParagraphStyle('f',fontName='Body',fontSize=8.6,leading=12.1,textColor=NAVY));_,ph=q.wrap(bw-18,1000);q.drawOn(c,x+9,y-11-ph)
  if i<len(items)-1:arrow(x+bw+2,y-31,x+bw+gap-2,y-31)
 y-=hh+14

def arrow(x1,y1,x2,y2):
 c.setStrokeColor(TEAL);c.setFillColor(TEAL);c.setLineWidth(1);c.line(x1,y1,x2,y2)
 ang=math.atan2(y2-y1,x2-x1);p=c.beginPath();p.moveTo(x2,y2);p.lineTo(x2-5*math.cos(ang-.45),y2-5*math.sin(ang-.45));p.lineTo(x2-5*math.cos(ang+.45),y2-5*math.sin(ang+.45));p.close();c.drawPath(p,fill=1,stroke=0)

def node(x,top,w,hh,title,sub,dark=False):
 c.setFillColor(NAVY if dark else SKY);c.roundRect(x,top-hh,w,hh,7,fill=1,stroke=0)
 q=Paragraph('<b>'+title+'</b><br/>'+sub,ParagraphStyle('n',fontName='Body',fontSize=9.2,leading=13,textColor=white if dark else NAVY));_,ph=q.wrap(w-20,2000);q.drawOn(c,x+10,top-10-ph)

def draw_diagram(kind):
 global y
 top=y
 if kind=='architecture':
  node(M,top,160,61,'Browser','Publik / Admin / Super Admin',True)
  node(M+191,top,130,61,'Nginx','SPA dist + TLS')
  node(M+352,top,159,61,'Laravel','Route, auth, domain',True)
  arrow(M+163,top-30,M+188,top-30);arrow(M+324,top-30,M+349,top-30)
  node(M+352,top-98,159,64,'PostgreSQL','Data, sesi, queue, cache')
  arrow(M+430,top-64,M+430,top-95)
  node(M,top-98,155,64,'Worker + scheduler','Impor, ekspor, heartbeat')
  arrow(M+158,top-130,M+349,top-130)
  node(M,top-198,155,62,'Storage privat','File impor + laporan')
  node(M+177,top-198,155,62,'ClamAV','Pemeriksaan unggahan')
  node(M+354,top-198,157,62,'SMTP','Pemulihan akun\n(belum aktif)')
  c.setStrokeColor(LINE);c.line(M,top-180,M+CW,top-180)
  y=top-279;para('Akses dependensi melalui backend/worker; browser tidak terhubung langsung ke DB atau file privat.',8.4,12,MUTED,gap=14)
 elif kind=='erd':
  node(M,top,153,62,'users','Akun & versi keamanan',True)
  node(M+180,top,153,62,'employees','Identitas & urutan DUK',True)
  node(M+360,top,151,62,'reference_values','Unit aktif terverifikasi')
  arrow(M+156,top-30,M+177,top-30)
  node(M+180,top-110,153,62,'employee_assignments','Histori kepegawaian')
  arrow(M+256,top-65,M+256,top-107)
  c.setStrokeColor(TEAL);c.line(M+511,top-31,M+520,top-31);c.line(M+520,top-31,M+520,top-188);c.line(M+520,top-188,M+257,top-188);arrow(M+257,top-188,M+257,top-175)
  node(M,top-110,153,62,'change_requests','Usulan + keputusan')
  node(M+360,top-110,151,62,'radar_records','Pemeriksaan KP/KGB')
  arrow(M+183,top-61,M+75,top-107);arrow(M+330,top-61,M+435,top-107)
  node(M,top-221,153,62,'import_batches / rows','Pemilik + usulan hasil')
  node(M+180,top-221,153,62,'audit / outbox','Jejak & event perubahan')
  node(M+360,top-221,151,62,'export_jobs','Pemilik + snapshot input')
  arrow(M+76,top-218,M+76,top-175)
  y=top-303;para('Diagram hubungan logis inti; FK tambahan ke users dan detail kardinalitas dijelaskan pada kamus data.',8.4,12,MUTED,gap=12)

def toc():
 global y
 groups=[];seen=set()
 for i,p in enumerate(PAGES,2):
  sec=p['section'];k=sec.split(' / ')[0]
  if k in seen or k=='01':continue
  seen.add(k);groups.append((sec,i))
 for sec,n in groups:
  c.setFillColor(TEAL);c.setFont('Bold',10);c.drawString(M+3,y-11,sec.split(' / ')[0]);c.setFillColor(INK);c.setFont('Body',11);c.drawString(M+37,y-11,sec.split(' / ')[1]);c.setFont('Bold',11);c.drawRightString(W-M-3,y-11,f'{n:02}')
  c.linkRect('',f'p{n}',(M,y-23,W-M,y+2),relative=0,thickness=0);c.setStrokeColor(LINE);c.line(M,y-25,W-M,y-25);y-=34
 y-=10;callout('Cara membaca sesuai tugas','Pengembang: arsitektur, kode, data, aturan dan API. Operator: konfigurasi, deployment dan operasi. Penanggung jawab: keamanan, batas layanan dan checklist serah terima. Setiap bab memuat rujukan file sumber.')

def footer(n,refs,cover=False):
 global y
 if refs:
  save=y;y=93;para('SUMBER IMPLEMENTASI',6.8,8,TEAL,bold=True,gap=3)
  para(' · '.join(escape(r) for r in refs),6.5,8.2,MUTED,gap=0)
  if y<42:raise RuntimeError(f'Source overflow {n}: {y}')
  y=save
 c.setStrokeColor(LINE);c.line(M,36,W-M,36);c.setFont('Body',7);c.setFillColor(HexColor('#B5D2DD') if cover else MUTED);c.drawString(M,22,'DPMPTSP Prov. Sulawesi Tengah  /  INTERNAL');c.drawRightString(W-M,22,f'{n:02} / {TOTAL:02}')

def cover():
 global y
 c.bookmarkPage('p1');c.addOutlineEntry('Dokumentasi Teknis NADI ASN','p1',0)
 c.setFillColor(NAVY);c.rect(0,0,W,H,fill=1,stroke=0)
 for i in range(8):
  c.setStrokeColor(HexColor('#23516A'));c.setLineWidth(.7);c.line(320+i*33,0,595,240+i*43)
 c.setFillColor(white);c.roundRect(M,H-171,115,120,12,fill=1,stroke=0);c.drawImage(str(ROOT/'public/logo-nadi-asn.png'),M+8,H-166,100,110,preserveAspectRatio=True,anchor='c',mask='auto')
 c.setFont('Bold',9);c.setFillColor(HexColor('#7ED0D2'));c.drawString(M,610,'SYSTEM DOCUMENTATION  /  TECHNICAL HANDOVER')
 c.setFont('Display',44);c.setFillColor(white);c.drawString(M,548,'NADI ASN');c.setFont('Display',29);c.drawString(M,498,'Dokumentasi teknis');c.drawString(M,460,'sistem')
 y=422;para('Arsitektur, data, API, keamanan<br/>dan panduan operasional untuk tim IT.',13,20,HexColor('#C4D9E3'),gap=20)
 c.setStrokeColor(TEAL);c.setLineWidth(3);c.line(M,337,M+75,337)
 y=308;para('DPMPTSP Prov. Sulawesi Tengah',12,18,white,True);para('Panduan serah terima pengembang & pengelola',10.5,16,HexColor('#C4D9E3'))
 c.setFillColor(HexColor('#204A62'));c.roundRect(M,121,CW,64,8,fill=1,stroke=0)
 y=168;para('EDISI 1.0  •  29 SEPTEMBER 2026',9.5,14,white,True,x=M+14,width=CW-28,gap=5);para(f'{TOTAL} halaman  |  Baseline 5b1b70a  |  Distribusi internal',9,13,HexColor('#C4D9E3'),x=M+14,width=CW-28)
 footer(1,[],True);c.showPage()

cover()
for n,page in enumerate(PAGES,2):
 c.bookmarkPage(f'p{n}');c.addOutlineEntry(page['title'],f'p{n}',0)
 c.setFillColor(TEAL);c.rect(0,H-7,W,7,fill=1,stroke=0)
 c.setFont('Bold',7.5);c.setFillColor(NAVY);c.drawString(M,H-32,'NADI ASN  /  DOKUMENTASI TEKNIS');c.setFont('Body',7.5);c.setFillColor(MUTED);c.drawRightString(W-M,H-32,page['section'].upper());c.setStrokeColor(LINE);c.line(M,H-42,W-M,H-42)
 y=H-67;para(page['title'],22,27,NAVY,True,gap=9);para(page['deck'],10,14.5,MUTED,gap=16)
 for block in page['blocks']:
  k=block[0]
  if k=='p':para(block[1])
  elif k=='b':para('<font color="#078B91">•</font> '+block[1],gap=7)
  elif k=='h':y-=2;para(block[1],11.8,16,NAVY,True,gap=6)
  elif k=='table':block_table(*block[1:])
  elif k=='box':callout(*block[1:])
  elif k=='code':draw_code(block[1])
  elif k=='flow':draw_flow(block[1])
  elif k=='diagram':draw_diagram(block[1])
  elif k=='toc':toc()
 checks.append({'page':n,'title':page['title'],'bottom':round(y,1)})
 footer(n,page['refs']);c.showPage()
c.save()
(TMP/'layout.json').write_text(json.dumps(checks,indent=2,ensure_ascii=False))

def txt(s):
 s=clean(s).replace('<b>','**').replace('</b>','**').replace('<br/>','\n')
 return html.unescape(re.sub('<[^>]+>','',s))
mermaids={
 'architecture':'''flowchart LR\n Browser --> Nginx\n Nginx --> SPA[Vue build dist]\n Nginx --> Laravel\n Laravel --> PostgreSQL\n Worker --> PostgreSQL\n Scheduler --> PostgreSQL\n Laravel --> ClamAV\n Laravel --> SMTP[SMTP - belum aktif]\n Laravel --> Storage[Storage privat]\n Worker --> Storage''',
 'erd':'''erDiagram\n employees ||--o{ employee_assignments : mempunyai\n reference_values ||--o{ employee_assignments : unit\n employees ||--o| users : akun_opsional\n employees ||--o{ change_requests : perubahan\n users ||--o{ change_requests : maker_checker\n employees ||--o{ radar_records : pemeriksaan\n users ||--o{ import_batches : pemilik\n import_batches ||--o{ import_rows : baris\n change_requests ||--o{ import_rows : hasil\n users ||--o{ export_jobs : pemilik\n users ||--o{ audit_events : aktor'''
}
lines=['# Dokumentasi Teknis Sistem dan Panduan Serah Terima NADI ASN','','DPMPTSP Prov. Sulawesi Tengah | Edisi 1.0 | 29 September 2026','','Baseline: `5b1b70ae786ec4384614cebd41b50041cc1979df`. Distribusi internal.','']
for n,pa in enumerate(PAGES,2):
 lines+=['## '+pa['title'],'',pa['deck'],'',f'*Bab {pa["section"]}; halaman PDF {n}.*','']
 for bl in pa['blocks']:
  k=bl[0]
  if k=='p':lines+=[txt(bl[1]),'']
  elif k=='h':lines+=['### '+txt(bl[1]),'']
  elif k=='b':lines+=['- '+txt(bl[1]),'']
  elif k=='box':lines+=['> **'+txt(bl[1])+'**','> '+txt(bl[2]).replace('\n','\n> '),'']
  elif k=='code':lines+=['```text',bl[1],'```','']
  elif k=='flow':lines+=[' → '.join('**'+txt(a)+'**: '+txt(b) for a,b in bl[1]),'']
  elif k=='diagram':lines+=['```mermaid',mermaids[bl[1]],'```','']
  elif k=='toc':lines += [f'- {i+2}. {x["title"]}' for i,x in enumerate(PAGES) if x['section'].startswith(('02','03','04','05','06','07','08','09','10','11','12','13','14','15'))];lines+=['']
  elif k=='table':
   lines+=['| '+' | '.join(txt(s).replace('|','\\|') for s in bl[1])+' |','| '+' | '.join('---' for s in bl[1])+' |']
   lines+=['| '+' | '.join(txt(str(s)).replace('\n','<br>').replace('|','\\|') for s in row)+' |' for row in bl[2]];lines+=['']
 if pa['refs']:lines+=['Sumber: '+', '.join('`'+s+'`' for s in pa['refs'])+'.','']
MD.write_text('\n'.join(lines),encoding='utf-8')
issues=[x for x in checks if x['bottom']<108]
print(json.dumps({'pages':TOTAL,'pdf':str(OUT),'markdown':str(MD),'layout_issues':issues},ensure_ascii=False))
if issues:raise SystemExit(2)
