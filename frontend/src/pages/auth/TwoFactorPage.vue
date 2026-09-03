<template>
  <div>
    <h1 class="text-xl font-semibold mb-1">Two-factor verification</h1>
    <p class="text-sm text-ink-muted dark:text-ink-dark-muted mb-6">
      Enter the 6-digit code from your authenticator app.
    </p>

    <form @submit.prevent="submit" class="space-y-4">
      <input v-model="code" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
             class="input text-center tracking-[0.5em] text-lg" placeholder="••••••" autofocus />
      <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
      <button type="submit" class="btn-primary w-full" :disabled="loading || code.length !== 6">
        {{ loading ? 'Verifying…' : 'Verify' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const code = ref('');
const loading = ref(false);
const error = ref('');

const submit = async () => {
  loading.value = true;
  error.value = '';
  try {
    await auth.verifyTwoFactor(code.value);
    router.push({ name: 'dashboard' });
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid code';
  } finally {
    loading.value = false;
  }
};
</script>
