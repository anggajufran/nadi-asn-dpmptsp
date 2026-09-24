import { describe, it, expect } from 'vitest';
import { publicStatistics, seedEmployees } from '../../scripts/demo-data';
import { completeness } from '../../resources/js/domain/personnel';
describe('Rekap dan batas data publik', () => {
  it('hanya PNS dan PPPK Penuh Waktu masuk total ASN', () => {
    const s = publicStatistics(seedEmployees());
    expect(s.total).toBe(155);
    expect(s.partTime).toBe(15);
    expect(s.composition).toHaveLength(2);
  });
  it('menampilkan kelompok kecil tanpa membocorkan identitas', () => {
    const e = seedEmployees()[0];
    const s = publicStatistics([e], e.unit, 'PNS');
    expect(s.total).toBe(1);
    expect(s.units[0].value).toBe(1);
    const json = JSON.stringify(s);
    for (const value of [e.nip, e.name, 'birthDate', 'driveUrl', 'sourceCells'])
      expect(json).not.toContain(value);
  });
  it('menampilkan kosong sebagai nol', () => {
    expect(publicStatistics([]).total).toBe(0);
  });
  it('kelengkapan berubah sesuai isian termasuk nilai numerik nol', () => {
    const e = { ...seedEmployees()[1], serviceYears: 0, serviceMonths: 0 };
    expect(completeness(e).completeness).toBe(100);
    expect(completeness({ ...e, birthDate: '' }).missingFields).toContain('Tanggal lahir');
    expect(completeness({ ...e, birthDate: '' }).completeness).toBeLessThan(100);
  });
  it('rekap paruh waktu terpisah ketika difilter', () => {
    const s = publicStatistics(seedEmployees(), '', 'PPPK Paruh Waktu');
    expect(s.total).toBe(0);
    expect(s.partTime).toBe(15);
    expect(s.units.reduce((n, r) => n + (r.value || 0), 0)).toBe(15);
  });
});
