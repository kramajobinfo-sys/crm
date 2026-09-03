<template>
  <router-view />
</template>

<script setup>
import { onMounted, watch } from 'vue';
import { useUiStore } from '@/stores/ui';
const ui = useUiStore();
onMounted(() => { ui.applyTheme(); if (ui.locale === 'ar') ui.setLocale('ar'); });
watch(() => ui.theme, () => ui.applyTheme());
</script>
