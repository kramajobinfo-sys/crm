<template>
  <div class="page">

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('activities.title') }}</h1>
        <p class="page-sub">{{ $t('activities.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('activities.refresh') }}
        </button>
        <button v-if="can('activities.create')" class="btn-primary btn-sm" @click="openCreate(tab === 'feed' ? 'task' : tab.slice(0, -1))">
          <Plus :size="12" /> {{ $t('activities.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="stat">
        <div class="stat-label">{{ $t(s.label) }}</div>
        <div class="text-lg font-semibold mt-0.5" :class="s.key === 'overdue' && stats.overdue ? 'text-red-600' : 'text-ink dark:text-ink-dark'">
          {{ stats[s.key] ?? 0 }}
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-3 border-b border-slate-200 dark:border-slate-700">
      <button v-for="tb in tabs" :key="tb" class="px-3 py-1.5 text-xs -mb-px border-b-2"
              :class="tab === tb ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
              @click="tab = tb; load()">
        {{ $t(`activities.tab.${tb}`) }}
      </button>
    </div>

    <div class="flex flex-col lg:flex-row gap-3 items-start">
      <div class="flex-1 min-w-0">
        <!-- Filters (per tab) -->
        <div v-if="tab !== 'feed'" class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
          <input v-model="filters.q" class="input text-sm w-52" :placeholder="$t('activities.search')" @keyup.enter="load" />
          <template v-if="tab === 'tasks'">
            <select v-model="filters.status" class="input text-sm w-auto" @change="load">
              <option value="all">{{ $t('activities.all_statuses') }}</option>
              <option value="open">{{ $t('activities.f.open') }}</option>
              <option value="in_progress">{{ $t('activities.st.in_progress') }}</option>
              <option value="done">{{ $t('activities.st.done') }}</option>
            </select>
            <select v-model="filters.due" class="input text-sm w-auto" @change="load">
              <option value="">{{ $t('activities.any_due') }}</option>
              <option value="overdue">{{ $t('activities.f.overdue') }}</option>
              <option value="today">{{ $t('activities.f.today') }}</option>
            </select>
          </template>
          <template v-if="tab === 'meetings'">
            <select v-model="filters.when" class="input text-sm w-auto" @change="load">
              <option value="">{{ $t('activities.all_time') }}</option>
              <option value="upcoming">{{ $t('activities.f.upcoming') }}</option>
              <option value="past">{{ $t('activities.f.past') }}</option>
            </select>
          </template>
          <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted ml-1">
            <input type="checkbox" class="rounded border-slate-300" v-model="filters.mine" @change="load" />
            {{ $t('activities.mine_only') }}
          </label>
        </div>

        <!-- FEED -->
        <div v-if="tab === 'feed'" class="card overflow-hidden">
          <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
          <div v-else-if="!feed.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('activities.feed_empty') }}</div>
          <div v-for="f in feed" :key="`${f.kind}-${f.id}`" class="flex items-center gap-2.5 px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <component :is="kindIcon(f.kind)" :size="14" class="shrink-0 text-ink-subtle" />
            <div class="min-w-0 flex-1">
              <div class="text-sm text-ink dark:text-ink-dark truncate">{{ f.title }}</div>
              <div class="text-[11px] text-ink-subtle">{{ $t(`activities.tab.${f.kind}s`) }}<span v-if="f.who"> · {{ f.who }}</span></div>
            </div>
            <span v-if="f.overdue" class="text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700">{{ $t('activities.f.overdue') }}</span>
            <span class="text-[11px] text-ink-subtle shrink-0">{{ f.when_human }}</span>
          </div>
        </div>

        <!-- TASKS -->
        <div v-else-if="tab === 'tasks'" class="card overflow-hidden">
          <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
          <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('activities.empty') }}</div>
          <div v-for="t in rows" :key="t.id" class="flex items-center gap-2.5 px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <button v-if="can('activities.update')" class="shrink-0" :disabled="!t.is_open" @click="complete(t)">
              <CheckCircle2 v-if="!t.is_open" :size="16" class="text-emerald-500" />
              <Circle v-else :size="16" class="text-ink-subtle hover:text-emerald-500" />
            </button>
            <div class="min-w-0 flex-1">
              <div class="text-sm truncate" :class="t.is_open ? 'text-ink dark:text-ink-dark' : 'text-ink-subtle line-through'">{{ t.title }}</div>
              <div class="text-[11px] text-ink-subtle flex items-center gap-1.5">
                <span v-if="t.related" class="text-primary-600">{{ t.related.label }}</span>
                <span v-if="t.assignee">· {{ t.assignee.name }}</span>
              </div>
            </div>
            <span class="text-[10px] px-1.5 py-0.5 rounded" :class="priorityClass(t.priority)">{{ $t(`activities.pr.${t.priority}`) }}</span>
            <span class="text-[11px] shrink-0 w-24 text-right" :class="t.is_overdue ? 'text-red-600' : 'text-ink-subtle'">{{ t.due_human || '—' }}</span>
            <button v-if="can('activities.delete')" class="p-1 text-ink-subtle hover:text-red-500" @click="removeItem('task', t.id)"><Trash2 :size="12" /></button>
          </div>
        </div>

        <!-- MEETINGS -->
        <div v-else-if="tab === 'meetings'" class="card overflow-hidden">
          <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
          <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('activities.empty') }}</div>
          <div v-for="m in rows" :key="m.id" class="px-3 py-2.5 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <div class="flex items-center gap-2">
              <CalendarClock :size="14" class="shrink-0 text-ink-subtle" />
              <div class="text-sm text-ink dark:text-ink-dark truncate flex-1">{{ m.title }}</div>
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="meetingStatusClass(m.status)">{{ $t(`activities.ms.${m.status}`) }}</span>
              <button v-if="can('activities.delete')" class="p-1 text-ink-subtle hover:text-red-500" @click="removeItem('meeting', m.id)"><Trash2 :size="12" /></button>
            </div>
            <div class="text-[11px] text-ink-subtle mt-0.5 flex items-center gap-2 flex-wrap ml-6">
              <span>{{ formatDate(m.start_at) }}</span>
              <span v-if="m.location">· {{ m.location }}</span>
              <span v-if="m.related" class="text-primary-600">· {{ m.related.label }}</span>
              <span v-if="m.participants?.length">· {{ m.participants.length }} {{ $t('activities.participants') }}</span>
            </div>
          </div>
        </div>

        <!-- CALLS -->
        <div v-else class="card overflow-hidden">
          <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
          <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('activities.empty') }}</div>
          <div v-for="c in rows" :key="c.id" class="flex items-center gap-2.5 px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <component :is="c.direction === 'inbound' ? PhoneIncoming : PhoneOutgoing" :size="14"
                       class="shrink-0" :class="c.status === 'missed' ? 'text-red-500' : 'text-ink-subtle'" />
            <div class="min-w-0 flex-1">
              <div class="text-sm text-ink dark:text-ink-dark truncate">{{ c.subject }}</div>
              <div class="text-[11px] text-ink-subtle">
                <span v-if="c.related" class="text-primary-600">{{ c.related.label }}</span>
                <span v-if="c.duration_human"> · {{ c.duration_human }}</span>
                <span v-if="c.user"> · {{ c.user.name }}</span>
              </div>
            </div>
            <span class="text-[10px] px-1.5 py-0.5 rounded" :class="callStatusClass(c.status)">{{ $t(`activities.cs.${c.status}`) }}</span>
            <span class="text-[11px] text-ink-subtle shrink-0 w-24 text-right">{{ c.occurred_human || formatDate(c.scheduled_at) || '—' }}</span>
            <button v-if="can('activities.delete')" class="p-1 text-ink-subtle hover:text-red-500" @click="removeItem('call', c.id)"><Trash2 :size="12" /></button>
          </div>
        </div>

        <div v-if="tab !== 'feed' && pagination.last_page > 1" class="flex items-center justify-between mt-2 text-xs">
          <span class="text-ink-subtle">{{ $t('activities.showing', { from: pagination.from, to: pagination.to, total: pagination.total }) }}</span>
          <div class="flex gap-1">
            <button class="btn-secondary btn-xs" :disabled="page <= 1" @click="page--; load()">‹</button>
            <button class="btn-secondary btn-xs" :disabled="page >= pagination.last_page" @click="page++; load()">›</button>
          </div>
        </div>
      </div>

      <!-- Reminders panel -->
      <div class="card w-full lg:w-72 shrink-0">
        <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <Bell :size="14" class="text-ink-subtle" />
          <span class="text-xs font-medium text-ink dark:text-ink-dark flex-1">{{ $t('activities.reminders') }}</span>
          <button v-if="can('activities.create')" class="p-0.5 text-primary-600 hover:text-primary-700" :title="$t('activities.new_reminder')" @click="openCreate('reminder')">
            <Plus :size="13" />
          </button>
        </div>
        <div v-if="!reminders.length" class="text-[11px] text-ink-subtle text-center py-6">{{ $t('activities.no_reminders') }}</div>
        <div v-for="r in reminders" :key="r.id" class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
          <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
              <div class="text-xs text-ink dark:text-ink-dark">{{ r.title }}</div>
              <div class="text-[11px] text-ink-subtle">{{ r.remind_human }}</div>
              <div v-if="r.related" class="text-[10px] text-primary-600 truncate">{{ r.related.label }}</div>
            </div>
            <button v-if="can('activities.update')" class="p-0.5 text-ink-subtle hover:text-emerald-500" :title="$t('activities.dismiss')" @click="dismissReminder(r.id)"><Check :size="13" /></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t(`activities.new_${form.type}`) }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2">
            <label class="label">{{ form.type === 'call' ? $t('activities.subject') : $t('activities.field_title') }} *</label>
            <input v-model="form.data.title" class="input text-sm" />
            <p v-if="form.errors.title || form.errors.subject" class="text-[11px] text-red-500 mt-0.5">{{ (form.errors.title || form.errors.subject)[0] }}</p>
          </div>

          <!-- Task fields -->
          <template v-if="form.type === 'task'">
            <div><label class="label">{{ $t('activities.priority') }}</label>
              <select v-model="form.data.priority" class="input text-sm">
                <option v-for="p in meta.task_priorities" :key="p" :value="p">{{ $t(`activities.pr.${p}`) }}</option>
              </select>
            </div>
            <div><label class="label">{{ $t('activities.due') }}</label><input v-model="form.data.due_at" type="datetime-local" class="input text-sm" /></div>
            <div><label class="label">Repeat</label>
              <select v-model="form.data.recurrence" class="input text-sm capitalize">
                <option :value="null">Does not repeat</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>
            <div v-if="form.data.recurrence"><label class="label">Repeat until</label>
              <input v-model="form.data.recurrence_until" type="date" class="input text-sm" />
            </div>
          </template>

          <!-- Meeting fields -->
          <template v-if="form.type === 'meeting'">
            <div><label class="label">{{ $t('activities.start') }} *</label><input v-model="form.data.start_at" type="datetime-local" class="input text-sm" /></div>
            <div><label class="label">{{ $t('activities.end') }}</label><input v-model="form.data.end_at" type="datetime-local" class="input text-sm" /></div>
            <div class="col-span-2"><label class="label">{{ $t('activities.location') }}</label><input v-model="form.data.location" class="input text-sm" /></div>
          </template>

          <!-- Call fields -->
          <template v-if="form.type === 'call'">
            <div><label class="label">{{ $t('activities.direction') }}</label>
              <select v-model="form.data.direction" class="input text-sm">
                <option v-for="d in meta.call_directions" :key="d" :value="d">{{ $t(`activities.dir.${d}`) }}</option>
              </select>
            </div>
            <div><label class="label">{{ $t('activities.duration_min') }}</label><input v-model.number="form.durationMin" type="number" min="0" class="input text-sm" /></div>
          </template>

          <!-- Reminder fields -->
          <template v-if="form.type === 'reminder'">
            <div><label class="label">{{ $t('activities.remind_at') }} *</label><input v-model="form.data.remind_at" type="datetime-local" class="input text-sm" /></div>
            <div><label class="label">{{ $t('activities.channel') }}</label>
              <select v-model="form.data.channel" class="input text-sm">
                <option value="in_app">{{ $t('activities.channel_in_app') }}</option>
                <option value="email">{{ $t('activities.channel_email') }}</option>
              </select>
            </div>
            <p v-if="form.errors.remind_at" class="col-span-2 text-[11px] text-red-500">{{ form.errors.remind_at[0] }}</p>
          </template>

          <!-- Related link (all types) -->
          <div><label class="label">{{ $t('activities.link_to') }}</label>
            <select v-model="form.data.related_type" class="input text-sm" @change="form.data.related_id = null">
              <option :value="null">—</option>
              <option v-for="rt in meta.related_types" :key="rt" :value="rt">{{ $t(`activities.rt.${rt}`) }}</option>
            </select>
          </div>
          <div v-if="form.data.related_type">
            <label class="label">{{ $t(`activities.rt.${form.data.related_type}`) }}</label>
            <select v-model="form.data.related_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="o in relatedOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('activities.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving" @click="submitForm">{{ form.saving ? $t('activities.saving') : $t('activities.save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/activities';
import dealApi from '@/services/deals';
import leadApi from '@/services/leads';
import customerApi from '@/services/customers';
import contactApi from '@/services/contacts';
import salesApi from '@/services/sales';
import {
  RefreshCw, Plus, Trash2, Bell, Check, CheckCircle2, Circle, CalendarClock,
  CheckSquare, Phone, PhoneIncoming, PhoneOutgoing,
} from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const can = (p) => auth.can(p);

const tabs = ['feed', 'tasks', 'meetings', 'calls'];
const statTiles = [
  { key: 'my_open_tasks',       label: 'activities.stat.open_tasks' },
  { key: 'overdue',             label: 'activities.stat.overdue' },
  { key: 'due_today',           label: 'activities.stat.today' },
  { key: 'upcoming_meetings',   label: 'activities.stat.meetings' },
  { key: 'calls_logged',        label: 'activities.stat.calls' },
  { key: 'completed_this_week', label: 'activities.stat.completed' },
];

const tab        = ref('feed');
const rows       = ref([]);
const feed       = ref([]);
const reminders  = ref([]);
const stats      = reactive({});
const meta       = reactive({ task_priorities: [], call_directions: [], related_types: [] });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const loading    = ref(false);
const page       = ref(1);
const filters    = reactive({ q: '', status: 'all', due: '', when: '', mine: true });
const form = reactive({ open: false, type: 'task', saving: false, durationMin: 0, data: {}, errors: {} });

const relatedCache = reactive({ deal: [], lead: [], customer: [], contact: [], quotation: [] });
const relatedOptions = computed(() => relatedCache[form.data.related_type] || []);

watch(() => form.data.related_type, (type) => { if (type) ensureRelated(type); });

async function load() {
  loading.value = true;
  try {
    if (tab.value === 'feed') {
      const { data } = await api.feed({ scope: filters.mine ? 'me' : 'all' });
      feed.value = data.data || [];
    } else {
      const params = { page: page.value, per_page: 25 };
      if (filters.q) params.q = filters.q;
      if (tab.value === 'tasks') {
        if (filters.status !== 'all') params.status = filters.status;
        if (filters.due) params.due = filters.due;
        if (filters.mine) params.assigned_to = 'me';
      } else if (tab.value === 'meetings') {
        if (filters.when) params.when = filters.when;
        if (filters.mine) params.organizer_id = 'me';
      } else if (tab.value === 'calls') {
        if (filters.mine) params.user_id = 'me';
      }
      const fn = { tasks: api.tasks, meetings: api.meetings, calls: api.calls }[tab.value];
      const { data } = await fn(params);
      rows.value = data.data || [];
      Object.assign(pagination, data.meta || {});
    }
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}

async function loadAux() {
  try {
    const [s, m, r] = await Promise.all([api.stats(), api.meta(), api.reminders()]);
    Object.assign(stats, s.data.data || {});
    Object.assign(meta, m.data.data || {});
    reminders.value = r.data.data || [];
  } catch { /* non-critical */ }
}

function reload() { page.value = 1; return Promise.all([load(), loadAux()]); }

async function ensureRelated(type) {
  if (relatedCache[type].length) return;
  try {
    const apiByType = { deal: dealApi, lead: leadApi, customer: customerApi, contact: contactApi, quotation: { list: salesApi.quotations } }[type];
    const { data } = await apiByType.list({ per_page: 100 });
    relatedCache[type] = (data.data || []).map((x) => ({ id: x.id, label: x.title || x.name || x.quote_no || `#${x.id}` }));
  } catch { /* non-critical */ }
}

async function complete(task) {
  try {
    await api.completeTask(task.id);
    await reload();
  } catch { /* interceptor surfaces the error */ }
}

async function removeItem(type, id) {
  const fn = { task: api.removeTask, meeting: api.removeMeeting, call: api.removeCall }[type];
  try { await fn(id); await reload(); } catch { /* interceptor surfaces the error */ }
}

async function dismissReminder(id) {
  try { await api.completeReminder(id); reminders.value = reminders.value.filter((r) => r.id !== id); } catch { /* noop */ }
}

function openCreate(type, relatedType = null, relatedId = null) {
  form.type = type; form.errors = {}; form.durationMin = 0;
  form.data = {
    title: '', priority: 'medium', direction: 'outbound',
    due_at: '', start_at: '', end_at: '', location: '', remind_at: '', channel: 'in_app',
    recurrence: null, recurrence_until: '',
    related_type: relatedType, related_id: relatedId ? Number(relatedId) : null,
  };
  form.open = true;
}

async function submitForm() {
  form.saving = true; form.errors = {};
  try {
    const d = form.data;
    let payload = { related_type: d.related_type || undefined, related_id: d.related_id || undefined };
    let fn;
    if (form.type === 'task') {
      payload = { ...payload, title: d.title, priority: d.priority, due_at: toIso(d.due_at),
        recurrence: d.recurrence || undefined, recurrence_until: d.recurrence_until || undefined };
      fn = api.createTask;
    } else if (form.type === 'meeting') {
      payload = { ...payload, title: d.title, start_at: toIso(d.start_at), end_at: toIso(d.end_at), location: d.location || undefined };
      fn = api.createMeeting;
    } else if (form.type === 'call') {
      payload = { ...payload, subject: d.title, direction: d.direction, status: 'completed', duration_seconds: (form.durationMin || 0) * 60 };
      fn = api.createCall;
    } else {
      payload = { ...payload, title: d.title, remind_at: toIso(d.remind_at), channel: d.channel };
      fn = api.createReminder;
    }
    await fn(payload);
    toast.success(t('activities.created'));
    form.open = false;
    if (form.type !== 'reminder' && tab.value === `${form.type}s`) await reload(); else await loadAux();
  } catch (e) {
    if (e.response?.status === 422) form.errors = e.response.data?.errors || {};
  } finally { form.saving = false; }
}

const kindIcon = (k) => ({ task: CheckSquare, meeting: CalendarClock, call: Phone }[k] || CheckSquare);
const toIso = (localDateTime) => localDateTime ? new Date(localDateTime).toISOString() : undefined;
const formatDate = (iso) => iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';

const priorityClass = (p) => ({
  urgent: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  high:   'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  medium: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  low:    'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
}[p] || 'bg-slate-100 text-slate-700');

const meetingStatusClass = (s) => ({
  scheduled: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
}[s] || 'bg-slate-100 text-slate-700');

const callStatusClass = (s) => ({
  completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  scheduled: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  missed:    'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => {
  await Promise.all([load(), loadAux()]);
  if (route.query.new && can('activities.create')) {
    openCreate(route.query.new, route.query.related_type || null, route.query.related_id || null);
    await router.replace({ name: 'activities' });
  }
});
</script>
