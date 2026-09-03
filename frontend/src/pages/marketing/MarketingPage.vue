<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">

    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('marketing.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('marketing.subtitle') }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('marketing.refresh') }}
        </button>
        <button v-if="can('campaigns.create')" class="btn-primary text-xs px-3 py-1.5" @click="openCreate">
          <Plus :size="12" /> {{ $t('marketing.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="card p-3">
        <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">{{ $t(s.label) }}</div>
        <div class="text-lg font-semibold text-ink dark:text-ink-dark mt-0.5">
          <span v-if="s.pct">{{ stats[s.key] == null ? '—' : stats[s.key] + '%' }}</span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <input v-model="filters.q" class="input text-sm w-52" :placeholder="$t('marketing.search')" @keyup.enter="load" />
      <select v-model="filters.type" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('marketing.all_types') }}</option>
        <option value="email">{{ $t('marketing.email') }}</option>
        <option value="sms">{{ $t('marketing.sms') }}</option>
      </select>
      <select v-model="filters.status" class="input text-sm w-auto" @change="load">
        <option value="all">{{ $t('marketing.all_statuses') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ $t(`marketing.st.${s}`) }}</option>
      </select>
    </div>

    <div class="flex gap-3 items-start">
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('marketing.empty') }}</div>
        <table v-else class="w-full text-sm">
          <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
            <tr>
              <th class="text-left font-medium px-3 py-2">{{ $t('marketing.col.name') }}</th>
              <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('marketing.col.status') }}</th>
              <th class="text-right font-medium px-3 py-2 hidden lg:table-cell">{{ $t('marketing.col.recipients') }}</th>
              <th class="text-right font-medium px-3 py-2">{{ $t('marketing.col.open_rate') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in rows" :key="c.id"
                class="border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
                :class="selected?.id === c.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
                @click="openDetail(c.id)">
              <td class="px-3 py-2">
                <div class="flex items-center gap-1.5">
                  <component :is="c.type === 'sms' ? MessageSquare : Mail" :size="13" class="shrink-0 text-ink-subtle" />
                  <span class="text-ink dark:text-ink-dark truncate max-w-[16rem]">{{ c.name }}</span>
                </div>
              </td>
              <td class="px-3 py-2 hidden md:table-cell"><span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(c.status)">{{ $t(`marketing.st.${c.status}`) }}</span></td>
              <td class="px-3 py-2 text-right hidden lg:table-cell tabular-nums text-ink-muted">{{ c.recipients_count }}</td>
              <td class="px-3 py-2 text-right tabular-nums text-ink-muted">{{ c.open_rate == null ? '—' : c.open_rate + '%' }}</td>
            </tr>
          </tbody>
        </table>
        <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(x)=>{page+=x;load()}" />
      </div>

      <!-- Detail drawer -->
      <div v-if="selected" class="card w-full sm:w-96 shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-14rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ selected.name }}</div>
            <div class="text-[11px] text-ink-subtle">{{ $t(`marketing.${selected.type}`) }}</div>
          </div>
          <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(selected.status)">{{ $t(`marketing.st.${selected.status}`) }}</span>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>
        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 flex gap-1.5">
          <button v-if="['draft','scheduled'].includes(selected.status) && can('campaigns.launch')" class="btn-primary text-[11px] px-2.5 py-1" @click="doLaunch">{{ $t('marketing.launch') }}</button>
          <button v-if="selected.is_editable && can('campaigns.delete')" class="btn-secondary text-[11px] px-2 py-1 text-red-600" @click="removeCampaign">{{ $t('marketing.delete') }}</button>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <div v-if="selected.subject"><span class="text-ink-subtle">{{ $t('marketing.subject') }}:</span> <span class="text-ink dark:text-ink-dark">{{ selected.subject }}</span></div>
          <div class="rounded-md bg-slate-50 dark:bg-surface-dark-subtle p-2 text-ink-muted dark:text-ink-dark-muted whitespace-pre-wrap max-h-40 overflow-y-auto" v-html="sanitized(selected.body)"></div>
          <dl class="grid grid-cols-2 gap-y-1">
            <dt class="text-ink-subtle">{{ $t('marketing.audience') }}</dt>
            <dd class="text-right text-ink dark:text-ink-dark">{{ $t(`marketing.src.${selected.audience?.source || 'customers'}`) }}</dd>
            <dt class="text-ink-subtle">{{ $t('marketing.col.recipients') }}</dt><dd class="text-right tabular-nums">{{ selected.recipients_count }}</dd>
            <dt class="text-ink-subtle">{{ $t('marketing.sent') }}</dt><dd class="text-right tabular-nums">{{ selected.sent_count }}</dd>
            <dt class="text-ink-subtle">{{ $t('marketing.opened') }}</dt><dd class="text-right tabular-nums">{{ selected.opened_count }}</dd>
            <dt class="text-ink-subtle">{{ $t('marketing.clicked') }}</dt><dd class="text-right tabular-nums">{{ selected.clicked_count }}</dd>
          </dl>
          <div v-if="recipients.length">
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('marketing.recipients_sample') }}</div>
            <div v-for="r in recipients" :key="r.id" class="flex justify-between py-0.5 text-[11px]">
              <span class="text-ink dark:text-ink-dark truncate">{{ r.name }}</span>
              <span class="text-ink-subtle">{{ r.email || r.phone }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-2xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('marketing.new_title') }}</div>
        <div class="space-y-2.5">
          <div class="grid grid-cols-2 gap-2.5">
            <div><label class="label">{{ $t('marketing.col.name') }} *</label>
              <input v-model="form.data.name" class="input text-sm" />
              <p v-if="form.errors.name" class="text-[11px] text-red-500">{{ form.errors.name[0] }}</p></div>
            <div><label class="label">{{ $t('marketing.channel') }}</label>
              <select v-model="form.data.type" class="input text-sm" @change="refreshPreview">
                <option value="email">{{ $t('marketing.email') }}</option>
                <option value="sms">{{ $t('marketing.sms') }}</option>
              </select></div>
          </div>
          <div v-if="form.data.type === 'email'" class="grid grid-cols-2 gap-2.5">
            <div><label class="label">{{ $t('marketing.subject') }} *</label><input v-model="form.data.subject" class="input text-sm" /></div>
            <div><label class="label">{{ $t('marketing.template') }}</label>
              <select v-model="templateId" class="input text-sm" @change="applyTemplate">
                <option :value="null">—</option>
                <option v-for="tp in meta.templates" :key="tp.id" :value="tp.id">{{ tp.name }}</option>
              </select></div>
          </div>
          <div><label class="label">{{ $t('marketing.body') }} *</label>
            <textarea v-model="form.data.body" rows="5" class="input text-sm font-mono"></textarea></div>
          <!-- Audience builder -->
          <div class="grid grid-cols-3 gap-2.5 items-end">
            <div><label class="label">{{ $t('marketing.audience') }}</label>
              <select v-model="form.audience.source" class="input text-sm" @change="form.audience.filters = {}; refreshPreview()">
                <option value="customers">{{ $t('marketing.src.customers') }}</option>
                <option value="leads">{{ $t('marketing.src.leads') }}</option>
              </select></div>
            <div v-if="form.audience.source === 'customers'"><label class="label">{{ $t('marketing.filter_status') }}</label>
              <select v-model="form.audience.filters.status" class="input text-sm" @change="refreshPreview">
                <option :value="undefined">{{ $t('marketing.any') }}</option>
                <option value="active">active</option><option value="on_hold">on_hold</option>
              </select></div>
            <div v-else><label class="label">{{ $t('marketing.filter_rating') }}</label>
              <select v-model="form.audience.filters.rating" class="input text-sm" @change="refreshPreview">
                <option :value="undefined">{{ $t('marketing.any') }}</option>
                <option value="hot">hot</option><option value="warm">warm</option><option value="cold">cold</option>
              </select></div>
            <div class="text-[11px] text-ink-muted pb-2">
              <span v-if="preview">{{ $t('marketing.reachable', { r: preview.reachable, t: preview.total }) }}</span>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="form.open = false">{{ $t('marketing.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="form.saving" @click="submit">{{ form.saving ? $t('marketing.saving') : $t('marketing.save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, h } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/marketing';
import { RefreshCw, Plus, X, Mail, MessageSquare } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('marketing.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
  h('div', { class: 'flex gap-1' }, [
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page <= 1, onClick: () => emit('go', -1) }, '‹'),
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page >= props.p.last_page, onClick: () => emit('go', 1) }, '›'),
  ]),
]);
Pager.props = ['p', 'page'];
Pager.emits = ['go'];

