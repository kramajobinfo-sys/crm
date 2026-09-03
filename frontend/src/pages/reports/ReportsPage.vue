<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">
    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('reports.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('reports.subtitle') }}</div>
      </div>
      <button v-if="tab === 'builder' && can('reports.create')" class="btn-primary text-xs px-3 py-1.5" @click="newReport"><Plus :size="12" /> {{ $t('reports.new') }}</button>
    </div>

    <div class="flex gap-1 mb-4 border-b border-slate-200 dark:border-slate-700">
      <button class="px-3 py-2 text-xs font-medium border-b-2 -mb-px" :class="tab === 'builder' ? 'border-primary-600 text-primary-600' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'" @click="tab = 'builder'">{{ $t('reports.tab_builder') }}</button>
      <button class="px-3 py-2 text-xs font-medium border-b-2 -mb-px" :class="tab === 'dashboards' ? 'border-primary-600 text-primary-600' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'" @click="tab = 'dashboards'">{{ $t('reports.tab_dashboards') }}</button>
    </div>

    <DashboardsTab v-if="tab === 'dashboards'" :saved-reports="saved" />

    <div v-else class="flex gap-3 items-start">
      <!-- Saved reports -->
      <div class="card w-56 shrink-0 hidden md:block overflow-hidden">
        <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 text-xs font-medium text-ink dark:text-ink-dark">{{ $t('reports.saved') }}</div>
        <div v-if="!saved.length" class="text-[11px] text-ink-subtle text-center py-6">{{ $t('reports.no_saved') }}</div>
        <button v-for="r in saved" :key="r.id" class="w-full text-left px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0 hover:bg-slate-50 dark:hover:bg-surface-dark-subtle"
                :class="loadedId === r.id && 'bg-primary-50 dark:bg-primary-900/20'"
                @click="loadSaved(r.id)">
          <div class="text-xs text-ink dark:text-ink-dark truncate">{{ r.name }}</div>
          <div class="text-[10px] text-ink-subtle">{{ r.dataset }}</div>
        </button>
      </div>

      <!-- Builder + result -->
      <div class="flex-1 min-w-0 space-y-3">
        <div class="card p-3">
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
            <div><label class="label">{{ $t('reports.dataset') }}</label>
              <select v-model="spec.dataset" class="input text-sm" @change="onDatasetChange">
                <option v-for="d in datasets" :key="d.key" :value="d.key">{{ d.label }}</option>
              </select></div>
            <div><label class="label">{{ $t('reports.group_by') }}</label>
              <select v-model="spec.dimension" class="input text-sm">
                <option v-for="dim in currentDataset?.dimensions || []" :key="dim.key" :value="dim.key">{{ dim.label }}</option>
              </select></div>
            <div><label class="label">{{ $t('reports.chart') }}</label>
              <select v-model="spec.chart_type" class="input text-sm">
                <option value="table">{{ $t('reports.ct.table') }}</option>
                <option value="bar">{{ $t('reports.ct.bar') }}</option>
                <option value="line">{{ $t('reports.ct.line') }}</option>
                <option value="pie">{{ $t('reports.ct.pie') }}</option>
              </select></div>
            <div class="flex items-end"><button class="btn-primary text-xs px-4 py-1.5 w-full" :disabled="running" @click="run">{{ running ? $t('reports.running') : $t('reports.run') }}</button></div>
          </div>
          <div class="mt-2">
            <label class="label">{{ $t('reports.measures') }}</label>
            <div class="flex flex-wrap gap-2 mt-1">
              <label v-for="m in currentDataset?.measures || []" :key="m.key" class="flex items-center gap-1 text-xs text-ink-muted">
                <input type="checkbox" class="rounded border-slate-300" :value="m.key" v-model="spec.measures" /> {{ m.label }}
              </label>
            </div>
          </div>
        </div>

        <!-- Result -->
        <div v-if="result" class="card overflow-hidden">
          <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
            <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ result.rows.length }} {{ $t('reports.rows') }}</span>
            <div class="ml-auto flex gap-2">
              <button v-if="can('reports.create')" class="btn-secondary text-[11px] px-2.5 py-1" @click="openSave"><Save :size="11" /> {{ $t('reports.save') }}</button>
              <button v-if="loadedId && can('reports.export')" class="btn-secondary text-[11px] px-2.5 py-1" @click="doExport"><Download :size="11" /> {{ $t('reports.export') }}</button>
            </div>
          </div>

          <!-- Chart -->
          <div v-if="spec.chart_type !== 'table' && result.rows.length" class="p-3">
            <ReportChart :type="spec.chart_type" :result="result" />
          </div>

          <!-- Table -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
                <tr><th v-for="c in result.columns" :key="c.key" class="text-left font-medium px-3 py-2" :class="c.key !== 'dimension' && 'text-right'">{{ c.label }}</th></tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i" class="border-t border-slate-100 dark:border-slate-700/60">
                  <td v-for="c in result.columns" :key="c.key" class="px-3 py-2" :class="c.key === 'dimension' ? 'text-ink dark:text-ink-dark' : 'text-right tabular-nums text-ink-muted'">
                    {{ c.key === 'dimension' ? row[c.key] : fmt(row[c.key]) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="card p-10 text-center text-sm text-ink-subtle">{{ $t('reports.run_hint') }}</div>
      </div>
    </div>

    <!-- Save modal -->
    <div v-if="saveForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="saveForm.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('reports.save_report') }}</div>
        <label class="label">{{ $t('reports.name') }} *</label>
        <input v-model="saveForm.name" class="input text-sm mb-2" />
        <label class="label">{{ $t('reports.description') }}</label>
        <input v-model="saveForm.description" class="input text-sm" />
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="saveForm.open = false">{{ $t('reports.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="!saveForm.name || saveForm.saving" @click="submitSave">{{ saveForm.saving ? $t('reports.saving') : $t('reports.save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/reports';
import { Plus, Save, Download } from 'lucide-vue-next';
import ReportChart from '@/components/charts/ReportChart.vue';
import DashboardsTab from '@/components/reports/DashboardsTab.vue';

const tab = ref('builder');

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const datasets = ref([]);
const saved = ref([]);
const result = ref(null);
const running = ref(false);
const loadedId = ref(null);
const spec = reactive({ dataset: 'deals', dimension: 'stage', measures: ['count'], chart_type: 'bar', filters: {} });
const saveForm = reactive({ open: false, saving: false, name: '', description: '' });

const currentDataset = computed(() => datasets.value.find((d) => d.key === spec.dataset));

function onDatasetChange() {
  const d = currentDataset.value;
  spec.dimension = d?.dimensions?.[0]?.key ?? null;
  spec.measures = d?.measures?.length ? [d.measures[0].key] : [];
  result.value = null; loadedId.value = null;
}

async function run() {
  running.value = true;
  try {
    const { data } = await api.run({ dataset: spec.dataset, dimension: spec.dimension, measures: spec.measures, filters: spec.filters });
    result.value = data.data;
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { running.value = false; }
}

async function loadSaved(id) {
  try {
    const { data } = await api.show(id);
    const r = data.data.report;
    Object.assign(spec, { dataset: r.dataset, dimension: r.dimension, measures: r.measures || ['count'], chart_type: r.chart_type || 'table', filters: r.filters || {} });
    result.value = data.data.result;
    loadedId.value = id;
  } catch { /* noop */ }
}

function newReport() { loadedId.value = null; result.value = null; spec.dataset = 'deals'; onDatasetChange(); }

function openSave() { saveForm.open = true; saveForm.name = ''; saveForm.description = ''; }
async function submitSave() {
  saveForm.saving = true;
  try {
    await api.create({ name: saveForm.name, description: saveForm.description || undefined,
      dataset: spec.dataset, dimension: spec.dimension, measures: spec.measures, filters: spec.filters, chart_type: spec.chart_type });
    toast.success(t('reports.saved_ok'));
    saveForm.open = false;
    await loadList();
  } catch (e) { if (e.response?.status === 422) toast.error(t('reports.check_fields')); }
  finally { saveForm.saving = false; }
}

async function doExport() {
  try {
    const { data } = await api.export(loadedId.value);
    toast.success(t('reports.exported', { n: data.data.row_count }));
    // Exports are on the private disk now — stream it through the authenticated endpoint
    // instead of opening a public URL (which is what made every past export world-readable).
    const reportName = saved.value.find((r) => r.id === loadedId.value)?.name || 'report';
    if (data.data.id) await api.downloadExport(data.data.id, `${reportName.replace(/[\\/:*?"<>|]/g, '-')}.csv`);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

async function loadList() { try { const { data } = await api.list(); saved.value = data.data || []; } catch { /* noop */ } }

const fmt = (v) => {
  const n = Number(v);
  if (Number.isNaN(n)) return v;
  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(n);
};

onMounted(async () => {
  try { const { data } = await api.datasets(); datasets.value = data.data || []; } catch { /* noop */ }
  await loadList();
  await run();
});
</script>
