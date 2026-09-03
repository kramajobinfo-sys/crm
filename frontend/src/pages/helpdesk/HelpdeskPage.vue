<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">

    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('helpdesk.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('helpdesk.subtitle') }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('helpdesk.refresh') }}
        </button>
        <button v-if="can('tickets.create')" class="btn-primary text-xs px-3 py-1.5" @click="openCreate">
          <Plus :size="12" /> {{ $t('helpdesk.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-2.5 mb-3">
      <button v-for="s in statTiles" :key="s.key" class="card p-3 text-left transition-colors"
              :class="isActiveTile(s) ? 'ring-1 ring-primary-500' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
              @click="applyTile(s)">
        <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">{{ $t(s.label) }}</div>
        <div class="text-lg font-semibold mt-0.5" :class="(s.key === 'breaching' && stats.breaching) || (s.key === 'urgent' && stats.urgent) ? 'text-red-600' : 'text-ink dark:text-ink-dark'">
          {{ stats[s.key] ?? 0 }}
        </div>
      </button>
    </div>

    <!-- Filters -->
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <input v-model="filters.q" class="input text-sm w-52" :placeholder="$t('helpdesk.search')" @keyup.enter="load" />
      <select v-model="filters.status" class="input text-sm w-auto" @change="load">
        <option value="open">{{ $t('helpdesk.open_only') }}</option>
        <option value="all">{{ $t('helpdesk.all_statuses') }}</option>
        <option v-for="s in meta.statuses" :key="s" :value="s">{{ $t(`helpdesk.st.${s}`) }}</option>
      </select>
      <select v-model="filters.priority" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('helpdesk.all_priorities') }}</option>
        <option v-for="p in meta.priorities" :key="p" :value="p">{{ $t(`helpdesk.pr.${p}`) }}</option>
      </select>
      <select v-model="filters.category_id" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('helpdesk.all_categories') }}</option>
        <option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted ml-1">
        <input type="checkbox" class="rounded border-slate-300" :checked="filters.assigned_to === 'me'"
               @change="filters.assigned_to = $event.target.checked ? 'me' : ''; load()" />
        {{ $t('helpdesk.mine_only') }}
      </label>
    </div>

    <div class="flex gap-3 items-start">
      <!-- List -->
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('helpdesk.empty') }}</div>
        <table v-else class="w-full text-sm">
          <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
            <tr>
              <th class="text-left font-medium px-3 py-2">{{ $t('helpdesk.col.subject') }}</th>
              <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('helpdesk.col.status') }}</th>
              <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">{{ $t('helpdesk.col.assignee') }}</th>
              <th class="text-left font-medium px-3 py-2">{{ $t('helpdesk.col.due') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tk in rows" :key="tk.id"
                class="border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
                :class="selected?.id === tk.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
                @click="openDetail(tk.id)">
              <td class="px-3 py-2">
                <div class="flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="priorityDot(tk.priority)" />
                  <span class="text-ink dark:text-ink-dark truncate max-w-[16rem]">{{ tk.subject }}</span>
                </div>
                <div class="text-[11px] text-ink-subtle font-mono">{{ tk.ticket_no }} · {{ tk.customer?.name || tk.requester_name || '—' }}</div>
              </td>
              <td class="px-3 py-2 hidden md:table-cell"><span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(tk.status)">{{ $t(`helpdesk.st.${tk.status}`) }}</span></td>
              <td class="px-3 py-2 hidden lg:table-cell text-ink-muted">{{ tk.assignee?.name || $t('helpdesk.unassigned') }}</td>
              <td class="px-3 py-2">
                <span v-if="tk.is_breaching" class="text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700">{{ $t('helpdesk.overdue') }}</span>
                <span v-else class="text-[11px] text-ink-subtle">{{ tk.due_human || '—' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(x)=>{page+=x;load()}" />
      </div>

      <!-- Detail drawer -->
      <div v-if="selected" class="card w-full sm:w-[27rem] shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-14rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ selected.subject }}</div>
            <div class="text-[11px] text-ink-subtle font-mono">{{ selected.ticket_no }}</div>
          </div>
          <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(selected.status)">{{ $t(`helpdesk.st.${selected.status}`) }}</span>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <!-- Controls -->
        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 grid grid-cols-2 gap-1.5">
          <select v-if="can('tickets.update')" :value="selected.status" class="input text-xs" @change="doStatus($event.target.value)">
            <option v-for="s in meta.statuses" :key="s" :value="s">{{ $t(`helpdesk.st.${s}`) }}</option>
          </select>
          <select v-if="can('tickets.assign')" :value="selected.assignee?.id || ''" class="input text-xs" @change="doAssign($event.target.value)">
            <option value="">{{ $t('helpdesk.unassigned') }}</option>
            <option v-for="a in meta.agents" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <select v-if="can('tickets.update')" :value="selected.priority" class="input text-xs" @change="doPriority($event.target.value)">
            <option v-for="p in meta.priorities" :key="p" :value="p">{{ $t(`helpdesk.pr.${p}`) }}</option>
          </select>
          <button v-if="can('tickets.update')" class="btn-secondary text-xs px-2 py-1 text-amber-600" @click="doEscalate">{{ $t('helpdesk.escalate') }}</button>
        </div>

        <!-- SLA -->
        <div v-if="selected.sla_policy" class="px-3 py-1.5 text-[11px] border-b border-slate-100 dark:border-slate-700/60"
             :class="selected.is_breaching ? 'text-red-600' : 'text-ink-subtle'">
          {{ selected.sla_policy.name }} · {{ $t('helpdesk.due') }} {{ selected.due_human || '—' }}
          <span v-if="selected.reopened_count"> · {{ $t('helpdesk.reopened', { n: selected.reopened_count }) }}</span>
        </div>

        <!-- Conversation -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2 text-xs">
          <div v-if="selected.description" class="rounded-md bg-slate-50 dark:bg-surface-dark-subtle p-2 text-ink dark:text-ink-dark">{{ selected.description }}</div>
          <div v-for="r in selected.replies" :key="r.id"
               class="rounded-md p-2"
               :class="r.is_internal ? 'bg-amber-50 dark:bg-amber-900/15 border border-amber-200 dark:border-amber-800/40'
                       : r.author_type === 'customer' ? 'bg-slate-50 dark:bg-surface-dark-subtle'
                       : 'bg-primary-50 dark:bg-primary-900/15'">
            <div class="flex items-center gap-1.5 mb-0.5">
              <span class="text-[10px] font-medium text-ink dark:text-ink-dark">{{ r.user || $t(`helpdesk.author.${r.author_type}`) }}</span>
              <span v-if="r.is_internal" class="text-[9px] px-1 py-0.5 rounded bg-amber-200 text-amber-800">{{ $t('helpdesk.internal') }}</span>
              <span class="text-ink-subtle ml-auto">{{ r.created_human }}</span>
            </div>
            <div class="text-ink-muted dark:text-ink-dark-muted whitespace-pre-wrap">{{ r.body }}</div>
          </div>
        </div>

        <!-- Reply box -->
        <div v-if="can('tickets.update')" class="border-t border-slate-200 dark:border-slate-700 p-2.5 shrink-0">
          <textarea v-model="replyDraft" rows="2" class="input text-xs w-full" :placeholder="$t('helpdesk.reply_ph')"></textarea>
          <div class="flex items-center gap-2 mt-1.5">
            <label class="flex items-center gap-1 text-[11px] text-ink-muted">
              <input type="checkbox" class="rounded border-slate-300" v-model="replyInternal" /> {{ $t('helpdesk.internal_note') }}
            </label>
            <button class="btn-primary text-[11px] px-3 py-1 ml-auto" :disabled="!replyDraft.trim() || sending" @click="submitReply">
              <Send :size="11" /> {{ replyInternal ? $t('helpdesk.add_note') : $t('helpdesk.send_reply') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('helpdesk.new_title') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2"><label class="label">{{ $t('helpdesk.col.subject') }} *</label>
            <input v-model="form.data.subject" class="input text-sm" />
            <p v-if="form.errors.subject" class="text-[11px] text-red-500">{{ form.errors.subject[0] }}</p></div>
          <div class="col-span-2"><label class="label">{{ $t('helpdesk.description') }}</label>
            <textarea v-model="form.data.description" rows="3" class="input text-sm"></textarea></div>
          <div><label class="label">{{ $t('helpdesk.col.priority') }}</label>
            <select v-model="form.data.priority" class="input text-sm">
              <option v-for="p in meta.priorities" :key="p" :value="p">{{ $t(`helpdesk.pr.${p}`) }}</option>
            </select></div>
          <div><label class="label">{{ $t('helpdesk.category') }}</label>
            <select v-model="form.data.category_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select></div>
          <div><label class="label">{{ $t('helpdesk.customer') }}</label>
            <select v-model="form.data.customer_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select></div>
          <div><label class="label">{{ $t('helpdesk.channel') }}</label>
            <select v-model="form.data.channel" class="input text-sm">
              <option v-for="ch in meta.channels" :key="ch" :value="ch">{{ $t(`helpdesk.ch.${ch}`) }}</option>
            </select></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="form.open = false">{{ $t('helpdesk.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="form.saving" @click="submitForm">{{ form.saving ? $t('helpdesk.saving') : $t('helpdesk.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Escalate modal -->
    <div v-if="esc.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="esc.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('helpdesk.escalate') }}</div>
        <label class="label">{{ $t('helpdesk.reason') }}</label>
        <input v-model="esc.reason" class="input text-sm mb-2" />
        <label class="label">{{ $t('helpdesk.reassign_to') }}</label>
        <select v-model="esc.escalated_to" class="input text-sm mb-2">
          <option :value="null">—</option>
          <option v-for="a in meta.agents" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <label class="label">{{ $t('helpdesk.note') }}</label>
        <textarea v-model="esc.note" rows="2" class="input text-sm"></textarea>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="esc.open = false">{{ $t('helpdesk.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="esc.saving" @click="submitEscalate">{{ $t('helpdesk.escalate') }}</button>
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
import api from '@/services/helpdesk';
import customerApi from '@/services/customers';
import { RefreshCw, Plus, X, Send } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('helpdesk.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
  h('div', { class: 'flex gap-1' }, [
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page <= 1, onClick: () => emit('go', -1) }, '‹'),
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page >= props.p.last_page, onClick: () => emit('go', 1) }, '›'),
  ]),
]);
Pager.props = ['p', 'page'];
Pager.emits = ['go'];

const statTiles = [
  { key: 'open',           label: 'helpdesk.stat.open',       set: { status: 'open', assigned_to: '', breaching: '' } },
  { key: 'unassigned',     label: 'helpdesk.stat.unassigned', set: { status: 'open', assigned_to: 'unassigned', breaching: '' } },
  { key: 'mine',           label: 'helpdesk.stat.mine',       set: { status: 'open', assigned_to: 'me', breaching: '' } },
  { key: 'breaching',      label: 'helpdesk.stat.breaching',  set: { status: 'open', assigned_to: '', breaching: '1' } },
  { key: 'urgent',         label: 'helpdesk.stat.urgent',     set: { status: 'open', priority: 'urgent', breaching: '' } },
  { key: 'resolved_today', label: 'helpdesk.stat.resolved' },
];

const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ categories: [], sla_policies: [], agents: [], statuses: [], priorities: [], channels: [] });
const customers  = ref([]);
const selected   = ref(null);
const loading    = ref(false);
const page       = ref(1);
const replyDraft = ref('');
const replyInternal = ref(false);
const sending    = ref(false);
const filters    = reactive({ q: '', status: 'open', priority: '', category_id: '', assigned_to: '', breaching: '' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const form = reactive({ open: false, saving: false, data: {}, errors: {} });
const esc  = reactive({ open: false, saving: false, reason: '', escalated_to: null, note: '' });

const isActiveTile = (s) => s.set && filters.status === s.set.status && (filters.assigned_to || '') === (s.set.assigned_to || '')
  && (filters.breaching || '') === (s.set.breaching || '') && (filters.priority || '') === (s.set.priority || '');

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    Object.entries(filters).forEach(([k, v]) => { if (v !== '' && v !== null) params[k] = v; });
    const { data } = await api.list(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
async function loadAux() {
  try {
    const [s, m] = await Promise.all([api.stats(), api.meta()]);
    Object.assign(stats, s.data.data || {});
    Object.assign(meta, m.data.data || {});
  } catch { /* non-critical */ }
}
async function loadCustomers() {
  try { const { data } = await customerApi.list({ per_page: 100 }); customers.value = data.data || []; } catch { /* noop */ }
}
function reload() { page.value = 1; return Promise.all([load(), loadAux()]); }

function applyTile(s) {
  if (!s.set) return;
  Object.assign(filters, { priority: '', ...s.set });
  page.value = 1; load();
}

async function openDetail(id) { try { const { data } = await api.show(id); selected.value = data.data; } catch { /* noop */ } }

async function doStatus(status) {
  try { const { data } = await api.setStatus(selected.value.id, status); selected.value = data.data; await Promise.all([load(), loadAux()]); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}
async function doAssign(userId) {
  try { const { data } = await api.assign(selected.value.id, userId || null); selected.value = data.data; await Promise.all([load(), loadAux()]); }
  catch { /* noop */ }
}
async function doPriority(priority) {
  try { const { data } = await api.update(selected.value.id, { priority }); selected.value = data.data; await load(); }
  catch { /* noop */ }
}

async function submitReply() {
  const body = replyDraft.value.trim();
  if (!body || !selected.value) return;
  sending.value = true;
  try {
    const { data } = await api.reply(selected.value.id, body, replyInternal.value);
    selected.value = data.data;
    replyDraft.value = ''; replyInternal.value = false;
    await Promise.all([load(), loadAux()]);
  } catch { /* noop */ }
  finally { sending.value = false; }
}

function doEscalate() { esc.open = true; esc.reason = ''; esc.escalated_to = null; esc.note = ''; }
async function submitEscalate() {
  esc.saving = true;
  try {
    const { data } = await api.escalate(selected.value.id, { reason: esc.reason || undefined, escalated_to: esc.escalated_to || undefined, note: esc.note || undefined });
    selected.value = data.data;
    esc.open = false;
    toast.success(t('helpdesk.escalated_ok'));
    await Promise.all([load(), loadAux()]);
  } catch { /* noop */ }
  finally { esc.saving = false; }
}

function openCreate() {
  form.errors = {};
  form.data = { subject: '', description: '', priority: 'medium', category_id: null, customer_id: null, channel: 'manual' };
  form.open = true;
}
async function submitForm() {
  form.saving = true; form.errors = {};
  try {
    const { data } = await api.create(form.data);
    toast.success(t('helpdesk.created'));
    form.open = false;
    await Promise.all([load(), loadAux()]);
    selected.value = data.data;
  } catch (e) { if (e.response?.status === 422) form.errors = e.response.data?.errors || {}; }
  finally { form.saving = false; }
}

const priorityDot = (p) => ({ urgent: 'bg-red-500', high: 'bg-amber-500', medium: 'bg-sky-500', low: 'bg-slate-400' }[p] || 'bg-slate-400');
const statusClass = (s) => ({
  new: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  open: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  resolved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  closed: 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux(), loadCustomers()]); });
</script>
