import { beforeAll, afterAll, describe, it, expect } from 'vitest';
import { spawn, type ChildProcess } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
let server: ChildProcess;
const dir = mkdtempSync(join(tmpdir(), 'nadi-api-test-')),
  origin = 'http://127.0.0.1:5174';
const tokens: Record<string, string> = {};
async function request(path: string, role = '', body?: unknown) {
  const r = await fetch(origin + '/api/' + path, {
    method: body ? 'POST' : 'GET',
    headers: {
      Origin: origin,
      'Content-Type': 'application/json',
      ...(role ? { Cookie: tokens[role] } : {}),
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
  return {
    status: r.status,
    data: await r.json(),
    cookie: r.headers.get('set-cookie')?.split(';')[0],
  };
}
beforeAll(async () => {
  server = spawn(
    process.execPath,
    ['node_modules/vite/bin/vite.js', '--host', '127.0.0.1', '--port', '5174'],
    { env: { ...process.env, NADI_DEMO_STATE: join(dir, 'state.json') }, stdio: 'pipe' },
  );
  let ready = false;
  for (let i = 0; i < 80; i++) {
    try {
      const r = await fetch(origin + '/api/session');
      if (r.ok) {
        ready = true;
        break;
      }
    } catch {}
    await new Promise((r) => setTimeout(r, 150));
  }
  if (!ready) throw Error('Test server failed to start');
  for (const role of ['ADMIN', 'SUPER_ADMIN']) {
    const r = await request('demo/login', '', { role });
    tokens[role] = r.cookie!;
  }
}, 20000);
afterAll(() => {
  server?.kill('SIGTERM');
  rmSync(dir, { recursive: true, force: true });
});
describe.sequential('Authorization dan maker-checker pada server simulasi', () => {
  let employee: any, proposalId: string;
  it('menolak akses internal tanpa sesi', async () => {
    expect((await request('employees')).status).toBe(401);
  });
  it('menolak origin lain', async () => {
    const r = await fetch(origin + '/api/demo/login', {
      method: 'POST',
      headers: { Origin: 'https://external.example', 'Content-Type': 'application/json' },
      body: JSON.stringify({ role: 'ADMIN' }),
    });
    expect(r.status).toBe(403);
  });
  it('public filter allowlist menolak NIP', async () => {
    expect((await request('public/statistics?nip=199001012020010001')).status).toBe(422);
  });
  it('menolak peran yang sudah dihapus', async () => {
    expect((await request('demo/login', '', { role: 'VIEWER' })).status).toBe(422);
  });
  it('perubahan tidak menggantikan data resmi sebelum persetujuan', async () => {
    employee = (await request('employees', 'ADMIN')).data.data[0];
    const r = await request('proposals', 'ADMIN', {
      employeeId: employee.id,
      revision: employee.revision,
      changes: { name: 'Nama Uji Sintetis, S.E.' },
      reason: 'Pengujian perubahan nama',
    });
    expect(r.status).toBe(201);
    proposalId = r.data.id;
    expect(
      (await request('employees', 'ADMIN')).data.data.find((e: any) => e.id === employee.id).name,
    ).toBe(employee.name);
  });
  it('admin tanpa izin tidak dapat memutuskan usulan', async () => {
    expect(
      (
        await request(`proposals/${proposalId}/decide`, 'ADMIN', {
          decision: 'APPROVED',
          reason: 'Memeriksa usulan sendiri',
        })
      ).status,
    ).toBe(403);
  });
  it('checker berbeda menerapkan perubahan dan menaikkan revision', async () => {
    const r = await request(`proposals/${proposalId}/decide`, 'SUPER_ADMIN', {
      decision: 'APPROVED',
      reason: 'Nilai telah sesuai sumber uji',
    });
    expect(r.status).toBe(200);
    const updated = (await request('employees', 'ADMIN')).data.data.find(
      (e: any) => e.id === employee.id,
    );
    expect(updated.name).toBe('Nama Uji Sintetis, S.E.');
    expect(updated.revision).toBe(employee.revision + 1);
  });
  it('keputusan tidak dapat diterapkan dua kali', async () => {
    expect(
      (
        await request(`proposals/${proposalId}/decide`, 'SUPER_ADMIN', {
          decision: 'APPROVED',
          reason: 'Pengulangan',
        })
      ).status,
    ).toBe(409);
  });
  it('menolak edit memakai revision lama', async () => {
    expect(
      (
        await request('proposals', 'ADMIN', {
          employeeId: employee.id,
          revision: employee.revision,
          changes: { name: 'Usulan lama' },
          reason: 'Pengujian stale version',
        })
      ).status,
    ).toBe(409);
  });
  it('super admin dapat menyetujui usulan sendiri', async () => {
    const e = (await request('employees', 'SUPER_ADMIN')).data.data[1];
    const r = await request('proposals', 'SUPER_ADMIN', {
      employeeId: e.id,
      revision: e.revision,
      changes: { name: 'Usulan sendiri' },
      reason: 'Pengujian maker checker',
    });
    expect(r.status).toBe(201);
    expect(
      (
        await request(`proposals/${r.data.id}/decide`, 'SUPER_ADMIN', {
          decision: 'APPROVED',
          reason: 'Tidak boleh',
        })
      ).status,
    ).toBe(200);
  });
  it('mencatat penerapan perubahan pada audit', async () => {
    const audit = (await request('audit', 'ADMIN')).data;
    expect(audit.some((e: any) => e.action === 'Menyetujui perubahan')).toBe(true);
  });
  it('tidak menciptakan tanggal atau urutan dari aturan yang belum diverifikasi', async () => {
    const rules = (await request('rules', 'ADMIN')).data;
    expect(
      rules.filter((r: any) => r.kind !== 'DUK').every((r: any) => r.status === 'ACTIVE_REMINDER'),
    ).toBe(true);
    expect(rules.find((r: any) => r.kind === 'DUK').status).toBe('MANUAL_ORDER');
  });
});
