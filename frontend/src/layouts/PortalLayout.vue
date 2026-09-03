<template>
  <div class="min-h-screen bg-surface-muted dark:bg-surface-dark">
    <header class="bg-white dark:bg-surface-dark-muted border-b border-slate-200 dark:border-slate-700">
      <div class="max-w-4xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="font-semibold text-ink dark:text-ink-dark">{{ auth.contact?.customer_name || 'Customer Portal' }}</div>
        <nav class="flex items-center gap-4 text-sm">
          <router-link :to="{ name: 'portal-quotations' }" class="text-ink-muted dark:text-ink-dark-muted hover:text-primary-600">{{ $t('portal.nav_quotations') }}</router-link>
          <router-link :to="{ name: 'portal-invoices' }" class="text-ink-muted dark:text-ink-dark-muted hover:text-primary-600">{{ $t('portal.nav_invoices') }}</router-link>
          <router-link :to="{ name: 'portal-tickets' }" class="text-ink-muted dark:text-ink-dark-muted hover:text-primary-600">{{ $t('portal.nav_support') }}</router-link>
          <router-link :to="{ name: 'portal-kb' }" class="text-ink-muted dark:text-ink-dark-muted hover:text-primary-600">{{ $t('portal.nav_kb') }}</router-link>
          <button @click="logout" class="text-ink-muted dark:text-ink-dark-muted hover:text-red-600">{{ $t('portal.sign_out') }}</button>
        </nav>
      </div>
    </header>
    <main class="max-w-4xl mx-auto px-4 py-8">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { usePortalAuthStore } from '@/stores/portalAuth';
import { useRouter } from 'vue-router';

const auth = usePortalAuthStore();
const router = useRouter();

const logout = async () => {
  await auth.logout();
  router.push({ name: 'portal-login' });
};
</script>
