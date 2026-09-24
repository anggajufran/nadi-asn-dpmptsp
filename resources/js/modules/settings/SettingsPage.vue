<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api, post, session } from '@/api/client';
import Icon from '@/components/Icon.vue';
import AccountsPanel from './AccountsPanel.vue';
const units = ref<any[]>([]),
  unitName = ref(''),
  editingUnit = ref<string | null>(null),
  unitBusy = ref(false),
  unitError = ref('');
async function loadUnits() {
  try {
    units.value = await api('units');
  } catch (e) {
    unitError.value = (e as Error).message;
  }
}
async function saveUnit() {
  unitBusy.value = true;
  unitError.value = '';
  try {
    if (editingUnit.value)
      await api('units/' + editingUnit.value, {
        method: 'PATCH',
        body: JSON.stringify({ label: unitName.value }),
      });
    else await post('units', { label: unitName.value });
    editingUnit.value = null;
    unitName.value = '';
    await loadUnits();
  } catch (e) {
    unitError.value = (e as Error).message;
  } finally {
    unitBusy.value = false;
  }
}
async function disableUnit(u: any) {
  if (!window.confirm('Nonaktifkan unit ' + u.label + '?')) return;
  try {
    await api('units/' + u.id, { method: 'PATCH', body: JSON.stringify({ active: false }) });
    await loadUnits();
  } catch (e) {
    unitError.value = (e as Error).message;
  }
}
onMounted(loadUnits);
const rules = ref<any[]>([]),
  tab = ref('units'),
  error = ref('');
