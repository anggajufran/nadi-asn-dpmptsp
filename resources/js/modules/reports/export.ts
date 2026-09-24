import { personnelFields } from '@/domain/personnel';
import type { PublicStats, Employee } from '@/types';
import { dateLabel } from '@/composables';
export async function exportPublicPdf(stats: PublicStats, unit: string, type: string) {
  const { jsPDF } = await import('jspdf');
  const { default: autoTable } = await import('jspdf-autotable');
  const doc = new jsPDF();
  doc.setFontSize(14);
  doc.setTextColor('#28579a');
  doc.text('NADI ASN DPMPTSP Prov. Sulawesi Tengah', 14, 22);
  doc.setFontSize(11);
  doc.text('Ringkasan Statistik Kepegawaian', 14, 31);
  doc.setFontSize(9);
  doc.setTextColor('#61718c');
  doc.text(
    `Data per ${dateLabel(stats.asOf)} | ${unit || 'Seluruh unit'} | ${type || 'ASN dan rekap PPPK Paruh Waktu'}`,
    14,
    40,
    { maxWidth: 180 },
  );
  if (stats.demo) doc.text('DATA SIMULASI - BUKAN DATA RESMI', 14, 59);
  const body = [
    ...stats.units.map((s) => ['Unit kerja', s.label, s.display || String(s.value)]),
    ['Ringkasan', 'Total ASN (PNS + PPPK Penuh Waktu)', String(stats.total)],
    ['Rekap terpisah', 'PPPK Paruh Waktu', String(stats.partTime)],
    ...Object.entries({
      'Jenis ASN': stats.composition,
      Pendidikan: stats.education,
      Gender: stats.gender,
      'Kelompok jabatan': stats.positions,
      'Pangkat PNS': stats.grades,
      'Golongan PPPK': stats.pppkGrades,
      'Jabatan fungsional': stats.functional,
    }).flatMap(([key, rows]) => rows.map((s) => [key, s.label, s.display || String(s.value)])),
  ];
  autoTable(doc, {
    startY: 64,
    head: [['Kategori', 'Kelompok', 'Jumlah']],
    body,
    headStyles: { fillColor: '#28579a' },
    styles: { fontSize: 9 },
    didDrawPage: () => {
      doc.setFontSize(8);
      doc.text('Statistik agregat tanpa identitas individu.', 14, 286);
    },
  });
  doc.save('NADI-ASN-Ringkasan-Statistik.pdf');
}
export async function exportEmployees(rows: Employee[]) {
  const ExcelJS = await import('exceljs');
  const wb = new ExcelJS.Workbook();
  const sheet = wb.addWorksheet('Data Pegawai');
  sheet.addRow(personnelFields.map((f) => f.label));
  rows.forEach((e) => sheet.addRow(personnelFields.map((f) => String((e as any)[f.key] ?? ''))));
  sheet.columns.forEach((c) => (c.width = 25));
  sheet.getColumn(1).width = 38;
  sheet.getColumn(personnelFields.length).width = 60;
  sheet.getColumn(personnelFields.findIndex((f) => f.key === 'nip') + 1).numFmt = '@';
  sheet.getRow(1).font = { bold: true, color: { argb: 'FFFFFFFF' } };
  sheet.getRow(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF28579A' } };
  sheet.views = [{ state: 'frozen', ySplit: 1, xSplit: 2 }];
  sheet.autoFilter = {
    from: { row: 1, column: 1 },
    to: { row: 1, column: personnelFields.length },
  };
  wb.subject = 'Data simulasi untuk pengujian format 18 kolom';
  const buffer = await wb.xlsx.writeBuffer();
  const url = URL.createObjectURL(
    new Blob([buffer], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }),
  );
  const a = document.createElement('a');
  a.href = url;
  a.download = 'NADI-ASN-Operasional-Simulasi.xlsx';
  a.click();
  URL.revokeObjectURL(url);
}
