<template>
  <div v-if="state.open" class="fixed inset-0 z-[80] flex items-start justify-center bg-black/50 p-4 overflow-y-auto" @click.self="$emit('close')">
    <div class="card w-full max-w-3xl p-4 my-6">
      <div class="flex items-start gap-3">
        <div>
          <div class="text-sm font-semibold text-ink dark:text-ink-dark">{{ $t('duplicates.merge_title') }}</div>
          <p class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('duplicates.merge_message') }}</p>
        </div>
        <button class="ml-auto text-ink-subtle" :disabled="state.saving" @click="$emit('close')"><X :size="17" /></button>
      </div>
      <div v-if="state.loading" class="py-12 text-center text-xs text-ink-muted">{{ $t('common.loading') }}</div>
      <div v-else-if="state.preview">
        <div class="grid grid-cols-2 gap-2 mt-4 text-xs">
          <div class="rounded border border-primary-200 bg-primary-50/50 dark:bg-primary-950/20 p-2">
            <div class="text-[10px] text-primary-600 font-medium">{{ $t('duplicates.primary_record') }}</div>
            <div class="font-medium mt-0.5">{{ state.preview.primary.label }}</div>
          </div>
          <div class="rounded border border-red-200 bg-red-50/50 dark:bg-red-950/20 p-2">
            <div class="text-[10px] text-red-600 font-medium">{{ $t('duplicates.removed_record') }}</div>
            <div class="font-medium mt-0.5">{{ state.preview.duplicate.label }}</div>
          </div>
        </div>
        <div v-if="Object.keys(state.preview.relationships).length" class="mt-3 rounded bg-slate-50 dark:bg-slate-900 p-2 text-[11px] text-ink-muted">
          {{ $t('duplicates.move_related') }}:
          <span v-for="(count, name) in state.preview.relationships" :key="name" class="ml-2">{{ name }} ({{ count }})</span>
        </div>
        <div class="mt-3 max-h-72 overflow-y-auto border rounded divide-y dark:divide-slate-700">
          <div v-for="field in visibleFields" :key="field.field" class="grid grid-cols-[130px_1fr_1fr] gap-2 p-2 text-xs items-start">
            <div class="text-ink-muted pt-1">{{ fieldLabel(field.field) }}</div>
            <label class="rounded border p-1.5 cursor-pointer" :class="sources[field.field] === 'primary' ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/20' : 'border-transparent'">
              <input v-model="sources[field.field]" type="radio" :name="field.field" value="primary" class="mr-1" /> {{ display(field.primary) }}
            </label>
            <label class="rounded border p-1.5 cursor-pointer" :class="sources[field.field] === 'duplicate' ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/20' : 'border-transparent'">
              <input v-model="sources[field.field]" type="radio" :name="field.field" value="duplicate" class="mr-1" /> {{ display(field.duplicate) }}
            </label>
          </div>
        </div>
        <p class="mt-3 text-[11px] text-red-600">{{ $t('duplicates.merge_warning') }}</p>
      </div>
      <p v-if="state.error" class="mt-3 text-xs text-red-600">{{ state.error }}</p>
      <div class="flex justify-end gap-2 mt-4">
        <button class="btn-secondary btn-sm" :disabled="state.saving" @click="$emit('close')">{{ $t('common.cancel') }}</button>
        <button v-if="state.preview" class="btn-primary btn-sm bg-red-600 hover:bg-red-700" :disabled="state.saving" @click="$emit('confirm', sources)">
          {{ state.saving ? $t('duplicates.merging') : $t('duplicates.confirm_merge') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({ state: { type: Object, required: true } });
defineEmits(['close', 'confirm']);
const sources = reactive({});
watch(() => props.state.preview, (preview) => {
  Object.keys(sources).forEach((key) => delete sources[key]);
  (preview?.fields || []).forEach((field) => { sources[field.field] = field.recommended; });
}, { immediate: true });
const visibleFields = computed(() => (props.state.preview?.fields || []).filter((field) => field.primary || field.duplicate));
const display = (value) => value === null || value === '' ? '—' : String(value);
const fieldLabel = (field) => field.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
</script>
