<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-semibold text-ink dark:text-ink-dark">{{ $t('portal.tickets_title') }}</h1>
      <button class="btn-primary" @click="showNew = !showNew">{{ showNew ? $t('portal.cancel') : $t('portal.new_ticket') }}</button>
    </div>

    <form v-if="showNew" @submit.prevent="submit" class="card p-4 mb-6 space-y-3">
      <div>
        <label class="label">{{ $t('portal.subject') }}</label>
        <input v-model="subject" required class="input" />
      </div>
      <div>
        <label class="label">{{ $t('portal.description') }}</label>
        <textarea v-model="description" rows="3" class="input"></textarea>
      </div>
      <button class="btn-primary" :disabled="creating">{{ creating ? $t('portal.creating') : $t('portal.submit_ticket') }}</button>
    </form>

    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.loading') }}</div>
    <div v-else-if="!tickets.length" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.tickets_empty') }}</div>
    <div v-else class="card divide-y divide-slate-200 dark:divide-slate-700">
      <router-link
        v-for="t in tickets" :key="t.id"
        :to="{ name: 'portal-ticket-detail', params: { id: t.id } }"
        class="flex items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800"
      >
        <div>
          <div class="font-medium text-ink dark:text-ink-dark">{{ t.subject }}</div>
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted">{{ t.ticket_no }}</div>
        </div>
        <span class="text-xs capitalize px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700">{{ t.status }}</span>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import portalService from '@/services/portal';

const tickets = ref([]);
const loading = ref(true);
const showNew = ref(false);
const subject = ref('');
const description = ref('');
const creating = ref(false);

const load = async () => {
  loading.value = true;
  try {
    const { data } = await portalService.tickets();
    tickets.value = data.data;
  } finally {
    loading.value = false;
  }
};

const submit = async () => {
  creating.value = true;
  try {
    await portalService.createTicket({ subject: subject.value, description: description.value });
    subject.value = ''; description.value = ''; showNew.value = false;
    await load();
  } finally {
    creating.value = false;
  }
};

onMounted(load);
</script>
