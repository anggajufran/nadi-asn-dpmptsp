<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { post, api, session, waitForResult, downloadFile } from '@/api/client';
import { personnelFields, employeeTypes } from '@/domain/personnel';
import { notify, dateLabel } from '@/composables';
import Icon from '@/components/Icon.vue';
const rows = ref<any[]>([]),
  order = ref<string[]>([]),
  original = ref<string[]>([]),
  hash = ref(''),
  draft = ref<Record<string, any>>({}),
  editing = ref(false),
  busy = ref(false),
  error = ref(''),
  search = ref(''),
  type = ref(''),
  positionType = ref(''),
  unit = ref(''),
  units = ref<string[]>([]),
  page = ref(1),
  pageSize = ref(10),
  reason = ref('Penyesuaian data dan urutan DUK'),
  fields = personnelFields;
const superAdmin = computed(() => session.user?.role === 'SUPER_ADMIN');
const ordered = computed(() => {
  const map = new Map(rows.value.map((e) => [e.id, e]));
  return order.value.map((id) => map.get(id)).filter(Boolean);
});
const positions = computed(() => new Map(order.value.map((id, i) => [id, i + 1])));
const filtered = computed(() =>
  ordered.value.filter(
    (e) =>
      e.status === 'Aktif' &&
      (!type.value || e.type === type.value) &&
      (!positionType.value || e.positionType === positionType.value) &&
      (!unit.value || e.unit === unit.value) &&
      (!search.value ||
        e.name.toLowerCase().includes(search.value.toLowerCase()) ||
        e.nip.includes(search.value)),
  ),
);
const pages = computed(() =>
  Math.max(
    1,
    Math.ceil(filtered.value.length / (pageSize.value || Math.max(1, filtered.value.length))),
  ),
);
const visible = computed(() =>
  pageSize.value
    ? filtered.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value)
    : filtered.value,
);
watch([search, type, positionType, unit, pageSize], () => (page.value = 1));
const orderChanged = computed(() => order.value.join() !== original.value.join());
const changes = computed(() =>
  rows.value.flatMap((e) => {
    const d = draft.value[e.id];
    if (!d) return [];
    const changes = Object.fromEntries(
      fields
        .filter(
          (f) =>
            !['sourceOrder', 'ageAtReference'].includes(f.key) &&
            String(d[f.key] ?? '') !== String(e[f.key] ?? ''),
        )
        .map((f) => [
          f.key,
          f.type === 'number' ? (d[f.key] === '' ? null : Number(d[f.key])) : d[f.key],
        ]),
    );
    if ('serviceYears' in changes || 'serviceMonths' in changes) {
      changes.serviceYears =
        d.serviceYears == null || d.serviceYears === '' ? null : Number(d.serviceYears);
      changes.serviceMonths =
        d.serviceMonths == null || d.serviceMonths === '' ? null : Number(d.serviceMonths);
      if (e.serviceReference) changes.serviceReference = e.serviceReference;
    }
    return Object.keys(changes).length ? [{ employeeId: e.id, revision: e.revision, changes }] : [];
  }),
);
const dirty = computed(() => orderChanged.value || changes.value.length > 0);
async function load() {
  busy.value = true;
  error.value = '';
  try {
    const [r, u] = await Promise.all([api('duk'), api('units')]);
    rows.value = r.rows;
    order.value = [...r.order];
    original.value = [...r.order];
    hash.value = r.hash;
    units.value = u.map((i: any) => i.label);
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
onMounted(load);
function start() {
  draft.value = Object.fromEntries(rows.value.map((e) => [e.id, { ...e }]));
  editing.value = true;
  error.value = '';
}
function cancel() {
  if (dirty.value && !window.confirm('Buang perubahan DUK yang belum disimpan?')) return;
  editing.value = false;
  draft.value = {};
  order.value = [...original.value];
}
onBeforeRouteLeave(
  () =>
    !editing.value ||
    !dirty.value ||
    window.confirm('Perubahan DUK belum disimpan. Tinggalkan halaman ini?'),
);
function move(id: string, direction: number) {
  const index = filtered.value.findIndex((e) => e.id === id),
    target = filtered.value[index + direction];
  if (!target) return;
  const next = [...order.value],
    a = next.indexOf(id),
    b = next.indexOf(target.id);
  [next[a], next[b]] = [next[b], next[a]];
  order.value = next;
  if (pageSize.value) page.value = Math.floor((index + direction) / pageSize.value) + 1;
}
function options(key: string) {
  return key === 'unit'
    ? units.value
    : key === 'type'
      ? employeeTypes
      : key === 'gender'
        ? ['Laki-laki', 'Perempuan']
        : ['Struktural', 'Fungsional', 'Pelaksana'];
}
async function save() {
  busy.value = true;
  error.value = '';
  try {
    const result = await post(superAdmin.value ? 'duk/order' : 'proposals/batch', {
      order: order.value,
      hash: hash.value,
      rows: changes.value,
      reason: reason.value,
    });
    editing.value = false;
    draft.value = {};
    notify(
      result.count
        ? `Urutan tersimpan. ${result.count} perubahan data menunggu pemeriksaan di Persetujuan.`
        : 'Urutan DUK berhasil disimpan.',
    );
    await load();
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function download() {
  busy.value = true;
  error.value = '';
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
        search: search.value,
        status: 'Aktif',
      });
      await waitForResult('exports/' + job.id, 'READY');
      await downloadFile('exports/' + job.id + '/download', 'NADI-DUK.xlsx');
    }
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">LEMBAR KERJA KEPEGAWAIAN</span>
      <h1>DUK Digital<span class="heading-dot">.</span></h1>
      <p>Data sesuai daftar kepegawaian, dengan urutan yang ditetapkan Super Admin.</p>
    </div>
    <div class="button-row">
      <button
        v-if="superAdmin && !editing"
        class="button button-outline"
        :disabled="busy"
        @click="download"
      >
        <Icon name="Download" :size="17" />Export Excel
      </button>
      <template v-if="editing"
        ><button class="button button-outline" :disabled="busy" @click="cancel">Batal edit</button
        ><button
          class="button button-primary"
          :disabled="busy || !dirty || reason.trim().length < 5"
          @click="save"
        >
          <Icon name="Save" :size="17" />{{ busy ? 'Menyimpan…' : 'Simpan DUK' }}
        </button></template
      >
      <button v-else class="button button-primary" :disabled="busy || !rows.length" @click="start">
        <Icon name="Pencil" :size="17" />Edit DUK
      </button>
    </div>
  </div>
  <div class="notice">
    <Icon name="Info" />
    <p>
      Urutan awal mengikuti data sumber. Super Admin dapat menaikkan atau menurunkan pegawai saat
      mengedit, lalu menyimpan urutannya langsung. Perubahan isi sel masuk ke Persetujuan; Super
      Admin dapat menyetujui usulan sendiri. Masa kerja bertambah setiap bulan dari acuan sumber;
      usia mengikuti tanggal lahir.
    </p>
  </div>
  <p v-if="error" class="form-error" role="alert">{{ error }}</p>
  <section class="card section-space">
    <div class="table-toolbar directory-filters">
      <strong>DUK Digital Live</strong
      ><input
        v-model="search"
        aria-label="Cari pada DUK"
        placeholder="Cari nama atau NIP…"
      /><select v-model="type" aria-label="Jenis pegawai DUK">
        <option value="">Semua pegawai</option>
        <option v-for="t in employeeTypes" :key="t">{{ t }}</option></select
      ><select v-model="positionType" aria-label="Jenis jabatan DUK">
        <option value="">Semua jenis jabatan</option>
        <option v-for="j in ['Struktural', 'Fungsional', 'Pelaksana']" :key="j">
          {{ j }}
        </option></select
      ><select v-model="unit" aria-label="Unit DUK">
        <option value="">Semua unit</option>
        <option v-for="u in units" :key="u">{{ u }}</option>
      </select>
    </div>
    <div v-if="editing" class="sheet-edit-note">
      <span class="badge amber"
        >Mode edit · {{ changes.length }} baris berubah{{
          orderChanged ? ' · Urutan berubah' : ''
        }}</span
      ><label>Alasan penyimpanan<input v-model="reason" minlength="5" maxlength="1000" /></label>
    </div>
    <div class="sheet-hint">
      <Icon name="MoveHorizontal" :size="16" />Geser tabel untuk melihat seluruh kolom.
      {{
        editing && superAdmin
          ? 'Panah menukar posisi dengan pegawai sebelum atau sesudahnya dalam hasil filter, termasuk lintas halaman.'
          : 'Nomor DUK mengikuti urutan yang disimpan Super Admin.'
      }}
    </div>
    <div v-if="busy && !rows.length" class="loading-state">Memuat DUK…</div>
    <div
      class="table-wrap personnel-sheet editable-sheet"
      :class="{ 'duk-order-edit': editing && superAdmin }"
    >
      <table>
        <thead>
          <tr>
            <th>No. DUK</th>
            <th v-if="editing && superAdmin">Atur urutan</th>
            <th v-for="f in fields" :key="f.key">{{ f.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in visible" :key="e.id">
            <td>
              <strong>{{ positions.get(e.id) }}</strong>
            </td>
            <td v-if="editing && superAdmin">
              <div class="button-row order-controls">
                <button
                  class="icon-button"
                  :disabled="busy || filtered[0]?.id === e.id"
                  :aria-label="`Naikkan ${e.name}`"
                  title="Naik satu posisi"
                  @click="move(e.id, -1)"
                >
                  <Icon name="ArrowUp" :size="17" /></button
                ><button
                  class="icon-button"
                  :disabled="busy || filtered[filtered.length - 1]?.id === e.id"
                  :aria-label="`Turunkan ${e.name}`"
                  title="Turun satu posisi"
                  @click="move(e.id, 1)"
                >
                  <Icon name="ArrowDown" :size="17" />
                </button>
              </div>
            </td>
            <td
              v-for="f in fields"
              :key="f.key"
              :class="{
                changed: editing && String(draft[e.id]?.[f.key] ?? '') !== String(e[f.key] ?? ''),
              }"
            >
              <template v-if="editing && !['sourceOrder', 'ageAtReference'].includes(f.key)"
                ><select
                  v-if="f.type === 'select'"
                  v-model="draft[e.id][f.key]"
                  :disabled="busy"
                  :aria-label="`${f.label} ${e.name}`"
                >
                  <option value="">Belum tercatat</option>
                  <option v-for="o in options(f.key)" :key="o">{{ o }}</option></select
                ><input
                  v-else
                  v-model="draft[e.id][f.key]"
                  :disabled="busy"
                  :type="f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text'"
                  :aria-label="`${f.label} ${e.name}`"
                  :maxlength="f.key === 'nip' ? 18 : 300"
                  :min="f.type === 'number' ? 0 : undefined"
                  :max="f.key === 'serviceMonths' ? 11 : undefined"
              /></template>
              <template v-else>{{
                e[f.key] === null || e[f.key] === undefined || e[f.key] === ''
                  ? '—'
                  : f.type === 'date'
                    ? dateLabel(e[f.key])
                    : e[f.key]
              }}</template>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!filtered.length && !busy" class="empty-inline">
        Tidak ada pegawai yang sesuai filter.
      </div>
    </div>
    <div class="pagination">
      <label class="page-size"
        >Tampilkan
        <select v-model.number="pageSize" aria-label="Jumlah pegawai DUK per halaman">
          <option :value="10">10</option>
          <option :value="30">30</option>
          <option :value="50">50</option>
          <option :value="0">All</option>
        </select></label
      ><span>{{ filtered.length }} pegawai · halaman {{ page }} dari {{ pages }}</span>
      <div>
        <button class="icon-button" :disabled="page === 1" aria-label="Sebelumnya" @click="page--">
          <Icon name="ChevronLeft" /></button
        ><button
          class="icon-button"
          :disabled="page === pages"
          aria-label="Berikutnya"
          @click="page++"
        >
          <Icon name="ChevronRight" />
        </button>
      </div>
    </div>
  </section>
</template>
