"""Extract the user-authorized PDF into private staging; never import this file in frontend."""
import json,re,hashlib,sys,os
from pathlib import Path
from datetime import datetime
import pdfplumber
source=Path(sys.argv[1]);target=Path(sys.argv[2]);records=[]
def clean(v): return re.sub(r'\s+',' ',v or '').strip()
def date(v):
 if not v or v=='-':return ''
 try:return datetime.strptime(v,'%d/%m/%Y').strftime('%Y-%m-%d')
 except ValueError:return ''
with pdfplumber.open(source) as doc:
 for pi,p in enumerate(doc.pages,1):
  table=p.find_tables()[0]
  for raw,row in zip(table.extract(),table.rows):
   if not (raw[0] or '').isdigit() or raw[1]=='2':continue
   r=list(map(clean,raw));y0,y1=row.bbox[1],row.bbox[3]
   glyphs=[c for c in p.chars if y0<=c['top']<y1]
   # Name bold text can overflow the NIP cell. Font-aware extraction separates the original glyphs.
   name=''.join(c['text'] for c in glyphs if 'Bold' in c['fontname'] and 34<c['x0']<187).strip()
   nip=''.join(c['text'] for c in glyphs if 'Bold' not in c['fontname'] and c['text'].isdigit() and 132<c['x0']<187)
   assert re.fullmatch(r'\d{18}',nip),(r[0],nip)
   kind={'PNS':'PNS','PPPK PENUH WAKTU':'PPPK Penuh Waktu','PPPK PARUH WAKTU':'PPPK Paruh Waktu'}[r[16]]
   grade=re.search(r'/\s*([IVX]+)\s*([a-e])$',r[3],re.I)
   issues=[]
   for idx in [4,6,10,13]:
    if r[idx] and not date(r[idx]):issues.append(f'Tanggal sumber perlu diperiksa: kolom {idx+1}')
   if r[8].isdigit() and int(r[8])>11:issues.append('Masa kerja bulan pada sumber melebihi 11; nilai sumber dipertahankan.')
   if date(r[13]) and nip[:8]!=date(r[13]).replace('-',''):issues.append('Tanggal lahir sumber berbeda dengan bagian tanggal pada NIP; perlu verifikasi.')
   unit=r[18]
   # Page 3 row 60 overflows its cell into row 61; verified against the rendered source.
   if pi==3 and int(r[0])==60:
    assert unit=='TIM 6 - TIM KERJA PENYUSUNAN KEBIJAKAN, LAYANAN PENGADUAN,'
    unit+=' DAN PELAPORAN PELAYANAN'
   if pi==3 and int(r[0])==61:
    assert unit.startswith('DAN PELAPORAN PELAYANAN TIM 3')
    unit=unit.removeprefix('DAN PELAPORAN PELAYANAN ')
   records.append(dict(sourceOrder=int(r[0]),name=name,nip=nip,rankName=r[3],grade=(grade[1].upper()+'/'+grade[2].lower()) if grade else ('' if r[3]=='-' else r[3]),rankTmt=date(r[4]),position=r[5],effectiveDate=date(r[6]),serviceYears=int(r[7]) if r[7].isdigit() else None,serviceMonths=int(r[8]) if r[8].isdigit() else None,serviceReference='2026-09',institution=r[9],graduationDate=date(r[10]),education=r[11],birthPlace=r[12],birthDate=date(r[13]),ageAtReference=int(r[14]) if r[14].isdigit() else None,gender={'L':'Laki-laki','P':'Perempuan'}.get(r[15],''),type=kind,positionType={'STURKTURAL':'Struktural','STRUKTURAL':'Struktural','FUNGSIONAL':'Fungsional','PELAKSANA':'Pelaksana'}.get(r[17],r[17]),unit=unit,status='Aktif',sourcePage=pi,sourceIssues=issues,sourceCells=r))
assert all(r['name'] and r['nip'] for r in records), 'Nama/NIP kosong harus diperiksa'
assert len(records)==168 and len(set(r['nip'] for r in records))==168
assert [r['sourceOrder'] for r in records]==list(range(1,169))
target.parent.mkdir(parents=True,exist_ok=True);target.write_text(json.dumps({'source':source.name,'sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'asOf':'2026-09-01','records':records},ensure_ascii=False,indent=2));os.chmod(target,0o600)
from collections import Counter
print('Records',len(records),'types',dict(Counter(r['type'] for r in records)),'units',len(set(r['unit'] for r in records)),'issues',sum(bool(r['sourceIssues']) for r in records),'education',dict(Counter(r['education'] for r in records)))
