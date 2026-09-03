<template>
  <div class="card card-pad">
    <div class="flex items-center justify-between">
      <span class="text-xs text-ink-muted dark:text-ink-dark-muted">{{ label }}</span>
      <component :is="icon" v-if="icon" :size="14" :class="iconColorClass" />
    </div>
    <div class="text-2xl font-medium mt-1.5 text-ink dark:text-ink-dark">{{ formattedValue }}</div>
    <div v-if="subtitle" class="text-[10px] mt-1" :class="subtitleColorClass">
      {{ subtitle }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label:    { type: String, required: true },
  value:    { type: [String, Number], required: true },
  subtitle: { type: String, default: '' },
  icon:     { type: [Object, Function], default: null },
  trend:    { type: String, default: '' }, // 'up' | 'down' | 'neutral'
  format:   { type: String, default: 'number' }, // 'number' | 'currency'
  currency: { type: String, default: 'USD' },
});

const formattedValue = computed(() => {
  if (props.format === 'currency') {
    try {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: props.currency,
        maximumFractionDigits: 0,
      }).format(Number(props.value));
    } catch { return props.value; }
  }
  return typeof props.value === 'number' ? props.value.toLocaleString() : props.value;
});

const iconColorClass = computed(() => {
  if (props.trend === 'up')   return 'text-emerald-600';
  if (props.trend === 'down') return 'text-rose-600';
  return 'text-primary-600';
});

const subtitleColorClass = computed(() => {
  if (props.trend === 'up')   return 'text-emerald-600';
  if (props.trend === 'down') return 'text-rose-600';
  return 'text-ink-muted dark:text-ink-dark-muted';
});
</script>
