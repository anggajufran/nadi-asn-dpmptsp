export type AsnType = 'PNS' | 'PPPK Penuh Waktu' | 'PPPK Paruh Waktu';
export type Role = 'SUPER_ADMIN' | 'ADMIN';
export interface User {
  id: string;
  name: string;
  role: Role;
}
export interface Employee {
  id: string;
  name: string;
  nip: string;
  type: AsnType;
  gender: string;
  unit: string;
  position: string;
  positionType: string;
  grade: string;
  education: string;
  status: string;
  birthPlace?: string;
  birthDate?: string;
  effectiveDate: string;
  driveUrl?: string;
  contractStart?: string;
  contractEnd?: string;
  revision: number;
  completeness: number;
  sourceOrder?: number | null;
  rankName?: string;
  rankTmt?: string;
  serviceYears?: number | null;
  serviceMonths?: number | null;
  serviceReference?: string;
  institution?: string;
  graduationDate?: string;
  ageAtReference?: number | null;
  missingFields?: string[];
  sourceIssues?: string[];
  history?: { date: string; label: string }[];
}
export interface Series {
  label: string;
  value: number | null;
  display?: string;
  color?: string;
}
export interface PublicStats {
  asOf: string;
  demo: boolean;
  total: number | null;
  partTime: number;
  composition: Series[];
  education: Series[];
  units: Series[];
  gender: Series[];
  positions: Series[];
  grades: Series[];
  pppkGrades: Series[];
  functional: Series[];
  privacyApplied: boolean;
}
export interface Proposal {
  id: string;
  employeeId: string | null;
  name: string;
  makerId: string;
  makerName: string;
  status: string;
  createdAt: string;
  reason: string;
  changes: Partial<Employee>;
  before: Partial<Employee>;
  revision: number;
  decisionReason?: string;
}
export interface AuditEvent {
  id: string;
  actor: string;
  action: string;
  subject: string;
  createdAt: string;
}
