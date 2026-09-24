/** Loopback-only synthetic sandbox. Excluded from production builds; never use for real personnel. */
import { spawnSync } from 'node:child_process';
import { normalizeEmployee } from '../resources/js/domain/personnel';
import { orderUnits } from '../resources/js/domain/unit-order';
import dukFields from '../resources/js/domain/duk-schema.json';
import { personnelFields } from '../resources/js/domain/personnel';
import type { Plugin, ViteDevServer } from 'vite';
import { randomBytes, randomUUID, createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync, renameSync } from 'node:fs';
import { resolve } from 'node:path';
import { demoUsers, seedEmployees, publicStatistics, units } from './demo-data';
import type { AuditEvent, Employee, Proposal } from '../resources/js/types/index';
const sessions = new Map<string, { user: (typeof demoUsers)[number]; expires: number }>();
const file = process.env.NADI_DEMO_STATE || resolve('.runtime/demo-state.json');
interface State {
  employees: Employee[];
  proposals: Proposal[];
  audit: AuditEvent[];
  imports: any[];
  radar?: Record<string, { data: any; revision: number }>;
  units?: { id: string; label: string }[];
}
let state: State;
function readState() {
  if (!state) {
    mkdirSync(resolve('.runtime'), { recursive: true });
    state = existsSync(file)
      ? JSON.parse(readFileSync(file, 'utf8'))
      : { employees: seedEmployees(), proposals: [], audit: [], imports: [] };
  }
  state.employees = state.employees.map((employee) =>
    normalizeEmployee({
      ...employee,
      type: String(employee.type) === 'PPPK' ? 'PPPK Penuh Waktu' : employee.type,
    }),
  );
  state.units ??= units.map((label, i) => ({ id: String(i + 1), label }));
  return state;
}
function save() {
  writeFileSync(file + '.tmp', JSON.stringify(state, null, 2), { mode: 0o600 });
  renameSync(file + '.tmp', file);
}
function event(actor: string, action: string, subject: string) {
  state.audit.unshift({
    id: randomUUID(),
    actor,
    action,
    subject,
    createdAt: new Date().toISOString(),
  });
}
export function demoServer(): Plugin {
  return {
    name: 'nadi-synthetic-sandbox',
    configureServer(server: ViteDevServer) {
      server.middlewares.use(async (req, res, next) => {
        if (!req.url?.startsWith('/api/')) return next();
        const url = new URL(req.url, 'http://localhost');
        const path = url.pathname.slice(5);
        const method = req.method || 'GET';
        const send = (data: unknown, status = 200) => {
          res.statusCode = status;
          res.setHeader('Content-Type', 'application/json; charset=utf-8');
          res.setHeader('Cache-Control', 'no-store');
          res.setHeader('X-Robots-Tag', 'noindex');
          res.end(JSON.stringify(data));
        };
        try {
          if (method !== 'GET' && req.headers.origin !== `http://${req.headers.host}`)
            return send({ message: 'Asal permintaan tidak diizinkan.' }, 403);
          let raw = '';
          for await (const chunk of req) {
            raw += chunk;
            if (raw.length > 6_000_000)
              return send({ message: 'Ukuran permintaan terlalu besar.' }, 413);
          }
          const body = raw ? JSON.parse(raw) : {};
          const token = req.headers.cookie
            ?.split('; ')
            .find((c) => c.startsWith('nadi_demo='))
            ?.slice(10);
          const session = token ? sessions.get(token) : undefined;
          const user = session && session.expires > Date.now() ? session.user : null;
          const db = readState();
          if (path === 'session') return send({ user, demo: true });
          if (path === 'demo/login' && method === 'POST') {
            const selected = demoUsers.find((u) => u.role === body.role);
            if (!selected) return send({ message: 'Peran tidak valid.' }, 422);
            const id = randomBytes(32).toString('hex');
            sessions.set(id, { user: selected, expires: Date.now() + 8 * 3600_000 });
            res.setHeader(
              'Set-Cookie',
              `nadi_demo=${id}; HttpOnly; SameSite=Strict; Path=/; Max-Age=28800`,
            );
            event(selected.name, 'Masuk ke ruang simulasi', 'Sesi');
            save();
            return send({ user: selected, demo: true });
          }
          if (path === 'logout' && method === 'POST') {
            if (token) sessions.delete(token);
            res.setHeader('Set-Cookie', 'nadi_demo=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0');
            return send({ ok: true });
          }
          if (path === 'public/units' && method === 'GET') return send(orderUnits(db.units!));
          if (path === 'public/statistics' && method === 'GET') {
            const unit = url.searchParams.get('unit') || '',
              type = url.searchParams.get('jenis') || '';
            if (
              [...url.searchParams.keys()].some((k) => !['unit', 'jenis'].includes(k)) ||
              (unit && !db.units!.some((u) => u.label === unit)) ||
              (type && !['PNS', 'PPPK Penuh Waktu', 'PPPK Paruh Waktu'].includes(type))
            )
              return send({ message: 'Filter statistik tidak diizinkan.' }, 422);
            return send(publicStatistics(db.employees, unit, type));
          }
          if (!user) return send({ message: 'Silakan masuk untuk mengakses ruang internal.' }, 401);
          const allowedMutation = ['ADMIN', 'SUPER_ADMIN'].includes(user.role);
          const limited = (e: Employee) => e;
          if (path === 'units' && method === 'GET') return send(orderUnits(db.units!));
          if ((path === 'units' || path.startsWith('units/')) && method !== 'GET') {
            if (user.role !== 'SUPER_ADMIN') return send({ message: 'Hanya Super Admin.' }, 403);
            const label = String(body.label || '').trim(),
              record = db.units!.find((u) => u.id === path.split('/')[1]);
            if (body.active === false) {
              if (!record) return send({ message: 'Unit tidak ditemukan.' }, 404);
              if (db.employees.some((e) => e.unit === record.label))
                return send({ message: 'Unit masih digunakan pegawai.' }, 422);
              db.units = db.units!.filter((u) => u.id !== record.id);
            } else {
              if (
                !label ||
                label.length > 150 ||
                db.units!.some((u) => u.label.toLowerCase() === label.toLowerCase() && u !== record)
              )
                return send({ message: 'Nama unit kosong atau sudah ada.' }, 422);
              if (record) {
                const old = record.label;
                record.label = label;
                db.employees.forEach((e) => {
                  if (e.unit === old) {
                    e.unit = label;
                    e.revision++;
                  }
                });
              } else db.units!.push({ id: randomUUID(), label });
            }
            event(user.name, 'Mengelola unit kerja', label || record?.label || 'Unit');
            save();
            return send({ ok: true }, 201);
          }
          if (path === 'duk' && method === 'GET') {
            const order = db.employees.map((e) => e.id);
            return send({
              rows: db.employees.map(limited),
              order,
              hash: createHash('sha256').update(JSON.stringify(order)).digest('hex'),
            });
          }
          if (path === 'employees' && method === 'GET') {
            let rows = db.employees;
            const search = (url.searchParams.get('search') || '').toLowerCase();
            if (search)
              rows = rows.filter(
                (e) => e.name.toLowerCase().includes(search) || e.nip.includes(search),
              );
            for (const key of ['type', 'unit', 'status'] as const) {
              const value = url.searchParams.get(key);
              if (value) rows = rows.filter((e) => e[key] === value);
            }
            if (url.searchParams.get('quality') === 'incomplete')
              rows = rows.filter((e) => e.completeness < 100);
            return send({ data: rows.map(limited), total: rows.length });
          }
          if (path === 'dashboard') {
            const active = db.employees.filter((e) => e.status === 'Aktif');
            return send({
              total: active.filter((e) => e.type !== 'PPPK Paruh Waktu').length,
              partTime: active.filter((e) => e.type === 'PPPK Paruh Waktu').length,
              pns: active.filter((e) => e.type === 'PNS').length,
              pppk: active.filter((e) => e.type === 'PPPK Penuh Waktu').length,
              pending: db.proposals.filter((p) => p.status === 'PENDING').length,
              incomplete: active.filter((e) => e.completeness < 100).length,
              quality: Math.round(
                active.reduce((n, e) => n + e.completeness, 0) / (active.length || 1),
              ),
              recent: db.audit.slice(0, 6),
              units: publicStatistics(db.employees).units,
              demo: true,
            });
          }
          if (path === 'proposals' && method === 'GET') {
            return send(db.proposals);
          }
          if (['proposals/batch', 'duk/order'].includes(path) && method === 'POST') {
            if (!allowedMutation) return send({ message: 'Akses tidak diizinkan.' }, 403);
            if (
              !Array.isArray(body.rows) ||
              (!body.rows.length && path !== 'duk/order') ||
              body.rows.length > 500 ||
              !body.reason?.trim()
            )
              return send({ message: 'Isi perubahan dan alasan penyimpanan.' }, 422);
            if (path === 'duk/order') {
              if (user.role !== 'SUPER_ADMIN')
                return send({ message: 'Hanya Super Admin dapat mengatur urutan.' }, 403);
              const ids = db.employees.map((e) => e.id);
              if (body.hash !== createHash('sha256').update(JSON.stringify(ids)).digest('hex'))
                return send({ message: 'Urutan berubah. Muat ulang DUK.' }, 409);
              if (
                !Array.isArray(body.order) ||
                JSON.stringify([...body.order].sort()) !== JSON.stringify([...ids].sort())
              )
                return send({ message: 'Urutan tidak lengkap.' }, 422);
            }
            const staged: Proposal[] = [];
            const seen = new Set<string>();
            for (const row of body.rows) {
              const existing = db.employees.find((e) => e.id === row.employeeId);
              if (!existing || seen.has(row.employeeId))
                return send({ message: 'Baris pegawai tidak valid atau berulang.' }, 422);
              seen.add(row.employeeId);
              if (existing.revision !== row.revision)
                return send({ message: 'Data telah berubah. Muat ulang sebelum mengajukan.' }, 409);
              const changes = row.changes || {},
                candidate = { ...existing, ...changes };
              if (
                Object.keys(changes).some(
                  (k) => ![...personnelFields, ...dukFields].some((f) => f.key === k),
                ) ||
                !candidate.name?.trim() ||
                !/^\d{18}$/.test(candidate.nip) ||
                !db.units!.some((u) => u.label === candidate.unit) ||
                !['PNS', 'PPPK Penuh Waktu', 'PPPK Paruh Waktu'].includes(candidate.type) ||
                !['Perempuan', 'Laki-laki'].includes(candidate.gender) ||
                !candidate.position ||
                db.employees.some((e) => e.nip === candidate.nip && e.id !== existing.id)
              )
                return send({ message: 'Periksa isian wajib, NIP, dan unit kerja.' }, 422);
              staged.push({
                id: randomUUID(),
                employeeId: existing.id,
                name: candidate.name,
                makerId: user.id,
                makerName: user.name,
                status: 'PENDING',
                createdAt: new Date().toISOString(),
                reason: body.reason,
                changes,
                before: existing,
                revision: existing.revision,
              });
            }
            if (path === 'duk/order') {
              const map = new Map(db.employees.map((e) => [e.id, e]));
              db.employees = body.order.map((id: string) => map.get(id)!);
              event(user.name, 'Menyimpan urutan DUK manual', 'DUK Digital');
            }
            db.proposals.unshift(...staged);
            event(user.name, 'Menyimpan usulan DUK', `${staged.length} pegawai`);
            save();
            return send({ count: staged.length, ids: staged.map((p) => p.id) }, 201);
          }
          if (path === 'proposals' && method === 'POST') {
            if (!allowedMutation) return send({ message: 'Akses hanya baca.' }, 403);
            const changes = body.changes || {};
            const allowed = [
              ...[...personnelFields, ...dukFields].map((f) => f.key),
              'name',
              'nip',
              'type',
              'gender',
              'unit',
              'position',
              'positionType',
              'grade',
              'education',
              'status',
              'birthPlace',
              'birthDate',
              'effectiveDate',
              'driveUrl',
              'contractStart',
              'contractEnd',
            ];
            if (Object.keys(changes).some((k) => !allowed.includes(k)))
              return send({ message: 'Field tidak diizinkan.' }, 422);
            const existing = body.employeeId
              ? db.employees.find((e) => e.id === body.employeeId)
              : undefined;
            if (body.employeeId && !existing)
              return send({ message: 'Pegawai tidak ditemukan.' }, 404);
            if (existing && existing.revision !== body.revision)
              return send({ message: 'Data telah berubah. Muat ulang sebelum mengajukan.' }, 409);
            const candidate = { ...existing, ...changes };
            if (
              !candidate.name?.trim() ||
              !/^\d{18}$/.test(candidate.nip) ||
              !db.units!.some((u) => u.label === candidate.unit) ||
              !['PNS', 'PPPK Penuh Waktu', 'PPPK Paruh Waktu'].includes(candidate.type) ||
              !['Perempuan', 'Laki-laki'].includes(candidate.gender) ||
              !candidate.position ||
              !['Aktif', 'Pensiun', 'Mutasi', 'Meninggal'].includes(candidate.status)
            )
              return send(
                { message: 'Lengkapi data wajib, NIP 18 digit, dan tanggal efektif.' },
                422,
              );
            if (
              candidate.driveUrl &&
              !/^https:\/\/drive\.google\.com\/drive\/folders\/[a-zA-Z0-9_-]+(?:\?.*)?$/.test(
                candidate.driveUrl,
              )
            )
              return send({ message: 'Gunakan URL folder Google Drive yang valid.' }, 422);
            if (db.employees.some((e) => e.nip === candidate.nip && e.id !== existing?.id))
              return send({ message: 'NIP sudah digunakan pegawai lain.' }, 422);
            if (!body.reason?.trim())
              return send({ message: 'Alasan perubahan wajib diisi.' }, 422);
            const proposal: Proposal = {
              id: randomUUID(),
              employeeId: existing?.id || null,
              name: candidate.name,
              makerId: user.id,
              makerName: user.name,
              status: 'PENDING',
              createdAt: new Date().toISOString(),
              reason: body.reason,
              changes,
              before: existing || {},
              revision: existing?.revision || 0,
            };
            db.proposals.unshift(proposal);
            event(user.name, 'Mengajukan perubahan', candidate.name);
            save();
            return send(proposal, 201);
          }
          if (path.match(/^proposals\/[^/]+\/decide$/) && method === 'POST') {
            if (user.role !== 'SUPER_ADMIN')
              return send({ message: 'Hanya pemeriksa berwenang yang dapat memutuskan.' }, 403);
            const p = db.proposals.find((p) => p.id === path.split('/')[1]);
            if (!p) return send({ message: 'Usulan tidak ditemukan.' }, 404);
            if (p.status !== 'PENDING') return send({ message: 'Usulan sudah diputuskan.' }, 409);
            if (!['APPROVED', 'REJECTED'].includes(body.decision) || !body.reason?.trim())
              return send({ message: 'Keputusan dan catatan pemeriksaan wajib diisi.' }, 422);
            if (body.decision === 'APPROVED') {
              const employee = db.employees.find((e) => e.id === p.employeeId);
              if (employee && employee.revision !== p.revision)
                return send({ message: 'Versi data berubah. Usulan perlu diajukan ulang.' }, 409);
              if (db.employees.some((e) => e.nip === p.changes.nip && e.id !== p.employeeId))
                return send({ message: 'NIP sudah digunakan.' }, 409);
              if (
                p.changes.effectiveDate &&
                p.changes.effectiveDate >
                  new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Makassar' })
              )
                return send(
                  {
                    message:
                      'Penerapan TMT masa depan belum diaktifkan pada versi ini; usulan tetap ditahan.',
                  },
                  422,
                );
              if (employee) {
                Object.assign(employee, p.changes, { revision: employee.revision + 1 });
                Object.assign(employee, normalizeEmployee(employee));
                employee.history = [
                  {
                    date: p.changes.effectiveDate || '2026-09-10',
                    label: 'Perubahan disetujui — ' + body.reason,
                  },
                  ...(employee.history || []),
                ];
              } else
                db.employees.unshift({
                  ...p.changes,
                  id: randomUUID(),
                  revision: 1,
                  completeness: 0,
                  history: [
                    {
                      date: p.changes.effectiveDate || '',
                      label: 'Pegawai baru disetujui (simulasi)',
                    },
                  ],
                } as Employee);
            }
            p.status = body.decision;
            p.decisionReason = body.reason;
            event(
              user.name,
              body.decision === 'APPROVED' ? 'Menyetujui perubahan' : 'Menolak perubahan',
              p.name,
            );
            save();
            return send(p);
          }
          if (path === 'audit' && method === 'GET') {
            return send(db.audit);
          }
          if (path === 'imports' && method === 'GET') {
            if (!allowedMutation) return send({ message: 'Akses tidak diizinkan.' }, 403);
            return send(db.imports);
          }
          if (path === 'imports' && method === 'POST') {
            if (!allowedMutation) return send({ message: 'Akses tidak diizinkan.' }, 403);
            if (!Array.isArray(body.rows) || !body.rows.length || body.rows.length > 1000)
              return send({ message: 'Import memerlukan 1–1.000 baris.' }, 422);
            const checksum = createHash('sha256').update(JSON.stringify(body.rows)).digest('hex');
            if (db.imports.some((b) => b.checksum === checksum) && !body.confirmDuplicate)
              return send(
                { message: 'File identik pernah diperiksa. Konfirmasi pengulangan diperlukan.' },
                409,
              );
            const batch = {
              id: randomUUID(),
              filename: String(body.filename || 'Import').slice(0, 150),
              checksum,
              count: body.rows.length,
              status: 'PREVIEW',
              createdAt: new Date().toISOString(),
              rows: body.rows,
            };
            db.imports.unshift(batch);
            event(user.name, 'Memvalidasi import', batch.filename);
            save();
            return send(batch, 201);
          }
          if (/^radar\/(kp|kgb)(\/[^/]+)?$/.test(path)) {
            const [, kind, id] = path.split('/');
            db.radar ??= {};
            if (method === 'POST') {
              const employee = db.employees.find(
                (e) => e.id === id && e.type === 'PNS' && e.status === 'Aktif',
              );
              if (!employee) return send({ message: 'Radar hanya untuk PNS aktif.' }, 422);
              const key = kind + ':' + id,
                previous = db.radar[key];
              if (
                body.revision !== (previous?.revision || 0) ||
                body.employeeRevision !== employee.revision
              )
                return send({ message: 'Data berubah. Muat ulang terlebih dahulu.' }, 409);
              if (!body.data?.reason || body.data.reason.length < 5)
                return send({ message: 'Alasan perubahan wajib diisi.' }, 422);
              db.radar[key] = {
                data: { ...body.data, employeeRevision: employee.revision },
                revision: (previous?.revision || 0) + 1,
              };
              event(user.name, 'Memperbarui ' + kind.toUpperCase() + ' Radar', employee.name);
              save();
              return send({ ok: true });
            }
            const rows = db.employees
              .filter((e) => e.type === 'PNS' && e.status === 'Aktif')
              .map((employee) => ({
                employee,
                data: db.radar![kind + ':' + employee.id]?.data || {},
                revision: db.radar![kind + ':' + employee.id]?.revision || 0,
              }));
            const binary =
              process.env.NADI_PHP_BINARY ||
              (existsSync('/opt/homebrew/opt/php@8.4/bin/php')
                ? '/opt/homebrew/opt/php@8.4/bin/php'
                : 'php');
            const result = spawnSync(binary, [resolve('scripts/radar-demo.php')], {
              input: JSON.stringify({ kind, rows }),
              encoding: 'utf8',
              timeout: 10000,
              maxBuffer: 4 * 1024 * 1024,
            });
            if (result.status !== 0)
              return send(
                { message: 'Runtime PHP radar simulasi belum tersedia. Gunakan backend utama.' },
                503,
              );
            return send({ rows: JSON.parse(result.stdout), version: '2026-09-22.1' });
          }
          if (path === 'rules')
            return send(
              ['DUK', 'KP', 'KGB'].map((kind) => ({
                kind,
                status: kind === 'DUK' ? 'MANUAL_ORDER' : 'ACTIVE_REMINDER',
                version: 1,
                message:
                  kind === 'DUK'
                    ? 'Urutan mengikuti data sumber dan dapat diubah manual oleh Super Admin.'
                    : 'Radar PNS mengikuti aturan instansi 2026-09-22.1; pemeriksaan dokumen tetap diperlukan.',
              })),
            );
          return send({ message: 'Endpoint tidak tersedia.' }, 404);
        } catch (error) {
          return send(
            {
              message:
                error instanceof SyntaxError
                  ? 'Format permintaan tidak valid.'
                  : 'Permintaan gagal diproses.',
            },
            400,
          );
        }
      });
    },
  };
}
