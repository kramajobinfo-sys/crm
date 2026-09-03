<template>
  <div>
    <router-link :to="{ name: 'portal-tickets' }" class="text-sm text-primary-600">{{ $t('portal.back_to_tickets') }}</router-link>
    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.loading') }}</div>
    <div v-else-if="!ticket" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.ticket_not_found') }}</div>
    <div v-else class="mt-4">
      <div class="card p-6 mb-4">
        <div class="flex items-center justify-between mb-2">
          <h1 class="text-lg font-semibold text-ink dark:text-ink-dark">{{ ticket.subject }}</h1>
          <span class="text-xs capitalize px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700">{{ ticket.status }}</span>
        </div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mb-3">{{ ticket.ticket_no }}</div>
        <p class="text-sm text-ink dark:text-ink-dark">{{ ticket.description }}</p>
      </div>

      <div class="card divide-y divide-slate-200 dark:divide-slate-700 mb-4">
        <div v-for="r in ticket.replies" :key="r.id" class="px-4 py-3">
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted mb-1 capitalize">{{ r.author_type }} · {{ r.created_at?.slice(0, 16).replace('T', ' ') }}</div>
          <div class="text-sm text-ink dark:text-ink-dark">{{ r.body }}</div>
        </div>
      </div>

      <form @submit.prevent="submitReply" class="card p-4 space-y-3">
        <textarea v-model="reply" rows="3" required class="input" :placeholder="$t('portal.reply_placeholder')"></textarea>
        <button class="btn-primary" :disabled="sending">{{ sending ? $t('portal.sending') : $t('portal.send_reply') }}</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import portalService from '@/services/portal';

const route = useRoute();
const ticket = ref(null);
const loading = ref(true);
const reply = ref('');
const sending = ref(false);

const load = async () => {
  try {
    const { data } = await portalService.ticket(route.params.id);
    ticket.value = data.data;
  } catch {
    ticket.value = null;
  } finally {
    loading.value = false;
  }
};

const submitReply = async () => {
  sending.value = true;
  try {
    await portalService.reply(route.params.id, reply.value);
    reply.value = '';
    await load();
  } finally {
    sending.value = false;
  }
};

onMounted(load);
</script>
