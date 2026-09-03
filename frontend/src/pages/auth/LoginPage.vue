<template>
  <div>
    <h1 class="text-xl font-semibold text-ink dark:text-ink-dark mb-1">{{ $t('auth.welcome_back') }}</h1>
    <p class="text-sm text-ink-muted dark:text-ink-dark-muted mb-6">{{ $t('auth.sign_in_subtitle') }}</p>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="label" for="email">{{ $t('auth.email') }}</label>
        <input id="email" v-model="form.email" type="email" required autocomplete="email"
               class="input" placeholder="you@krama.local" />
        <p v-if="errors.email" class="text-xs text-rose-600 mt-1">{{ errors.email[0] }}</p>
      </div>

      <div>
        <label class="label" for="password">{{ $t('auth.password') }}</label>
        <input id="password" v-model="form.password" type="password" required autocomplete="current-password"
               class="input" placeholder="••••••••" />
        <p v-if="errors.password" class="text-xs text-rose-600 mt-1">{{ errors.password[0] }}</p>
      </div>

      <div class="flex items-center justify-between text-sm">
        <label class="inline-flex items-center gap-2">
          <input v-model="form.remember" type="checkbox"
                 class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
          <span class="text-ink dark:text-ink-dark">{{ $t('auth.remember_me') }}</span>
        </label>
        <router-link :to="{ name: 'forgot' }" class="text-primary-600 hover:text-primary-700">
          {{ $t('auth.forgot_password') }}
        </router-link>
      </div>

      <p v-if="genericError" class="text-sm text-rose-600 bg-rose-50 dark:bg-rose-900/20 rounded px-3 py-2">
        {{ genericError }}
      </p>

      <button type="submit" class="btn-primary w-full" :disabled="loading">
        <span v-if="loading">{{ $t('app.signing_in') }}</span>
        <span v-else>{{ $t('auth.sign_in') }}</span>
      </button>
    </form>

    <p class="mt-4 text-sm text-center text-ink-muted dark:text-ink-dark-muted">
      {{ $t('auth.no_account') }}
      <router-link :to="{ name: 'register' }" class="text-primary-600 hover:text-primary-700 font-medium">{{ $t('auth.create_account') }}</router-link>
    </p>

    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-700 text-xs text-ink-subtle dark:text-ink-dark-subtle">
      <p class="font-medium mb-1 text-ink-muted dark:text-ink-dark-muted">Seeded logins (dev only)</p>
      <ul class="space-y-0.5">
        <li><code>admin@krama.local</code> — Owner / Platform admin</li>
        <li><code>ceo@krama.local</code> — CEO</li>
        <li><code>sales.mgr@krama.local</code> — Sales Manager</li>
        <li>Password: <code>password123</code></li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '', remember: false });
const loading = ref(false);
const errors = ref({});
const genericError = ref('');

const submit = async () => {
  loading.value = true;
  errors.value = {};
  genericError.value = '';

  try {
    const payload = await auth.login(form.email, form.password, form.remember);
    if (payload.requires_2fa) {
      router.push({ name: '2fa' });
    } else {
      router.push(route.query.redirect || { name: 'dashboard' });
    }
  } catch (e) {
    const status = e.response?.status;
    const data = e.response?.data;
    if (status === 422) errors.value = data?.errors || {};
    else genericError.value = data?.message || 'Sign-in failed';
  } finally {
    loading.value = false;
  }
};
</script>
