<!-- Dynamic form inputs for admin-defined custom fields. v-model binds the values object. -->
<template>
  <div v-if="(fields || []).length" class="mt-3 pt-3 border-t border-line dark:border-line-dark">
    <div class="text-[10px] tracking-wider text-ink-subtle mb-1.5">{{ heading }}</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      <div v-for="cf in fields" :key="cf.key" :class="cf.type === 'textarea' ? 'sm:col-span-2' : ''">
        <label class="label">{{ cf.label }}<span v-if="cf.required" class="text-rose-500"> *</span></label>
        <textarea v-if="cf.type === 'textarea'" :value="model[cf.key]" @input="set(cf, $event.target.value)" rows="2" class="input text-sm"></textarea>
        <select v-else-if="cf.type === 'select'" :value="model[cf.key]" @change="set(cf, $event.target.value)" class="input text-sm">
          <option value="">—</option>
          <option v-for="o in (cf.options || [])" :key="o" :value="o">{{ o }}</option>
        </select>
        <label v-else-if="cf.type === 'checkbox'" class="flex items-center gap-2 text-sm h-8">
          <input type="checkbox" :checked="!!model[cf.key]" @change="set(cf, $event.target.checked)" /> {{ cf.help || 'Yes' }}
        </label>
        <input v-else :value="model[cf.key]" @input="set(cf, $event.target.value)"
               :type="cf.type === 'number' ? 'number' : cf.type === 'date' ? 'date' : cf.type === 'email' ? 'email' : cf.type === 'url' ? 'url' : 'text'"
               class="input text-sm" />
        <p v-if="cf.help && cf.type !== 'checkbox'" class="text-[11px] text-ink-subtle mt-0.5">{{ cf.help }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  fields: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
  heading: { type: String, default: 'Custom fields' },
});
const emit = defineEmits(['update:modelValue']);
const model = computed(() => props.modelValue || {});
function set(cf, val) { emit('update:modelValue', { ...model.value, [cf.key]: val }); }
</script>
