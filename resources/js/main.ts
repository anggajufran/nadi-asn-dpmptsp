import '@fontsource/plus-jakarta-sans/latin-400.css';
import '@fontsource/plus-jakarta-sans/latin-500.css';
import '@fontsource/plus-jakarta-sans/latin-600.css';
import '@fontsource/plus-jakarta-sans/latin-700.css';
import '@fontsource/plus-jakarta-sans/latin-800.css';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { registerNadiTools } from './api/webmcp';
import PublicDashboard from './apps/public/PublicDashboard.vue';
import '../css/app.css';
try {
  document.documentElement.dataset.theme =
    localStorage.getItem('nadi-theme') === 'dark' ? 'dark' : 'light';
} catch {
  document.documentElement.dataset.theme = 'light';
}
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: PublicDashboard },
    { path: '/tentang', component: () => import('./apps/public/AboutPage.vue') },
    { path: '/aktivasi', component: () => import('./apps/internal/ActivationPage.vue') },
    { path: '/login', component: () => import('./apps/internal/LoginPage.vue') },
    { path: '/internal/:page?', component: () => import('./apps/internal/InternalApp.vue') },
    { path: '/:pathMatch(.*)*', component: () => import('./apps/public/NotFound.vue') },
  ],
  scrollBehavior(to) {
    if (to.hash && to.path !== '/aktivasi') return { el: to.hash, behavior: 'smooth' };
    return { top: 0 };
  },
});
router.afterEach((to) => {
  document.title = `${to.path.startsWith('/internal') ? 'Ruang Internal' : to.path === '/login' ? 'Login Internal' : 'Dashboard Statistik'} | NADI ASN DPMPTSP Prov. Sulawesi Tengah`;
  let meta = document.querySelector('meta[name="robots"]');
  if (!meta) {
    meta = document.createElement('meta');
    meta.setAttribute('name', 'robots');
    document.head.appendChild(meta);
  }
  meta.setAttribute(
    'content',
    to.path.startsWith('/internal') ||
      ['/login', '/aktivasi'].includes(to.path) ||
      Object.keys(to.query).length
      ? 'noindex,nofollow'
      : 'index,follow',
  );
});
createApp(App).use(router).mount('#app');

registerNadiTools(router);
