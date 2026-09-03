<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 via-white to-slate-100
              dark:from-surface-dark dark:via-surface-dark-muted dark:to-surface-dark">
    <div class="w-full max-w-sm p-6">
      <div class="text-center mb-8">
        <div class="text-lg font-semibold text-ink dark:text-ink-dark">{{ $t('portal.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.subtitle') }}</div>
      </div>

      <div class="card p-8">
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="label">{{ $t('portal.email') }}</label>
            <input v-model="email" type="email" required class="input" autocomplete="username" />
          </div>
          <div>
            <label class="label">{{ $t('portal.password') }}</label>
            <input v-model="password" type="password" required class="input" autocomplete="current-password" />
          </div>
          <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
          <button class="btn-primary w-full" :disabled="loading">
            {{ loading ? $t('portal.signing_in') : $t('portal.sign_in') }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { usePortalAuthStore } from '@/stores/portalAuth';

const auth = usePortalAuthStore();
const router = useRouter();

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

const submit = async () => {
  loading.value = true;
  error.value = '';
  try {
    await auth.login(email.value, password.value);
    router.push({ name: 'portal-invoices' });
  } catch (e) {
    error.value = e.response?.data?.message || 'Sign in failed';
  } finally {
    loading.value = false;
  }
};
</script>
