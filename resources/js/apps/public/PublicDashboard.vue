<script setup lang="ts">
import VisitorStats from '@/components/VisitorStats.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '@/components/Icon.vue';
import ChartCard from '@/components/ChartCard.vue';
import { api } from '@/api/client';
import { notify, dateLabel } from '@/composables';
import type { PublicStats } from '@/types';
const route = useRoute(),
  router = useRouter();
const unit = ref(String(route.query.unit || '')),
  type = ref(String(route.query.jenis || '')),
  stats = ref<PublicStats | null>(null),
  loading = ref(true),
  error = ref(''),
  tableOpen = ref(false);
const appliedUnit = ref(unit.value),
  appliedType = ref(type.value);
const units = ref<string[]>([]);
const types = ['PNS', 'PPPK Penuh Waktu', 'PPPK Paruh Waktu'];
async function refresh() {
  loading.value = true;
  error.value = '';
  try {
    const requestedUnit = unit.value,
      requestedType = type.value;
    stats.value = await api<PublicStats>(
      'public/statistics?' + new URLSearchParams({ unit: requestedUnit, jenis: requestedType }),
    );
    appliedUnit.value = requestedUnit;
    appliedType.value = requestedType;
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    loading.value = false;
  }
}
async function apply() {
  await router.replace({
    query: {
      ...(unit.value ? { unit: unit.value } : {}),
      ...(type.value ? { jenis: type.value } : {}),
    },
  });
  await refresh();
}
function reset() {
  unit.value = '';
  type.value = '';
  apply();
}
function drill(label: string) {
  unit.value = label;
  apply();
  document.getElementById('overview')?.scrollIntoView({ behavior: 'smooth' });
}
async function share() {
  try {
    await navigator.clipboard.writeText(location.href);
    notify('Tautan filter berhasil disalin.');
  } catch {
    notify('Salin tautan dari bilah alamat browser Anda.');
  }
}
async function download() {
  if (!stats.value) return;
  try {
    const { exportPublicPdf } = await import('@/modules/reports/export');
    await exportPublicPdf(stats.value, appliedUnit.value, appliedType.value);
    notify('Ringkasan statistik berhasil diunduh.');
  } catch {
    notify('Ringkasan belum berhasil dibuat. Silakan coba lagi.');
  }
}
watch(
  () => route.query,
  () => {
    const nextUnit = String(route.query.unit || ''),
      nextType = String(route.query.jenis || '');
    if (unit.value !== nextUnit || type.value !== nextType) {
      unit.value = nextUnit;
      type.value = nextType;
      refresh();
    }
  },
);
onMounted(async () => {
  try {
    units.value = (await api('public/units')).map((u: any) => u.label);
  } catch {}
  await refresh();
});
</script>
<template>
  <div class="public-app">
    <header class="public-header">
      <div class="public-header-inner">
        <RouterLink to="/" class="brand" aria-label="NADI ASN beranda"
          ><img src="/logo-nadi-asn.png" alt="NADI ASN DPMPTSP Prov. Sulawesi Tengah"
        /></RouterLink>
        <nav aria-label="Navigasi utama">
          <RouterLink to="/" class="active">Dashboard Statistik</RouterLink
          ><a href="#distribution">Sebaran ASN</a
          ><RouterLink to="/tentang">Tentang NADI</RouterLink>
        </nav>
        <ThemeToggle /><RouterLink to="/login" class="button button-outline login-link"
          ><Icon name="LockKeyhole" :size="16" /><span>Login Internal</span
          ><Icon name="ArrowUpRight" :size="15"
        /></RouterLink>
      </div>
    </header>
    <main class="public-main">
      <div class="breadcrumb">
        <span>DPMPTSP Prov. Sulawesi Tengah</span><Icon name="ChevronRight" :size="12" /><span
          >Portal Statistik Kepegawaian</span
        >
      </div>
      <section class="public-hero">
        <div>
          <div class="eyebrow"><span></span> PORTAL STATISTIK KEPEGAWAIAN</div>
          <h1>Data ASN dalam <span>Satu Pandangan.</span></h1>
          <p>
            Mengenal komposisi dan sebaran Aparatur Sipil Negara DPMPTSP Prov. Sulawesi Tengah.<br />Data
            yang terintegrasi untuk informasi yang lebih berarti.
          </p>
        </div>
        <div class="hero-date">
          <Icon name="CalendarDays" :size="22" />
          <div>
            <span>Data per</span
            ><strong>{{ stats ? dateLabel(stats.asOf) : 'Memuat data…' }}</strong
            ><span v-if="stats?.demo" class="badge blue">Data simulasi · bukan data resmi</span
            ><span v-else-if="stats" class="badge green">Statistik agregat pegawai</span>
          </div>
        </div>
      </section>
      <div id="overview" class="section-title">
        <div>
          <div class="section-kicker">STATISTIK PEGAWAI</div>
          <h2>Gambaran umum ASN</h2>
          <p>
            Ringkasan pegawai aktif berdasarkan data
            {{ stats?.demo ? 'simulasi' : 'yang tercatat' }}.
          </p>
        </div>
        <button class="button button-outline" :disabled="!stats || loading" @click="download">
          <Icon name="ArrowDownToLine" :size="17" />Unduh Ringkasan
        </button>
      </div>
      <form class="card filter-card" @submit.prevent="apply">
        <label
          >Unit kerja<select v-model="unit">
            <option value="">Seluruh unit kerja</option>
            <option v-for="u in units" :key="u">{{ u }}</option>
          </select></label
        ><label
          >Jenis pegawai<select v-model="type">
            <option value="">Semua jenis pegawai</option>
            <option v-for="t in types" :key="t">{{ t }}</option>
          </select></label
        ><button class="button button-soft" :disabled="loading">
          <Icon name="SlidersHorizontal" :size="17" />Terapkan</button
        ><button type="button" class="button button-plain" @click="reset">Reset</button>
        <div class="filter-divider"></div>
        <button type="button" class="button button-plain" @click="share">
          <Icon name="Link" :size="17" />Bagikan
        </button>
      </form>
      <div v-if="error" class="notice danger" role="alert">
        <Icon name="AlertTriangle" />
        <div>
          <strong>Statistik belum tersedia</strong>
          <p>{{ error }}</p>
        </div>
        <button class="button button-outline" @click="refresh">Coba lagi</button>
      </div>
      <div v-if="loading && !stats" class="loading-state">
        <span class="spinner"></span>Menyiapkan statistik ASN…
      </div>
      <div v-if="stats && !error" :class="{ refreshing: loading }" :aria-busy="loading">
        <div class="stats-grid">
          <article class="stat-card primary">
            <div>
              <span>Total ASN</span>
              <div class="stat-icon"><Icon name="Users" /></div>
            </div>
            <strong>{{ stats.total ?? '—' }}<span>pegawai</span></strong>
            <p>
              <span class="small-dot"></span>Pegawai aktif
              {{ stats.demo ? 'dalam simulasi' : 'sesuai data tersimpan' }}
            </p>
          </article>
          <article v-for="(s, i) in stats.composition" :key="s.label" class="stat-card">
            <div>
              <span>{{ s.label }}</span>
              <div class="stat-icon" :class="`tone-${i}`">
                <Icon :name="['Building2', 'FileCheck2', 'Clock3'][i]" />
              </div>
            </div>
            <strong>{{ s.display || s.value }}<span>pegawai</span></strong>
            <p>
              {{
                [
                  'Pegawai Negeri Sipil',
                  'Perjanjian kerja penuh waktu',
                  'Perjanjian kerja paruh waktu',
                ][i]
              }}
            </p>
          </article>
          <article class="stat-card part-time-card">
            <div>
              <span>PPPK Paruh Waktu</span>
              <div class="stat-icon tone-2"><Icon name="Clock3" /></div>
            </div>
            <strong>{{ stats.partTime }}<span>pegawai</span></strong>
            <p>Rekap terpisah · di luar total ASN</p>
          </article>
        </div>
        <div class="chart-grid">
          <ChartCard
            title="Komposisi ASN"
            subtitle="Distribusi berdasarkan jenis kepegawaian"
            :series="stats.composition"
            kind="donut"
            :total="stats.total"
            badge="Aktif"
          /><ChartCard
            title="Pendidikan terakhir"
            :subtitle="
              appliedType === 'PPPK Paruh Waktu'
                ? 'Latar belakang pendidikan PPPK Paruh Waktu'
                : 'Latar belakang pendidikan ASN'
            "
            :series="stats.education"
            kind="bar"
            ><div class="chart-footnote">
              <Icon name="Info" :size="13" />Pendidikan formal tertinggi yang tercatat.
            </div></ChartCard
          ><ChartCard
            id="distribution"
            title="Sebaran unit kerja"
            subtitle="Pilih unit untuk melihat statistik agregat"
            :series="stats.units"
            interactive
            @choose="drill"
          /><ChartCard
            title="Komposisi gender"
            :subtitle="
              appliedType === 'PPPK Paruh Waktu' ? 'Rekap PPPK Paruh Waktu' : 'Distribusi ASN aktif'
            "
            :series="stats.gender"
            kind="donut"
            :total="appliedType === 'PPPK Paruh Waktu' ? stats.partTime : stats.total"
            :total-label="appliedType === 'PPPK Paruh Waktu' ? 'Paruh waktu' : 'Total ASN'"
          />
        </div>
        <div class="chart-grid thirds">
          <ChartCard
            title="Kelompok jabatan"
            subtitle="Berdasarkan jenis jabatan"
            :series="stats.positions"
          /><ChartCard
            title="Pangkat & golongan"
            subtitle="Komposisi golongan PNS"
            :series="stats.grades"
          /><ChartCard
            title="Golongan PPPK"
            subtitle="Komposisi golongan PPPK"
            :series="stats.pppkGrades"
          />
        </div>
        <ChartCard
          class="functional-card"
          title="Jabatan fungsional"
          subtitle="Sebaran kelompok fungsional"
          :series="stats.functional"
        />
        <div class="notice privacy-notice">
          <div class="notice-icon"><Icon name="ShieldCheck" :size="23" /></div>
          <div>
            <strong>Transparan dalam informasi, terlindungi dalam privasi.</strong>
            <p>
              Angka agregat tersedia sesuai filter. Nama, NIP, tanggal lahir, dan dokumen pegawai
              tidak ditampilkan pada halaman publik.
            </p>
          </div>
          <RouterLink to="/tentang#privasi" class="text-link"
            >Tentang privasi <Icon name="ArrowUpRight" :size="16"
          /></RouterLink>
        </div>
        <section class="card accessible-table">
          <button @click="tableOpen = !tableOpen" :aria-expanded="tableOpen">
            <span
              ><Icon name="ChartColumn" :size="18" />Lihat tabel statistik yang dapat diakses</span
            ><Icon name="ChevronDown" :class="{ rotated: tableOpen }" />
          </button>
          <div v-if="tableOpen" class="table-wrap">
            <table>
              <caption>
                Statistik yang sama dengan grafik di atas
              </caption>
              <thead>
                <tr>
                  <th>Kategori</th>
                  <th>Kelompok</th>
                  <th>Jumlah pegawai</th>
                </tr>
              </thead>
              <tbody>
                <template
                  v-for="(group, key) in {
                    'Jenis pegawai': stats.composition,
                    Pendidikan: stats.education,
                    'Unit kerja': stats.units,
                    Gender: stats.gender,
                    Jabatan: stats.positions,
                    'Golongan PNS': stats.grades,
                    'Golongan PPPK': stats.pppkGrades,
                    Fungsional: stats.functional,
                  }"
                  :key="key"
                  ><tr v-for="s in group" :key="s.label">
                    <td>{{ key }}</td>
                    <td>{{ s.label }}</td>
                    <td>{{ s.display || s.value }}</td>
                  </tr></template
                >
              </tbody>
            </table>
          </div>
        </section>
      </div>
      <VisitorStats />
      <footer class="public-footer">
        <span
          >© {{ new Date().getFullYear() }} NADI ASN · DPMPTSP Prov. Sulawesi Tengah · Developer:
          Angga Jufran</span
        ><span>Navigasi dan Analitik Data Internal ASN</span
        ><RouterLink to="/tentang#privasi"
          >Tentang data & privasi <Icon name="ArrowUpRight" :size="13"
        /></RouterLink>
      </footer>
    </main>
  </div>
</template>
