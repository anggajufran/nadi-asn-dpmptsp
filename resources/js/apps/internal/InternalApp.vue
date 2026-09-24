<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import { computed, ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { session, loadSession, post, api } from '@/api/client';
import { roleLabel, initials } from '@/composables';
import Icon from '@/components/Icon.vue';
import DashboardPage from '@/modules/dashboard/DashboardPage.vue';
import PersonnelPage from '@/modules/personnel/PersonnelPage.vue';
import GovernancePage from '@/modules/governance/GovernancePage.vue';
import ImportsPage from '@/modules/imports/ImportsPage.vue';
import RadarPage from '@/modules/radar/RadarPage.vue';
import DukPage from '@/modules/duk/DukPage.vue';
import ReportsPage from '@/modules/reports/ReportsPage.vue';
import SettingsPage from '@/modules/settings/SettingsPage.vue';
import AuditPage from '@/modules/audit/AuditPage.vue';
const route = useRoute(),
  router = useRouter(),
  sidebar = ref(false),
  notifications = ref(false),
  pending = ref(0),
  globalSearch = ref('');
const collapsed = ref(false);
try {
  collapsed.value = localStorage.getItem('nadi-sidebar-collapsed') === 'true';
} catch {}
watch(collapsed, (value) => {
  try {
    localStorage.setItem('nadi-sidebar-collapsed', String(value));
  } catch {}
});
const mobileQuery = window.matchMedia('(max-width: 850px)');
const mobile = ref(mobileQuery.matches);
const menuButton = ref<HTMLButtonElement | null>(null);
const sidebarElement = ref<HTMLElement | null>(null);
const updateMobile = () => {
  mobile.value = mobileQuery.matches;
  if (!mobile.value) sidebar.value = false;
};
onMounted(() => mobileQuery.addEventListener('change', updateMobile));
onUnmounted(() => mobileQuery.removeEventListener('change', updateMobile));
async function openSidebar() {
  sidebar.value = true;
  await nextTick();
  sidebarElement.value?.querySelector<HTMLButtonElement>('.sidebar-close')?.focus();
}
async function closeSidebar() {
  sidebar.value = false;
  await nextTick();
  menuButton.value?.focus();
}
function sidebarKeydown(event: KeyboardEvent) {
  if (!mobile.value || !sidebar.value) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    closeSidebar();
  }
  if (event.key === 'Tab') {
    const items = Array.from(
      sidebarElement.value?.querySelectorAll<HTMLElement>('a, button') || [],
    ).filter((el) => el.offsetParent !== null);
    const first = items[0],
      last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first?.focus();
    }
  }
}
const page = computed(() => String(route.params.page || 'dashboard'));
const nav = [
  { label: 'Command Center', id: 'dashboard', icon: 'LayoutDashboard', group: 'UTAMA' },
  { label: 'Master Pegawai', id: 'pegawai', icon: 'Users' },
  { label: 'Import Excel', id: 'import', icon: 'UploadCloud', write: true },
  { label: 'DUK Digital', id: 'duk', icon: 'ListOrdered', group: 'MONITORING & LAPORAN' },
  { label: 'KP Radar', id: 'kp', icon: 'Radar' },
  { label: 'KGB Radar', id: 'kgb', icon: 'Clock3' },
  { label: 'Laporan & Export', id: 'laporan', icon: 'FileSpreadsheet', super: true },
  {
    label: 'Persetujuan',
    id: 'persetujuan',
    icon: 'FileCheck2',
    group: 'TATA KELOLA',
    write: true,
  },
  { label: 'Audit Log', id: 'audit', icon: 'History', write: true },
  { label: 'Pengaturan', id: 'pengaturan', icon: 'Settings2', super: true },
];
const visibleNav = computed(() =>
  nav.filter((n) => !n.super || session.user?.role === 'SUPER_ADMIN'),
);
const title = computed(
  () => nav.find((n) => n.id === page.value)?.label || 'Halaman tidak tersedia',
);
const accessible = computed(() => visibleNav.value.some((n) => n.id === page.value));
const pages: Record<string, any> = {
  dashboard: DashboardPage,
  pegawai: PersonnelPage,
  import: ImportsPage,
  duk: DukPage,
  kp: RadarPage,
  kgb: RadarPage,
  laporan: ReportsPage,
  persetujuan: GovernancePage,
  audit: AuditPage,
  pengaturan: SettingsPage,
};
async function count() {
  try {
    const d = await api('dashboard');
    pending.value = d.pending;
  } catch {}
}
onMounted(async () => {
  try {
    await loadSession();
    if (!session.user) return router.replace('/login');
    count();
  } catch {
    router.replace('/login');
  }
});
watch(page, () => {
  sidebar.value = false;
  notifications.value = false;
  count();
});
watch(
  () => session.user,
  (user) => {
    if (session.ready && !user) router.replace('/login');
  },
);
async function logout() {
  try {
    await post('logout', {});
    session.user = null;
    router.push('/login');
  } catch {
    notifications.value = false;
    alert('Belum berhasil keluar. Periksa koneksi lalu coba lagi.');
  }
}
function search() {
  if (globalSearch.value.trim())
    router.push({ path: '/internal/pegawai', query: { q: globalSearch.value } });
}
</script>
<template>
  <div v-if="!session.ready" class="loading-state">
    <span class="spinner"></span>Memeriksa sesi…
  </div>
  <div
    v-else-if="session.user"
    class="internal-app"
    :class="{ 'sidebar-minimized': collapsed && !mobile }"
  >
    <div v-if="sidebar" class="sidebar-overlay" @click="closeSidebar"></div>
    <aside
      id="internal-navigation"
      ref="sidebarElement"
      class="sidebar"
      :class="{ open: sidebar }"
      :inert="mobile && !sidebar"
      @keydown="sidebarKeydown"
    >
      <button
        v-if="mobile"
        class="icon-button sidebar-close"
        aria-label="Tutup navigasi"
        @click="closeSidebar"
      >
        <Icon name="X" />
      </button>
      <RouterLink to="/internal" class="sidebar-brand"
        ><img src="/logo-nadi-asn.png" alt="NADI ASN DPMPTSP Prov. Sulawesi Tengah" />
        <div>
          <strong>NADI<span>ASN</span></strong
          ><small>DPMPTSP Prov. Sulawesi Tengah</small>
        </div></RouterLink
      >
      <div class="workspace-label">
        <span class="small-dot"></span>Ruang Internal <Icon name="LockKeyhole" :size="12" />
      </div>
      <nav aria-label="Navigasi internal">
        <template v-for="item in visibleNav" :key="item.id"
          ><div v-if="item.group" class="nav-group">{{ item.group }}</div>
          <RouterLink
            :to="item.id === 'dashboard' ? '/internal' : `/internal/${item.id}`"
            :aria-label="item.label"
            :title="collapsed && !mobile ? item.label : undefined"
            class="nav-item"
            :class="{ active: page === item.id }"
            ><Icon :name="item.icon" :size="18" /><span>{{ item.label }}</span
            ><span v-if="item.id === 'persetujuan' && pending" class="nav-count">{{
              pending
            }}</span></RouterLink
          ></template
        >
      </nav>
      <div class="sidebar-bottom">
        <RouterLink
          to="/"
          class="nav-item"
          aria-label="Lihat portal publik"
          title="Lihat portal publik"
          ><Icon name="ArrowUpRight" :size="18" /><span>Lihat portal publik</span></RouterLink
        >
        <div class="sidebar-profile">
          <div class="avatar">{{ initials(session.user.name) }}</div>
          <div>
            <strong>{{ session.user.name }}</strong
            ><small>{{ roleLabel(session.user.role) }}</small>
          </div>
          <button class="icon-button" aria-label="Keluar akun" @click="logout">
            <Icon name="LogOut" :size="17" />
          </button>
        </div>
      </div>
    </aside>
    <div class="internal-workspace" :inert="mobile && sidebar">
      <header class="internal-header">
        <div class="header-crumb">
          <button
            v-if="!mobile"
            class="icon-button sidebar-toggle"
            :aria-label="collapsed ? 'Perluas navigasi' : 'Minimalkan navigasi'"
            :title="collapsed ? 'Perluas navigasi' : 'Minimalkan navigasi'"
            :aria-expanded="!collapsed"
            aria-controls="internal-navigation"
            @click="collapsed = !collapsed"
          >
            <Icon :name="collapsed ? 'PanelLeftOpen' : 'PanelLeftClose'" />
          </button>
          <button
            class="icon-button mobile-menu"
            ref="menuButton"
            aria-label="Buka navigasi"
            :aria-expanded="sidebar"
            aria-controls="internal-navigation"
            @click="openSidebar"
          >
            <Icon name="Menu" /></button
          ><span>Ruang Internal</span><Icon name="ChevronRight" :size="13" /><strong>{{
            title
          }}</strong>
        </div>
        <div class="header-actions">
          <ThemeToggle />
          <form class="global-search" @submit.prevent="search">
            <Icon name="Search" :size="16" /><input
              v-model="globalSearch"
              aria-label="Cari ASN"
              placeholder="Cari nama atau NIP…"
            /><kbd>↵</kbd>
          </form>
          <div class="notification-wrap">
            <button
              class="icon-button notification-button"
              aria-label="Buka notifikasi"
              :aria-expanded="notifications"
              @click="notifications = !notifications"
            >
              <Icon name="Bell" /><span v-if="pending" class="unread-dot"></span>
            </button>
            <div v-if="notifications" class="notification-popover card">
              <h3>Pusat notifikasi</h3>
              <p>
                {{
                  pending
                    ? `${pending} usulan menunggu pemeriksaan.`
                    : 'Belum ada pemberitahuan baru.'
                }}
              </p>
              <RouterLink v-if="pending" to="/internal/persetujuan" class="text-link"
                >Buka persetujuan<Icon name="ArrowRight" :size="14"
              /></RouterLink>
              <p class="muted-small">Membaca notifikasi tidak mengubah status radar.</p>
            </div>
          </div>
          <div class="header-separator"></div>
          <div class="avatar small">{{ initials(session.user.name) }}</div>
        </div>
      </header>
      <div v-if="session.demo" class="demo-strip">
        <Icon name="Info" :size="14" /><span
          >Mode simulasi — seluruh data pegawai adalah contoh. Bukan data atau keputusan
          resmi.</span
        ><span class="demo-role">{{ roleLabel(session.user.role) }}</span>
      </div>
      <main class="internal-main">
        <component
          :is="pages[page]"
          v-if="accessible"
          :key="page"
          v-bind="['kp', 'kgb'].includes(page) ? { kind: page } : {}"
          v-on="page === 'persetujuan' ? { updated: count } : {}"
        />
        <div v-else class="card empty-state">
          <Icon name="ShieldAlert" :size="38" />
          <h2>Halaman tidak tersedia untuk peran Anda.</h2>
          <RouterLink class="button button-primary" to="/internal">Kembali ke dashboard</RouterLink>
        </div>
      </main>
      <footer class="internal-footer">
        <span>© 2026 NADI ASN · DPMPTSP Prov. Sulawesi Tengah · Developer: Angga Jufran</span
        ><span>Data ASN dalam Satu Pandangan <Icon name="HeartPulse" :size="14" /></span>
      </footer>
    </div>
  </div>
</template>
