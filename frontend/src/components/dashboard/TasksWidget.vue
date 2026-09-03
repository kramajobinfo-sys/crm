<template>
  <div class="card card-pad">
    <div class="flex items-center justify-between mb-2">
      <span class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('dashboard.tasks_today') }}</span>
      <span v-if="summary.overdue" class="badge-danger">{{ summary.overdue }} overdue</span>
    </div>
    <div v-if="!summary.items?.length" class="text-xs text-ink-subtle py-4 text-center">
      {{ $t('dashboard.no_tasks') }}
    </div>
    <div v-else class="space-y-1.5">
      <div v-for="t in summary.items" :key="t.id" class="flex gap-2 text-xs">
        <Square :size="12" class="text-ink-subtle mt-0.5 shrink-0" />
        <div class="min-w-0 flex-1">
          <div class="truncate text-ink dark:text-ink-dark">{{ t.title }}</div>
          <div class="text-[10px] text-ink-subtle">
            {{ formatDue(t.due_at) }} · {{ t.priority }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Square } from 'lucide-vue-next';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

defineProps({ summary: { type: Object, default: () => ({ items: [], overdue: 0 }) } });

const formatDue = (d) => (d ? dayjs(d).fromNow() : '');
</script>
