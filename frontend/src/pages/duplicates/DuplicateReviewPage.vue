<template>
  <div class="page">
    <div class="mb-3">
      <h1 class="text-lg font-semibold text-ink dark:text-ink-dark">Duplicate Review</h1>
      <p class="text-xs text-ink-muted dark:text-ink-dark-muted">Find and merge records that already exist more than once.</p>
    </div>

    <div class="flex gap-1.5 mb-4">
      <button
        v-for="tab in types" :key="tab.value"
        class="text-xs px-3 py-1.5 rounded border"
        :class="active === tab.value
          ? 'bg-primary-600 text-white border-primary-600'
          : 'border-slate-200 dark:border-slate-700 text-ink-muted dark:text-ink-dark-muted'"
        @click="select(tab.value)"
      >{{ $t(tab.label) }}</button>
    </div>

    <div v-if="loading" class="text-sm text-ink-muted dark:text-ink-dark-muted py-10 text-center">{{ $t('app.loading') }}</div>
    <div v-else-if="!groups.length" class="text-sm text-ink-muted dark:text-ink-dark-muted py-10 text-center">
      No duplicates found.
    </div>
    <div v-else class="space-y-3">
      <div v-for="(g, i) in groups" :key="i" class="card p-3">
        <div class="flex items-center justify-between gap-2 mb-2">
          <span class="text-[11px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
            {{ $t(`duplicates.reason.${g.reason}`) }} · {{ g.value }} · {{ g.count }}
          </span>
          <button
            class="btn-primary btn-xs"
            :disabled="g.records.length < 2 || mergeGuard.state.saving"
            @click="startMerge(g)"
          >{{ $t('duplicates.merge_review') }}</button>
        </div>
        <div class="grid gap-1">
          <div
            v-for="r in g.records" :key="r.id"
            class="text-xs flex items-center gap-2 py-1 border-b border-slate-100 dark:border-slate-700/50 last:border-0"
          >
            <span class="font-medium text-ink dark:text-ink-dark truncate">{{ r.name || ('#' + r.id) }}</span>
            <span class="text-ink-muted dark:text-ink-dark-muted truncate">{{ r.email || '—' }}</span>
            <span class="text-ink-subtle ml-auto shrink-0 tabular-nums">{{ r.phone || r.mobile || '' }}</span>
          </div>
        </div>
      </div>
    </div>

    <RecordMergeModal :state="mergeGuard.state" @close="mergeGuard.close" @confirm="mergeGuard.confirm" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import dupApi from '@/services/duplicates';
import RecordMergeModal from '@/components/crm/RecordMergeModal.vue';
import { useRecordMerge } from '@/composables/useRecordMerge';

const { t } = useI18n();
const toast = useToast();

const types = [
  { value: 'account', label: 'nav.accounts' },
  { value: 'contact', label: 'nav.contacts' },
  { value: 'lead',    label: 'nav.leads' },
];
const active = ref('account');
const groups = ref([]);
const loading = ref(false);

async function load() {
  loading.value = true;
  try {
    const { data } = await dupApi.scan(active.value);
    groups.value = data.data.groups || [];
  } catch { /* http interceptor surfaces the error */ }
  finally { loading.value = false; }
}
function select(v) { if (v !== active.value) { active.value = v; load(); } }

const mergeGuard = useRecordMerge(async () => {
  toast.success(t('duplicates.merged'));
  await load();
});
function startMerge(g) {
  // Merge the first two of a group; a re-scan (via onMerged) surfaces any remaining pair.
  const [primary, dup] = g.records;
  if (primary && dup) mergeGuard.open(active.value, primary.id, dup);
}

onMounted(load);
</script>
