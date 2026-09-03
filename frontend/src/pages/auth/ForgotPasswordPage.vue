<template>
  <div>
    <h1 class="text-xl font-semibold mb-1">Reset your password</h1>
    <p class="text-sm text-ink-muted dark:text-ink-dark-muted mb-6">
      We'll email you a link to reset your password.
    </p>

    <form v-if="!sent" @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="label">Email</label>
        <input v-model="email" type="email" required class="input" placeholder="you@krama.local" />
      </div>
      <button class="btn-primary w-full" :disabled="loading">
        {{ loading ? 'Sending…' : 'Send reset link' }}
      </button>
    </form>

    <div v-else class="text-sm text-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 rounded p-3">
      If that email exists in our system, a reset link has been sent.
    </div>

    <router-link :to="{ name: 'login' }" class="block text-center text-sm text-primary-600 mt-6">
      ← Back to sign in
    </router-link>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import authService from '@/services/auth';

const email = ref('');
const loading = ref(false);
const sent = ref(false);

const submit = async () => {
  loading.value = true;
  try {
    await authService.forgotPassword(email.value);
    sent.value = true;
  } catch { sent.value = true; } // don't reveal existence
  finally { loading.value = false; }
};
</script>
