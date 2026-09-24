import { orderUnits } from '../resources/js/domain/unit-order';
import { normalizeEmployee } from '../resources/js/domain/personnel';
import type { Employee, PublicStats, Series } from '../resources/js/types/index';
export const units = [
  'Sekretariat',
  'Pelayanan Perizinan',
  'Penanaman Modal',
  'Pengendalian & Pengawasan',
  'Pengaduan & Informasi',
];
export const demoUsers = [
  { id: 'demo-admin', name: 'Admin Kepegawaian', role: 'ADMIN' },
  { id: 'demo-checker', name: 'Pemeriksa Utama', role: 'SUPER_ADMIN' },
];
export function seedEmployees(): Employee[] {
  const first = [
    'Ayu',
    'Budi',
    'Citra',
    'Dedi',
    'Eka',
    'Fitri',
    'Gilang',
    'Hana',
    'Indra',
    'Jelita',
    'Kartika',
    'Lukman',
    'Maya',
    'Nanda',
    'Oki',
    'Putri',
    'Rizal',
  ];
  const last = [
    'Pratama',
    'Wulandari',
    'Saputra',
    'Lestari',
    'Ramadhan',
    'Kusuma',
    'Wijaya',
    'Permana',
    'Setiawan',
    'Pertiwi',
  ];
  return Array.from({ length: 170 }, (_, i) => {
    const n = i % 34;
    const type = n < 24 ? 'PNS' : n < 31 ? 'PPPK Penuh Waktu' : 'PPPK Paruh Waktu';
    const positionType = i % 5 === 0 ? 'Struktural' : i % 5 < 3 ? 'Fungsional' : 'Pelaksana';
    return {
      id: `00000000-0000-4000-a000-${String(i + 1).padStart(12, '0')}`,
      name: `${first[i % 17]} ${last[Math.floor(i / 17)]}${i % 3 === 0 ? ', S.E.' : i % 3 === 1 ? ', S.Kom.' : ', S.Sos.'}`,
      nip: `19900101202001${String(i + 1).padStart(4, '0')}`,
      type,
      gender: i % 5 < 3 ? 'Perempuan' : 'Laki-laki',
      unit: units[Math.floor(i / 34)],
      position:
        positionType === 'Fungsional'
          ? i % 2
            ? 'Analis Kebijakan'
            : 'Pranata Komputer'
          : positionType === 'Struktural'
            ? 'Kepala Subbagian'
            : 'Pengelola Administrasi',
      positionType,
      grade:
        type === 'PNS'
          ? ['III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b'][n % 6]
          : ['IX', 'VII', 'V'][i % 3],
      education: ['S1', 'S1', 'S2', 'D3', 'SMA'][i % 5],
      status: 'Aktif',
      birthPlace: 'Kota Simulasi',
      birthDate: i % 13 === 0 ? '' : '1990-01-01',
      effectiveDate: '2024-01-01',
      revision: 1,
      completeness: 0,
      sourceOrder: i + 1,
      rankName: type === 'PNS' ? 'Penata Muda / III a' : 'IX',
      rankTmt: '2024-01-01',
      serviceYears: 2,
      serviceMonths: 8,
      institution: 'Perguruan Tinggi Contoh',
      graduationDate: '2019-07-01',
      ageAtReference: 36,
      serviceReference: '2026-09',
      history: [
        { date: '2024-01-01', label: 'Penetapan unit kerja dan jabatan (simulasi)' },
        { date: '2020-01-01', label: 'Pengangkatan ASN (simulasi)' },
      ],
      ...(type !== 'PNS' ? { contractStart: '2024-01-01', contractEnd: '2028-12-31' } : {}),
    };
  }).map((e) => normalizeEmployee(e as Employee));
}
export function group(rows: Employee[], key: keyof Employee): Series[] {
  const counts = new Map<string, number>();
  for (const row of rows) {
    const label = String(row[key] || 'Belum tercatat');
    counts.set(label, (counts.get(label) || 0) + 1);
  }
  return [...counts].map(([label, value]) => ({ label, value }));
}
export function publicStatistics(rows: Employee[], unit = '', type = ''): PublicStats {
  const filtered = rows.filter(
    (e) => e.status === 'Aktif' && (!unit || e.unit === unit) && (!type || e.type === type),
  );
  const asn = filtered.filter((e) => e.type !== 'PPPK Paruh Waktu');
  const chartRows = type === 'PPPK Paruh Waktu' ? filtered : asn;
  return {
    asOf: new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Makassar' }),
    demo: true,
    total: asn.length,
    partTime: filtered.filter((e) => e.type === 'PPPK Paruh Waktu').length,
    composition: ['PNS', 'PPPK Penuh Waktu'].map((label) => ({
      label,
      value: asn.filter((e) => e.type === label).length,
    })),
    education: group(chartRows, 'education'),
    units: orderUnits(group(chartRows, 'unit')),
    gender: group(chartRows, 'gender'),
    positions: group(chartRows, 'positionType'),
    grades: group(
      asn.filter((e) => e.type === 'PNS'),
      'grade',
    ),
    pppkGrades: group(
      asn.filter((e) => e.type === 'PPPK Penuh Waktu'),
      'grade',
    ),
    functional: group(
      chartRows.filter((e) => e.positionType === 'Fungsional'),
      'position',
    ),
    privacyApplied: false,
  };
}
