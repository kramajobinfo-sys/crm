<template>
  <div v-if="open" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/45 p-4" @click.self="$emit('cancel')">
    <div class="card w-full max-w-lg p-4">
      <div class="flex items-start gap-3">
        <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 flex items-center justify-center shrink-0">
          <TriangleAlert :size="16" />
        </div>
        <div>
          <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('duplicates.title') }}</div>
          <p class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('duplicates.message') }}</p>
        </div>
      </div>

      <div class="mt-3 space-y-2 max-h-64 overflow-y-auto">
        <div v-for="candidate in candidates" :key="`${candidate.type}-${candidate.id}`" class="rounded border border-amber-200 dark:border-amber-800/60 bg-amber-50/60 dark:bg-amber-950/20 px-3 py-2">
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ candidate.label }}</span>
            <span class="text-[9px] px-1.5 py-0.5 rounded ml-auto" :class="candidate.confidence === 'high' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'">
              {{ $t(`duplicates.confidence.${candidate.confidence}`) }}
            </span>
          </div>
          <div class="text-[11px] text-ink-subtle mt-0.5">{{ candidate.secondary }}</div>
          <div class="text-[10px] text-amber-700 dark:text-amber-300 mt-1">
            {{ candidate.reasons.map((reason) => $t(`duplicates.reason.${reason}`)).join(' · ') }}
          </div>
          <button v-if="primaryId" class="text-[10px] text-primary-600 hover:underline mt-1.5" @click="$emit('merge', candidate)">
            {{ $t('duplicates.merge_review') }}
          </button>
        </div>
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button class="btn-secondary text-xs px-3 py-1.5" @click="$emit('cancel')">{{ $t('duplicates.review') }}</button>
        <button class="btn-primary text-xs px-3 py-1.5 bg-amber-600 hover:bg-amber-700" @click="$emit('proceed')">{{ $t('duplicates.save_anyway') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { TriangleAlert } from 'lucide-vue-next';

defineProps({
  open: { type: Boolean, default: false },
  candidates: { type: Array, default: () => [] },
  primaryId: { type: [Number, String], default: null },
});
defineEmits(['cancel', 'proceed', 'merge']);
</script>
