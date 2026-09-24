<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Icon from '@/components/Icon.vue';
import ChartCard from '@/components/ChartCard.vue';
import { api, session } from '@/api/client';
import { dateLabel } from '@/composables';
const data = ref<any>(null),
  error = ref('');
onMounted(async () => {
  try {
    const [dashboard, kp, kgb] = await Promise.all([
      api('dashboard'),
      api('radar/kp'),
      api('radar/kgb'),
    ]);
    dashboard.radar = {
      kp: kp.rows.filter((r: any) => r.result.reminderActive).length,
      kgb: kgb.rows.filter((r: any) => r.result.reminderActive).length,
    };
    data.value = dashboard;
  } catch (e) {
    error.value = (e as Error).message;
  }
});
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">RUANG KERJA ANDA</span>
      <h1>{{ 'Command Center' }}<span class="heading-dot">.</span></h1>
      <p>Selamat datang. Berikut kondisi ASN dan prioritas yang perlu Anda perhatikan.</p>
    </div>
    <div class="date-chip">
      <Icon name="CalendarDays" :size="16" />{{ dateLabel(new Date().toISOString()) }}
    </div>
  </div>
  <div v-if="error" class="notice danger">{{ error }}</div>
  <div v-else-if="!data" class="loading-state"><span class="spinner"></span>Memuat ringkasan…</div>
  <template v-else
    ><div class="stats-grid internal-stats">
      <article class="stat-card primary">
        <div>
          <span>Total ASN aktif</span>
          <div class="stat-icon"><Icon name="Users" /></div>
        </div>
        <strong>{{ data.total }}<span>pegawai</span></strong>
        <p>Seluruh unit kerja DPMPTSP Prov. Sulawesi Tengah</p>
      </article>
      <article class="stat-card">
        <div>
          <span>Pegawai Negeri Sipil</span>
          <div class="stat-icon"><Icon name="Building2" /></div>
        </div>
        <strong>{{ data.pns }}</strong>
        <p>{{ Math.round((data.pns / (data.total || 1)) * 100) }}% dari total ASN aktif</p>
      </article>
      <article class="stat-card">
        <div>
          <span>PPPK Penuh Waktu</span>
          <div class="stat-icon tone-1"><Icon name="FileCheck2" /></div>
        </div>
        <strong>{{ data.pppk }}</strong>
        <p>Termasuk dalam total ASN</p>
      </article>
      <article class="stat-card part-time-card">
        <div>
          <span>PPPK Paruh Waktu</span>
          <div class="stat-icon tone-2"><Icon name="Clock3" /></div>
        </div>
        <strong>{{ data.partTime }}</strong>
        <p>Rekap terpisah · di luar total ASN</p>
      </article>
      <article class="stat-card">
        <div>
          <span>Kelengkapan data</span>
          <div class="stat-icon tone-1"><Icon name="ShieldCheck" /></div>
        </div>
        <strong>{{ data.quality }}<span>%</span></strong>
        <p>{{ data.incomplete }} data masih perlu dilengkapi</p>
      </article>
    </div>
    <div class="dashboard-columns">
      <section class="card attention-card">
        <div class="card-heading">
          <div>
            <h3>Perlu perhatian Anda</h3>
            <p>Prioritas untuk menjaga data tetap akurat</p>
          </div>
          <span class="badge amber"
            >{{ data.pending + data.incomplete + data.radar.kp + data.radar.kgb }} perhatian</span
          >
        </div>
        <RouterLink to="/internal/persetujuan" class="attention-row"
          ><div class="attention-icon amber"><Icon name="FileCheck2" /></div>
          <div>
            <strong>Usulan menunggu persetujuan</strong>
            <p>
              {{
                data.pending
                  ? `${data.pending} perubahan perlu diperiksa sebelum diterapkan.`
                  : 'Belum ada usulan yang menunggu pemeriksaan.'
              }}
            </p>
          </div>
          <span class="attention-count">{{ data.pending }}</span
          ><Icon name="ChevronRight" :size="16" /></RouterLink
        ><RouterLink to="/internal/pegawai?quality=incomplete" class="attention-row"
          ><div class="attention-icon blue"><Icon name="Database" /></div>
          <div>
            <strong>Lengkapi data pegawai</strong>
            <p>Pastikan sumber informasi ASN siap digunakan.</p>
          </div>
          <span class="attention-count">{{ data.incomplete }}</span
          ><Icon name="ChevronRight" :size="16" /></RouterLink
        ><RouterLink to="/internal/kp" class="attention-row"
          ><div class="attention-icon red"><Icon name="ShieldAlert" /></div>
          <div>
            <strong>Persiapan usulan KP dan KGB</strong>
            <p>Periksa jadwal, dokumen pendukung, dan syarat pegawai pada halaman Radar.</p>
          </div>
          <span class="attention-count">{{ data.radar.kp + data.radar.kgb }}</span
          ><Icon name="ChevronRight" :size="16"
        /></RouterLink>
      </section>
      <section class="card quick-card">
        <div class="card-heading">
          <div>
            <h3>Akses cepat</h3>
            <p>Langkah kecil, data lebih tertata</p>
          </div>
        </div>
        <div class="quick-grid">
          <RouterLink to="/internal/pegawai"
            ><Icon name="Users" /><strong>Data pegawai</strong
            ><span>Lihat & kelola ASN</span></RouterLink
          ><RouterLink to="/internal/import"
            ><Icon name="UploadCloud" /><strong>Import Excel</strong
            ><span>Periksa data sumber</span></RouterLink
          ><RouterLink to="/internal/duk"
            ><Icon name="ListOrdered" /><strong>DUK Digital</strong
            ><span>Daftar urut kepangkatan</span></RouterLink
          ><RouterLink v-if="session.user?.role === 'SUPER_ADMIN'" to="/internal/laporan"
            ><Icon name="FileSpreadsheet" /><strong>Buat laporan</strong
            ><span>Ringkasan siap unduh</span></RouterLink
          >
        </div>
      </section>
    </div>
    <div class="dashboard-columns">
      <ChartCard
        title="Sebaran pegawai"
        subtitle="Pegawai aktif menurut unit kerja"
        :series="data.units"
      />
      <section class="card radar-summary">
        <div class="card-heading">
          <div>
            <h3>Radar kepegawaian</h3>
            <p>Pengingat administrasi PNS sesuai dokumen instansi</p>
          </div>
          <Icon name="Radar" />
        </div>
        <RouterLink
          v-for="(label, key) in { kp: 'Kenaikan Pangkat', kgb: 'Kenaikan Gaji Berkala' }"
          :key="key"
          :to="`/internal/${key}`"
          class="radar-summary-item"
          ><div>
            <span>{{ label }}</span
            ><strong>{{ data.radar[key] }} pegawai memasuki masa pengingat</strong
            ><small>Buka pemeriksaan dan status usulan</small>
          </div>
          <Icon name="ArrowUpRight"
        /></RouterLink>
        <div class="mini-note">
          <Icon name="Info" :size="14" />Radar adalah pengingat, bukan penetapan hak.
        </div>
      </section>
    </div>
    <section class="card activity-card">
      <div class="card-heading">
        <div>
          <h3>Aktivitas terbaru</h3>
          <p>Jejak pekerjaan tim kepegawaian</p>
        </div>
        <RouterLink to="/internal/audit" class="text-link"
          >Lihat semua<Icon name="ArrowRight" :size="15"
        /></RouterLink>
      </div>
      <div v-if="!data.recent.length" class="empty-inline">
        Aktivitas akan tercatat saat tim mulai bekerja.
      </div>
      <div v-for="event in data.recent" :key="event.id" class="activity-row">
        <div class="activity-dot"><Icon name="History" :size="16" /></div>
        <div>
          <strong>{{ event.action }}</strong>
          <p>{{ event.actor }} · {{ event.subject }}</p>
        </div>
        <span
          >{{
            new Date(event.createdAt).toLocaleTimeString('id-ID', {
              hour: '2-digit',
              minute: '2-digit',
            })
          }}
          WITA</span
        >
      </div>
    </section></template
  >
</template>
