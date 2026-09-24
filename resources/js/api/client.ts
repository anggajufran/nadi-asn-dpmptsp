import { reactive } from 'vue';
import type { User } from '@/types';
export const session = reactive<{ user: User | null; ready: boolean; demo: boolean }>({
  user: null,
  ready: false,
  demo: false,
});
export async function api<T = any>(path: string, options: RequestInit = {}): Promise<T> {
  const csrf = document.cookie
    .split('; ')
    .find((c) => c.startsWith('XSRF-TOKEN='))
    ?.split('=')
    .slice(1)
    .join('=');
  const response = await fetch(`/api/${path}`, {
    credentials: 'same-origin',
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {}),
      ...options.headers,
    },
  });
  const data = await response
    .json()
    .catch(() => ({ message: 'Respons server tidak dapat dibaca.' }));
  if (!response.ok) {
    if (response.status === 401) session.user = null;
    const details = data.errors ? Object.values(data.errors).flat().join(' ') : '';
    throw new Error(details || data.message || 'Permintaan tidak dapat diproses.');
  }
  return data;
}
export const post = <T = any>(path: string, data: unknown) =>
  api<T>(path, { method: 'POST', body: JSON.stringify(data) });
export async function loadSession() {
  try {
    const result = await api('session');
    session.user = result.user;
    session.demo = result.demo;
  } finally {
    session.ready = true;
  }
}
/** Follow bounded backend pagination; the demo returns the complete synthetic dataset. */
export async function allEmployees(query = '') {
  const result = await api('employees' + (query ? '?' + query : ''));
  const rows = [...result.data];
  if (result.per_page) {
    const pages = Math.ceil(result.total / result.per_page);
    for (let page = 2; page <= pages; page++) {
      const params = new URLSearchParams(query);
      params.set('page', String(page));
      params.set('per_page', String(result.per_page));
      rows.push(...(await api('employees?' + params)).data);
    }
  }
  return { ...result, data: rows };
}

export async function waitForResult(path: string, success: string) {
  for (let attempt = 0; attempt < 60; attempt++) {
    const result = await api(path);
    if (result.status === success) return result;
    if (result.status === 'FAILED')
      throw new Error(result.error || 'Pemrosesan gagal. Silakan coba kembali.');
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  throw new Error('Pemrosesan masih berlangsung. Periksa riwayat beberapa saat lagi.');
}
export async function downloadFile(path: string, filename: string) {
  const response = await fetch('/api/' + path, {
    credentials: 'same-origin',
    headers: { Accept: '*/*' },
  });
  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new Error(data.message || 'Berkas belum tersedia.');
  }
  const url = URL.createObjectURL(await response.blob());
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
