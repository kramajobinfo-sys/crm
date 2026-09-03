<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">

    <!-- Header -->
    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('customers.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('customers.subtitle') }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="load">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('customers.refresh') }}
        </button>
        <button v-if="can('customers.create')" class="btn-primary text-xs px-3 py-1.5" @click="openCreate">
          <Plus :size="12" /> {{ $t('customers.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2.5 mb-3">
      <button
        v-for="s in statTiles" :key="s.key"
        class="card p-3 text-left transition-colors"
        :class="filters.status === s.status ? 'ring-1 ring-primary-500' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
        @click="applyStatus(s.status)"
      >
        <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">{{ $t(s.label) }}</div>
        <div class="text-lg font-semibold text-ink dark:text-ink-dark mt-0.5">{{ stats[s.key] ?? 0 }}</div>
      </button>
    </div>

    <!-- Filters -->
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <input v-model="filters.q" class="input text-sm w-56" :placeholder="$t('customers.search')" @keyup.enter="load" />
      <select v-model="filters.type" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('customers.all_types') }}</option>
        <option v-for="t in meta.types" :key="t" :value="t">{{ $t(`customers.type.${t}`) }}</option>
      </select>
      <select v-model="filters.group_id" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('customers.all_groups') }}</option>
        <option v-for="g in meta.groups" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
      <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted ml-1">
        <input type="checkbox" class="rounded border-slate-300"
               :checked="filters.owner_id === 'me'"
               @change="filters.owner_id = $event.target.checked ? 'me' : ''; load()" />
        {{ $t('customers.mine_only') }}
      </label>
      <button class="btn-secondary text-xs px-2.5 py-1 ml-auto" @click="resetFilters">{{ $t('customers.reset') }}</button>
    </div>

    <div class="flex gap-3">
      <!-- List -->
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('customers.empty') }}</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-ink-subtle dark:text-ink-dark-subtle bg-slate-50 dark:bg-surface-dark-subtle">
              <tr>
                <th class="text-left font-medium px-3 py-2">{{ $t('customers.col.number') }}</th>
                <th class="text-left font-medium px-3 py-2">{{ $t('customers.col.name') }}</th>
                <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('customers.col.group') }}</th>
                <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">{{ $t('customers.col.owner') }}</th>
                <th class="text-right font-medium px-3 py-2 hidden lg:table-cell">{{ $t('customers.col.credit') }}</th>
                <th class="text-left font-medium px-3 py-2">{{ $t('customers.col.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in rows" :key="r.id"
                class="border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
                :class="selected?.id === r.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
                @click="openDetail(r.id)"
              >
                <td class="px-3 py-2 font-mono text-xs text-ink-muted dark:text-ink-dark-muted">{{ r.customer_no }}</td>
                <td class="px-3 py-2">
                  <div class="text-ink dark:text-ink-dark truncate max-w-[16rem]">{{ r.name }}</div>
                  <div class="text-[11px] text-ink-subtle truncate max-w-[16rem]">{{ r.email || r.phone }}</div>
                </td>
                <td class="px-3 py-2 hidden md:table-cell text-ink-muted dark:text-ink-dark-muted">{{ r.group?.name || '—' }}</td>
                <td class="px-3 py-2 hidden lg:table-cell text-ink-muted dark:text-ink-dark-muted">{{ r.owner?.name || '—' }}</td>
                <td class="px-3 py-2 hidden lg:table-cell text-right tabular-nums text-ink-muted dark:text-ink-dark-muted">
                  {{ money(r.credit_limit, r.currency) }}
                </td>
                <td class="px-3 py-2">
                  <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(r.status)">
                    {{ $t(`customers.status.${r.status}`) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
          <span class="text-ink-subtle">
            {{ $t('customers.showing', { from: pagination.from, to: pagination.to, total: pagination.total }) }}
          </span>
          <div class="flex gap-1">
            <button class="btn-secondary text-xs px-2 py-0.5" :disabled="page <= 1" @click="page--; load()">‹</button>
            <button class="btn-secondary text-xs px-2 py-0.5" :disabled="page >= pagination.last_page" @click="page++; load()">›</button>
          </div>
        </div>
      </div>

      <!-- Detail drawer -->
      <div v-if="selected" class="card w-full sm:w-96 shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-16rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2 shrink-0">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark truncate">{{ selected.name }}</div>
            <div class="text-[11px] text-ink-subtle font-mono">{{ selected.customer_no }}</div>
          </div>
          <button v-if="can('customers.update')" class="btn-secondary text-[11px] px-2 py-0.5" @click="openEdit(selected)">
            {{ $t('customers.edit') }}
          </button>
          <button v-if="can('activities.create')" class="btn-secondary text-[10px] px-2 py-0.5" @click="addFollowUp(selected)">{{ $t('activities.quick_follow_up') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <dl class="grid grid-cols-3 gap-y-1.5">
            <dt class="text-ink-subtle col-span-1">{{ $t('customers.col.group') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.group?.name || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('customers.col.owner') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.owner?.name || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('customers.terms') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">
              {{ selected.effective_payment_terms != null ? $t('customers.days', { n: selected.effective_payment_terms }) : '—' }}
            </dd>
            <dt class="text-ink-subtle">{{ $t('customers.col.credit') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark tabular-nums">{{ money(selected.credit_limit, selected.currency) }}</dd>
            <dt class="text-ink-subtle">{{ $t('customers.tax_id') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark font-mono">{{ selected.tax_id || '—' }}</dd>
          </dl>

          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('customers.contacts') }}</div>
            <div v-if="!selected.contacts?.length" class="text-ink-subtle">{{ $t('customers.no_contacts') }}</div>
            <div v-for="c in selected.contacts" :key="c.id" class="flex items-center gap-2 py-1">
              <span class="text-ink dark:text-ink-dark truncate">{{ c.name }}</span>
              <span v-if="c.is_primary" class="text-[9px] px-1 py-0.5 rounded bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300">
                {{ $t('customers.primary') }}
              </span>
              <span class="text-ink-subtle truncate ml-auto">{{ c.title }}</span>
              <span class="text-[9px] px-1 py-0.5 rounded" :class="c.portal_enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-ink-subtle dark:bg-slate-700'">
                {{ c.portal_enabled ? $t('contacts.enabled') : $t('contacts.disabled') }}
              </span>
              <button v-if="can('customers.update')" class="text-[10px] text-primary-600 hover:underline shrink-0" @click="openPortal(c)">
                {{ $t('contacts.portal_manage') }}
              </button>
            </div>
          </div>

          <div v-if="selected.addresses?.length">
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('customers.addresses') }}</div>
            <div v-for="a in selected.addresses" :key="a.id" class="text-ink-muted dark:text-ink-dark-muted py-0.5">
              <span class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 mr-1">{{ $t(`customers.addr.${a.type}`) }}</span>
              {{ a.one_line }}
            </div>
          </div>

          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('customers.timeline') }}</div>
            <div v-if="can('customers.update')" class="flex gap-1.5 mb-2">
              <input v-model="noteDraft" class="input text-xs" :placeholder="$t('customers.add_note')" @keyup.enter="submitNote" />
              <button class="btn-primary text-[11px] px-2" :disabled="!noteDraft.trim() || savingNote" @click="submitNote">
                <Send :size="11" />
              </button>
            </div>
            <div v-for="t in selected.timeline" :key="t.id" class="py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <div class="flex items-center gap-1.5">
                <span class="text-[9px] px-1 py-0.5 rounded" :class="timelineClass(t.type)">{{ $t(`customers.tl.${t.type}`) }}</span>
                <span class="text-ink dark:text-ink-dark truncate">{{ t.title }}</span>
                <span class="text-ink-subtle ml-auto shrink-0">{{ t.occurred_human }}</span>
              </div>
              <div v-if="t.body" class="text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ t.body }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / edit modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">
          {{ form.id ? $t('customers.edit_title') : $t('customers.new_title') }}
        </div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2">
            <label class="label">{{ $t('customers.col.name') }} *</label>
            <input v-model="form.data.name" class="input text-sm" />
            <p v-if="form.errors.name" class="text-[11px] text-red-500 mt-0.5">{{ form.errors.name[0] }}</p>
          </div>
          <div>
            <label class="label">{{ $t('customers.col.type') }}</label>
            <select v-model="form.data.type" class="input text-sm">
              <option v-for="t in meta.types" :key="t" :value="t">{{ $t(`customers.type.${t}`) }}</option>
            </select>
          </div>
          <div>
            <label class="label">{{ $t('customers.col.group') }}</label>
            <select v-model="form.data.group_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="g in meta.groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
          </div>
          <div v-if="meta.price_books.length">
            <label class="label">{{ $t('customers.price_book') }}</label>
            <select v-model="form.data.price_book_id" class="input text-sm">
              <option :value="null">{{ $t('customers.price_book_default') }}</option>
              <option v-for="b in meta.price_books" :key="b.id" :value="b.id">{{ b.name }} ({{ b.currency }})</option>
            </select>
          </div>
          <div>
            <label class="label">{{ $t('customers.email') }}</label>
            <input v-model="form.data.email" class="input text-sm" />
            <p v-if="form.errors.email" class="text-[11px] text-red-500 mt-0.5">{{ form.errors.email[0] }}</p>
          </div>
          <div>
            <label class="label">{{ $t('customers.phone') }}</label>
            <input v-model="form.data.phone" class="input text-sm" />
          </div>
          <div>
            <label class="label">{{ $t('customers.col.credit') }}</label>
            <input v-model="form.data.credit_limit" type="number" min="0" class="input text-sm" />
          </div>
          <div>
            <label class="label">{{ $t('customers.col.status') }}</label>
            <select v-model="form.data.status" class="input text-sm">
              <option v-for="s in meta.statuses" :key="s" :value="s">{{ $t(`customers.status.${s}`) }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="label">{{ $t('customers.notes') }}</label>
            <textarea v-model="form.data.notes" rows="2" class="input text-sm resize-none" />
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="form.open = false">{{ $t('customers.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="form.saving" @click="submitForm">
            {{ form.saving ? $t('customers.saving') : $t('customers.save') }}
          </button>
        </div>
      </div>
    </div>

    <DuplicateWarningModal
      :open="duplicateGuard.state.open"
      :candidates="duplicateGuard.state.candidates"
      :primary-id="can('customers.update') && can('customers.delete') ? form.id : null"
      @cancel="duplicateGuard.cancel"
      @proceed="duplicateGuard.proceed"
      @merge="startMerge"
    />
    <RecordMergeModal :state="mergeGuard.state" @close="mergeGuard.close" @confirm="mergeGuard.confirm" />
    <PortalAccessModal :open="portal.open" :contact="portal.contact" @close="portal.open = false" @updated="portalUpdated" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/customers';
import DuplicateWarningModal from '@/components/crm/DuplicateWarningModal.vue';
import RecordMergeModal from '@/components/crm/RecordMergeModal.vue';
import PortalAccessModal from '@/components/crm/PortalAccessModal.vue';
import { useDuplicateGuard } from '@/composables/useDuplicateGuard';
import { useRecordMerge } from '@/composables/useRecordMerge';
import { RefreshCw, Plus, X, Send } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const can = (p) => auth.can(p);

function addFollowUp(account) {
  router.push({ name: 'activities', query: { new: 'task', related_type: 'customer', related_id: account.id } });
}

const statTiles = [
  { key: 'total',   status: 'all',      label: 'customers.stat.total' },
  { key: 'active',  status: 'active',   label: 'customers.stat.active' },
  { key: 'on_hold', status: 'on_hold',  label: 'customers.stat.on_hold' },
  { key: 'blocked', status: 'blocked',  label: 'customers.stat.blocked' },
  { key: 'new_this_month', status: 'all', label: 'customers.stat.new_this_month' },
];

const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ groups: [], types: [], statuses: [], price_books: [] });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const selected   = ref(null);
const loading    = ref(false);
const page       = ref(1);
const noteDraft  = ref('');
const savingNote = ref(false);
const filters    = reactive({ q: '', status: 'all', type: '', group_id: '', owner_id: '' });
const form = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });
const portal = reactive({ open: false, contact: null });
const duplicateGuard = useDuplicateGuard();
const mergeGuard = useRecordMerge(async () => {
  form.open = false;
  selected.value = null;
  toast.success(t('duplicates.merged'));
  await load();
});

function startMerge(candidate) {
  duplicateGuard.cancel();
  mergeGuard.open('account', form.id, candidate);
}

function openPortal(contact) {
  portal.contact = { ...contact, customer_id: selected.value.id };
  portal.open = true;
}

function portalUpdated(contact) {
  portal.open = false;
  portal.contact = null;
  if (selected.value?.contacts) {
    const index = selected.value.contacts.findIndex((item) => item.id === contact.id);
    if (index >= 0) selected.value.contacts[index] = { ...selected.value.contacts[index], ...contact };
  }
  toast.success(t('contacts.portal_updated'));
}

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    Object.entries(filters).forEach(([k, v]) => { if (v !== '' && v !== null) params[k] = v; });
    const { data } = await api.list(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || {});
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

async function openDetail(id) {
  try {
    const { data } = await api.show(id);
    selected.value = data.data;
  } catch { /* interceptor surfaces the error */ }
}

function applyStatus(status) { filters.status = status; page.value = 1; load(); }
function resetFilters() {
  Object.assign(filters, { q: '', status: 'all', type: '', group_id: '', owner_id: '' });
  page.value = 1; load();
}

function openCreate() {
  form.id = null; form.errors = {};
  form.data = {
    name: '', type: 'company', group_id: null, price_book_id: null, email: '', phone: '',
    credit_limit: 0, status: 'active', notes: '',
  };
  form.open = true;
}
function openEdit(c) {
  form.id = c.id; form.errors = {};
  form.data = {
    name: c.name, type: c.type, group_id: c.group?.id ?? null, price_book_id: c.price_book_id ?? null, email: c.email ?? '',
    phone: c.phone ?? '', credit_limit: c.credit_limit ?? 0, status: c.status, notes: c.notes ?? '',
  };
  form.open = true;
}

async function submitForm(force = false) {
  form.saving = true; form.errors = {};
  try {
    const payload = { ...form.data };
    if (!payload.email) delete payload.email;      // '' would fail the email rule
    if (!payload.phone) delete payload.phone;
    if (!force) {
      const clear = await duplicateGuard.check('account', payload, form.id, () => submitForm(true));
      if (!clear) { form.saving = false; return; }
    }
    const { data } = form.id ? await api.update(form.id, payload) : await api.create(payload);
    toast.success(form.id ? t('customers.updated') : t('customers.created'));
    form.open = false;
    await Promise.all([load(), loadAux()]);
    if (selected.value?.id === data.data.id) selected.value = data.data;
  } catch (e) {
    if (e.response?.status === 422) form.errors = e.response.data?.errors || {};
  } finally { form.saving = false; }
}

async function submitNote() {
  const body = noteDraft.value.trim();
  if (!body || !selected.value) return;
  savingNote.value = true;
  try {
    await api.addNote(selected.value.id, body, 'note');
    noteDraft.value = '';
    await openDetail(selected.value.id);
  } catch { /* interceptor surfaces the error */ }
  finally { savingNote.value = false; }
}

const money = (v, ccy) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { style: 'currency', currency: ccy || 'USD', maximumFractionDigits: 0 }).format(v);

const statusClass = (s) => ({
  active:   'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  on_hold:  'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  blocked:  'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  archived: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
}[s] || 'bg-slate-100 text-slate-700');

const timelineClass = (ty) => ({
  note:          'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  call:          'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  email:         'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  meeting:       'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  status_change: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  system:        'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
}[ty] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux()]); });
</script>
