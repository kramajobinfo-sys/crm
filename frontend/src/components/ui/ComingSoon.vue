<template>
  <div class="p-6 md:p-8">
    <div class="max-w-2xl mx-auto card p-8 text-center">
      <div class="w-12 h-12 rounded-lg bg-primary-100 dark:bg-primary-900/30 text-primary-700
                  dark:text-primary-300 flex items-center justify-center mx-auto mb-4">
        <component :is="icon" :size="22" />
      </div>
      <h2 class="text-lg font-semibold text-ink dark:text-ink-dark mb-1">{{ title || $t('coming_soon.title') }}</h2>
      <p class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('coming_soon.body') }}</p>
      <p v-if="moduleNumber" class="text-xs text-ink-subtle mt-4">Module {{ moduleNumber }}</p>
    </div>
  </div>
</template>

<script setup>
import { Sparkles } from 'lucide-vue-next';
defineProps({
  title: String,
  icon:  { type: [Object, Function], default: Sparkles },
  moduleNumber: [String, Number],
});
</script>
