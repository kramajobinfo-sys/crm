<template>
  <div class="flex gap-3 items-start">
    <!-- Dashboard list -->
    <div class="card w-56 shrink-0 hidden md:block overflow-hidden">
      <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('reports.dashboards.title') }}</span>
        <button v-if="can('reports.create')" class="text-primary-600 hover:text-primary-700" @click="openCreate"><Plus :size="14" /></button>
      </div>
      <div v-if="!dashboards.length" class="text-[11px] text-ink-subtle text-center py-6">{{ $t('reports.dashboards.none') }}</div>
      <button v-for="d in dashboards" :key="d.id" class="w-full text-left px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0 hover:bg-slate-50 dark:hover:bg-surface-dark-subtle"
              :class="activeId === d.id && 'bg-primary-50 dark:bg-primary-900/20'"
              @click="select(d.id)">
        <div class="text-xs text-ink dark:text-ink-dark truncate">{{ d.name }}</div>
        <div class="text-[10px] text-ink-subtle">{{ (d.layout || []).length }} {{ $t('reports.dashboards.widgets') }}<span v-if="d.is_default"> · {{ $t('reports.dashboards.default') }}</span></div>
      </button>
    </div>

    <!-- Active dashboard -->
    <div class="flex-1 min-w-0">
      <div v-if="!active" class="card p-10 text-center text-sm text-ink-subtle">{{ $t('reports.dashboards.pick_hint') }}</div>
      <div v-else>
        <div class="flex items-center justify-between mb-3">
          <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ active.name }}</div>
          <div class="flex gap-2">
            <button v-if="can('reports.create')" class="btn-secondary btn-xs" @click="openAddWidget"><Plus :size="11" /> {{ $t('reports.dashboards.add_widget') }}</button>
            <button v-if="can('reports.create')" class="btn-secondary btn-xs text-red-600" @click="removeDashboard"><Trash2 :size="11" /> {{ $t('reports.dashboards.delete') }}</button>
          </div>
        </div>

        <div v-if="!(active.layout || []).length" class="card p-10 text-center text-sm text-ink-subtle">{{ $t('reports.dashboards.empty') }}</div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div v-for="(item, i) in active.layout" :key="i" class="card p-3" :class="item.size === 'full' && 'md:col-span-2'">
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs font-medium text-ink dark:text-ink-dark truncate">{{ widgetData[i]?.report?.name || '…' }}</span>
              <button v-if="can('reports.create')" class="text-ink-subtle hover:text-red-600" @click="removeWidget(i)"><X :size="13" /></button>
            </div>
            <div v-if="widgetData[i] === undefined" class="h-40 flex items-center justify-center text-xs text-ink-subtle">{{ $t('reports.dashboards.loading') }}</div>
            <div v-else-if="widgetData[i] === null" class="h-20 flex items-center justify-center text-xs text-ink-subtle">{{ $t('reports.dashboards.unavailable') }}</div>
            <div v-else-if="widgetData[i].report.chart_type === 'table'" class="overflow-x-auto max-h-56">
              <table class="data-table">
                <thead><tr><th v-for="c in widgetData[i].result.columns" :key="c.key">{{ c.label }}</th></tr></thead>
                <tbody>
                  <tr v-for="(row, ri) in widgetData[i].result.rows" :key="ri">
                    <td v-for="c in widgetData[i].result.columns" :key="c.key">{{ row[c.key] }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <ReportChart v-else :type="widgetData[i].report.chart_type" :result="widgetData[i].result" :height="180" />
          </div>
        </div>
      </div>
    </div>

    <!-- Create dashboard modal -->
    <div v-if="createForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="createForm.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('reports.dashboards.new') }}</div>
        <label class="label">{{ $t('reports.name') }} *</label>
        <input v-model="createForm.name" class="input text-sm mb-2" />
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="createForm.open = false">{{ $t('reports.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="!createForm.name || createForm.saving" @click="submitCreate">{{ createForm.saving ? $t('reports.saving') : $t('reports.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Add widget modal -->
    <div v-if="addForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="addForm.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('reports.dashboards.add_widget') }}</div>
        <label class="label">{{ $t('reports.saved') }}</label>
        <select v-model="addForm.reportId" class="input text-sm mb-2">
          <option v-for="r in savedReports" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
        <label class="label">{{ $t('reports.dashboards.size') }}</label>
        <select v-model="addForm.size" class="input text-sm">
          <option value="half">{{ $t('reports.dashboards.half') }}</option>
          <option value="full">{{ $t('reports.dashboards.full') }}</option>
        </select>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="addForm.open = false">{{ $t('reports.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="!addForm.reportId || addForm.saving" @click="submitAddWidget">{{ addForm.saving ? $t('reports.saving') : $t('reports.save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/reports';
import { Plus, Trash2, X } from 'lucide-vue-next';
import ReportChart from '@/components/charts/ReportChart.vue';

const props = defineProps({ savedReports: { type: Array, default: () => [] } });

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const dashboards = ref([]);
const activeId = ref(null);
const widgetData = ref({});
const createForm = reactive({ open: false, saving: false, name: '' });
const addForm = reactive({ open: false, saving: false, reportId: null, size: 'half' });

const active = computed(() => dashboards.value.find((d) => d.id === activeId.value));

async function loadDashboards() {
  try {
    const { data } = await api.dashboards();
    dashboards.value = data.data || [];
    if (!activeId.value && dashboards.value.length) {
      const def = dashboards.value.find((d) => d.is_default) || dashboards.value[0];
      select(def.id);
    }
  } catch { /* noop */ }
}

function select(id) {
  activeId.value = id;
}

// Fetch every widget's data in parallel — one round trip per widget, but concurrent,
// not sequential, so it stays fast despite this dev stack's per-request latency.
watch(active, async (dash) => {
  widgetData.value = {};
  if (!dash) return;
  const layout = dash.layout || [];
  await Promise.all(layout.map(async (item, i) => {
    try {
      const { data } = await api.show(item.report_id);
      widgetData.value = { ...widgetData.value, [i]: data.data };
    } catch {
      widgetData.value = { ...widgetData.value, [i]: null };
    }
  }));
}, { immediate: true });

function openCreate() { createForm.open = true; createForm.name = ''; }
async function submitCreate() {
  createForm.saving = true;
  try {
    const { data } = await api.createDashboard({ name: createForm.name });
    toast.success(t('reports.dashboards.created'));
    createForm.open = false;
    await loadDashboards();
    select(data.data.id);
  } catch { toast.error(t('reports.check_fields')); }
  finally { createForm.saving = false; }
}

function openAddWidget() {
  addForm.open = true;
  addForm.reportId = props.savedReports[0]?.id ?? null;
  addForm.size = 'half';
}
async function submitAddWidget() {
  addForm.saving = true;
  try {
    const layout = [...(active.value.layout || []), { report_id: addForm.reportId, size: addForm.size }];
    await api.updateDashboard(activeId.value, { layout });
    addForm.open = false;
    await loadDashboards();
  } catch { toast.error(t('reports.check_fields')); }
  finally { addForm.saving = false; }
}

async function removeWidget(index) {
  const layout = (active.value.layout || []).filter((_, i) => i !== index);
  try {
    await api.updateDashboard(activeId.value, { layout });
    await loadDashboards();
  } catch { /* noop */ }
}

async function removeDashboard() {
  try {
    await api.deleteDashboard(activeId.value);
    activeId.value = null;
    await loadDashboards();
  } catch { /* noop */ }
}

onMounted(loadDashboards);
</script>
