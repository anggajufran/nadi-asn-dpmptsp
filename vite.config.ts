import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import { demoServer } from './scripts/demo-server';
import { createHash } from 'node:crypto';
export default defineConfig(({ command, mode }) => ({
  cacheDir: `node_modules/.vite-${mode}-${process.env.NADI_DEMO_STATE ? createHash('sha256').update(process.env.NADI_DEMO_STATE).digest('hex').slice(0, 12) : 'local'}`,
  plugins: [
    vue(),
    tailwindcss(),
    ...(command === 'serve' && (mode === 'demo' || Boolean(process.env.NADI_DEMO_STATE))
      ? [demoServer()]
      : []),
  ],
  resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
  server: {
    port: 5173,
    proxy:
      mode !== 'demo' && !process.env.NADI_DEMO_STATE
        ? { '/api': { target: 'http://127.0.0.1:8000' } }
        : undefined,
    strictPort: true,
    fs: {
      deny: [
        '**/.env',
        '**/.env.*',
        '**/.git/**',
        '**/.runtime/**',
        '**/storage/**',
        '**/*.md',
        '**/app/**',
        '**/database/**',
        '**/config/**',
        '**/infra/**',
        '**/composer.*',
      ],
    },
  },
  build: {
    outDir: 'dist',
    manifest: true,
    rollupOptions: { output: { manualChunks: { vue: ['vue', 'vue-router'] } } },
  },
}));
