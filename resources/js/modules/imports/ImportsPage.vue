<script setup lang="ts">
const statusLabels: Record<string, string> = {
  UPLOADED: 'Menunggu mapping',
  VALIDATING: 'Sedang diperiksa',
  PREVIEW: 'Siap ditinjau',
  CONFIRMED: 'Diajukan',
  FAILED: 'Perlu perbaikan',
};
import { ref, computed, onMounted } from 'vue';
import { api, post, allEmployees, session, waitForResult } from '@/api/client';
import { notify, dateLabel } from '@/composables';
import Icon from '@/components/Icon.vue';
import { personnelFields } from '@/domain/personnel';
import type { Employee } from '@/types';
const input = ref<HTMLInputElement>(),
  file = ref<File | null>(null),
  headers = ref<string[]>([]),
  rawRows = ref<Record<string, string>[]>([]),
  mapping = ref<Record<string, string>>({}),
  step = ref(1),
  busy = ref(false),
  error = ref(''),
  employees = ref<Employee[]>([]),
  batches = ref<any[]>([]),
  result = ref<any[]>([]),
  confirmed = ref(false),
  duplicate = ref(false),
  duplicateDetected = ref(false),
  reason = ref(
    session.demo ? 'Migrasi data dari berkas Excel — simulasi' : 'Migrasi data dari berkas Excel',
  ),
  batch = ref<any>(null);
const fields = personnelFields
  .filter((f) => f.key !== 'ageAtReference')
  .map((f) => [f.key, f.label]);
