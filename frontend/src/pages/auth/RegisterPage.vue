<template>
  <div>
    <h1 class="text-xl font-semibold text-ink dark:text-ink-dark mb-1">{{ $t('auth.create_account') }}</h1>
    <p class="text-sm text-ink-muted dark:text-ink-dark-muted mb-6">{{ $t('auth.register_subtitle') }}</p>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="label" for="company">{{ $t('auth.company_name') }}</label>
        <input id="company" v-model="form.company_name" required class="input" :placeholder="$t('auth.company_ph')" />
        <p v-if="errors.company_name" class="text-xs text-rose-600 mt-1">{{ errors.company_name[0] }}</p>
      </div>
      <div>
        <label class="label" for="name">{{ $t('auth.your_name') }}</label>
        <input id="name" v-model="form.name" required autocomplete="name" class="input" :placeholder="$t('auth.name_ph')" />
        <p v-if="errors.name" class="text-xs text-rose-600 mt-1">{{ errors.name[0] }}</p>
      </div>
      <div>
        <label class="label" for="email">{{ $t('auth.email') }}</label>
        <input id="email" v-model="form.email" type="email" required autocomplete="email" class="input" placeholder="you@company.com" />
        <p v-if="errors.email" class="text-xs text-rose-600 mt-1">{{ errors.email[0] }}</p>
      </div>
      <div>
        <label class="label" for="password">{{ $t('auth.password') }}</label>
        <input id="password" v-model="form.password" type="password" required autocomplete="new-password" class="input" placeholder="••••••••" />
        <p class="text-[11px] text-ink-subtle mt-1">{{ $t('auth.password_hint') }}</p>
        <p v-if="errors.password" class="text-xs text-rose-600 mt-1">{{ errors.password[0] }}</p>
      </div>
      <div>
        <label class="label" for="password2">{{ $t('auth.confirm_password') }}</label>
        <input id="password2" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="input" placeholder="••••••••" />
      </div>

      <p v-if="genericError" class="text-sm text-rose-600 bg-rose-50 dark:bg-rose-900/20 rounded px-3 py-2">{{ genericError }}</p>

      <button type="submit" class="btn-primary w-full" :disabled="loading">
        <span v-if="loading">{{ $t('auth.creating') }}</span>
        <span v-else>{{ $t('auth.create_account') }}</span>
      </button>
    </form>

    <p class="mt-6 text-sm text-center text-ink-muted dark:text-ink-dark-muted">
      {{ $t('auth.have_account') }}
      <router-link :to="{ name: 'login' }" class="text-primary-600 hover:text-primary-700 font-medium">{{ $t('auth.sign_in') }}</router-link>
    </p>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const form = reactive({ company_name: '', name: '', email: '', password: '', password_confirmation: '' });
const loading = ref(false);
const errors = ref({});
const genericError = ref('');

const submit = async () => {
  loading.value = true; errors.value = {}; genericError.value = '';
  try {
    await auth.register({ ...form });
    router.push({ name: 'dashboard' });
  } catch (e) {
    const status = e.response?.status;
    const data = e.response?.data;
    if (status === 422) errors.value = data?.errors || {};
    else genericError.value = data?.message || 'Registration failed';
  } finally {
    loading.value = false;
  }
};
</script>
