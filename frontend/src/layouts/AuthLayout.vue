<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-white to-slate-100
              dark:from-surface-dark dark:via-surface-dark-muted dark:to-surface-dark">
    <div class="w-full max-w-md p-6">
      <div class="flex items-center justify-center mb-8">
        <img v-if="tenant?.logo_url" :src="tenant.logo_url" class="w-12 h-12 rounded-lg object-cover" alt="" />
        <div v-else class="w-12 h-12 rounded-lg bg-primary-600 text-white flex items-center justify-center text-lg font-semibold"
             :style="tenant?.primary_color ? { backgroundColor: tenant.primary_color } : {}">
          {{ tenant ? tenant.name[0].toUpperCase() : 'N' }}
        </div>
        <div class="ml-3">
          <div class="text-lg font-semibold text-ink dark:text-ink-dark">{{ tenant?.name || $t('app.name') }}</div>
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted">Enterprise</div>
        </div>
      </div>

      <div class="card p-8">
        <router-view />
      </div>

      <p class="mt-6 text-center text-xs text-ink-subtle dark:text-ink-dark-subtle">
        © {{ new Date().getFullYear() }} Krama. All rights reserved.
      </p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import tenantService from '@/services/tenant';

const tenant = ref(null);

onMounted(async () => {
  try { const { data } = await tenantService.info(); tenant.value = data.data; }
  catch { /* no tenant for this host — default Krama branding stays */ }
});
</script>
