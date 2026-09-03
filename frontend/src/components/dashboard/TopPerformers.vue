<template>
  <div class="card card-pad">
    <div class="text-sm font-medium mb-2 text-ink dark:text-ink-dark">{{ $t('dashboard.top_performers') }}</div>
    <div v-if="!items.length" class="text-xs text-ink-subtle py-4 text-center">
      {{ $t('dashboard.no_data') }}
    </div>
    <div v-else class="space-y-1.5">
      <div v-for="p in items" :key="p.user_id" class="flex justify-between text-xs">
        <span class="text-ink dark:text-ink-dark truncate">{{ p.name }}</span>
        <span class="text-emerald-600 font-medium">${{ formatK(p.total) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({ items: { type: Array, default: () => [] } });
const formatK = (n) => {
  const v = Number(n || 0);
  if (v >= 1000) return `${(v / 1000).toFixed(0)}K`;
  return v.toFixed(0);
};
</script>
