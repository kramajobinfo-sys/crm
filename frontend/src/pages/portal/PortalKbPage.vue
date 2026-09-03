<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-lg font-semibold text-ink dark:text-ink-dark">{{ $t('portal.kb_title') }}</h1>
      <input v-model="q" class="input w-56" :placeholder="$t('portal.kb_search')" @keyup.enter="load" />
    </div>

    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.loading') }}</div>
    <div v-else-if="!articles.length" class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('portal.kb_empty') }}</div>
    <div v-else class="card divide-y divide-slate-200 dark:divide-slate-700">
      <router-link
        v-for="a in articles" :key="a.id"
        :to="{ name: 'portal-kb-article', params: { id: a.id } }"
        class="block px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800"
      >
        <div class="font-medium text-ink dark:text-ink-dark">{{ a.title }}</div>
        <div v-if="a.excerpt" class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ a.excerpt }}</div>
        <div v-if="a.category" class="text-[11px] text-ink-subtle mt-1">{{ a.category.name }}</div>
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import portalService from '@/services/portal';

const articles = ref([]);
const loading = ref(true);
const q = ref('');

const load = async () => {
  loading.value = true;
  try {
    const { data } = await portalService.kbArticles(q.value ? { q: q.value } : {});
    articles.value = data.data;
  } finally {
    loading.value = false;
  }
};

onMounted(load);
</script>