const valid = computed(() =>
  result.value.filter((r) => !r.errors.length && Object.keys(r.changes).length),
);
onMounted(async () => {
  try {
    if (session.demo) employees.value = (await allEmployees()).data;
    batches.value = await api('imports');
  } catch (e) {
    error.value = (e as Error).message;
  }
});
async function resumeBatch(id: string) {
  busy.value = true;
  error.value = '';
  confirmed.value = false;
  duplicate.value = false;
  try {
    batch.value = await api(`imports/${id}`);
    headers.value = batch.value.headers;
    mapping.value = batch.value.mapping || Object.fromEntries(fields.map(([key]) => [key, '']));
    if (batch.value.status === 'VALIDATING')
      batch.value = await waitForResult(`imports/${id}`, 'PREVIEW');
    result.value = batch.value.rows || [];
    step.value = batch.value.status === 'CONFIRMED' ? 4 : batch.value.status === 'PREVIEW' ? 3 : 2;
    if (batch.value.error) error.value = batch.value.error;
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function selectFile(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0];
  if (f) await parse(f);
}
async function parse(f: File) {
  error.value = '';
  duplicateDetected.value = false;
  if (!f.name.toLowerCase().endsWith('.xlsx') || f.size > 5 * 1024 * 1024) {
    error.value = 'Gunakan berkas .xlsx dengan ukuran maksimal 5 MB.';
    return;
  }
  busy.value = true;
  try {
    if (!session.demo) {
      const data = new FormData();
      data.append('file', f);
      batch.value = await api('imports', { method: 'POST', body: data });
      headers.value = batch.value.headers;
      mapping.value = Object.fromEntries(
        fields.map(([key, label]) => [
          key,
          headers.value.find(
            (h) => h.toLowerCase() === key.toLowerCase() || h.toLowerCase() === label.toLowerCase(),
          ) || '',
        ]),
      );
      file.value = f;
      step.value = 2;
      confirmed.value = false;
      duplicate.value = false;
      return;
    }
    const ExcelJS = await import('exceljs');
    const wb = new ExcelJS.Workbook();
    await wb.xlsx.load(await f.arrayBuffer());
    const sheet = wb.worksheets[0];
    if (!sheet || sheet.rowCount < 2)
      throw new Error('Berkas harus memiliki header dan minimal satu baris data.');
    if (sheet.rowCount > 1001) throw new Error('Maksimal 1.000 baris data per import simulasi.');
    const row1 = sheet.getRow(1);
    headers.value = [];
    row1.eachCell({ includeEmpty: true }, (cell, i) => {
      headers.value[i - 1] = cell.text.trim();
    });
    if (headers.value.some((h) => !h) || new Set(headers.value).size !== headers.value.length)
      throw new Error('Header tidak boleh kosong atau duplikat.');
    rawRows.value = [];
    sheet.eachRow((row, i) => {
      if (i === 1) return;
      const record: Record<string, string> = {};
      headers.value.forEach((h, j) => {
        const cell = row.getCell(j + 1);
        if (cell.type === ExcelJS.ValueType.Formula)
          throw new Error(`Formula tidak diterima pada baris ${i}. Gunakan nilai teks.`);
        if (h.toLowerCase().includes('nip') && typeof cell.value === 'number')
          throw new Error(
            `NIP baris ${i} harus disimpan sebagai teks agar 18 digit tidak berubah.`,
          );
        record[h] =
          cell.value instanceof Date ? cell.value.toISOString().slice(0, 10) : cell.text.trim();
      });
      rawRows.value.push(record);
    });
    mapping.value = {};
    fields.forEach(([key, label]) => {
      mapping.value[key] =
        headers.value.find(
          (h) => h.toLowerCase() === key.toLowerCase() || h.toLowerCase() === label.toLowerCase(),
        ) || '';
    });
    file.value = f;
    step.value = 2;
    batch.value = null;
    confirmed.value = false;
    duplicate.value = false;
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function validate() {
  error.value = '';
  const mappedHeaders = Object.values(mapping.value).filter(Boolean);
  if (new Set(mappedHeaders).size !== mappedHeaders.length) {
    error.value = 'Satu kolom tidak boleh dipetakan ke beberapa field.';
    return;
  }
  if (!session.demo) {
    busy.value = true;
    try {
      await post(`imports/${batch.value.id}/validate`, { mapping: mapping.value });
      batch.value = await waitForResult(`imports/${batch.value.id}`, 'PREVIEW');
      result.value = batch.value.rows;
      step.value = 3;
    } catch (e) {
      error.value = (e as Error).message;
    } finally {
      busy.value = false;
    }
    return;
  }
  const nipCounts = new Map<string, number>();
  for (const row of rawRows.value) {
    const nip = row[mapping.value.nip];
    nipCounts.set(nip, (nipCounts.get(nip) || 0) + 1);
  }
  result.value = rawRows.value.map((raw, i) => {
    const mapped = Object.fromEntries(
      fields.filter(([key]) => mapping.value[key]).map(([key]) => [key, raw[mapping.value[key]]]),
    );
    const errors: string[] = [];
    if (!/^\d{18}$/.test(mapped.nip || '')) errors.push('NIP harus 18 digit teks');
    if ((nipCounts.get(mapped.nip) || 0) > 1) errors.push('NIP duplikat dalam berkas');
    const existing = employees.value.find((e) => e.nip === mapped.nip);
    const changes: Record<string, any> = existing ? {} : { status: 'Aktif' };
    for (const [key, value] of Object.entries(mapped)) {
      if (value !== '' && value !== String((existing as any)?.[key] || '')) changes[key] = value;
    }
    if (!existing) {
      for (const key of [
        'name',
        'type',
        'unit',
        'gender',
        'position',
        'positionType',
        'rankName',
        'effectiveDate',
      ])
        if (!mapped[key]) errors.push(`${fields.find((f) => f[0] === key)?.[1]} wajib`);
    }
    const merged = { ...existing, ...changes };

    return { line: i + 2, mapped, existing, changes, errors };
  });
  step.value = 3;
}
async function confirm() {
  busy.value = true;
  error.value = '';
  try {
    if (!session.demo) {
      const submitted = await post(`imports/${batch.value.id}/confirm`, {
        previewHash: batch.value.previewHash,
        reason: reason.value,
        confirmDuplicate: duplicate.value,
      });
      step.value = 4;
      batches.value = await api('imports');
      notify(`${submitted.count} usulan import diajukan dalam satu transaksi.`);
      return;
    }
    if (!batch.value)
      batch.value = await post('imports', {
        filename: file.value?.name,
        rows: rawRows.value,
        confirmDuplicate: duplicate.value,
      });
    let done = 0;
    for (const row of valid.value) {
      if (row.submitted) continue;
      await post('proposals', {
        employeeId: row.existing?.id,
        revision: row.existing?.revision,
        changes: row.changes,
        reason: `${reason.value} | Batch ${batch.value.id}`,
      });
      row.submitted = true;
      done++;
    }
    step.value = 4;
    batches.value = await api('imports');
    notify(`${done} usulan import diajukan untuk pemeriksaan.`);
  } catch (e) {
    error.value = (e as Error).message;
    if (error.value.includes('identik')) duplicateDetected.value = true;
  } finally {
    busy.value = false;
  }
}
async function template() {
  const ExcelJS = await import('exceljs');
  const wb = new ExcelJS.Workbook();
  const sheet = wb.addWorksheet('Data Pegawai');
  sheet.addRow(fields.map((f) => f[1]));
  sheet.addRow([
    'Pegawai Contoh',
    '199001012020019999',
    'Penata Muda / III a',
    '2024-01-01',
    'Pengelola Administrasi',
    '2024-01-01',
    2,
    8,
    'Perguruan Tinggi Contoh',
    '2019-07-01',
    'S1',
    'Kota Contoh',
    '1990-01-01',
    'Perempuan',
    'PNS',
    'Pelaksana',
    'Isi sesuai daftar unit kerja',
  ]);
  sheet.getColumn(2).numFmt = '@';
  sheet.columns.forEach((c) => (c.width = 25));
  sheet.getRow(1).font = { bold: true };
  sheet.getCell('D1').note =
    'Tanggal memakai YYYY-MM-DD atau sel tanggal Excel. NIP wajib teks. Urutan pegawai diatur melalui DUK Digital. Masa kerja diisi sesuai bulan impor; usia otomatis dari tanggal lahir.';
  const bytes = await wb.xlsx.writeBuffer();
  const url = URL.createObjectURL(new Blob([bytes]));
  const a = document.createElement('a');
  a.href = url;
  a.download = 'Template-Import-NADI-ASN.xlsx';
  a.click();
  URL.revokeObjectURL(url);
}
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">MIGRASI & PERTUKARAN DATA</span>
      <h1>Import Excel<span class="heading-dot">.</span></h1>
      <p>Dari berkas sumber menuju data yang terstruktur dan terverifikasi.</p>
    </div>
    <button class="button button-outline" @click="template">
      <Icon name="ArrowDownToLine" :size="17" />Unduh template
    </button>
  </div>
  <div class="import-steps">
    <div
      v-for="(label, i) in ['Upload berkas', 'Mapping kolom', 'Validasi & preview', 'Pengajuan']"
      :key="label"
      :class="{ active: step === i + 1, done: step > i + 1 }"
    >
      <span
        ><Icon v-if="step > i + 1" name="Check" :size="16" /><template v-else>{{
          i + 1
        }}</template></span
      ><strong>{{ label }}</strong
      ><i v-if="i < 3"></i>
    </div>
  </div>
  <div v-if="error" class="notice danger section-space" role="alert">
    <Icon name="AlertTriangle" />
    <p>{{ error }}</p>
  </div>
  <section v-if="step === 1" class="card upload-card">
    <input
      ref="input"
      type="file"
      accept=".xlsx"
      class="sr-only"
      aria-label="Pilih berkas Excel"
      @change="selectFile"
    />
    <div
      class="drop-zone"
      @dragover.prevent
      @drop.prevent="
        (e) => {
          const f = e.dataTransfer?.files[0];
          if (f) parse(f);
        }
      "
    >
      <div class="upload-icon"><Icon name="UploadCloud" :size="36" /></div>
      <h2>Mulai dari berkas Excel Anda</h2>
      <p>
        Tarik dan lepas berkas di sini, atau pilih dari perangkat.<br />Format .xlsx · Maksimal 5 MB
        · Hingga 1.000 baris
      </p>
      <button class="button button-primary" :disabled="busy" @click="input?.click()">
        <Icon name="Plus" :size="17" />{{ busy ? 'Membaca berkas…' : 'Pilih berkas Excel' }}
      </button>
    </div>
    <div class="import-notes">
      <div>
        <Icon name="ShieldCheck" /><strong>Data resmi tetap aman</strong>
        <p>Import menjadi usulan, bukan perubahan langsung.</p>
      </div>
      <div>
        <Icon name="ChevronsUpDown" /><strong>Pencocokan NIP exact</strong>
        <p>Simpan NIP sebagai teks, tanpa pembulatan digit.</p>
      </div>
      <div>
        <Icon name="Eye" /><strong>Periksa sebelum mengajukan</strong>
        <p>Bandingkan nilai lama dan baru pada preview.</p>
      </div>
    </div>
  </section>
  <section v-if="step === 2" class="card mapping-card">
    <div class="card-heading">
      <div>
        <h3>Hubungkan kolom data</h3>
        <p>
          {{ batch?.filename || file?.name }} · {{ batch?.count || rawRows.length }} baris ditemukan
        </p>
      </div>
      <span class="badge blue">Kolom kosong diabaikan</span>
    </div>
    <div class="mapping-grid">
      <label v-for="[key, label] in fields" :key="key"
        >{{ label
        }}<select v-model="mapping[key]">
          <option value="">Tidak dipetakan</option>
          <option v-for="h in headers" :key="h">{{ h }}</option>
        </select></label
      >
    </div>
    <div class="modal-actions">
      <button class="button button-outline" @click="step = 1">Kembali</button
      ><button class="button button-primary" :disabled="!mapping.nip || busy" @click="validate">
        {{ busy ? 'Memvalidasi berkas…' : 'Validasi & lihat perubahan'
        }}<Icon name="ArrowRight" :size="16" />
      </button>
    </div>
  </section>
  <section v-if="step === 3" class="card">
    <div class="table-toolbar">
      <div>
        <h3>Preview hasil validasi</h3>
        <p>
          {{ valid.length }} baris berubah dan siap diajukan ·
          {{ result.filter((r) => r.errors.length).length }} perlu perbaikan ·
          {{ result.filter((r) => !r.errors.length && !Object.keys(r.changes).length).length }}
          tidak berubah
        </p>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>BARIS</th>
            <th>NAMA / NIP</th>
            <th>PENCOCOKAN</th>
            <th>PERUBAHAN</th>
            <th>HASIL</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in result" :key="r.line">
            <td>{{ r.line }}</td>
            <td>
              <div class="table-detail">
                <strong>{{ r.mapped.name || r.existing?.name || 'Tanpa nama' }}</strong
                ><small>{{ r.mapped.nip }}</small>
              </div>
            </td>
            <td>{{ r.existing ? 'NIP ditemukan' : 'Pegawai baru' }}</td>
            <td>
              <div class="import-diff" v-for="(v, k) in r.changes" :key="k">
                <span>{{ fields.find((f) => f[0] === k)?.[1] }}:</span>
                {{ r.existing?.[k] || '—' }} → <strong>{{ v }}</strong>
              </div>
            </td>
            <td>
              <span class="badge" :class="r.errors.length ? 'red' : 'green'">{{
                r.errors.length
                  ? 'Perlu perbaikan'
                  : Object.keys(r.changes).length
                    ? 'Siap diajukan'
                    : 'Tidak berubah'
              }}</span>
              <p v-for="err in r.errors" :key="err" class="muted-small">{{ err }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="import-confirm">
      <label>Alasan pengajuan<textarea v-model="reason" rows="2" /></label
      ><label class="checkbox-label"
        ><input v-model="confirmed" type="checkbox" />Saya sudah memeriksa perubahan. Hanya baris
        valid akan diajukan.</label
      >
      <label v-if="batch?.duplicateOf || duplicateDetected" class="checkbox-label"
        ><input v-model="duplicate" type="checkbox" />Berkas identik pernah diunggah. Saya ingin
        mengajukan ulang dengan alasan di atas.</label
      >
      <div class="modal-actions">
        <button class="button button-outline" @click="step = 2">Ubah mapping</button
        ><button
          class="button button-primary"
          :disabled="
            !confirmed ||
            !valid.length ||
            busy ||
            reason.trim().length < 5 ||
            ((batch?.duplicateOf || duplicateDetected) && !duplicate)
          "
          @click="confirm"
        >
          {{ busy ? 'Mengajukan…' : `Ajukan ${valid.length} baris valid`
          }}<Icon name="ArrowRight" :size="16" />
        </button>
      </div>
    </div>
  </section>
  <section v-if="step === 4" class="card empty-state">
    <div class="empty-icon success"><Icon name="CheckCheck" :size="35" /></div>
    <h2>Data berhasil diajukan</h2>
    <p>Usulan menunggu pemeriksaan. Data resmi tetap digunakan sampai persetujuan selesai.</p>
    <div class="button-row">
      <button class="button button-outline" @click="step = 1">Import berkas lain</button
      ><RouterLink to="/internal/persetujuan" class="button button-primary"
        >Lihat persetujuan<Icon name="ArrowRight" :size="16"
      /></RouterLink>
    </div>
  </section>
  <section v-if="step === 1" class="card section-space">
    <div class="table-toolbar">
      <div>
        <h3>Riwayat pemeriksaan berkas</h3>
        <p>Metadata import yang pernah divalidasi</p>
      </div>
      <Icon name="History" />
    </div>
    <div v-if="!batches.length" class="empty-inline">Belum ada berkas yang diperiksa.</div>
    <div v-else class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>BERKAS</th>
            <th>BATCH</th>
            <th>JUMLAH BARIS</th>
            <th>TANGGAL</th>
            <th v-if="!session.demo">STATUS / AKSI</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in batches" :key="b.id">
            <td>{{ b.filename }}</td>
            <td>{{ b.id.slice(0, 8) }}</td>
            <td>{{ b.count }}</td>
            <td>{{ dateLabel(b.createdAt) }}</td>
            <td v-if="!session.demo">
              <span class="badge blue">{{ statusLabels[b.status] || b.status }}</span>
              <button class="button button-outline" :disabled="busy" @click="resumeBatch(b.id)">
                {{ b.status === 'CONFIRMED' ? 'Lihat' : 'Lanjutkan' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