onMounted(async () => {
  try {
    rules.value = await api('rules');
  } catch (e) {
    error.value = (e as Error).message;
  }
});
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">KONFIGURASI & KENDALI SISTEM</span>
      <h1>Pengaturan<span class="heading-dot">.</span></h1>
      <p>Kelola dasar operasional dengan kebijakan yang dapat ditelusuri.</p>
    </div>
  </div>
  <section class="card">
    <div class="tabs" role="tablist" aria-label="Pengaturan">
      <button
        role="tab"
        :aria-selected="tab === 'accounts'"
        :class="{ active: tab === 'accounts' }"
        @click="tab = 'accounts'"
      >
        Manajemen Akun
      </button>
      <button
        role="tab"
        :aria-selected="tab === 'units'"
        :class="{ active: tab === 'units' }"
        @click="tab = 'units'"
      >
        Unit kerja
      </button>
      <button
        role="tab"
        :aria-selected="tab === 'rules'"
        :class="{ active: tab === 'rules' }"
        @click="tab = 'rules'"
      >
        Ruleset & kebijakan</button
      ><button
        role="tab"
        :aria-selected="tab === 'access'"
        :class="{ active: tab === 'access' }"
        @click="tab = 'access'"
      >
        Akses & keamanan</button
      ><button
        role="tab"
        :aria-selected="tab === 'system'"
        :class="{ active: tab === 'system' }"
        @click="tab = 'system'"
      >
        Informasi sistem
      </button>
    </div>
    <div class="settings-content">
      <AccountsPanel v-if="tab === 'accounts'" /><template v-else-if="tab === 'units'"
        ><h3>Kelola unit kerja organisasi</h3>
        <p>
          Tambahkan atau ubah nama unit. Unit yang masih digunakan pegawai perlu dikosongkan
          terlebih dahulu sebelum dinonaktifkan.
        </p>
        <form class="unit-form section-space" @submit.prevent="saveUnit">
          <label
            >{{ editingUnit ? 'Ubah nama unit' : 'Nama unit baru'
            }}<input
              v-model="unitName"
              required
              maxlength="150"
              placeholder="Masukkan nama unit kerja" /></label
          ><button class="button button-primary" :disabled="unitBusy">
            {{ editingUnit ? 'Simpan nama' : 'Tambah unit' }}</button
          ><button
            v-if="editingUnit"
            type="button"
            class="button button-outline"
            @click="
              editingUnit = null;
              unitName = '';
            "
          >
            Batal
          </button>
        </form>
        <p v-if="unitError" role="alert" class="form-error">{{ unitError }}</p>
        <div class="unit-list">
          <div v-for="u in units" :key="u.id">
            <Icon name="Building2" /><strong>{{ u.label }}</strong
            ><button
              class="button button-outline"
              @click="
                editingUnit = u.id;
                unitName = u.label;
              "
            >
              Ubah nama</button
            ><button
              class="icon-button"
              :aria-label="`Nonaktifkan ${u.label}`"
              @click="disableUnit(u)"
            >
              <Icon name="Archive" :size="16" />
            </button>
          </div></div></template
      ><template v-else-if="tab === 'rules'"
        ><h3>Aturan perhitungan</h3>
        <p>Radar mengikuti aturan instansi; hasil pemeriksaan bukan penetapan hak.</p>
        <div v-if="error" class="form-error">{{ error }}</div>
        <div v-for="r in rules" :key="r.kind" class="rule-card">
          <div class="attention-icon blue"><Icon name="SlidersHorizontal" /></div>
          <div>
            <h3>Ruleset {{ r.kind }}</h3>
            <p>{{ r.message }}</p>
            <span class="muted-small"
              >Versi {{ r.version }} ·
              {{
                r.status === 'MANUAL_ORDER' ? 'Urutan manual' : 'Pengingat administratif aktif'
              }}</span
            >
          </div>
          <span class="badge" :class="'green'">{{
            r.status === 'MANUAL_ORDER' ? 'Urutan manual' : 'Radar aktif'
          }}</span>
        </div>
        <div class="notice section-space">
          <Icon name="ShieldCheck" />
          <div>
            <strong>Kewenangan persetujuan</strong>
            <p>
              Super Admin dapat menyetujui perubahan sendiri maupun usulan Admin Kepegawaian. Urutan
              DUK disimpan langsung oleh Super Admin dengan catatan audit. Admin dan Super Admin
              dapat memperbarui pemeriksaan KP/KGB berdasarkan dokumen sah.
            </p>
          </div>
        </div></template
      ><template v-else-if="tab === 'access'"
        ><h3>Pembatasan akses berbasis peran</h3>
        <div class="table-wrap section-space">
          <table>
            <thead>
              <tr>
                <th>PERAN</th>
                <th>AKSES UTAMA</th>
                <th>BATASAN</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Super Admin</td>
                <td>Pemeriksaan usulan dan konfigurasi</td>
                <td>Dapat menyetujui usulan sendiri dan mengelola akun Admin</td>
              </tr>
              <tr>
                <td>Admin Kepegawaian</td>
                <td>Data pegawai, import, pengajuan</td>
                <td>Tidak mengaktifkan aturan</td>
              </tr>
              <tr>
                <td>Publik</td>
                <td>Statistik agregat</td>
                <td>Tanpa identitas individu</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="notice compact section-space">
          <Icon name="LockKeyhole" />
          <p>
            Pada produksi, akses internal memerlukan kata sandi dan TOTP. Akun simulasi hanya
            berlaku pada server lokal pengembangan.
          </p>
        </div></template
      ><template v-else
        ><h3>Identitas aplikasi</h3>
        <dl class="explanation-list">
          <div>
            <dt>Nama aplikasi</dt>
            <dd>NADI ASN DPMPTSP Prov. Sulawesi Tengah</dd>
          </div>
          <div>
            <dt>Versi implementasi</dt>
            <dd>0.1.0 · Pengembangan</dd>
          </div>
          <div>
            <dt>Bahasa</dt>
            <dd>Bahasa Indonesia</dd>
          </div>
          <div>
            <dt>Zona waktu acuan</dt>
            <dd>Asia/Makassar (WITA)</dd>
          </div>
          <div>
            <dt>Lingkungan</dt>
            <dd>
              {{
                session.demo
                  ? 'Simulasi lokal dengan data sintetis'
                  : 'Backend lokal dengan direktori pegawai'
              }}
            </dd>
          </div>
          <div>
            <dt>Arsitektur target</dt>
            <dd>Laravel · Vue 3 · PostgreSQL</dd>
          </div>
        </dl>
        <div class="notice compact">
          <Icon name="Info" />
          <p>
            Konfigurasi ini adalah baseline pengembangan. Nilai produksi, akun, dan kebijakan harus
            ditetapkan sesuai keputusan instansi.
          </p>
        </div></template
      >
    </div>
  </section>
</template>
