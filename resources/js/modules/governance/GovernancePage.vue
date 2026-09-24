<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import Icon from '@/components/Icon.vue';
import Modal from '@/components/Modal.vue';
import { api, post, session } from '@/api/client';
import { notify, dateLabel } from '@/composables';
import type { Proposal } from '@/types';
const emit = defineEmits<{ updated: [] }>(),
  rows = ref<Proposal[]>([]),
  tab = ref('PENDING'),
  selected = ref<Proposal | null>(null),
  note = ref(''),
  error = ref(''),
  busy = ref(false),
  loading = ref(true);
const filtered = computed(() => rows.value.filter((p) => p.status === tab.value));
const labels: Record<string, string> = {
  dukPositionLevel: 'Jenjang jabatan DUK',
  trainingLevel: 'Tingkat diklat diakui',
  trainingDate: 'Tanggal lulus diklat',
  name: 'Nama lengkap',
  nip: 'NIP',
  type: 'Jenis ASN',
  gender: 'Jenis kelamin',
  unit: 'Unit kerja',
  position: 'Jabatan',
  positionType: 'Jenis jabatan',
  grade: 'Golongan',
  education: 'Pendidikan',
  status: 'Status',
  birthPlace: 'Tempat lahir',
  birthDate: 'Tanggal lahir',
  effectiveDate: 'TMT',
  driveUrl: 'Folder Drive',
  contractStart: 'Mulai kontrak',
  contractEnd: 'Akhir kontrak',
};
async function load() {
  try {
    rows.value = await api('proposals');
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}
onMounted(load);
async function decide(decision: string) {
  if (!selected.value) return;
  busy.value = true;
  error.value = '';
  try {
    await post(`proposals/${selected.value.id}/decide`, { decision, reason: note.value });
    selected.value = null;
    await load();
    emit('updated');
    notify(
      decision === 'APPROVED'
        ? 'Perubahan disetujui dan diterapkan.'
        : 'Usulan ditolak dengan catatan pemeriksaan.',
    );
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
function open(p: Proposal) {
  selected.value = p;
  note.value = '';
  error.value = '';
}
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">TATA KELOLA DATA</span>
      <h1>Persetujuan<span class="heading-dot">.</span></h1>
      <p>Periksa perubahan bersama, jaga keakuratan sumber data.</p>
    </div>
    <span class="badge blue"><Icon name="ShieldCheck" :size="14" />Persetujuan Super Admin</span>
  </div>
  <div class="notice compact">
    <Icon name="Info" />
    <p>
      Super Admin dapat menyetujui usulan sendiri maupun usulan Admin Kepegawaian. Seluruh keputusan
      beserta alasannya tercatat pada Audit Log.
    </p>
  </div>
  <section class="card section-space">
    <div class="tabs" role="tablist" aria-label="Status persetujuan">
      <button
        v-for="(label, key) in {
          PENDING: 'Menunggu pemeriksaan',
          APPROVED: 'Disetujui',
          REJECTED: 'Ditolak',
        }"
        :key="key"
        role="tab"
        :aria-selected="tab === key"
        :class="{ active: tab === key }"
        @click="tab = key"
      >
        {{ label }}<span>{{ rows.filter((p) => p.status === key).length }}</span>
      </button>
    </div>
    <div v-if="loading" class="loading-state"><span class="spinner"></span>Memuat usulan…</div>
    <div v-else-if="!filtered.length" class="empty-state">
      <div class="empty-icon"><Icon name="FileCheck2" :size="32" /></div>
      <h3>
        {{
          tab === 'PENDING'
            ? 'Semua pemeriksaan sudah tertangani'
            : 'Belum ada usulan pada status ini'
        }}
      </h3>
      <p>Usulan dari Master Pegawai akan muncul di sini.</p>
      <RouterLink class="button button-outline" to="/internal/pegawai"
        >Buka Master Pegawai<Icon name="ArrowRight" :size="16"
      /></RouterLink>
    </div>
    <div v-else class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>PEGAWAI / PERUBAHAN</th>
            <th>PENGAJU</th>
            <th>TANGGAL PENGAJUAN</th>
            <th>STATUS</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in filtered" :key="p.id">
            <td>
              <div class="table-detail">
                <strong>{{ p.name }}</strong
                ><small
                  >{{ p.employeeId ? 'Perubahan data pegawai' : 'Pegawai baru' }} ·
                  {{ Object.keys(p.changes).length }} field</small
                >
              </div>
            </td>
            <td>{{ p.makerName }}</td>
            <td>{{ dateLabel(p.createdAt) }}</td>
            <td>
              <span
                class="badge"
                :class="tab === 'PENDING' ? 'amber' : tab === 'APPROVED' ? 'green' : 'red'"
                >{{
                  { PENDING: 'Menunggu', APPROVED: 'Disetujui', REJECTED: 'Ditolak' }[tab]
                }}</span
              >
            </td>
            <td>
              <button class="button button-outline small" @click="open(p)">
                Periksa perubahan<Icon name="ArrowRight" :size="14" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
  <p v-if="error && !selected" class="form-error">{{ error }}</p>
  <Modal v-if="selected" title="Pemeriksaan perubahan" wide @close="selected = null"
    ><div class="review-intro">
      <h3>{{ selected.name }}</h3>
      <p>Diajukan oleh {{ selected.makerName }} · {{ dateLabel(selected.createdAt) }}</p>
      <div class="quote-note">{{ selected.reason }}</div>
    </div>
    <div class="table-wrap diff-table">
      <table>
        <thead>
          <tr>
            <th>FIELD</th>
            <th>SEBELUM</th>
            <th>DIUSULKAN</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(value, key) in selected.changes" :key="key">
            <td>{{ labels[key] || key }}</td>
            <td>{{ selected.before[key] || '—' }}</td>
            <td>{{ value || 'Dikosongkan' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="notice compact section-space">
      <Icon name="ShieldAlert" />
      <p>
        Dampak: perubahan identitas, statistik, dan dasar laporan. Snapshot yang sudah diterbitkan
        tidak diubah.
      </p>
    </div>
    <template v-if="selected.status === 'PENDING'"
      ><div v-if="session.user?.role !== 'SUPER_ADMIN'" class="mini-note">
        <Icon name="LockKeyhole" :size="16" />{{
          'Super Admin akan memeriksa dan memutuskan usulan Anda.'
        }}
      </div>
      <form v-else @submit.prevent="decide('APPROVED')">
        <label class="section-space"
          >Catatan pemeriksaan *<textarea
            v-model="note"
            required
            rows="3"
            placeholder="Tuliskan hasil pemeriksaan dan dasar keputusan."
          />
        </label>
        <div v-if="error" class="form-error" role="alert">{{ error }}</div>
        <div class="modal-actions">
          <button
            type="button"
            class="button button-outline"
            :disabled="busy || !note.trim()"
            @click="decide('REJECTED')"
          >
            Tolak usulan</button
          ><button class="button button-primary" :disabled="busy || !note.trim()">
            <Icon name="CheckCheck" :size="17" />Setujui & terapkan
          </button>
        </div>
      </form></template
    >
    <div v-else class="quote-note">Catatan keputusan: {{ selected.decisionReason }}</div></Modal
  >
</template>
