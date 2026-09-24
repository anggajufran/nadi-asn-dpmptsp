import labels from './unit-order.json';
const normalize = (s: string) => s.trim().replace(/\s+/g, ' ').toLocaleUpperCase('id-ID');
const ranks = new Map(labels.map((label, i) => [normalize(label), i]));
export function orderUnits<T extends { label: string }>(rows: T[]): T[] {
  return [...rows].sort(
    (a, b) =>
      (ranks.get(normalize(a.label)) ?? 999) - (ranks.get(normalize(b.label)) ?? 999) ||
      a.label.localeCompare(b.label, 'id', { numeric: true }),
  );
}
