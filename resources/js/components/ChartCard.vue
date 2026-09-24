<script setup lang="ts">
import type { Series } from '@/types';
import { computed } from 'vue';
const props = withDefaults(
  defineProps<{
    title: string;
    subtitle: string;
    series: Series[];
    kind?: 'donut' | 'bar' | 'horizontal';
    total?: number | null;
    badge?: string;
    totalLabel?: string;
    interactive?: boolean;
  }>(),
  { kind: 'horizontal', totalLabel: 'Total ASN' },
);
const emit = defineEmits<{ choose: [label: string] }>();
const colors = ['#2b579a', '#3c8d9c', '#367dba', '#626fb1', '#346f82', '#53648d'];
const sum = computed(() => props.series.reduce((n, s) => n + (s.value || 0), 0));
const max = computed(() => Math.max(...props.series.map((s) => s.value || 0), 1));
const segments = computed(() => {
  let offset = 0;
  return props.series.map((s, i) => {
    const length = ((s.value || 0) / (sum.value || 1)) * 100;
    const segment = {
      ...s,
      length: Math.max(length - 0.6, 0),
      offset: -offset,
      color: s.color || colors[i % colors.length],
    };
    offset += length;
    return segment;
  });
});
</script>
<template>
  <section class="card chart-card">
    <div class="card-heading">
      <div>
        <h3>{{ title }}</h3>
        <p>{{ subtitle }}</p>
      </div>
      <span v-if="badge" class="badge blue">{{ badge }}</span>
    </div>
    <div v-if="series.length === 0" class="empty-inline">Belum ada data untuk kategori ini</div>
    <div v-else-if="kind === 'donut'" class="donut-content">
      <div
        class="donut"
        role="img"
        :aria-label="series.map((s) => `${s.label}: ${s.display || s.value}`).join(', ')"
      >
        <svg viewBox="0 0 160 160">
          <circle cx="80" cy="80" r="62" fill="none" stroke="#edf2f8" stroke-width="21" />
          <circle
            v-for="(s, i) in segments"
            :key="i"
            cx="80"
            cy="80"
            r="62"
            fill="none"
            :stroke="s.color"
            stroke-width="21"
            pathLength="100"
            :stroke-dasharray="`${s.length} ${100 - s.length}`"
            :stroke-dashoffset="s.offset"
            transform="rotate(-90 80 80)"
          />
        </svg>
        <div class="donut-label">
          <strong>{{ total ?? (series.some((s) => s.value === null) ? '—' : sum) }}</strong
          ><span>{{ totalLabel }}</span>
        </div>
      </div>
      <div class="legend">
        <div v-for="(s, i) in series" :key="s.label">
          <span class="legend-name"
            ><i :style="{ background: colors[i % colors.length] }"></i>{{ s.label }}</span
          ><strong>{{ s.display || s.value }}</strong>
        </div>
      </div>
    </div>
    <div v-else-if="kind === 'bar'" class="vertical-chart">
      <div v-for="(s, i) in series" :key="s.label" class="vertical-item">
        <div class="vertical-track">
          <strong>{{ s.display || s.value }}</strong>
          <div
            :style="{
              height: `${((s.value || 0) / max) * 125}px`,
              background: colors[i % colors.length],
            }"
          ></div>
        </div>
        <span>{{ s.label }}</span>
      </div>
    </div>
    <div v-else class="horizontal-chart">
      <component
        :is="interactive ? 'button' : 'div'"
        v-for="(s, i) in series"
        :key="s.label"
        class="horizontal-item"
        @click="interactive && emit('choose', s.label)"
        ><span
          ><span>{{ s.label }}</span
          ><strong>{{ s.display || s.value }}</strong></span
        >
        <div class="bar-track">
          <i
            :style="{
              width: `${((s.value || 0) / max) * 100}%`,
              background: colors[i % colors.length],
            }"
          ></i></div
      ></component>
    </div>
    <slot />
  </section>
</template>
