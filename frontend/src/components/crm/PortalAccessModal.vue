<template>
  <div v-if="open" class="fixed inset-0 z-[75] flex items-center justify-center bg-black/45 p-4" @click.self="close">
    <div class="card w-full max-w-md p-4">
      <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 flex items-center justify-center shrink-0">
          <KeyRound :size="17" />
        </div>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-ink dark:text-ink-dark">{{ $t('contacts.portal_manage') }}</div>
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted truncate">{{ contact?.name }} · {{ contact?.email || $t('contacts.no_email') }}</div>
        </div>
        <button class="ml-auto p-1 text-ink-subtle" :disabled="saving" @click="close"><X :size="16" /></button>
      </div>

      <div v-if="!contact?.email" class="mt-4 rounded border border-amber-200 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-800 p-3 text-xs text-amber-800 dark:text-amber-300">
        {{ $t('contacts.portal_email_required') }}
      </div>

      <div class="mt-4 space-y-3">
        <label class="flex items-center justify-between gap-3 rounded border border-slate-200 dark:border-slate-700 p-3">
          <span>
            <span class="block text-xs font-medium text-ink dark:text-ink-dark">{{ $t('contacts.portal_allow') }}</span>
            <span class="block text-[11px] text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('contacts.portal_allow_hint') }}</span>
          </span>
          <input v-model="enabled" type="checkbox" class="rounded border-slate-300" :disabled="!contact?.email" />
        </label>

        <div v-if="enabled">
          <label class="label">{{ contact?.portal_enabled ? $t('contacts.portal_new_password') : $t('contacts.portal_password') }}</label>
          <input v-model="password" type="password" autocomplete="new-password" class="input text-sm w-full" minlength="8" :placeholder="contact?.portal_enabled ? $t('contacts.portal_password_optional') : '••••••••'" />
          <p class="text-[10px] text-ink-subtle mt-1">{{ $t('contacts.portal_password_hint') }}</p>
        </div>
      </div>

      <p v-if="error" class="text-xs text-red-600 mt-3">{{ error }}</p>

      <div class="flex items-center gap-2 mt-4">
        <a href="/portal/login" target="_blank" class="text-xs text-primary-600 hover:underline">{{ $t('contacts.portal_open') }}</a>
        <div class="ml-auto flex gap-2">
          <button class="btn-secondary btn-sm" :disabled="saving" @click="close">{{ $t('contacts.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="saving || !contact?.email || (enabled && !contact?.portal_enabled && password.length < 8)" @click="save">
            {{ saving ? $t('contacts.saving') : $t('contacts.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { KeyRound, X } from 'lucide-vue-next';
import customerApi from '@/services/customers';

const props = defineProps({ open: Boolean, contact: { type: Object, default: null } });
const emit = defineEmits(['close', 'updated']);
const enabled = ref(false);
const password = ref('');
const saving = ref(false);
const error = ref('');

watch(() => [props.open, props.contact?.id], () => {
  if (props.open) {
    enabled.value = !!props.contact?.portal_enabled;
    password.value = '';
    error.value = '';
  }
});

function close() { if (!saving.value) emit('close'); }

async function save() {
  saving.value = true; error.value = '';
  try {
    const payload = { portal_enabled: enabled.value };
    if (password.value) payload.password = password.value;
    const { data } = await customerApi.updateContactPortal(props.contact.customer_id || props.contact.account?.id, props.contact.id, payload);
    emit('updated', data.data);
  } catch (requestError) {
    error.value = requestError.response?.data?.message || Object.values(requestError.response?.data?.errors || {})[0]?.[0] || 'Unable to update portal access.';
  } finally { saving.value = false; }
}
</script>
