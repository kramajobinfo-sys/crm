<template>
  <div class="page">

    <!-- Header -->
    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('customers.title') }}</h1>
        <p class="page-sub">{{ $t('customers.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="load">
          <RefreshCw :size="14" :class="loading && 'animate-spin'" /> {{ $t('customers.refresh') }}
        </button>
        <button v-if="can('customers.create')" class="btn-primary btn-sm" @click="openCreate">
          <Plus :size="14" /> {{ $t('customers.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
      <button
        v-for="s in statTiles" :key="s.key"
        class="stat"
        :class="[!s.static && 'stat-clickable', !s.static && filters.status === s.status && 'stat-active', s.static && 'cursor-default']"
        @click="!s.static && applyStatus(s.status)"
      >
        <span class="stat-label">{{ $t(s.label) }}</span>
        <span class="stat-value">{{ stats[s.key] ?? 0 }}</span>
      </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="toolbar">
        <input v-model="filters.q" class="input input-sm w-56" :placeholder="$t('customers.search')" @keyup.enter="load" />
        <select v-model="filters.type" class="input input-sm w-auto" @change="load">
          <option value="">{{ $t('customers.all_types') }}</option>
          <option v-for="t in meta.types" :key="t" :value="t">{{ $t(`customers.type.${t}`) }}</option>
        </select>
        <select v-model="filters.group_id" class="input input-sm w-auto" @change="load">
          <option value="">{{ $t('customers.all_groups') }}</option>
          <option v-for="g in meta.groups" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
        <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted ml-1">
          <input type="checkbox" class="rounded border-slate-300"
                 :checked="filters.owner_id === 'me'"
                 @change="filters.owner_id = $event.target.checked ? 'me' : ''; load()" />
          {{ $t('customers.mine_only') }}
        </label>
        <button class="btn-ghost btn-sm ml-auto" @click="resetFilters">{{ $t('customers.reset') }}</button>
      </div>
    </div>

    <div class="flex gap-3">
      <!-- List -->
      <div class="panel flex-1 min-w-0">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="empty">{{ $t('customers.empty') }}</div>
        <div v-else class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('customers.col.number') }}</th>
                <th>{{ $t('customers.col.name') }}</th>
                <th class="hidden md:table-cell">{{ $t('customers.col.group') }}</th>
                <th class="hidden lg:table-cell">{{ $t('customers.col.owner') }}</th>
                <th class="th-num hidden lg:table-cell">{{ $t('customers.col.credit') }}</th>
                <th>{{ $t('customers.col.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in rows" :key="r.id"
                class="cursor-pointer"
                :class="selected?.id === r.id && 'is-selected'"
                @click="openDetail(r.id)"
              >
                <td class="font-mono text-xs text-ink-muted dark:text-ink-dark-muted">{{ r.customer_no }}</td>
                <td>
                  <div class="text-ink dark:text-ink-dark truncate max-w-[16rem]">{{ r.name }}</div>
                  <div class="text-[11px] text-ink-subtle truncate max-w-[16rem]">{{ r.email || r.phone }}</div>
                </td>
                <td class="hidden md:table-cell text-ink-muted dark:text-ink-dark-muted">{{ r.group?.name || '—' }}</td>
                <td class="hidden lg:table-cell text-ink-muted dark:text-ink-dark-muted">{{ r.owner?.name || '—' }}</td>
                <td class="td-num hidden lg:table-cell text-ink-muted dark:text-ink-dark-muted">
                  {{ money(r.credit_limit, r.currency) }}
                </td>
                <td>
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
            <button class="btn-secondary btn-xs" :disabled="page <= 1" @click="page--; load()">‹</button>
            <button class="btn-secondary btn-xs" :disabled="page >= pagination.last_page" @click="page++; load()">›</button>
          </div>
        </div>
      </div>

      <!-- Detail modal (record popup) -->
      <div v-if="selected" class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="selected = null">
      <div class="card w-full max-w-2xl my-6 flex flex-col overflow-hidden max-h-[calc(100vh-3rem)]">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2 shrink-0">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark truncate">{{ selected.name }}</div>
            <div class="text-[11px] text-ink-subtle font-mono">{{ selected.customer_no }}</div>
          </div>
          <button v-if="can('quotations.create')" class="btn-secondary btn-xs" @click="newQuote">New quote</button>
          <button v-if="can('customers.update')" class="btn-secondary btn-xs" @click="openEdit(selected)">{{ $t('customers.edit') }}</button>
          <button v-if="can('activities.create')" class="btn-secondary btn-xs" @click="addFollowUp(selected)">{{ $t('activities.quick_follow_up') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <!-- Tabs -->
        <div class="px-4 pt-2 flex gap-1.5 border-b border-slate-200 dark:border-slate-700 shrink-0 overflow-x-auto">
          <button v-for="tb in detailTabs" :key="tb.key" @click="detailTab = tb.key"
                  class="px-3 py-2 -mb-px border-b-2 whitespace-nowrap text-xs flex items-center gap-1.5"
                  :class="detailTab === tb.key ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-subtle hover:text-ink'">
            {{ tb.label }}<span v-if="tb.count" class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-ink-muted">{{ tb.count }}</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 text-xs">
          <!-- ===== OVERVIEW ===== -->
          <div v-if="detailTab === 'overview'" class="space-y-3">
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

            <div v-if="selected.addresses?.length">
              <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('customers.addresses') }}</div>
              <div v-for="a in selected.addresses" :key="a.id" class="text-ink-muted dark:text-ink-dark-muted py-0.5">
                <span class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 mr-1">{{ $t(`customers.addr.${a.type}`) }}</span>
                {{ a.one_line }}
              </div>
            </div>

            <CustomFieldsDisplay :fields="meta.custom_fields" :values="selected.custom_fields" />
          </div>

          <!-- ===== CONTACTS ===== -->
          <div v-else-if="detailTab === 'contacts'">
            <div v-if="!selected.contacts?.length" class="text-ink-subtle py-4 text-center">{{ $t('customers.no_contacts') }}</div>
            <div v-for="c in selected.contacts" :key="c.id" class="flex items-center gap-2 py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
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

          <!-- ===== CAMPAIGNS ===== -->
          <div v-else-if="detailTab === 'campaigns'">
            <div v-if="can('customers.update')" class="flex gap-1.5 mb-2">
              <select v-model.number="newMembership.campaign_id" class="input input-sm flex-1 text-xs">
                <option :value="null">Add to campaign…</option>
                <option v-for="c in availableCampaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <select v-model="newMembership.status" class="input input-sm w-auto text-xs capitalize">
                <option v-for="s in (meta.campaign_member_statuses || ['member','contacted','responded'])" :key="s" :value="s">{{ s }}</option>
              </select>
              <button class="btn-primary btn-sm" :disabled="!newMembership.campaign_id || campaignBusy" @click="addMembership"><Plus :size="12" /></button>
            </div>
            <div v-if="campaignsLoading" class="text-ink-subtle py-4 text-center">Loading…</div>
            <div v-else-if="!customerCampaigns.length" class="text-ink-subtle py-4 text-center">Not a member of any campaign yet.</div>
            <div v-for="m in customerCampaigns" :key="m.campaign_id" class="flex items-center gap-1.5 py-0.5">
              <span class="text-ink dark:text-ink-dark truncate flex-1">{{ m.name }}</span>
              <span v-if="m.type" class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-ink-muted uppercase shrink-0">{{ m.type }}</span>
              <select v-if="can('customers.update')" v-model="m.status" class="input input-xs w-auto capitalize shrink-0" :disabled="campaignBusy" @change="updateMembershipStatus(m)">
                <option v-for="s in (meta.campaign_member_statuses || ['member','contacted','responded'])" :key="s" :value="s">{{ s }}</option>
              </select>
              <span v-else class="text-ink-subtle capitalize shrink-0">{{ m.status }}</span>
              <button v-if="can('customers.update')" class="p-1 text-ink-subtle hover:text-red-500 shrink-0" :disabled="campaignBusy" @click="removeMembership(m)"><X :size="12" /></button>
            </div>
          </div>

          <!-- ===== TIMELINE ===== -->
          <div v-else-if="detailTab === 'timeline'">
            <div v-if="can('activities.create')" class="flex gap-1.5 mb-2">
              <button class="btn-secondary btn-xs" @click="newActivity('task')"><Plus :size="11" /> New task</button>
              <button class="btn-secondary btn-xs" @click="newActivity('call')"><Plus :size="11" /> Log call</button>
              <button class="btn-secondary btn-xs" @click="newActivity('meeting')"><Plus :size="11" /> New meeting</button>
            </div>
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
                <span v-if="t.source && t.source.type !== 'Customer'" class="text-[9px] text-ink-subtle shrink-0">{{ t.source.type }}: {{ t.source.name }}</span>
                <span class="text-ink-subtle ml-auto shrink-0">{{ t.occurred_human }}</span>
              </div>
              <div v-if="t.body" class="text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ t.body }}</div>
            </div>
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
          <div>
            <label class="label">Territory</label>
            <input v-model="form.data.territory" class="input text-sm" placeholder="e.g. North, GCC" />
          </div>
          <div>
            <label class="label">Tags</label>
            <input v-model="tagsInput" class="input text-sm" placeholder="comma-separated" />
          </div>
          <div class="col-span-2">
            <label class="label">{{ $t('customers.notes') }}</label>
            <textarea v-model="form.data.notes" rows="2" class="input text-sm resize-none" />
          </div>
        </div>
        <CustomFieldsInput v-model="form.data.custom_fields" :fields="meta.custom_fields" />
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('customers.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving" @click="submitForm">
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
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/customers';
import DuplicateWarningModal from '@/components/crm/DuplicateWarningModal.vue';
import RecordMergeModal from '@/components/crm/RecordMergeModal.vue';
import PortalAccessModal from '@/components/crm/PortalAccessModal.vue';
import CustomFieldsInput from '@/components/crm/CustomFieldsInput.vue';
import CustomFieldsDisplay from '@/components/crm/CustomFieldsDisplay.vue';
import http from '@/services/http';
import { seedCustomFields, stripBlankCustomFields } from '@/composables/useCustomFields';
import { useDuplicateGuard } from '@/composables/useDuplicateGuard';
import { useRecordMerge } from '@/composables/useRecordMerge';
import { RefreshCw, Plus, X, Send } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const can = (p) => auth.can(p);

// Open the Activities create form (task / call / meeting) pre-linked to the current account.
function newActivity(type) {
  if (!selected.value) return;
  router.push({ name: 'activities', query: { new: type, related_type: 'customer', related_id: selected.value.id } });
}
// Open Sales with a new quotation pre-filled for this account.
function newQuote() {
  if (!selected.value) return;
  router.push({ name: 'sales', query: { new: 'quotation', customer_id: selected.value.id } });
}
// Record modal tabs.
const detailTab = ref('overview');
const detailTabs = computed(() => [
  { key: 'overview', label: 'Overview' },
  { key: 'contacts', label: 'Contacts', count: selected.value?.contacts?.length || 0 },
  { key: 'campaigns', label: 'Campaigns', count: customerCampaigns.value.length },
  { key: 'timeline', label: 'Timeline', count: selected.value?.timeline?.length || 0 },
]);

function addFollowUp(account) {
  router.push({ name: 'activities', query: { new: 'task', related_type: 'customer', related_id: account.id } });
}

const statTiles = [
  { key: 'total',   status: 'all',      label: 'customers.stat.total' },
  { key: 'active',  status: 'active',   label: 'customers.stat.active' },
  { key: 'on_hold', status: 'on_hold',  label: 'customers.stat.on_hold' },
  { key: 'blocked', status: 'blocked',  label: 'customers.stat.blocked' },
  { key: 'new_this_month', status: 'all', label: 'customers.stat.new_this_month', static: true },
];

const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ groups: [], types: [], statuses: [], price_books: [], custom_fields: [] });
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
    detailTab.value = 'overview';
    loadCampaigns(id);
    try {
      const tl = await api.timeline(id);
      selected.value.timeline = tl.data?.data?.data ?? selected.value.timeline ?? [];
    } catch { /* keep the embedded timeline as fallback */ }
  } catch { /* interceptor surfaces the error */ }
}

// Campaign memberships (an account can belong to many marketing campaigns).
const customerCampaigns = ref([]);
const campaignsLoading = ref(false);
const campaignBusy = ref(false);
const newMembership = reactive({ campaign_id: null, status: 'member' });
const availableCampaigns = computed(() => {
  const joined = new Set(customerCampaigns.value.map((m) => m.campaign_id));
  return (meta.campaigns || []).filter((c) => !joined.has(c.id));
});
async function loadCampaigns(id) {
  campaignsLoading.value = true; customerCampaigns.value = [];
  try { const { data } = await http.get(`/customers/${id}/campaigns`); customerCampaigns.value = data.data || []; }
  catch { customerCampaigns.value = []; }
  finally { campaignsLoading.value = false; }
}
async function addMembership() {
  if (!newMembership.campaign_id || !selected.value) return;
  campaignBusy.value = true;
  try {
    const { data } = await http.post(`/customers/${selected.value.id}/campaigns`, { ...newMembership });
    customerCampaigns.value = data.data || [];
    newMembership.campaign_id = null; newMembership.status = 'member';
  } catch { /* interceptor surfaces the error */ }
  finally { campaignBusy.value = false; }
}
async function updateMembershipStatus(m) {
  campaignBusy.value = true;
  try {
    const { data } = await http.post(`/customers/${selected.value.id}/campaigns`, { campaign_id: m.campaign_id, status: m.status });
    customerCampaigns.value = data.data || [];
  } catch { /* noop */ }
  finally { campaignBusy.value = false; }
}
async function removeMembership(m) {
  campaignBusy.value = true;
  try {
    const { data } = await http.delete(`/customers/${selected.value.id}/campaigns/${m.campaign_id}`);
    customerCampaigns.value = data.data || [];
  } catch { /* noop */ }
  finally { campaignBusy.value = false; }
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
    credit_limit: 0, status: 'active', territory: '', tags: [], notes: '',
    custom_fields: seedCustomFields(meta.custom_fields),
  };
  form.open = true;
}
function openEdit(c) {
  form.id = c.id; form.errors = {};
  form.data = {
    name: c.name, type: c.type, group_id: c.group?.id ?? null, price_book_id: c.price_book_id ?? null, email: c.email ?? '',
    phone: c.phone ?? '', credit_limit: c.credit_limit ?? 0, status: c.status,
    territory: c.territory ?? '', tags: Array.isArray(c.tags) ? [...c.tags] : [], notes: c.notes ?? '',
    custom_fields: seedCustomFields(meta.custom_fields, c.custom_fields),
  };
  form.open = true;
}

// Tags edited as a comma-separated string, stored as an array.
const tagsInput = computed({
  get: () => (form.data.tags || []).join(', '),
  set: (v) => { form.data.tags = String(v).split(',').map((t) => t.trim()).filter(Boolean); },
});

async function submitForm(force = false) {
  form.saving = true; form.errors = {};
  try {
    const payload = { ...form.data };
    if (!payload.email) delete payload.email;      // '' would fail the email rule
    if (!payload.phone) delete payload.phone;
    if (payload.custom_fields) payload.custom_fields = stripBlankCustomFields(payload.custom_fields);
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

onMounted(async () => { await Promise.all([load(), loadAux()]); if (router.currentRoute.value.query.create) openCreate(); });
</script>
