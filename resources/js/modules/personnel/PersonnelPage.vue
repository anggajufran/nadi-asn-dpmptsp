<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import Icon from '@/components/Icon.vue';
import Modal from '@/components/Modal.vue';
import { api, post, session, allEmployees, waitForResult, downloadFile } from '@/api/client';
import { notify, dateLabel, initials } from '@/composables';
import { personnelFields, employeeTypes, completeness } from '@/domain/personnel';
import type { Employee } from '@/types';
const route = useRoute(),
  rows = ref<Employee[]>([]),
  units = ref<string[]>([]),
  search = ref(String(route.query.q || '')),
  type = ref(''),
  positionType = ref(''),
  unit = ref(''),
  status = ref('Aktif'),
  quality = ref(String(route.query.quality || '')),
  page = ref(1),
  pageSize = ref(10),
  loading = ref(true),
  error = ref(''),
  detail = ref<Employee | null>(null),
  editing = ref(false),
  current = ref<Employee | null>(null),
  saving = ref(false),
  formError = ref(''),
  reason = ref(''),
  form = ref<Record<string, any>>({});
const fields = personnelFields,
  filtered = computed(() =>
    rows.value.filter(
      (e) =>
        (!search.value ||
          e.name.toLowerCase().includes(search.value.toLowerCase()) ||
          e.nip.includes(search.value)) &&
        (!type.value || e.type === type.value) &&
        (!positionType.value || e.positionType === positionType.value) &&
        (!unit.value || e.unit === unit.value) &&
        (!status.value || e.status === status.value) &&
        (!quality.value || e.completeness < 100),
    ),
  ),
  pages = computed(() =>
    Math.max(
      1,
      Math.ceil(filtered.value.length / (pageSize.value || Math.max(1, filtered.value.length))),
    ),
  ),
  previewQuality = computed(() => completeness(form.value));
