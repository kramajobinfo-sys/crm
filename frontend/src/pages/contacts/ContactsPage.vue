<template>
  <div class="page">
    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('contacts.title') }}</h1>
        <p class="page-sub">{{ $t('contacts.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="load">
          <RefreshCw :size="14" :class="loading && 'animate-spin'" /> {{ $t('contacts.refresh') }}
        </button>
        <button v-if="can('contacts.create')" class="btn-primary btn-sm" @click="openCreate">
          <Plus :size="14" /> {{ $t('contacts.new') }}
        </button>
      </div>
    </div>

    <div class="card mb-4">
      <div class="toolbar">
        <input v-model="filters.q" class="input input-sm w-64" :placeholder="$t('contacts.search')" @keyup.enter="load" />
        <select v-model="filters.customer_id" class="input input-sm w-56" @change="load">
          <option value="">{{ $t('contacts.all_accounts') }}</option>
          <option v-for="a in meta.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted">
          <input v-model="filters.primaryOnly" type="checkbox" class="rounded border-slate-300" @change="load" />
          {{ $t('contacts.primary_only') }}
        </label>
        <button class="btn-ghost btn-sm ml-auto" @click="resetFilters">{{ $t('contacts.reset') }}</button>
      </div>
    </div>

    <div class="flex gap-3 items-start">
      <div class="panel flex-1 min-w-0">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="empty">
          <div class="empty-icon"><UserRound :size="18" /></div>
          <div class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('contacts.empty') }}</div>
        </div>
        <template v-else>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ $t('contacts.name') }}</th>
                  <th>{{ $t('contacts.account') }}</th>
                  <th class="hidden md:table-cell">{{ $t('contacts.title_field') }}</th>
                  <th class="hidden lg:table-cell">{{ $t('contacts.email') }}</th>
                  <th class="hidden xl:table-cell">{{ $t('contacts.phone') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rows" :key="r.id"
                    class="cursor-pointer"
                    :class="selected?.id === r.id && 'is-selected'"
                    @click="openDetail(r.id)">
                  <td class="text-ink dark:text-ink-dark">
                    <div class="flex items-center gap-1.5">
                      <span class="font-medium">{{ r.name }}</span>
                      <Star v-if="r.is_primary" :size="11" class="text-amber-500 fill-amber-500" :title="$t('contacts.primary')" />
                    </div>
                  </td>
                  <td class="text-ink-muted">{{ r.account?.name || '—' }}</td>
                  <td class="text-ink-muted hidden md:table-cell">{{ r.title || '—' }}</td>
                  <td class="text-ink-muted hidden lg:table-cell">{{ r.email || '—' }}</td>
                  <td class="text-ink-muted hidden xl:table-cell">{{ r.mobile || r.phone || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center text-xs text-ink-subtle">
            {{ $t('contacts.showing', { from: pagination.from || 0, to: pagination.to || 0, total: pagination.total || 0 }) }}
            <div class="ml-auto flex gap-1">
              <button class="btn-secondary btn-xs" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">‹</button>
              <button class="btn-secondary btn-xs" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">›</button>
            </div>
          </div>
        </template>
      </div>

      <!-- Detail modal (record popup) -->
      <div v-if="selected" class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="selected = null">
      <div class="card w-full max-w-2xl my-6 flex flex-col overflow-hidden max-h-[calc(100vh-3rem)]">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2 shrink-0">
          <UserRound :size="16" class="text-primary-600 shrink-0" />
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark truncate">{{ selected.name }}</div>
            <div class="text-[11px] text-ink-subtle truncate">{{ selected.title || selected.account?.name }}</div>
          </div>
          <button v-if="can('activities.create') && (selected.mobile || selected.phone)" class="p-1 text-ink-subtle hover:text-primary-600" :title="$t('contacts.call')" @click="callContact"><Phone :size="13" /></button>
          <button v-if="can('contacts.update')" class="p-1 text-ink-subtle hover:text-primary-600" @click="openEdit(selected)"><Pencil :size="13" /></button>
          <button v-if="can('activities.create')" class="btn-secondary btn-xs" @click="addFollowUp(selected)">{{ $t('activities.quick_follow_up') }}</button>
          <button v-if="can('contacts.delete')" class="p-1 text-ink-subtle hover:text-red-600" @click="removeSelected"><Trash2 :size="13" /></button>
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

        <div class="flex-1 overflow-y-auto p-4 text-xs">
          <!-- ===== OVERVIEW ===== -->
          <div v-if="detailTab === 'overview'" class="space-y-3">
            <dl class="grid grid-cols-3 gap-y-2">
              <dt class="text-ink-subtle">{{ $t('contacts.account') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.account?.name || '—' }}</dd>
              <dt class="text-ink-subtle">Department</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.department || '—' }}</dd>
              <dt class="text-ink-subtle">{{ $t('contacts.email') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark break-all">{{ selected.email || '—' }}</dd>
              <dt class="text-ink-subtle">{{ $t('contacts.phone') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.phone || '—' }}</dd>
              <dt class="text-ink-subtle">{{ $t('contacts.mobile') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.mobile || '—' }}</dd>
              <dt class="text-ink-subtle">{{ $t('contacts.portal') }}</dt>
              <dd class="col-span-2" :class="selected.portal_enabled ? 'text-emerald-600' : 'text-ink-subtle'">
                {{ selected.portal_enabled ? $t('contacts.enabled') : $t('contacts.disabled') }}
                <button v-if="can('customers.update')" class="ml-2 text-primary-600 hover:underline" @click="openPortal(selected)">
                  {{ $t('contacts.portal_manage') }}
                </button>
              </dd>
              <dt class="text-ink-subtle">{{ $t('contacts.notes') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark whitespace-pre-wrap">{{ selected.notes || '—' }}</dd>
            </dl>

            <CustomFieldsDisplay :fields="meta.custom_fields" :values="selected.custom_fields" />

            <!-- Communication consent -->
            <div class="border-t border-line dark:border-line-dark pt-3">
              <div class="section-label mb-2">{{ $t('contacts.consent.title') }}</div>
              <div v-if="selected.consents" class="space-y-1.5">
                <div v-for="c in selected.consents.current" :key="c.channel" class="flex items-center gap-2">
                  <span class="text-xs text-ink dark:text-ink-dark flex-1">{{ channelLabel(c.channel) }}</span>
                  <span class="badge" :class="c.can_receive ? 'badge-success' : (c.status === 'withdrawn' ? 'badge-danger' : 'badge-neutral')">
                    {{ c.can_receive ? $t('contacts.consent.reachable') : (c.status === 'withdrawn' ? $t('contacts.consent.opted_out') : $t('contacts.consent.opt_in_required')) }}
                  </span>
                  <button v-if="can('contacts.update')" class="btn-ghost btn-xs shrink-0" :disabled="consentBusy" @click="toggleConsent(c)">
                    {{ c.can_receive ? $t('contacts.consent.opt_out') : $t('contacts.consent.opt_in') }}
                  </button>
                </div>
                <button v-if="selected.consents.history?.length" class="text-[11px] text-primary-600 hover:underline mt-1"
                        @click="showConsentHistory = !showConsentHistory">
                  {{ showConsentHistory ? $t('contacts.consent.hide') : $t('contacts.consent.history') }} ({{ selected.consents.history.length }})
                </button>
                <div v-if="showConsentHistory" class="mt-1 space-y-1">
                  <div v-for="h in selected.consents.history" :key="h.id" class="text-[11px] text-ink-subtle">
                    <span :class="h.status === 'granted' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">{{ h.status === 'granted' ? $t('contacts.consent.opt_in') : $t('contacts.consent.opt_out') }}</span>
                    · {{ channelLabel(h.channel) }}<span v-if="h.occurred_at"> · {{ new Date(h.occurred_at).toLocaleDateString() }}</span><span v-if="h.user"> · {{ h.user.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ===== CAMPAIGNS ===== -->
          <div v-else-if="detailTab === 'campaigns'">
            <div v-if="can('contacts.update')" class="flex gap-1.5 mb-2">
              <select v-model.number="newMembership.campaign_id" class="input input-sm flex-1 text-xs">
                <option :value="null">Add to campaign…</option>
                <option v-for="c in availableCampaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <select v-model="newMembership.status" class="input input-sm w-auto text-xs capitalize">
                <option v-for="s in (meta.campaign_member_statuses || ['member','contacted','responded'])" :key="s" :value="s">{{ s }}</option>
              </select>
              <button class="btn-primary btn-sm" :disabled="!newMembership.campaign_id || campaignBusy" @click="addMembership"><Plus :size="12" /></button>
            </div>
            <div v-if="campaignsLoading" class="text-xs text-ink-subtle py-4 text-center">Loading…</div>
            <div v-else-if="!contactCampaigns.length" class="text-xs text-ink-subtle py-4 text-center">Not a member of any campaign yet.</div>
            <div v-else class="space-y-1">
              <div v-for="m in contactCampaigns" :key="m.campaign_id" class="flex items-center gap-1.5 text-xs py-0.5">
                <span class="text-ink dark:text-ink-dark truncate flex-1">{{ m.name }}</span>
                <span v-if="m.type" class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-ink-muted uppercase shrink-0">{{ m.type }}</span>
                <select v-if="can('contacts.update')" v-model="m.status" class="input input-xs w-auto capitalize shrink-0" :disabled="campaignBusy" @change="updateMembershipStatus(m)">
                  <option v-for="s in (meta.campaign_member_statuses || ['member','contacted','responded'])" :key="s" :value="s">{{ s }}</option>
                </select>
                <span v-else class="text-ink-subtle capitalize shrink-0">{{ m.status }}</span>
                <button v-if="can('contacts.update')" class="p-1 text-ink-subtle hover:text-red-500 shrink-0" :disabled="campaignBusy" @click="removeMembership(m)"><X :size="12" /></button>
              </div>
            </div>
          </div>

          <!-- ===== ACTIVITY (timeline: contact + its opportunities) ===== -->
          <div v-else-if="detailTab === 'activity'">
            <div v-if="can('activities.create')" class="flex gap-1.5 mb-2">
              <button class="btn-secondary btn-xs" @click="newActivity('task')"><Plus :size="11" /> New task</button>
              <button class="btn-secondary btn-xs" @click="newActivity('call')"><Plus :size="11" /> Log call</button>
              <button class="btn-secondary btn-xs" @click="newActivity('meeting')"><Plus :size="11" /> New meeting</button>
            </div>
            <div v-if="timelineLoading" class="text-xs text-ink-subtle py-4 text-center">Loading…</div>
            <div v-else-if="!timeline.length" class="text-xs text-ink-subtle py-4 text-center">No activity yet.</div>
            <div v-else class="space-y-2">
              <div v-for="t in timeline" :key="t.id" class="text-xs">
                <div class="flex items-center gap-1.5">
                  <span class="badge-neutral">{{ t.type }}</span>
                  <span class="text-ink dark:text-ink-dark font-medium truncate">{{ t.title }}</span>
                </div>
                <div v-if="t.body" class="text-ink-subtle mt-0.5 whitespace-pre-wrap">{{ t.body }}</div>
                <div class="text-[11px] text-ink-subtle mt-0.5">
                  <span v-if="t.source?.type && t.source.type !== 'Contact'">{{ t.source.type }} · </span>
                  {{ t.occurred_human }}<span v-if="t.user"> · {{ t.user.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>
    </div>

    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ form.id ? $t('contacts.edit_title') : $t('contacts.new_title') }}</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="md:col-span-2">
            <label class="label">{{ $t('contacts.account') }} *</label>
            <select v-model="form.data.customer_id" class="input text-sm w-full">
              <option :value="null" disabled>{{ $t('contacts.select_account') }}</option>
              <option v-for="a in meta.accounts" :key="a.id" :value="a.id">{{ a.customer_no }} · {{ a.name }}</option>
            </select>
            <p v-if="form.errors.customer_id" class="text-[11px] text-red-600 mt-1">{{ form.errors.customer_id[0] }}</p>
          </div>
          <div>
            <label class="label">{{ $t('contacts.name') }} *</label>
            <input v-model="form.data.name" class="input text-sm w-full" />
            <p v-if="form.errors.name" class="text-[11px] text-red-600 mt-1">{{ form.errors.name[0] }}</p>
          </div>
          <div><label class="label">{{ $t('contacts.title_field') }}</label><input v-model="form.data.title" class="input text-sm w-full" /></div>
          <div><label class="label">Department</label><input v-model="form.data.department" class="input text-sm w-full" /></div>
          <div><label class="label">{{ $t('contacts.email') }}</label><input v-model="form.data.email" type="email" class="input text-sm w-full" /></div>
          <div><label class="label">{{ $t('contacts.phone') }}</label><input v-model="form.data.phone" class="input text-sm w-full" /></div>
          <div><label class="label">{{ $t('contacts.mobile') }}</label><input v-model="form.data.mobile" class="input text-sm w-full" /></div>
          <label class="flex items-center gap-2 text-xs text-ink-muted dark:text-ink-dark-muted self-end pb-2">
            <input v-model="form.data.is_primary" type="checkbox" class="rounded border-slate-300" /> {{ $t('contacts.primary_for_account') }}
          </label>
          <div class="md:col-span-2"><label class="label">{{ $t('contacts.notes') }}</label><textarea v-model="form.data.notes" rows="3" class="input text-sm w-full"></textarea></div>
          <div class="md:col-span-2"><CustomFieldsInput v-model="form.data.custom_fields" :fields="meta.custom_fields" /></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('contacts.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving || !form.data.customer_id || !form.data.name?.trim()" @click="submitForm">
            {{ form.saving ? $t('contacts.saving') : $t('contacts.save') }}
          </button>
        </div>
      </div>
    </div>

    <DuplicateWarningModal
      :open="duplicateGuard.state.open"
      :candidates="duplicateGuard.state.candidates"
      :primary-id="can('contacts.update') && can('contacts.delete') ? form.id : null"
      @cancel="duplicateGuard.cancel"
      @proceed="duplicateGuard.proceed"
      @merge="startMerge"
    />
    <RecordMergeModal :state="mergeGuard.state" @close="mergeGuard.close" @confirm="mergeGuard.confirm" />
    <PortalAccessModal :open="portal.open" :contact="portal.contact" @close="portal.open = false" @updated="portalUpdated" />
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/contacts';
import http from '@/services/http';
import DuplicateWarningModal from '@/components/crm/DuplicateWarningModal.vue';
import RecordMergeModal from '@/components/crm/RecordMergeModal.vue';
import PortalAccessModal from '@/components/crm/PortalAccessModal.vue';
import CustomFieldsInput from '@/components/crm/CustomFieldsInput.vue';
import CustomFieldsDisplay from '@/components/crm/CustomFieldsDisplay.vue';
import { seedCustomFields, stripBlankCustomFields } from '@/composables/useCustomFields';
import { useDuplicateGuard } from '@/composables/useDuplicateGuard';
import { useRecordMerge } from '@/composables/useRecordMerge';
import { Pencil, Phone, Plus, RefreshCw, Star, Trash2, UserRound, X } from 'lucide-vue-next';

const { t } = useI18n();
const toast = useToast();
const auth = useAuthStore();
const router = useRouter();
const can = (permission) => auth.can(permission);

function addFollowUp(contact) {
  router.push({ name: 'activities', query: { new: 'task', related_type: 'contact', related_id: contact.id } });
}
// Open the Activities create form (task / call / meeting) pre-linked to the current contact.
function newActivity(type) {
  if (!selected.value) return;
  router.push({ name: 'activities', query: { new: type, related_type: 'contact', related_id: selected.value.id } });
}
// Record modal tabs.
const detailTab = ref('overview');
const detailTabs = computed(() => [
  { key: 'overview', label: 'Overview' },
  { key: 'campaigns', label: 'Campaigns', count: contactCampaigns.value.length },
  { key: 'activity', label: 'Activity', count: timeline.value.length },
]);

const rows = ref([]);
const selected = ref(null);
const timeline = ref([]);
const timelineLoading = ref(false);
async function loadTimeline(id) {
  timelineLoading.value = true; timeline.value = [];
  try { const { data } = await http.get(`/contacts/${id}/timeline`); timeline.value = data.data || []; }
  catch { timeline.value = []; }
  finally { timelineLoading.value = false; }
}
const loading = ref(false);
const consentBusy = ref(false);
const showConsentHistory = ref(false);
const meta = reactive({ accounts: [], custom_fields: [], campaigns: [], campaign_member_statuses: [] });
const filters = reactive({ q: '', customer_id: '', primaryOnly: false, page: 1 });
const pagination = reactive({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 });
const form = reactive({ open: false, saving: false, id: null, data: {}, errors: {} });
const portal = reactive({ open: false, contact: null });
const duplicateGuard = useDuplicateGuard();
const mergeGuard = useRecordMerge(async () => {
  form.open = false;
  toast.success(t('duplicates.merged'));
  await load();
});

function startMerge(candidate) {
  duplicateGuard.cancel();
  mergeGuard.open('contact', form.id, candidate);
}

function openPortal(contact) {
  portal.contact = contact;
  portal.open = true;
}

function portalUpdated(contact) {
  portal.open = false;
  portal.contact = null;
  if (selected.value?.id === contact.id) selected.value = { ...selected.value, ...contact };
  const index = rows.value.findIndex((row) => row.id === contact.id);
  if (index >= 0) rows.value[index] = { ...rows.value[index], ...contact };
  toast.success(t('contacts.portal_updated'));
}

const blank = () => ({ customer_id: null, name: '', title: '', department: '', email: '', phone: '', mobile: '', is_primary: false, notes: '', custom_fields: seedCustomFields(meta.custom_fields) });

async function load() {
  loading.value = true;
  try {
    const { data } = await api.list({
      q: filters.q || undefined,
      customer_id: filters.customer_id || undefined,
      primary: filters.primaryOnly ? 'yes' : 'all',
      page: filters.page,
    });
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || {});
    if (selected.value && !rows.value.some((r) => r.id === selected.value.id)) selected.value = null;
  } catch { /* interceptor displays the error */ }
  finally { loading.value = false; }
}

async function loadMeta() {
  const { data } = await api.meta();
  Object.assign(meta, data.data || {}); // accounts, custom_fields, campaigns, campaign_member_statuses
}

// Campaign memberships (a contact can belong to many marketing campaigns).
const contactCampaigns = ref([]);
const campaignsLoading = ref(false);
const campaignBusy = ref(false);
const newMembership = reactive({ campaign_id: null, status: 'member' });
const availableCampaigns = computed(() => {
  const joined = new Set(contactCampaigns.value.map((m) => m.campaign_id));
  return (meta.campaigns || []).filter((c) => !joined.has(c.id));
});
async function loadCampaigns(id) {
  campaignsLoading.value = true; contactCampaigns.value = [];
  try { const { data } = await http.get(`/contacts/${id}/campaigns`); contactCampaigns.value = data.data || []; }
  catch { contactCampaigns.value = []; }
  finally { campaignsLoading.value = false; }
}
async function addMembership() {
  if (!newMembership.campaign_id || !selected.value) return;
  campaignBusy.value = true;
  try {
    const { data } = await http.post(`/contacts/${selected.value.id}/campaigns`, { ...newMembership });
    contactCampaigns.value = data.data || [];
    newMembership.campaign_id = null; newMembership.status = 'member';
  } catch { /* interceptor surfaces the error */ }
  finally { campaignBusy.value = false; }
}
async function updateMembershipStatus(m) {
  campaignBusy.value = true;
  try {
    const { data } = await http.post(`/contacts/${selected.value.id}/campaigns`, { campaign_id: m.campaign_id, status: m.status });
    contactCampaigns.value = data.data || [];
  } catch { /* noop */ }
  finally { campaignBusy.value = false; }
}
async function removeMembership(m) {
  campaignBusy.value = true;
  try {
    const { data } = await http.delete(`/contacts/${selected.value.id}/campaigns/${m.campaign_id}`);
    contactCampaigns.value = data.data || [];
  } catch { /* noop */ }
  finally { campaignBusy.value = false; }
}

async function openDetail(id) {
  try {
    const { data } = await api.show(id); selected.value = data.data; detailTab.value = 'overview'; await loadConsents(id);
    loadTimeline(id);
    loadCampaigns(id);
  } catch { /* noop */ }
}

async function callContact() {
  const to = selected.value.mobile || selected.value.phone;
  if (!to) return;
  try {
    const { data } = await api.clickToCall({ to, subject: `Call ${selected.value.name}`, related_type: 'App\\Models\\Contact', related_id: selected.value.id });
    toast.success(data.message);
  } catch (e) { toast.error(e.response?.data?.message || 'Call failed'); }
}

async function loadConsents(id) {
  try {
    const { data } = await api.consents(id);
    if (selected.value?.id === id) selected.value = { ...selected.value, consents: data.data };
  } catch { /* non-critical */ }
}

const channelLabel = (c) => ({ email: 'Email', sms: 'SMS', phone: 'Phone', whatsapp: 'WhatsApp', marketing: 'Marketing' }[c] || c);

async function toggleConsent(c) {
  if (consentBusy.value) return;
  consentBusy.value = true;
  try {
    await api.setConsent(selected.value.id, {
      channel: c.channel,
      status: c.can_receive ? 'withdrawn' : 'granted',
      source: 'agent',
    });
    await loadConsents(selected.value.id);
  } catch { /* interceptor surfaces the error */ }
  finally { consentBusy.value = false; }
}

function openCreate() {
  form.id = null;
  form.data = blank();
  form.errors = {};
  form.open = true;
}

function openEdit(contact) {
  form.id = contact.id;
  form.data = {
    customer_id: contact.customer_id,
    name: contact.name,
    title: contact.title || '',
    department: contact.department || '',
    email: contact.email || '',
    phone: contact.phone || '',
    mobile: contact.mobile || '',
    is_primary: !!contact.is_primary,
    notes: contact.notes || '',
    custom_fields: seedCustomFields(meta.custom_fields, contact.custom_fields),
  };
  form.errors = {};
  form.open = true;
}

async function submitForm(force = false) {
  form.saving = true;
  form.errors = {};
  try {
    const payload = { ...form.data };
    if (payload.custom_fields) payload.custom_fields = stripBlankCustomFields(payload.custom_fields);
    if (!force) {
      const clear = await duplicateGuard.check('contact', payload, form.id, () => submitForm(true));
      if (!clear) { form.saving = false; return; }
    }
    const { data } = form.id ? await api.update(form.id, payload) : await api.create(payload);
    toast.success(form.id ? t('contacts.updated') : t('contacts.created'));
    form.open = false;
    await load();
    await openDetail(data.data.id);
  } catch (error) {
    if (error.response?.status === 422) form.errors = error.response.data?.errors || {};
  } finally { form.saving = false; }
}

async function removeSelected() {
  if (!selected.value || !window.confirm(t('contacts.confirm_delete', { name: selected.value.name }))) return;
  try {
    await api.remove(selected.value.id);
    toast.success(t('contacts.deleted'));
    selected.value = null;
    await load();
  } catch { /* interceptor displays the error */ }
}

function resetFilters() {
  Object.assign(filters, { q: '', customer_id: '', primaryOnly: false, page: 1 });
  load();
}

function goPage(page) { filters.page = page; load(); }

onMounted(async () => { await loadMeta(); await load(); if (router.currentRoute.value.query.create) openCreate(); });
</script>
