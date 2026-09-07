import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';
import { fileURLToPath, URL } from 'node:url';

// A per-build identifier. Baked into the running app (__BUILD_ID__) and emitted as
// /version.json, so a loaded tab can detect when a newer build has been deployed.
const BUILD_ID = String(Date.now());

export default defineConfig({
  define: { __BUILD_ID__: JSON.stringify(BUILD_ID) },
  plugins: [
    vue(),
    VueI18nPlugin({
      include: [fileURLToPath(new URL('./src/locales/**', import.meta.url))],
      runtimeOnly: true,
      jitCompilation: false,
      compositionOnly: true,
    }),
    {
      name: 'emit-version-json',
      generateBundle() {
        this.emitFile({ type: 'asset', fileName: 'version.json', source: JSON.stringify({ build: BUILD_ID }) });
      },
    },
  ],
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  server: {
    host: '0.0.0.0',        // listen on all interfaces (LAN reachable)
    port: 5173,
    strictPort: true,
    // Accept requests for any host header (LAN IP, hostname, etc.)
    allowedHosts: true,
    // Proxy API calls to the nginx container so the browser only ever
    // talks to ONE origin (the IP:8080). This sidesteps CORS entirely.
    proxy: {
      '/api': { target: 'http://nginx:80', changeOrigin: true },
    },
    // Make HMR work when accessed over the LAN IP
    hmr: { clientPort: 8081 },
  },
  build: {
    sourcemap: false,
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      output: { manualChunks: { vendor: ['vue', 'vue-router', 'pinia'], charts: ['chart.js', 'vue-chartjs'] } },
    },
  },
});