watch([search, type, positionType, unit, status, quality, pageSize], () => (page.value = 1));
watch(
  () => route.query.q,
  (q) => (search.value = String(q || '')),
);
async function load() {
  loading.value = true;
  try {
    const [data, refs] = await Promise.all([allEmployees(), api('units')]);
    rows.value = data.data;
    units.value = refs.map((u: any) => u.label);
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}
onMounted(load);
function display(e: any, f: any) {
  const v = e[f.key];
  return v === null || v === undefined || v === '' ? '—' : f.type === 'date' ? dateLabel(v) : v;
}
function edit(e?: Employee) {
  current.value = e || null;
  form.value = Object.fromEntries(fields.map((f) => [f.key, (e as any)?.[f.key] ?? '']));
  Object.assign(form.value, {
    status: e?.status || 'Aktif',
    type: e?.type || 'PNS',
    effectiveDate: e
      ? e.effectiveDate || ''
      : new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Makassar' }),
    driveUrl: e?.driveUrl || '',
    contractStart: e?.contractStart || '',
    contractEnd: e?.contractEnd || '',
  });
  reason.value = '';
  formError.value = '';
  detail.value = null;
  editing.value = true;
}
function options(key: string) {
  return key === 'type'
    ? employeeTypes
    : key === 'gender'
      ? ['Laki-laki', 'Perempuan']
      : key === 'positionType'
        ? ['Struktural', 'Fungsional', 'Pelaksana']
        : units.value;
}
async function submit() {
  saving.value = true;
  formError.value = '';
  try {
    const candidate = { ...form.value };
    for (const f of fields)
      if (f.type === 'number')
        candidate[f.key] = candidate[f.key] === '' ? null : Number(candidate[f.key]);
    const changes = Object.fromEntries(
      Object.entries(candidate).filter(
        ([k, v]) =>
          k !== 'sourceOrder' &&
          k !== 'ageAtReference' &&
          (!current.value || String(v ?? '') !== String((current.value as any)[k] ?? '')),
      ),
    );
    if (!Object.keys(changes).length) throw new Error('Belum ada perubahan.');
    if ('serviceYears' in changes || 'serviceMonths' in changes) {
      changes.serviceYears = candidate.serviceYears;
      changes.serviceMonths = candidate.serviceMonths;
      if (current.value?.serviceReference)
        changes.serviceReference = current.value.serviceReference;
    }
    await post('proposals', {
      employeeId: current.value?.id,
      revision: current.value?.revision,
      changes,
      reason: reason.value,
    });
    editing.value = false;
    notify('Perubahan tersimpan sebagai usulan. Kelengkapan dihitung ulang setelah disetujui.');
    await load();
  } catch (e) {
    formError.value = (e as Error).message;
  } finally {
    saving.value = false;
  }
}
async function download() {
  try {
    if (session.demo) {
      const { exportEmployees } = await import('@/modules/reports/export');
      await exportEmployees(filtered.value);
    } else {
      const job = await post('exports', {
        kind: 'employees',
        unit: unit.value,
        type: type.value,
        positionType: positionType.value,
        status: status.value || null,
        search: search.value,
        quality: Boolean(quality.value),
        allStatuses: !status.value,
      });
      await waitForResult(`exports/${job.id}`, 'READY');
      await downloadFile(`exports/${job.id}/download`, 'NADI-Data-Pegawai.xlsx');
    }
    notify('Data sesuai filter berhasil diunduh.');
  } catch (e) {
    notify((e as Error).message);
  }
}
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">DIREKTORI KEPEGAWAIAN</span>
      <h1>Master Pegawai<span class="heading-dot">.</span></h1>
      <p>Data lengkap sesuai daftar pegawai 2026, dalam satu sumber yang dapat ditelusuri.</p>
    </div>
    <div class="button-row">
      <button
        v-if="session.user?.role === 'SUPER_ADMIN'"
        class="button button-outline"
        @click="download"
      >
        <Icon name="Download" :size="17" />Export Excel</button
      ><button class="button button-primary" @click="edit()">
        <Icon name="Plus" :size="17" />Tambah pegawai
      </button>
    </div>
  </div>
  <div class="directory-summary">
    <span
      ><strong>{{ rows.filter((e) => e.status === 'Aktif').length }}</strong> pegawai aktif</span
    ><span
      ><strong>{{ rows.filter((e) => e.status === 'Aktif' && e.type !== 'PPPK Paruh Waktu').length }}</strong> ASN</span
    ><span
      ><strong>{{ rows.filter((e) => e.status === 'Aktif' && e.type === 'PPPK Paruh Waktu').length }}</strong> PPPK Paruh
      Waktu</span
    ><span class="text-muted"
      ><strong>{{ rows.filter((e) => e.status !== 'Aktif').length }}</strong> non-aktif</span
    ><button class="text-link" @click="quality = quality ? '' : 'incomplete'">
      {{ rows.filter((e) => e.completeness < 100).length }} perlu dilengkapi
    </button>
  </div>
  <section class="card section-space">
    <div class="table-toolbar directory-filters">
      <div class="search-field">
        <Icon name="Search" :size="17" /><input
          v-model="search"
          aria-label="Cari pegawai"
          placeholder="Cari nama atau NIP…"
        />
      </div>
      <select v-model="type" aria-label="Filter jenis pegawai">
        <option value="">Semua jenis pegawai</option>
        <option v-for="t in employeeTypes" :key="t">{{ t }}</option></select
      ><select v-model="positionType" aria-label="Filter jenis jabatan">
        <option value="">Semua jenis jabatan</option>
        <option v-for="j in ['Struktural', 'Fungsional', 'Pelaksana']" :key="j">
          {{ j }}
        </option></select
      ><select v-model="unit" aria-label="Filter unit kerja">
        <option value="">Semua unit kerja</option>
        <option v-for="u in units" :key="u">{{ u }}</option></select
      ><select v-model="status" aria-label="Filter status pegawai">
        <option value="">Semua status</option>
        <option v-for="s in ['Aktif', 'Pensiun', 'Mutasi', 'Meninggal', 'Mengundurkan Diri']" :key="s">
          {{ s }}
        </option></select
      ><button v-if="quality" class="button button-outline" @click="quality = ''">
        Hapus filter kelengkapan
      </button>
    </div>
    <div class="sheet-hint">
      <Icon name="MoveHorizontal" :size="16" />Geser tabel untuk melihat seluruh kolom pegawai. Masa
      kerja bertambah setiap bulan; usia dihitung dari tanggal lahir.
    </div>
    <div v-if="loading" class="loading-state">Memuat direktori…</div>
    <p v-else-if="error" role="alert" class="form-error">{{ error }}</p>
    <div v-else class="table-wrap personnel-sheet master-sheet">
      <table>
        <thead>
          <tr>
            <th v-for="f in fields" :key="f.key">{{ f.label }}</th>
            <th>Kelengkapan</th>
            <th>Status / aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="e in pageSize
              ? filtered.slice((page - 1) * pageSize, page * pageSize)
              : filtered"
            :key="e.id"
          >
            <td v-for="f in fields" :key="f.key">
              <button v-if="f.key === 'name'" class="employee-name" @click="detail = e">
                {{ e.name }}</button
              ><span v-else>{{ display(e, f) }}</span>
            </td>
            <td>
              <button
                class="quality-button"
                @click="detail = e"
                :title="e.missingFields?.join(', ')"
              >
                <span class="badge" :class="e.completeness === 100 ? 'green' : 'amber'"
                  >{{ e.completeness }}%</span
                >
              </button>
            </td>
            <td>
              <span
                class="badge"
                :class="
                  e.status === 'Aktif'
                    ? 'green'
                    : e.status === 'Mengundurkan Diri' || e.status === 'Meninggal'
                      ? 'red'
                      : 'gray'
                "
                >{{ e.status }}</span
              ><button class="icon-button" :aria-label="`Lihat ${e.name}`" @click="detail = e">
                <Icon name="Eye" :size="16" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!filtered.length" class="empty-inline">Tidak ada data yang sesuai filter.</div>
    </div>
    <div class="pagination">
      <label class="page-size"
        >Tampilkan
        <select v-model.number="pageSize" aria-label="Jumlah pegawai per halaman">
          <option :value="10">10</option>
          <option :value="30">30</option>
          <option :value="50">50</option>
          <option :value="0">All</option>
        </select></label
      >
      <span>{{ filtered.length }} pegawai · halaman {{ page }} dari {{ pages }}</span>
      <div>
        <button
          class="icon-button"
          :disabled="page === 1"
          aria-label="Halaman sebelumnya"
          @click="page--"
        >
          <Icon name="ChevronLeft" /></button
        ><button
          class="icon-button"
          :disabled="page === pages"
          aria-label="Halaman berikutnya"
          @click="page++"
        >
          <Icon name="ChevronRight" />
        </button>
      </div>
    </div>
  </section>
  <Modal v-if="detail" title="Profil pegawai" wide @close="detail = null"
    ><div class="employee-profile">
      <div class="avatar large">{{ initials(detail.name) }}</div>
      <div>
        <h2>{{ detail.name }}</h2>
        <p>{{ detail.nip }}</p>
        <span class="badge blue">{{ detail.type }}</span>
      </div>
      <button class="button button-outline" @click="edit(detail)">
        <Icon name="Pencil" :size="16" />Usulkan perubahan
      </button>
    </div>
    <div class="notice section-space" :class="detail.completeness === 100 ? '' : 'warning'">
      <Icon name="ClipboardCheck" />
      <div>
        <strong>Kelengkapan {{ detail.completeness }}%</strong>
        <p>
          {{
            detail.missingFields?.length
              ? 'Belum terisi: ' + detail.missingFields.join(', ')
              : 'Seluruh kolom yang dinilai telah terisi.'
          }}
        </p>
      </div>
    </div>
    <div v-if="detail.sourceIssues?.length" class="notice warning section-space">
      <Icon name="AlertTriangle" />
      <div>
        <strong>Catatan verifikasi sumber</strong>
        <p v-for="issue in detail.sourceIssues" :key="issue">{{ issue }}</p>
      </div>
    </div>
    <div class="detail-grid">
      <div v-for="f in fields" :key="f.key">
        <span>{{ f.label }}</span
        ><strong>{{ display(detail, f) }}</strong>
      </div>
    </div>
    <a
      v-if="detail.driveUrl"
      :href="detail.driveUrl"
      target="_blank"
      rel="noopener noreferrer"
      class="button button-outline"
      >Buka folder dokumen</a
    >
    <h3 class="section-space">Riwayat</h3>
    <div v-for="(h, i) in detail.history" :key="i" class="history-row">
      <span>{{ dateLabel(h.date) }}</span
      ><strong>{{ h.label }}</strong>
    </div></Modal
  >
  <Modal
    v-if="editing"
    :title="current ? 'Usulkan perubahan pegawai' : 'Tambah pegawai'"
    wide
    @close="editing = false"
    ><form @submit.prevent="submit">
      <div class="notice compact">
        <Icon name="ShieldCheck" />
        <p>
          Perubahan disimpan sebagai usulan untuk diperiksa akun lain. Kolom kosong dari sumber
          tetap dapat dilengkapi bertahap.
        </p>
      </div>
      <div class="quality-preview">
        Kelengkapan setelah perubahan: <strong>{{ previewQuality.completeness }}%</strong>
        <p>
          {{
            previewQuality.missingFields.length
              ? 'Belum terisi: ' + previewQuality.missingFields.join(', ')
              : 'Seluruh kolom yang dinilai terisi.'
          }}
        </p>
      </div>
      <div class="form-grid">
        <label
          v-for="f in fields.filter((f) => !['sourceOrder', 'ageAtReference'].includes(f.key))"
          :key="f.key"
          :class="['name', 'unit', 'position', 'institution'].includes(f.key) ? 'full' : ''"
          >{{ f.label
          }}<select v-if="f.type === 'select'" v-model="form[f.key]">
            <option value="">Belum tercatat</option>
            <option v-for="o in options(f.key)" :key="o">{{ o }}</option></select
          ><input
            v-else
            v-model="form[f.key]"
            :type="f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text'"
            :min="f.type === 'number' ? 0 : undefined"
            :max="f.key === 'serviceMonths' ? 11 : undefined"
            :maxlength="f.key === 'nip' ? 18 : 300"
            :required="['name', 'nip', 'position'].includes(f.key)" /></label
        ><label
          >Status<select v-model="form.status">
            <option v-for="s in ['Aktif', 'Pensiun', 'Mutasi', 'Meninggal', 'Mengundurkan Diri']" :key="s">
              {{ s }}
            </option>
          </select></label
        ><label>Folder dokumen Google Drive<input v-model="form.driveUrl" type="url" /></label
        ><label>Mulai kontrak (opsional)<input type="date" v-model="form.contractStart" /></label
        ><label>Akhir kontrak (opsional)<input type="date" v-model="form.contractEnd" /></label
        ><label class="full"
          >Alasan perubahan<textarea
            v-model="reason"
            required
            minlength="5"
            maxlength="900"
            rows="2"
          />
        </label>
      </div>
      <p v-if="formError" class="form-error" role="alert">{{ formError }}</p>
      <div class="modal-actions">
        <button type="button" class="button button-outline" @click="editing = false">Batal</button
        ><button class="button button-primary" :disabled="saving">
          {{ saving ? 'Menyimpan…' : 'Ajukan untuk pemeriksaan' }}
        </button>
      </div>
    </form></Modal
  >
</template>
