<template>
  <div>
    <router-link :to="{ name: 'portal-invoices' }" class="text-sm text-primary-600">{{ $t('portal.back_to_invoices') }}</router-link>
    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.loading') }}</div>
    <div v-else-if="!invoice" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.invoice_not_found') }}</div>
    <div v-else class="card p-6 mt-4">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-lg font-semibold text-ink dark:text-ink-dark">{{ invoice.invoice_no }}</h1>
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted">
            {{ $t('portal.issued') }} {{ invoice.issue_date?.slice(0, 10) }} · {{ $t('portal.due') }} {{ invoice.due_date?.slice(0, 10) }}
          </div>
        </div>
        <span class="text-xs capitalize px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700">{{ invoice.status.replace('_', ' ') }}</span>
      </div>

      <table class="data-table mb-6">
        <thead>
          <tr>
            <th>{{ $t('portal.item') }}</th><th class="th-num">{{ $t('portal.qty') }}</th><th class="th-num">{{ $t('portal.unit_price') }}</th><th class="th-num">{{ $t('portal.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in invoice.items" :key="i">
            <td>{{ item.name }}</td>
            <td class="td-num">{{ item.quantity }}</td>
            <td class="td-num">{{ item.unit_price }}</td>
            <td class="td-num">{{ item.line_total }}</td>
          </tr>
        </tbody>
      </table>

      <div class="flex justify-end">
        <div class="w-56 text-sm space-y-1">
          <div class="flex justify-between"><span class="text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.total') }}</span><span>{{ invoice.currency }} {{ invoice.grand_total }}</span></div>
          <div class="flex justify-between"><span class="text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.paid') }}</span><span>{{ invoice.currency }} {{ invoice.amount_paid }}</span></div>
          <div class="flex justify-between font-medium"><span>{{ $t('portal.balance') }}</span><span>{{ invoice.currency }} {{ invoice.balance }}</span></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import portalService from '@/services/portal';

const route = useRoute();
const invoice = ref(null);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await portalService.invoice(route.params.id);
    invoice.value = data.data;
  } catch {
    invoice.value = null;
  } finally {
    loading.value = false;
  }
});
</script>
