<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { api } from '@/api/client';
import { dateLabel } from '@/composables';
import Icon from '@/components/Icon.vue';
import type { AuditEvent } from '@/types';
const rows = ref<AuditEvent[]>([]),
  search = ref(''),
  error = ref(''),
  page = ref(1);
const filtered = computed(() =>
  rows.value.filter((e) =>
    `${e.actor} ${e.action} ${e.subject}`.toLowerCase().includes(search.value.toLowerCase()),
  ),
);
onMounted(async () => {
  try {
    rows.value = await api('audit');
  } catch (e) {
    error.value = (e as Error).message;
  }
});
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow">TRANSPARANSI OPERASIONAL</span>
      <h1>Audit Log<span class="heading-dot">.</span></h1>
      <p>Setiap tindakan penting memiliki jejak, pelaku, dan waktu yang jelas.</p>
    </div>
    <span class="badge blue"><Icon name="History" :size="14" />Hanya baca</span>
  </div>
  <section class="card">
    <div class="table-toolbar">
      <div>
        <h3>Jejak aktivitas</h3>
        <p>{{ rows.length }} peristiwa tercatat pada lingkungan ini</p>
      </div>
      <div class="search-field">
        <Icon name="Search" :size="16" /><input
          v-model="search"
          @input="page = 1"
          placeholder="Cari pelaku atau aktivitas…"
          aria-label="Cari audit log"
        />
      </div>
    </div>
    <p v-if="error" class="form-error">{{ error }}</p>
    <div v-else-if="!filtered.length" class="empty-state">
      <Icon name="History" :size="35" />
      <h3>Belum ada aktivitas yang sesuai</h3>
      <p>Jejak akan muncul setelah tindakan dilakukan pada aplikasi.</p>
    </div>
    <div v-else class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>WAKTU</th>
            <th>PELAKU</th>
            <th>AKTIVITAS</th>
            <th>OBJEK</th>
            <th>ID PERISTIWA</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in filtered.slice((page - 1) * 15, page * 15)" :key="e.id">
            <td>
              <div class="table-detail">
                <strong>{{ dateLabel(e.createdAt) }}</strong
                ><small
                  >{{
                    new Date(e.createdAt).toLocaleTimeString('id-ID', { timeZone: 'Asia/Makassar' })
                  }}
                  WITA</small
                >
              </div>
            </td>
            <td>{{ e.actor }}</td>
            <td>{{ e.action }}</td>
            <td>{{ e.subject }}</td>
            <td>
              <code class="muted-small">{{ e.id.slice(0, 8) }}</code>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="pagination">
      <span>Catatan audit tidak dapat diubah melalui aplikasi.</span>
      <div>
        <button class="icon-button" :disabled="page === 1" aria-label="Sebelumnya" @click="page--">
          <Icon name="ChevronLeft" :size="17" /></button
        ><span>{{ page }}</span
        ><button
          class="icon-button"
          :disabled="page * 15 >= filtered.length"
          aria-label="Berikutnya"
          @click="page++"
        >
          <Icon name="ChevronRight" :size="17" />
        </button>
      </div>
    </div>
  </section>
</template>
