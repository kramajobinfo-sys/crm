<template>
  <div>
    <router-link :to="{ name: 'portal-kb' }" class="text-xs text-primary-600 hover:underline">&larr; {{ $t('portal.kb_back') }}</router-link>

    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.loading') }}</div>
    <div v-else-if="!article" class="text-sm text-ink-muted dark:text-ink-dark-muted mt-4">{{ $t('portal.kb_not_found') }}</div>
    <div v-else class="card p-5 mt-3 max-w-3xl">
      <div v-if="article.category" class="text-[11px] text-ink-subtle mb-1">{{ article.category.name }}</div>
      <h1 class="text-xl font-semibold text-ink dark:text-ink-dark">{{ article.title }}</h1>
      <div v-if="article.published_at" class="text-[11px] text-ink-subtle mt-1">{{ formatDate(article.published_at) }}</div>
      <!-- Plain-text body, rendered with pre-wrap — deliberately NOT v-html (no HTML sink). -->
      <div class="mt-4 text-sm text-ink dark:text-ink-dark whitespace-pre-wrap leading-relaxed">{{ article.body }}</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import portalService from '@/services/portal';

const route = useRoute();
const article = ref(null);
const loading = ref(true);

const formatDate = (d) => { try { return new Date(d).toLocaleDateString(); } catch { return ''; } };

const load = async () => {
  loading.value = true;
  try {
    const { data } = await portalService.kbArticle(route.params.id);
    article.value = data.data;
  } catch { article.value = null; }
  finally { loading.value = false; }
};

onMounted(load);
</script>
