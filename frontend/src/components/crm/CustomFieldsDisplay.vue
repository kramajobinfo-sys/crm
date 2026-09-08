<!-- Read-only display of populated custom fields in a detail drawer. -->
<template>
  <div v-if="populated.length">
    <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ heading }}</div>
    <dl class="grid grid-cols-3 gap-x-2 gap-y-0.5">
      <template v-for="cf in populated" :key="cf.key">
        <dt class="text-ink-subtle">{{ cf.label }}</dt>
        <dd class="col-span-2 text-ink dark:text-ink-dark break-words">
          <span v-if="cf.type === 'checkbox'">{{ cf.value ? 'Yes' : 'No' }}</span>
          <a v-else-if="cf.type === 'url'" :href="cf.value" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ cf.value }}</a>
          <span v-else>{{ cf.value }}</span>
        </dd>
      </template>
    </dl>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  fields: { type: Array, default: () => [] },
  values: { type: Object, default: () => ({}) },
  heading: { type: String, default: 'Custom fields' },
});
const populated = computed(() => {
  const v = props.values || {};
  return (props.fields || [])
    .map((cf) => ({ ...cf, value: v[cf.key] }))
    .filter((cf) => cf.value !== undefined && cf.value !== null && cf.value !== '');
});
</script>
