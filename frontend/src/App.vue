<template>
  <router-view />
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { useUiStore } from '@/stores/ui';
const ui = useUiStore();
onMounted(() => { ui.applyTheme(); ui.applyAccent(); ui.applyChrome(); });
watch(() => ui.theme, () => ui.applyTheme());
watch(() => ui.accent, () => ui.applyAccent());
watch(() => ui.chrome, () => ui.applyChrome(), { deep: true });
</script>
