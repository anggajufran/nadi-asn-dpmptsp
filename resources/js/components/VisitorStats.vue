<script setup lang="ts">
import { computed } from 'vue';
import Icon from './Icon.vue';
import { visitorState } from '@/api/visitors';
import { dateLabel } from '@/composables';
const metrics = [
  { key: 'online', label: 'Sedang online', icon: 'Activity' },
  { key: 'today', label: 'Hari ini', icon: 'Users' },
  { key: 'yesterday', label: 'Kemarin', icon: 'History' },
  { key: 'week', label: 'Minggu ini', icon: 'CalendarDays' },
  { key: 'month', label: 'Bulan ini', icon: 'CalendarDays' },
  { key: 'year', label: 'Tahun ini', icon: 'ChartColumn' },
  { key: 'total', label: 'Total kunjungan', icon: 'Eye' },
] as const;
const updated = computed(
  () =>
    visitorState.data &&
    new Date(visitorState.data.updatedAt).toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'Asia/Makassar',
    }),
);
const format = (n: number) => n.toLocaleString('id-ID');
</script>
<template>
  <section class="card visitor-stats" aria-label="Statistik pengunjung">
    <div class="visitor-heading">
      <div>
        <span class="eyebrow">AKTIVITAS WEBSITE</span>
        <h2>Statistik pengunjung</h2>
        <p>Kunjungan ke NADI ASN, dari hari ke hari.</p>
      </div>
      <span class="visitor-live" :class="{ stale: visitorState.unavailable }">
        <span></span
        >{{
          visitorState.unavailable
            ? 'Pembaruan tertunda'
            : visitorState.data?.demo
              ? 'Data simulasi'
              : 'Diperbarui otomatis'
        }}
      </span>
    </div>
    <div class="visitor-grid">
      <article
        v-for="metric in metrics"
        :key="metric.key"
        class="visitor-metric"
        :class="{
          'visitor-online': metric.key === 'online',
          'visitor-total': metric.key === 'total',
        }"
      >
        <Icon :name="metric.icon" :size="18" />
        <span>{{ metric.label }}</span>
        <strong>{{ visitorState.data ? format(visitorState.data[metric.key]) : '—' }}</strong>
        <small>{{
          metric.key === 'online'
            ? 'Aktif 5 menit terakhir'
            : metric.key === 'total'
              ? 'Akumulasi harian'
              : 'Pengunjung harian'
        }}</small>
      </article>
    </div>
    <div class="visitor-footnote">
      <p>
        Satu browser dihitung sekali per hari. Minggu dimulai Senin; semua periode mengikuti WITA.
        Angka periode adalah jumlah kunjungan unik harian.
      </p>
      <span v-if="visitorState.data?.trackingSince"
        >Dicatat sejak {{ dateLabel(visitorState.data.trackingSince) }} · {{ updated }} WITA</span
      >
      <span v-else>{{
        visitorState.unavailable
          ? 'Statistik belum dapat diperbarui.'
          : 'Menyiapkan statistik kunjungan…'
      }}</span>
    </div>
  </section>
</template>
<style scoped>
.visitor-stats {
  padding: 24px;
  margin: 22px 0 0;
}
.visitor-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
}
.visitor-heading h2 {
  font-size: 21px;
  margin-top: 5px;
}
.visitor-heading p {
  color: var(--muted);
  font-size: 12px;
  margin-top: 7px;
}
.visitor-live {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: var(--teal);
  font-size: 11px;
  white-space: nowrap;
}
.visitor-live > span {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
  box-shadow: 0 0 0 4px color-mix(in srgb, currentColor 10%, transparent);
}
.visitor-live.stale {
  color: var(--muted);
}
.visitor-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 10px;
}
.visitor-metric {
  min-width: 0;
  padding: 16px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 9px;
}
.visitor-metric svg {
  color: var(--muted);
}
.visitor-metric > span {
  font-size: 11px;
  color: var(--muted);
}
.visitor-metric strong {
  font-size: 28px;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.04em;
  overflow-wrap: anywhere;
}
.visitor-metric small {
  color: var(--muted);
  font-size: 9px;
  line-height: 1.5;
}
.visitor-online {
  border-color: color-mix(in srgb, var(--teal) 30%, var(--border));
  background: color-mix(in srgb, var(--teal) 5%, transparent);
}
.visitor-online strong,
.visitor-online svg {
  color: var(--teal);
}
.visitor-total {
  background: color-mix(in srgb, var(--blue) 5%, transparent);
}
.visitor-total strong,
.visitor-total svg {
  color: var(--blue);
}
.visitor-footnote {
  display: flex;
  gap: 18px;
  justify-content: space-between;
  margin-top: 17px;
  padding-top: 15px;
  border-top: 1px solid var(--border);
  color: var(--muted);
  font-size: 10px;
  line-height: 1.7;
}
.visitor-footnote p {
  max-width: 670px;
}
.visitor-footnote > span {
  flex-shrink: 0;
}
@media (max-width: 1100px) {
  .visitor-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .visitor-footnote {
    flex-direction: column;
    gap: 6px;
  }
}
@media (max-width: 600px) {
  .visitor-stats {
    padding: 18px;
  }
  .visitor-heading {
    align-items: flex-start;
    flex-direction: column;
  }
  .visitor-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .visitor-total {
    grid-column: span 2;
  }
}
</style>
