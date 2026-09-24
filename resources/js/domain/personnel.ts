import schema from './personnel-schema.json';
import type { Employee } from '@/types';
export const personnelFields = schema;
export const employeeTypes = ['PNS', 'PPPK Penuh Waktu', 'PPPK Paruh Waktu'];
export function completeness(e: Partial<Employee>) {
  const excluded = ['sourceOrder', 'ageAtReference'];
  if (e.type === 'PPPK Paruh Waktu') excluded.push('rankName', 'serviceYears', 'serviceMonths');
  const required = schema.filter((f) => !excluded.includes(f.key));
  const missing = required.filter((f) => {
    const v = (e as any)[f.key];
    return v === null || v === undefined || String(v).trim() === '' || v === '-';
  });
  return {
    completeness: Math.round((100 * (required.length - missing.length)) / required.length),
    missingFields: missing.map((f) => f.label),
  };
}
export function normalizeEmployee(e: Employee): Employee {
  const asOf = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Makassar' });
  const reference = (e as any).serviceReference || '2026-09';
  const [year, month] = asOf.split('-').map(Number),
    [by, bm] = reference.split('-').map(Number);
  const result = { ...e };
  if (
    e.serviceYears != null &&
    e.serviceMonths != null &&
    e.serviceMonths >= 0 &&
    e.serviceMonths <= 11
  ) {
    const total =
      Number(e.serviceYears) * 12 +
      Number(e.serviceMonths) +
      Math.max(0, (year - by) * 12 + month - bm);
    result.serviceYears = Math.floor(total / 12);
    result.serviceMonths = total % 12;
    (result as any).serviceReference = asOf.slice(0, 7);
  }
  const dob = e.birthDate;
  result.ageAtReference =
    dob && dob <= asOf
      ? year - Number(dob.slice(0, 4)) - (asOf.slice(5) < dob.slice(5) ? 1 : 0)
      : null;
  return { ...result, ...completeness(result) };
}
