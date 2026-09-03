<template>
  <div>
    <h1 class="text-lg font-semibold text-ink dark:text-ink-dark mb-4">{{ $t('portal.invoices_title') }}</h1>
    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.loading') }}</div>
    <div v-else-if="!invoices.length" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.invoices_empty') }}</div>
    <div v-else class="card divide-y divide-slate-200 dark:divide-slate-700">
      <router-link
        v-for="inv in invoices" :key="inv.id"
        :to="{ name: 'portal-invoice-detail', params: { id: inv.id } }"
        class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800"
      >
        <div>
          <div class="font-medium text-ink dark:text-ink-dark">{{ inv.invoice_no }}</div>
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted">
            {{ $t('portal.due') }} {{ inv.due_date?.slice(0, 10) }}
            <span v-if="inv.is_overdue" class="text-red-600 font-medium ml-1">{{ $t('portal.overdue') }}</span>
          </div>
        </div>
        <div class="text-right">
          <div class="font-medium text-ink dark:text-ink-dark">{{ inv.currency }} {{ inv.grand_total }}</div>
          <div class="text-xs capitalize text-ink-muted dark:text-ink-dark-muted">{{ inv.status.replace('_', ' ') }}</div>
        </div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import portalService from '@/services/portal';

const invoices = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await portalService.invoices();
    invoices.value = data.data;
  } finally {
    loading.value = false;
  }
});
</script>
