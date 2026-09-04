import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
  plugins: [
    vue(),
    VueI18nPlugin({
      include: [fileURLToPath(new URL('./src/locales/**', import.meta.url))],
      runtimeOnly: true,
      jitCompilation: false,
      compositionOnly: true,
    }),
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
