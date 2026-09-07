<template>
  <router-view />

  <!-- Auto-update: appears when a newer build has been deployed. -->
  <transition name="fade">
    <div v-if="updateAvailable"
         class="fixed z-[100] bottom-4 right-4 max-w-xs card shadow-pop p-3.5 flex items-start gap-3">
      <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 flex items-center justify-center shrink-0">
        <RefreshCw :size="16" />
      </div>
      <div class="min-w-0">
        <div class="text-[13px] font-semibold text-ink dark:text-ink-dark">New version available</div>
        <div class="text-xs text-ink-subtle mt-0.5">Reload to get the latest update.</div>
        <div class="flex items-center gap-2 mt-2.5">
          <button class="btn-primary btn-sm" @click="reloadNow">Reload</button>
          <button class="btn-ghost btn-sm" @click="updateAvailable = false">Later</button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useUiStore } from '@/stores/ui';
import { RefreshCw } from 'lucide-vue-next';

const ui = useUiStore();

// ---- Build auto-updater -----------------------------------------------------
// __BUILD_ID__ is stamped in at build time (vite.config.js) and emitted as
// /version.json. We poll it and flag when the deployed build differs from ours.
const RUNNING = typeof __BUILD_ID__ !== 'undefined' ? __BUILD_ID__ : '';
const updateAvailable = ref(false);
let versionTimer = null;

async function checkVersion() {
  if (updateAvailable.value || !RUNNING) return;
  try {
    const res = await fetch(`/version.json?t=${Date.now()}`, { cache: 'no-store' });
    if (!res.ok) return;
    const { build } = await res.json();
    if (build && build !== RUNNING) {
      updateAvailable.value = true;
      if (versionTimer) { clearInterval(versionTimer); versionTimer = null; }
    }
  } catch { /* offline / transient — try again next tick */ }
}

function reloadNow() { window.location.reload(); }
function onVisible() { if (document.visibilityState === 'visible') checkVersion(); }

onMounted(() => {
  ui.applyTheme(); ui.applyAccent(); ui.applyChrome();
  versionTimer = setInterval(checkVersion, 180000); // every 3 min
  document.addEventListener('visibilitychange', onVisible);
});
onUnmounted(() => {
  if (versionTimer) clearInterval(versionTimer);
  document.removeEventListener('visibilitychange', onVisible);
});

watch(() => ui.theme, () => ui.applyTheme());
watch(() => ui.accent, () => ui.applyAccent());
watch(() => ui.chrome, () => ui.applyChrome(), { deep: true });
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .2s ease, transform .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(6px); }
</style>
