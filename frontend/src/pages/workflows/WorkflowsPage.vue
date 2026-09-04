<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('workflows.title') }}</h1>
        <p class="page-sub">{{ $t('workflows.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="reload"><RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('workflows.refresh') }}</button>
        <button v-if="can('workflows.create')" class="btn-primary btn-sm" @click="openCreate"><Plus :size="12" /> {{ $t('workflows.new') }}</button>
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="stat">
        <div class="stat-label">{{ $t(s.label) }}</div>
        <div class="stat-value text-xl">{{ stats[s.key] ?? 0 }}</div>
      </div>
    </div>

    <div class="flex gap-3 items-start">
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('workflows.empty') }}</div>
        <table class="data-table" v-else>
          <thead>
            <tr>
              <th>{{ $t('workflows.col.name') }}</th>
              <th class="hidden md:table-cell">{{ $t('workflows.col.entity') }}</th>
              <th class="hidden lg:table-cell">{{ $t('workflows.col.trigger') }}</th>
              <th class="th-num">{{ $t('workflows.col.runs') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in rows" :key="w.id"
                class="border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
                :class="selected?.id === w.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
                @click="openDetail(w.id)">
              <td class="px-3 py-2">
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="w.is_active ? 'bg-emerald-500' : 'bg-slate-300'" />
                  <span class="text-ink dark:text-ink-dark">{{ w.name }}</span>
                </div>
                <div class="text-[11px] text-ink-subtle ml-3">{{ w.actions_count }} {{ $t('workflows.actions_lc') }}</div>
              </td>
              <td class="px-3 py-2 hidden md:table-cell"><span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300">{{ w.entity }}</span></td>
              <td class="px-3 py-2 hidden lg:table-cell text-ink-muted">
                {{ $t(`workflows.tt.${w.trigger_type}`) }}
                <span v-if="w.trigger_event" class="text-[10px] text-ink-subtle">· {{ w.trigger_event }}</span>
                <span v-else-if="w.schedule_cron" class="text-[10px] text-ink-subtle font-mono">· {{ w.schedule_cron }}</span>
              </td>
              <td class="px-3 py-2 text-right tabular-nums text-ink-muted">{{ w.run_count }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Detail drawer -->
      <div v-if="selected" class="card w-full sm:w-96 shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-14rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ selected.name }}</div>
            <div class="text-[11px] text-ink-subtle">
              {{ selected.entity }} · {{ $t(`workflows.tt.${selected.trigger_type}`) }}
              <span v-if="selected.trigger_event">· {{ selected.trigger_event }}</span>
              <span v-else-if="selected.schedule_cron" class="font-mono">· {{ selected.schedule_cron }}</span>
            </div>
          </div>
          <button v-if="can('workflows.update')" class="btn-secondary btn-xs" @click="openEdit(selected)">{{ $t('workflows.edit') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>
        <div v-if="can('workflows.update')" class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 flex gap-1.5 items-center">
          <input v-model="runSubjectId" type="number" class="input text-xs w-24" :placeholder="$t('workflows.subject_id')" />
          <button class="btn-primary btn-xs" @click="doRun"><Play :size="11" /> {{ $t('workflows.run_now') }}</button>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <p v-if="selected.description" class="text-ink-muted dark:text-ink-dark-muted">{{ selected.description }}</p>
          <div v-if="selected.conditions?.length">
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('workflows.conditions') }}</div>
            <div v-for="(c, i) in selected.conditions" :key="i" class="font-mono text-[11px] text-ink dark:text-ink-dark">{{ c.field }} {{ c.op || 'eq' }} {{ c.value }}</div>
          </div>
          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('workflows.actions') }}</div>
            <div v-for="(a, i) in selected.actions" :key="a.id" class="flex items-center gap-2 py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <span class="text-[10px] text-ink-subtle w-4">{{ i + 1 }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="actionClass(a.type)">{{ $t(`workflows.at.${a.type}`) }}</span>
              <span class="text-ink-muted truncate">{{ a.config?.title || a.config?.subject || a.config?.field || a.config?.message || '' }}</span>
            </div>
          </div>
          <div v-if="selected.runs?.length">
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('workflows.run_history') }}</div>
            <div v-for="r in selected.runs" :key="r.id" class="py-1.5 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <div class="flex items-center gap-1.5">
                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="runClass(r.status)">{{ $t(`workflows.rs.${r.status}`) }}</span>
                <span class="text-ink-muted">{{ r.actions_run }} {{ $t('workflows.ran') }}</span>
                <span class="text-ink-subtle ml-auto">{{ r.created_human }}</span>
              </div>
              <div v-for="(l, li) in (r.log || [])" :key="li" class="text-[10px] ml-1 mt-0.5" :class="l.status === 'failed' ? 'text-red-500' : 'text-ink-subtle'">
                · {{ l.message }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/edit modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-2xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ form.id ? $t('workflows.edit_title') : $t('workflows.new_title') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2"><label class="label">{{ $t('workflows.col.name') }} *</label>
            <input v-model="form.data.name" class="input text-sm" />
            <p v-if="form.errors.name" class="text-[11px] text-red-500">{{ form.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('workflows.col.entity') }}</label>
            <select v-model="form.data.entity" class="input text-sm" @change="form.data.trigger_event = ''">
              <option v-for="e in meta.entities" :key="e" :value="e">{{ e }}</option>
            </select></div>
          <div><label class="label">{{ $t('workflows.col.trigger') }}</label>
            <select v-model="form.data.trigger_type" class="input text-sm">
              <option v-for="tt in meta.trigger_types" :key="tt" :value="tt">{{ $t(`workflows.tt.${tt}`) }}</option>
            </select></div>
          <div v-if="form.data.trigger_type === 'event'" class="col-span-2">
            <label class="label">{{ $t('workflows.col.trigger_event') }}</label>
            <select v-model="form.data.trigger_event" class="input text-sm">
              <option value="" disabled>{{ $t('workflows.select_event') }}</option>
              <option v-for="ev in (meta.events[form.data.entity] || [])" :key="ev" :value="ev">{{ ev }}</option>
            </select>
            <p v-if="form.errors.trigger_event" class="text-[11px] text-red-500">{{ form.errors.trigger_event[0] }}</p>
          </div>
          <div v-if="form.data.trigger_type === 'schedule'" class="col-span-2">
            <label class="label">{{ $t('workflows.col.schedule_cron') }}</label>
            <input v-model="form.data.schedule_cron" class="input text-sm font-mono" placeholder="*/15 * * * *" />
            <p class="text-[11px] text-ink-subtle">{{ $t('workflows.cron_hint') }}</p>
            <p v-if="form.errors.schedule_cron" class="text-[11px] text-red-500">{{ form.errors.schedule_cron[0] }}</p>
          </div>
          <label class="col-span-2 flex items-center gap-1.5 text-xs text-ink-muted">
            <input type="checkbox" class="rounded border-slate-300" v-model="form.data.is_active" /> {{ $t('workflows.active') }}
          </label>
        </div>

        <!-- Conditions -->
        <div class="mt-3">
          <div class="flex items-center mb-1"><span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('workflows.conditions') }}</span>
            <button class="text-[11px] text-primary-600 hover:underline ml-auto" @click="form.data.conditions.push({ field: '', op: 'eq', value: '' })">+ {{ $t('workflows.add_condition') }}</button></div>
          <div v-for="(c, i) in form.data.conditions" :key="i" class="flex gap-1.5 mb-1.5">
            <input v-model="c.field" class="input text-xs flex-1" :placeholder="$t('workflows.field')" />
            <select v-model="c.op" class="input text-xs w-24"><option v-for="op in meta.operators" :key="op" :value="op">{{ op }}</option></select>
            <input v-model="c.value" class="input text-xs flex-1" :placeholder="$t('workflows.value')" />
            <button class="p-1 text-ink-subtle hover:text-red-500" @click="form.data.conditions.splice(i, 1)"><X :size="12" /></button>
          </div>
        </div>

        <!-- Actions -->
        <div class="mt-3">
          <div class="flex items-center mb-1"><span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('workflows.actions') }}</span>
            <button class="text-[11px] text-primary-600 hover:underline ml-auto" @click="form.data.actions.push({ type: 'create_task', config: {} })">+ {{ $t('workflows.add_action') }}</button></div>
          <div v-for="(a, i) in form.data.actions" :key="i" class="flex gap-1.5 mb-1.5 items-start">
            <select v-model="a.type" class="input text-xs w-36"><option v-for="at in meta.action_types" :key="at" :value="at">{{ $t(`workflows.at.${at}`) }}</option></select>
            <input v-model="a.configText" class="input text-xs flex-1" :placeholder="configHint(a.type)" @input="a._dirty = true" />
            <button class="p-1 text-ink-subtle hover:text-red-500" @click="form.data.actions.splice(i, 1)"><X :size="12" /></button>
          </div>
          <p class="text-[11px] text-ink-subtle">{{ $t('workflows.config_hint') }}</p>
        </div>

        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('workflows.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving" @click="submit">{{ form.saving ? $t('workflows.saving') : $t('workflows.save') }}</button>
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
import api from '@/services/workflows';
import { RefreshCw, Plus, X, Play } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const statTiles = [
  { key: 'total', label: 'workflows.stat.total' },
  { key: 'active', label: 'workflows.stat.active' },
  { key: 'runs', label: 'workflows.stat.runs' },
  { key: 'runs_today', label: 'workflows.stat.today' },
];

const rows = ref([]);
const stats = reactive({});
const meta = reactive({ entities: [], trigger_types: [], events: {}, action_types: [], operators: [] });
const selected = ref(null);
const loading = ref(false);
const runSubjectId = ref('');
const form = reactive({ open: false, id: null, saving: false, data: { conditions: [], actions: [] }, errors: {} });

async function load() {
  loading.value = true;
  try { const { data } = await api.list({ per_page: 50 }); rows.value = data.data || []; }
  catch { /* noop */ } finally { loading.value = false; }
}
async function loadAux() {
  try { const [s, m] = await Promise.all([api.stats(), api.meta()]); Object.assign(stats, s.data.data || {}); Object.assign(meta, m.data.data || {}); }
  catch { /* noop */ }
}
function reload() { return Promise.all([load(), loadAux()]); }

async function openDetail(id) { try { const { data } = await api.show(id); selected.value = data.data; runSubjectId.value = ''; } catch { /* noop */ } }

function openCreate() {
  form.id = null; form.errors = {};
  form.data = {
    name: '', entity: 'leads', trigger_type: 'manual', trigger_event: '', schedule_cron: '',
    is_active: true, conditions: [], actions: [],
  };
  form.open = true;
}
function openEdit(w) {
  form.id = w.id; form.errors = {};
  form.data = {
    name: w.name, entity: w.entity, trigger_type: w.trigger_type,
    trigger_event: w.trigger_event || '', schedule_cron: w.schedule_cron || '', is_active: w.is_active,
    conditions: (w.conditions || []).map((c) => ({ ...c })),
    actions: (w.actions || []).map((a) => ({ type: a.type, configText: JSON.stringify(a.config || {}) })),
  };
  form.open = true;
}
function configHint(type) {
  return {
    create_task: '{"title":"...","priority":"high","due_in_days":1}',
    update_field: '{"field":"status","value":"open"}',
    send_email: '{"subject":"...","body":"..."}',
    notify: '{"message":"..."}', webhook: '{"url":"..."}', log: '{"message":"..."}',
  }[type] || '{}';
}

async function submit() {
  form.saving = true; form.errors = {};
  try {
    const actions = form.data.actions.map((a) => {
      let config = {};
      try { config = a.configText ? JSON.parse(a.configText) : {}; } catch { config = {}; }
      return { type: a.type, config };
    });
    const payload = {
      name: form.data.name, entity: form.data.entity, trigger_type: form.data.trigger_type,
      trigger_event: form.data.trigger_type === 'event' ? (form.data.trigger_event || null) : null,
      schedule_cron: form.data.trigger_type === 'schedule' ? (form.data.schedule_cron || null) : null,
      is_active: form.data.is_active,
      conditions: form.data.conditions.filter((c) => c.field),
      actions,
    };
    form.id ? await api.update(form.id, payload) : await api.create(payload);
    toast.success(t('workflows.saved'));
    form.open = false;
    await reload();
    if (selected.value?.id === form.id) await openDetail(form.id);
  } catch (e) { if (e.response?.status === 422) form.errors = e.response.data?.errors || {}; }
  finally { form.saving = false; }
}

async function doRun() {
  try {
    const { data } = await api.run(selected.value.id, runSubjectId.value ? Number(runSubjectId.value) : undefined);
    toast.success(t('workflows.ran_ok', { s: data.data.run.status }));
    await Promise.all([openDetail(selected.value.id), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

const actionClass = (ty) => ({
  create_task: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  update_field: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  send_email: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
}[ty] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300');
const runClass = (s) => ({
  success: 'bg-emerald-100 text-emerald-700', partial: 'bg-amber-100 text-amber-700',
  failed: 'bg-red-100 text-red-700', skipped: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux()]); });
</script>
