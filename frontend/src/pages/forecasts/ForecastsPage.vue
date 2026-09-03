<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">

    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('forecasts.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('forecasts.subtitle') }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="load">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('forecasts.refresh') }}
        </button>
        <button v-if="can('forecasts.manage')" class="btn-primary text-xs px-3 py-1.5" @click="openTargets">
          <Target :size="12" /> {{ $t('forecasts.set_targets') }}
        </button>
      </div>
    </div>

    <!-- Period controls -->
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <div class="flex rounded-md overflow-hidden border border-slate-200 dark:border-slate-700">
        <button v-for="pt in ['month', 'quarter']" :key="pt" class="px-3 py-1 text-xs"
                :class="periodType === pt ? 'bg-primary-600 text-white' : 'text-ink-muted dark:text-ink-dark-muted'"
                @click="periodType = pt; load()">
          {{ $t(`forecasts.${pt}`) }}
        </button>
      </div>
      <input v-model="monthValue" type="month" class="input text-sm w-auto" @change="load" />
      <span v-if="board" class="text-xs text-ink-subtle">{{ board.period_start }} → {{ board.period_end }}</span>
    </div>

    <!-- Board -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!board || !board.rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('forecasts.empty') }}</div>
      <table v-else class="w-full text-sm">
        <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
          <tr>
            <th class="text-left font-medium px-3 py-2">{{ $t('forecasts.rep') }}</th>
            <th class="text-right font-medium px-3 py-2">{{ $t('forecasts.target') }}</th>
            <th class="text-right font-medium px-3 py-2">{{ $t('forecasts.closed') }}</th>
            <th class="text-right font-medium px-3 py-2 hidden md:table-cell">{{ $t('forecasts.pipeline') }}</th>
            <th class="text-right font-medium px-3 py-2 hidden lg:table-cell">{{ $t('forecasts.forecast') }}</th>
            <th class="text-left font-medium px-3 py-2 w-48">{{ $t('forecasts.attainment') }}</th>
            <th class="text-right font-medium px-3 py-2 hidden md:table-cell">{{ $t('forecasts.gap') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in board.rows" :key="r.user_id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2 text-ink dark:text-ink-dark">{{ r.user }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-ink-muted">{{ money(r.target) }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-ink dark:text-ink-dark">{{ money(r.closed) }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-ink-muted hidden md:table-cell">{{ money(r.pipeline_weighted) }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-ink-muted hidden lg:table-cell">{{ money(r.forecast) }}</td>
            <td class="px-3 py-2">
              <div class="flex items-center gap-2">
                <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                  <div class="h-full rounded-full" :class="barClass(r.attainment)" :style="{ width: barWidth(r.attainment) }"></div>
                </div>
                <span class="text-[11px] tabular-nums w-10 text-right" :class="r.attainment >= 100 ? 'text-emerald-600' : 'text-ink-muted'">{{ r.attainment == null ? '—' : r.attainment + '%' }}</span>
              </div>
            </td>
            <td class="px-3 py-2 text-right tabular-nums hidden md:table-cell" :class="r.gap > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ money(r.gap) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t-2 border-slate-200 dark:border-slate-600 font-medium">
            <td class="px-3 py-2 text-ink dark:text-ink-dark">{{ $t('forecasts.total') }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ money(board.totals.target) }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ money(board.totals.closed) }}</td>
            <td class="px-3 py-2 text-right tabular-nums hidden md:table-cell">{{ money(board.totals.pipeline_weighted) }}</td>
            <td class="px-3 py-2 text-right tabular-nums hidden lg:table-cell">{{ money(board.totals.forecast) }}</td>
            <td class="px-3 py-2 text-[11px] tabular-nums" :class="board.totals.attainment >= 100 ? 'text-emerald-600' : 'text-ink-muted'">{{ board.totals.attainment == null ? '—' : board.totals.attainment + '%' }}</td>
            <td class="px-3 py-2 text-right tabular-nums hidden md:table-cell" :class="board.totals.gap > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ money(board.totals.gap) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Targets editor modal -->
    <div v-if="targetForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="targetForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-1">{{ $t('forecasts.set_targets') }}</div>
        <div class="text-[11px] text-ink-subtle mb-3">{{ $t(`forecasts.${periodType}`) }} · {{ targetForm.period_start }}</div>
        <div class="max-h-[55vh] overflow-y-auto">
          <div v-for="row in targetForm.rows" :key="row.user_id" class="flex items-center gap-2 py-1">
            <span class="flex-1 text-sm text-ink dark:text-ink-dark truncate">{{ row.user }}</span>
            <input v-model.number="row.target_amount" type="number" min="0" step="1000" class="input text-sm text-right w-36" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="targetForm.open = false">{{ $t('forecasts.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="targetForm.saving" @click="submitTargets">{{ targetForm.saving ? $t('forecasts.saving') : $t('forecasts.save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/forecasts';
import { RefreshCw, Target } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const periodType = ref('month');
const monthValue = ref(new Date().toISOString().slice(0, 7));   // YYYY-MM
const board = ref(null);
const loading = ref(false);
const targetForm = reactive({ open: false, saving: false, period_start: '', rows: [] });

const periodStart = () => `${monthValue.value}-01`;

async function load() {
  loading.value = true;
  try {
    const { data } = await api.board({ period_type: periodType.value, period_start: periodStart() });
    board.value = data.data;
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}

async function openTargets() {
  try {
    const { data } = await api.targets({ period_type: periodType.value, period_start: periodStart() });
    targetForm.period_start = data.data.period_start;
    targetForm.rows = data.data.targets.map((r) => ({ ...r }));
    targetForm.open = true;
  } catch { /* noop */ }
}
async function submitTargets() {
  targetForm.saving = true;
  try {
    await api.setTargets({
      period_type: periodType.value,
      period_start: periodStart(),
      targets: targetForm.rows.map((r) => ({ user_id: r.user_id, target_amount: r.target_amount || 0 })),
    });
    toast.success(t('forecasts.targets_saved'));
    targetForm.open = false;
    await load();
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { targetForm.saving = false; }
}

const money = (v) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v);
const barWidth = (a) => `${Math.max(0, Math.min(100, a || 0))}%`;
const barClass = (a) => (a >= 100 ? 'bg-emerald-500' : a >= 60 ? 'bg-primary-500' : 'bg-amber-500');

onMounted(load);
</script>
