<script setup lang="ts">
const statusLabels: Record<string, string> = {
  QUEUED: 'Dalam antrean',
  READY: 'Siap diunduh',
  FAILED: 'Gagal diproses',
  EXPIRED: 'Kedaluwarsa',
};
import { ref, onMounted } from 'vue';
import { api, session, allEmployees, post, waitForResult, downloadFile } from '@/api/client';
import { notify, dateLabel } from '@/composables';
import Icon from '@/components/Icon.vue';
import Modal from '@/components/Modal.vue';
import { useUnits } from '@/domain/units';
const units = useUnits();
const kind = ref('pegawai'),
  format = ref('xlsx'),
  unit = ref(''),
  busy = ref(false),
  error = ref(''),
  official = ref(false),
  downloads = ref<{ name: string; date: string }[]>([]);
const jobs = ref<any[]>([]);
async function refreshJobs() {
  if (!session.demo) {
    try {
      jobs.value = await api('exports');
    } catch (e) {
      error.value = (e as Error).message;
    }
  }
}
onMounted(refreshJobs);
async function downloadJob(job: any) {
  try {
    await downloadFile(`exports/${job.id}/download`, `NADI-ASN-${job.id}.${job.format}`);
    notify('Berkas berhasil diunduh.');
  } catch (e) {
    error.value = (e as Error).message;
  }
}
async function generate() {
  busy.value = true;
  error.value = '';
  try {
    if (!session.demo) {
      const job = await post('exports', {
        kind: kind.value === 'statistik' ? 'statistics' : 'employees',
        unit: unit.value,
      });
      await waitForResult(`exports/${job.id}`, 'READY');
      await downloadFile(
        `exports/${job.id}/download`,
        `NADI-ASN-Operasional-${job.id}.${kind.value === 'statistik' ? 'pdf' : 'xlsx'}`,
      );
    } else if (kind.value === 'statistik') {
      const { exportPublicPdf } = await import('./export');
      await exportPublicPdf(
        await api('public/statistics?' + new URLSearchParams({ unit: unit.value })),
        unit.value,
        '',
      );
    } else {
      const { exportEmployees } = await import('./export');
      await exportEmployees(
        (await allEmployees(new URLSearchParams({ unit: unit.value, status: 'Aktif' }).toString()))
          .data,
      );
    }
    downloads.value.unshift({
      name: kind.value === 'statistik' ? 'Ringkasan Statistik ASN.pdf' : 'Rekap Pegawai Aktif.xlsx',
      date: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
    });
    notify('Laporan berhasil dibuat dan diunduh.');
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
    await refreshJobs();
  }
}
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">INFORMASI SIAP DIGUNAKAN</span>
      <h1>Laporan & Export<span class="heading-dot">.</span></h1>
      <p>Susun ringkasan dari sumber data yang sama, tanpa input berulang.</p>
    </div>
  </div>
  <div class="report-layout">
    <section class="card report-form">
      <h3>Buat laporan operasional</h3>
      <p>Pilih kebutuhan informasi dan cakupan data.</p>
      <div class="report-options">
        <button
          :class="{ selected: kind === 'pegawai' }"
          @click="
            kind = 'pegawai';
            format = 'xlsx';
          "
        >
          <Icon name="FileSpreadsheet" :size="26" />
          <div>
            <strong>Rekap pegawai aktif</strong
            ><span>Data kepegawaian untuk kebutuhan internal</span>
          </div>
          <span class="radio-dot"></span></button
        ><button
          :class="{ selected: kind === 'statistik' }"
          @click="
            kind = 'statistik';
            format = 'pdf';
          "
        >
          <Icon name="ChartColumn" :size="26" />
          <div>
            <strong>Ringkasan statistik</strong
            ><span>Statistik agregat tanpa identitas individu</span>
          </div>
          <span class="radio-dot"></span>
        </button>
      </div>
      <label
        >Unit kerja<select v-model="unit">
          <option value="">Seluruh unit kerja</option>
          <option v-for="u in units" :key="u">
            {{ u }}
          </option>
        </select></label
      >
      <div class="form-grid section-space">
        <label
          >Format keluaran<input
            :value="format === 'xlsx' ? 'Excel (.xlsx)' : 'PDF (.pdf)'"
            readonly /></label
        ><label
          >Klasifikasi<input
            :value="session.demo ? 'Simulasi · operasional' : 'Operasional'"
            readonly
        /></label>
      </div>
      <div class="notice compact section-space">
        <Icon name="Info" :size="18" />
        <p>
          {{
            kind === 'statistik'
              ? 'Laporan menggunakan nilai agregat yang sama dengan portal publik.'
              : 'Baris Excel mengikuti urutan DUK yang disimpan Super Admin.'
          }}
        </p>
      </div>
      <p v-if="error" class="form-error">{{ error }}</p>
      <button class="button button-primary report-submit" :disabled="busy" @click="generate">
        <Icon name="ArrowDownToLine" :size="17" />{{
          busy ? 'Menyiapkan laporan…' : 'Buat & unduh laporan'
        }}
      </button>
    </section>
    <aside>
      <section class="card official-report">
        <div class="official-icon"><Icon name="FileCheck2" :size="29" /></div>
        <span class="badge blue">Urutan DUK</span>
        <h3>DUK Digital</h3>
        <p>
          Atur posisi pegawai melalui Edit DUK. Urutan yang disimpan Super Admin menjadi acuan
          ekspor Excel.
        </p>
        <button class="button button-outline" @click="official = true">
          Cara mengatur urutan<Icon name="ArrowRight" :size="16" />
        </button>
      </section>
      <section class="card section-space download-history">
        <h3>Unduhan sesi ini</h3>
        <p v-if="!downloads.length" class="empty-inline">Belum ada laporan yang dibuat.</p>
        <div v-for="(d, i) in downloads" :key="i">
          <Icon name="FileText" /><span
            ><strong>{{ d.name }}</strong
            ><small>{{ d.date }} · Tersimpan di perangkat Anda</small></span
          ><Icon name="CheckCircle2" :size="16" />
        </div>
      </section>
    </aside>
  </div>
  <section v-if="!session.demo" class="card section-space">
    <div class="table-toolbar">
      <div>
        <h3>Riwayat laporan Anda</h3>
        <p>Lanjutkan unduhan setelah kembali ke halaman ini. Berkas tersedia selama 7 hari.</p>
      </div>
      <button class="button button-outline" @click="refreshJobs">
        <Icon name="RefreshCw" :size="16" />Perbarui
      </button>
    </div>
    <p v-if="!jobs.length" class="empty-inline">Belum ada permintaan laporan.</p>
    <div v-else class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>LAPORAN</th>
            <th>DIBUAT</th>
            <th>STATUS</th>
            <th>AKSI</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="job in jobs" :key="job.id">
            <td>
              {{
                job.kind === 'duk'
                  ? 'DUK resmi'
                  : job.kind === 'employees'
                    ? 'Rekap pegawai'
                    : 'Ringkasan statistik'
              }}<small class="muted-small"> · {{ job.id.slice(0, 8) }}</small>
            </td>
            <td>{{ dateLabel(job.createdAt) }}</td>
            <td>
              <span class="badge" :class="job.status === 'READY' ? 'green' : 'amber'">{{
                statusLabels[job.status] || job.status
              }}</span>
            </td>
            <td>
              <button
                class="button button-outline"
                :disabled="job.status !== 'READY'"
                @click="downloadJob(job)"
              >
                Unduh {{ job.format.toUpperCase() }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
  <Modal v-if="official" title="Urutan DUK manual" @close="official = false"
    ><p>
      Buka DUK Digital, klik Edit DUK, gunakan panah naik/turun lalu Simpan DUK. Perubahan isi sel
      diperiksa melalui Persetujuan; Super Admin dapat menyetujui usulan sendiri.
    </p>
    <RouterLink to="/internal/duk" class="button button-primary section-space"
      >Buka DUK Digital<Icon name="ArrowRight" :size="16" /></RouterLink
  ></Modal>
</template>
