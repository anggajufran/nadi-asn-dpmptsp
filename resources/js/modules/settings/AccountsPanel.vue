<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { api, post, allEmployees, session } from '@/api/client';
import { notify, dateLabel } from '@/composables';
import Icon from '@/components/Icon.vue';
import Modal from '@/components/Modal.vue';
const accounts = ref<any[]>([]),
  employees = ref<any[]>([]),
  search = ref(''),
  selected = ref(''),
  busy = ref(false),
  error = ref(''),
  link = ref(''),
  expires = ref(''),
  action = ref<{ account: any; kind: string } | null>(null);
const available = computed(() =>
  employees.value.filter(
    (e) =>
      e.status === 'Aktif' &&
      !accounts.value.some((a) => a.employeeId === e.id || a.login === e.nip) &&
      (!search.value || (e.name + ' ' + e.nip).toLowerCase().includes(search.value.toLowerCase())),
  ),
);
const statuses: Record<string, string> = {
  ACTIVE: 'Aktif',
  PENDING: 'Menunggu aktivasi',
  EXPIRED: 'Tautan kedaluwarsa',
  DISABLED: 'Nonaktif',
};
async function load() {
  try {
    const [a, e] = await Promise.all([api('accounts'), allEmployees()]);
    accounts.value = a;
    employees.value = e.data;
  } catch (e) {
    error.value = (e as Error).message;
  }
}
onMounted(() => {
  if (!session.demo) load();
});
async function create() {
  busy.value = true;
  error.value = '';
  link.value = '';
  try {
    const r = await post('accounts', { employeeId: selected.value });
    showLink(r);
    selected.value = '';
    await load();
    notify('Akun Admin Kepegawaian dibuat. Bagikan tautan aktivasi kepada pegawai yang dipilih.');
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
function showLink(r: any) {
  link.value = new URL(r.activationPath, location.origin).href;
  expires.value = r.expiresAt;
}
async function perform() {
  if (!action.value) return;
  busy.value = true;
  error.value = '';
  link.value = '';
  try {
    const r = await post(`accounts/${action.value.account.id}/${action.value.kind}`, {});
    if (action.value.kind === 'activation') showLink(r);
    notify(
      action.value.kind === 'disable' ? 'Akses akun dinonaktifkan.' : 'Tautan baru siap dibagikan.',
    );
    action.value = null;
    await load();
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
async function copy() {
  try {
    await navigator.clipboard.writeText(link.value);
    notify('Tautan disalin.');
  } catch {
    notify('Pilih dan salin tautan pada kolom yang tersedia.');
  }
}
</script>
<template>
  <h3>Manajemen Akun</h3>
  <p>Berikan akses Admin Kepegawaian kepada satu atau beberapa pegawai dari Master Pegawai.</p>
  <div v-if="session.demo" class="notice section-space">
    <Icon name="Info" />
    <p>
      Pengelolaan akun tersedia pada aplikasi utama di port 5173. Akun simulasi tidak memberikan
      akses ke data pegawai asli.
    </p>
  </div>
  <template v-else>
    <div class="account-summary section-space">
      <span class="badge green"
        >{{ accounts.filter((a) => a.role === 'ADMIN' && a.status === 'ACTIVE').length }} Admin
        aktif</span
      ><span class="badge amber"
        >{{ accounts.filter((a) => a.status === 'PENDING').length }} menunggu aktivasi</span
      >
    </div>
    <form class="account-create section-space" @submit.prevent="create">
      <label
        >Cari pegawai<input
          v-model="search"
          placeholder="Nama atau NIP…"
          aria-label="Cari pegawai untuk akun" /></label
      ><label
        >Pilih pegawai<select
          v-model="selected"
          required
          aria-label="Pegawai untuk Admin Kepegawaian"
        >
          <option value="">Pilih pegawai aktif</option>
          <option v-for="e in available" :key="e.id" :value="e.id">
            {{ e.name }} · {{ e.nip }}
          </option>
        </select></label
      ><label>Level akses<input value="Admin Kepegawaian" readonly /></label
      ><button class="button button-primary" :disabled="busy || !selected">
        <Icon name="UserPlus" :size="17" />Buat akun & tautan
      </button>
    </form>
    <p class="muted-small">
      Pegawai yang sudah memiliki akun tidak ditampilkan lagi. Identitas masuk menggunakan NIP saat
      akun dibuat.
    </p>
    <div v-if="link" class="activation-result section-space" role="status">
      <strong>Tautan aktivasi / pemulihan siap dibagikan</strong>
      <p>
        Berlaku 24 jam, sekali pakai. Pegawai menetapkan kata sandi dan autentikator sendiri. Salin
        sebelum meninggalkan halaman ini.
      </p>
      <div class="button-row">
        <input
          :value="link"
          readonly
          aria-label="Tautan aktivasi akun"
          @focus="($event.target as HTMLInputElement).select()"
        /><button class="button button-outline" @click="copy">Salin tautan</button
        ><button class="icon-button" aria-label="Tutup tautan aktivasi" @click="link = ''">
          <Icon name="X" />
        </button>
      </div>
      <small>Berlaku hingga {{ new Date(expires).toLocaleString('id-ID') }}</small>
    </div>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <div class="table-wrap section-space">
      <table>
        <thead>
          <tr>
            <th>Pegawai / identitas masuk</th>
            <th>Level akses</th>
            <th>Status</th>
            <th>Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in accounts" :key="a.id">
            <td>
              <strong>{{ a.name }}</strong>
              <div class="muted-small">{{ a.login || '—' }}</div>
            </td>
            <td>{{ a.role === 'SUPER_ADMIN' ? 'Super Admin' : 'Admin Kepegawaian' }}</td>
            <td>
              <span
                class="badge"
                :class="a.status === 'ACTIVE' ? 'green' : a.status === 'PENDING' ? 'amber' : 'blue'"
                >{{ statuses[a.status] }}</span
              >
              <div v-if="a.expiresAt" class="muted-small">Batas: {{ dateLabel(a.expiresAt) }}</div>
            </td>
            <td>
              <div v-if="a.role === 'ADMIN'" class="button-row">
                <button
                  class="button button-outline small"
                  :disabled="busy"
                  @click="action = { account: a, kind: 'activation' }"
                >
                  {{ a.status === 'ACTIVE' ? 'Reset kata sandi' : 'Buat tautan aktivasi' }}</button
                ><button
                  v-if="a.status !== 'DISABLED'"
                  class="button button-outline small"
                  :disabled="busy"
                  @click="action = { account: a, kind: 'disable' }"
                >
                  Nonaktifkan
                </button>
              </div>
              <span v-else class="muted-small">Akun Super Admin</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>
  <Modal
    v-if="action"
    :title="action.kind === 'disable' ? 'Nonaktifkan akses' : 'Buat tautan aktivasi / reset'"
    @close="!busy && (action = null)"
    ><h3>{{ action.account.name }}</h3>
    <p>
      {{
        action.kind === 'disable'
          ? 'Akun tidak dapat masuk lagi. Sesi dan tautan aktivasi sebelumnya akan dibatalkan. Data pegawai tetap tersimpan.'
          : 'Sesi, kata sandi, autentikator, dan tautan lama akan dibatalkan. Pegawai perlu membuka tautan baru untuk menetapkan kata sandi serta autentikator sebelum masuk kembali.'
      }}
    </p>
    <p v-if="error" class="form-error">{{ error }}</p>
    <div class="modal-actions">
      <button class="button button-outline" :disabled="busy" @click="action = null">Batal</button
      ><button class="button button-primary" :disabled="busy" @click="perform">
        {{
          busy ? 'Memproses…' : action.kind === 'disable' ? 'Nonaktifkan akun' : 'Buat tautan baru'
        }}
      </button>
    </div></Modal
  >
</template>
