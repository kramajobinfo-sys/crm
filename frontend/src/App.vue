<template>
  <router-view />
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { useUiStore } from '@/stores/ui';
const ui = useUiStore();
onMounted(() => { ui.applyTheme(); ui.applyAccent(); });
watch(() => ui.theme, () => ui.applyTheme());
watch(() => ui.accent, () => ui.applyAccent());
</script>
