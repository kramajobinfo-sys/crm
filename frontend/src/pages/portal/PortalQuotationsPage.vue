<template>
  <div>
    <h1 class="text-lg font-semibold text-ink dark:text-ink-dark mb-4">{{ $t('portal.quotations_title') }}</h1>
    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.loading') }}</div>
    <div v-else-if="!quotations.length" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.quotations_empty') }}</div>
    <div v-else class="card divide-y divide-slate-200 dark:divide-slate-700">
      <router-link
        v-for="q in quotations" :key="q.id"
        :to="{ name: 'portal-quotation-detail', params: { id: q.id } }"
        class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800"
      >
        <div>
          <div class="font-medium text-ink dark:text-ink-dark">{{ q.quote_no }}</div>
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted">{{ q.currency }} {{ q.grand_total }}</div>
        </div>
        <span class="text-xs capitalize px-2 py-1 rounded-full"
              :class="q.status === 'accepted' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700'">
          {{ q.status }}
        </span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import portalService from '@/services/portal';

const quotations = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await portalService.quotations();
    quotations.value = data.data;
  } finally {
    loading.value = false;
  }
});
</script>
