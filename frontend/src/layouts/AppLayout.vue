<template>
  <div class="min-h-screen flex bg-surface-muted dark:bg-surface-dark">
    <Sidebar />
    <div v-if="ui.sidebarMobileOpen" class="fixed inset-0 z-40 bg-black/40 md:hidden" @click="ui.closeMobileSidebar()" />
    <Sidebar v-if="ui.sidebarMobileOpen" mobile />
    <div class="flex-1 flex flex-col min-w-0">
      <Topbar />
      <main class="flex-1 overflow-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import Sidebar from '@/components/layout/Sidebar.vue';
import Topbar  from '@/components/layout/Topbar.vue';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';

const ui = useUiStore();
const auth = useAuthStore();

onMounted(() => auth.loadMe().catch(() => {}));
</script>
