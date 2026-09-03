<template>
  <div class="rounded-lg border p-4"
       :class="borderClass">
    <div class="flex items-center gap-1.5 mb-1.5" :class="titleClass">
      <Sparkles :size="13" />
      <span class="text-sm font-medium">{{ $t('dashboard.ai_insight') }}</span>
    </div>
    <div v-if="!insights.length" class="text-xs text-ink-subtle">
      No insights yet.
    </div>
    <div v-else class="space-y-2">
      <div v-for="(ins, i) in insights" :key="i" class="text-xs leading-relaxed" :class="bodyClass">
        <div v-if="ins.title" class="font-medium">{{ ins.title }}</div>
        <div>{{ ins.body }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Sparkles } from 'lucide-vue-next';

const props = defineProps({ insights: { type: Array, default: () => [] } });

const level = computed(() => props.insights[0]?.level || 'info');

const borderClass = computed(() => ({
  info: 'bg-primary-50 border-primary-200 dark:bg-primary-900/20 dark:border-primary-800/40',
  warning: 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800/40',
  danger: 'bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800/40',
}[level.value] || 'bg-primary-50 border-primary-200'));

const titleClass = computed(() => ({
  info: 'text-primary-700 dark:text-primary-300',
  warning: 'text-amber-700 dark:text-amber-300',
  danger: 'text-rose-700 dark:text-rose-300',
}[level.value] || 'text-primary-700'));

const bodyClass = computed(() => ({
  info: 'text-primary-900 dark:text-primary-200',
  warning: 'text-amber-900 dark:text-amber-200',
  danger: 'text-rose-900 dark:text-rose-200',
}[level.value] || 'text-primary-900'));
</script>
