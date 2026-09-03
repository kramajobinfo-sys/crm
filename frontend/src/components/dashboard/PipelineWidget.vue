<template>
  <div class="card card-pad">
    <div class="text-sm font-medium mb-3 text-ink dark:text-ink-dark">
      {{ $t('dashboard.charts.pipeline') }}
    </div>
    <div v-if="!stages.length" class="text-xs text-ink-subtle py-6 text-center">
      {{ $t('dashboard.no_data') }}
    </div>
    <div v-else class="space-y-2">
      <div v-for="s in stages" :key="s.stage_id + s.name">
        <div class="flex justify-between text-xs mb-1">
          <span class="text-ink dark:text-ink-dark">{{ s.name }}</span>
          <span class="text-ink-muted dark:text-ink-dark-muted">
            {{ formatCurrency(s.total_value) }} · {{ s.count }}
          </span>
        </div>
        <div class="h-1.5 bg-slate-100 dark:bg-surface-dark-subtle rounded overflow-hidden">
          <div
            class="h-full rounded transition-all"
            :style="{ width: barWidth(s) + '%', backgroundColor: s.color || '#378ADD' }"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ stages: { type: Array, default: () => [] } });

const maxValue = computed(() => Math.max(1, ...props.stages.map((s) => s.total_value || 0)));

const barWidth = (s) => Math.max(5, Math.round((s.total_value / maxValue.value) * 100));

const formatCurrency = (v) => {
  const n = Number(v || 0);
  if (n >= 1000000) return `$${(n / 1000000).toFixed(1)}M`;
  if (n >= 1000)    return `$${(n / 1000).toFixed(0)}K`;
  return `$${n.toFixed(0)}`;
};
</script>