const statuses = ['draft', 'scheduled', 'running', 'sent', 'paused', 'cancelled'];
const statTiles = [
  { key: 'total',     label: 'marketing.stat.total' },
  { key: 'draft',     label: 'marketing.stat.draft' },
  { key: 'scheduled', label: 'marketing.stat.scheduled' },
  { key: 'sent',      label: 'marketing.stat.sent' },
  { key: 'recipients_reached', label: 'marketing.stat.reached' },
  { key: 'avg_open_rate', label: 'marketing.stat.open_rate', pct: true },
];

const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ templates: [], email_accounts: [], sms_providers: [], types: [] });
const recipients = ref([]);
const selected   = ref(null);
const loading    = ref(false);
const page       = ref(1);
const templateId = ref(null);
const preview    = ref(null);
const filters    = reactive({ q: '', type: '', status: 'all' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const form = reactive({ open: false, saving: false, data: {}, audience: { source: 'customers', filters: {} }, errors: {} });

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    if (filters.q) params.q = filters.q;
    if (filters.type) params.type = filters.type;
    if (filters.status !== 'all') params.status = filters.status;
    const { data } = await api.list(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
async function loadAux() {
  try { const [s, m] = await Promise.all([api.stats(), api.meta()]); Object.assign(stats, s.data.data || {}); Object.assign(meta, m.data.data || {}); }
  catch { /* non-critical */ }
}
function reload() { page.value = 1; return Promise.all([load(), loadAux()]); }

async function openDetail(id) {
  try {
    const { data } = await api.show(id); selected.value = data.data;
    recipients.value = [];
    if (data.data.sent_count > 0) { const r = await api.recipients(id); recipients.value = (r.data.data || []).slice(0, 12); }
  } catch { /* noop */ }
}

function openCreate() {
  form.errors = {}; templateId.value = null; preview.value = null;
  form.data = { name: '', type: 'email', subject: '', body: '' };
  form.audience = { source: 'customers', filters: {} };
  form.open = true;
  refreshPreview();
}
function applyTemplate() {
  const tp = meta.templates.find((x) => x.id === templateId.value);
  if (!tp) return;
  form.data.subject = tp.subject; form.data.body = tp.body_html;
}
async function refreshPreview() {
  try { const { data } = await api.preview({ type: form.data.type, audience: form.audience }); preview.value = data.data; }
  catch { preview.value = null; }
}

async function submit() {
  form.saving = true; form.errors = {};
  try {
    const payload = { ...form.data, audience: { source: form.audience.source, filters: form.audience.filters } };
    await api.create(payload);
    toast.success(t('marketing.created'));
    form.open = false;
    await reload();
  } catch (e) { if (e.response?.status === 422) form.errors = e.response.data?.errors || {}; }
  finally { form.saving = false; }
}

async function doLaunch() {
  try {
    const { data } = await api.launch(selected.value.id);
    selected.value = data.data;
    toast.success(t('marketing.launched'));
    await Promise.all([load(), loadAux(), openDetail(selected.value.id)]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}
async function removeCampaign() {
  try { await api.remove(selected.value.id); selected.value = null; await reload(); } catch { /* noop */ }
}

function sanitized(html) {
  if (!html) return '';
  return String(html).replace(/<\s*(script|style)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '').replace(/\son\w+\s*=\s*(["'])[\s\S]*?\1/gi, '');
}

const statusClass = (s) => ({
  draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  scheduled: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  running: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  sent: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  paused: 'bg-amber-100 text-amber-700',
  cancelled: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux()]); });
</script>
