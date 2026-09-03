<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <h1 class="text-lg font-semibold text-ink dark:text-ink-dark">{{ $t('credits.title') }}</h1>
        <p class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('credits.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn-secondary text-xs px-2.5 py-1.5" @click="loadAll">
          <RefreshCw :size="12" /> {{ $t('credits.refresh') }}
        </button>
        <button v-if="can('credits.create')" class="btn-primary text-xs px-2.5 py-1.5" @click="openIssue">
          <Plus :size="12" /> {{ $t('credits.new') }}
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div v-for="s in statTiles" :key="s.key" class="card p-3">
        <div class="text-[11px] text-ink-subtle">{{ s.label }}</div>
        <div class="text-lg font-semibold text-ink dark:text-ink-dark tabular-nums mt-0.5">{{ s.value }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-2 flex-wrap">
      <input v-model="filters.q" class="input text-sm max-w-xs" :placeholder="$t('credits.search')" @keyup.enter="loadList" />
      <select v-model="filters.status" class="input text-sm w-auto" @change="loadList">
        <option value="available">{{ $t('credits.available') }}</option>
        <option value="all">{{ $t('credits.all') }}</option>
        <option value="open">{{ $t('credits.open') }}</option>
        <option value="applied">{{ $t('credits.applied') }}</option>
        <option value="void">{{ $t('credits.void') }}</option>
      </select>
    </div>

    <!-- List -->
    <div class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
          <tr>
            <th class="text-left font-medium px-3 py-2">{{ $t('credits.credit_no') }}</th>
            <th class="text-left font-medium px-3 py-2">{{ $t('credits.customer') }}</th>
            <th class="text-left font-medium px-3 py-2">{{ $t('credits.source') }}</th>
            <th class="text-right font-medium px-3 py-2">{{ $t('credits.amount') }}</th>
            <th class="text-right font-medium px-3 py-2">{{ $t('credits.remaining') }}</th>
            <th class="text-left font-medium px-3 py-2">{{ $t('credits.status') }}</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!rows.length">
            <td colspan="7" class="text-center text-[11px] text-ink-subtle py-8">{{ $t('credits.no_credits') }}</td>
          </tr>
          <tr v-for="c in rows" :key="c.id"
              class="border-t border-slate-100 dark:border-slate-700/60 hover:bg-slate-50 dark:hover:bg-surface-dark-subtle cursor-pointer"
              @click="openDetail(c.id)">
            <td class="px-3 py-2 font-medium text-ink dark:text-ink-dark">{{ c.credit_no }}</td>
            <td class="px-3 py-2 text-ink-muted">{{ c.customer?.name || '—' }}</td>
            <td class="px-3 py-2 text-ink-muted text-xs">{{ sourceLabel(c.source) }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ money(c.amount, c.currency) }}</td>
            <td class="px-3 py-2 text-right tabular-nums font-medium">{{ money(c.remaining, c.currency) }}</td>
            <td class="px-3 py-2"><span class="badge" :class="statusClass(c.status)">{{ $t('credits.' + c.status) }}</span></td>
            <td class="px-3 py-2 text-right">
              <button v-if="can('credits.delete') && c.status !== 'void' && c.applied_amount === 0"
                      class="btn-secondary text-[11px] px-2 py-0.5" @click.stop="doVoid(c)">
                {{ $t('credits.void') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Issue modal -->
    <div v-if="issueForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="issueForm.open = false">
      <div class="card w-full max-w-md p-4 space-y-3">
        <h2 class="text-sm font-semibold text-ink dark:text-ink-dark">{{ $t('credits.issue_title') }}</h2>
        <p class="text-[11px] text-ink-subtle">{{ $t('credits.issue_hint') }}</p>

        <div>
          <label class="label">{{ $t('credits.customer') }} *</label>
          <select v-model="issueForm.customer_id" class="input text-sm">
            <option :value="null">—</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="label">{{ $t('credits.amount') }} *</label>
          <input v-model="issueForm.amount" type="number" step="0.01" min="0.01" class="input text-sm" />
        </div>
        <div>
          <label class="label">{{ $t('credits.reason') }} *</label>
          <input v-model="issueForm.reason" class="input text-sm" />
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="issueForm.open = false">{{ $t('common.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5"
                  :disabled="!issueForm.customer_id || !issueForm.amount || !issueForm.reason || issueForm.saving"
                  @click="submitIssue">
            {{ issueForm.saving ? $t('credits.issuing') : $t('credits.issue') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Detail drawer -->
    <div v-if="detail" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="detail = null">
      <div class="bg-surface dark:bg-surface-dark w-full max-w-md h-full overflow-y-auto p-4 space-y-4">
        <div class="flex items-start justify-between">
          <div>
            <div class="text-sm font-semibold text-ink dark:text-ink-dark">{{ detail.credit_no }}</div>
            <div class="text-[11px] text-ink-subtle">{{ sourceLabel(detail.source) }}</div>
          </div>
          <span class="badge" :class="statusClass(detail.status)">{{ $t('credits.' + detail.status) }}</span>
        </div>

        <dl class="text-xs space-y-1.5">
          <div class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.customer') }}</dt><dd>{{ detail.customer?.name || '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.amount') }}</dt><dd class="tabular-nums">{{ money(detail.amount, detail.currency) }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.applied') }}</dt><dd class="tabular-nums">{{ money(detail.applied_amount, detail.currency) }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.remaining') }}</dt><dd class="tabular-nums font-medium">{{ money(detail.remaining, detail.currency) }}</dd></div>
          <div v-if="detail.source_invoice" class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.from_invoice') }}</dt><dd>{{ detail.source_invoice.invoice_no }}</dd></div>
          <div v-if="detail.source_payment" class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.from_payment') }}</dt><dd>{{ detail.source_payment.payment_no }}</dd></div>
          <div v-if="detail.issued_at" class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.issued') }}</dt><dd>{{ new Date(detail.issued_at).toLocaleString() }}</dd></div>
          <div v-if="detail.created_by" class="flex justify-between"><dt class="text-ink-subtle">{{ $t('credits.issued_by') }}</dt><dd>{{ detail.created_by }}</dd></div>
        </dl>

        <div v-if="detail.reason" class="text-xs text-ink-muted border-t border-slate-100 dark:border-slate-700/60 pt-3">{{ detail.reason }}</div>

        <div class="border-t border-slate-100 dark:border-slate-700/60 pt-3">
          <div class="text-[11px] font-medium text-ink dark:text-ink-dark mb-1.5">{{ $t('credits.applications') }}</div>
          <div v-if="!detail.applications?.length" class="text-[11px] text-ink-subtle">{{ $t('credits.no_applications') }}</div>
          <div v-for="a in detail.applications" :key="a.id" class="flex justify-between text-xs py-1">
            <span class="text-ink-muted">{{ a.invoice?.invoice_no || '—' }}</span>
            <span class="tabular-nums">{{ money(a.amount, detail.currency) }}</span>
          </div>
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
import api from '@/services/credits';
import customersApi from '@/services/customers';
import { RefreshCw, Plus } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const rows = ref([]);
const customers = ref([]);
const stats = ref({});
const detail = ref(null);
const filters = reactive({ q: '', status: 'available' });
const issueForm = reactive({ open: false, saving: false, customer_id: null, amount: '', reason: '' });

const money = (v, ccy) =>
  `${Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${ccy || ''}`.trim();

const sourceLabel = (s) => t(`credits.source_${s}`);

const statusClass = (s) => ({
  open: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  applied: 'bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300',
  void: 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
}[s] || '');

const statTiles = computed(() => [
  { key: 'open_count', label: t('credits.stat_open_count'), value: stats.value.open_count ?? '—' },
  { key: 'open_value', label: t('credits.stat_open_value'), value: money(stats.value.open_value) },
  { key: 'applied_value', label: t('credits.stat_applied_value'), value: money(stats.value.applied_value) },
  { key: 'from_overpayment', label: t('credits.stat_from_overpayment'), value: stats.value.from_overpayment ?? '—' },
]);

async function loadList() {
  try {
    const { data } = await api.list({ q: filters.q || undefined, status: filters.status, per_page: 50 });
    rows.value = data.data || [];
  } catch { /* interceptor toasts */ }
}
async function loadStats() {
  try { const { data } = await api.stats(); stats.value = data.data || {}; } catch { /* noop */ }
}
async function loadCustomers() {
  try { const { data } = await customersApi.list({ per_page: 100 }); customers.value = data.data || []; } catch { /* noop */ }
}
// Fetched in parallel — this dev stack adds real per-request latency.
async function loadAll() { await Promise.all([loadList(), loadStats()]); }

function openIssue() {
  Object.assign(issueForm, { open: true, saving: false, customer_id: null, amount: '', reason: '' });
  if (!customers.value.length) loadCustomers();
}

async function submitIssue() {
  issueForm.saving = true;
  try {
    await api.create({
      customer_id: issueForm.customer_id,
      amount: Number(issueForm.amount),
      reason: issueForm.reason,
    });
    issueForm.open = false;
    await loadAll();
  } catch (e) {
    if (e.response?.status === 422) toast.error(e.response.data?.message);
  } finally {
    issueForm.saving = false;
  }
}

async function openDetail(id) {
  try { const { data } = await api.get(id); detail.value = data.data; } catch { /* noop */ }
}

async function doVoid(c) {
  if (!window.confirm(t('credits.void_confirm'))) return;
  try {
    await api.void(c.id);
    toast.success(t('credits.voided'));
    await loadAll();
  } catch (e) {
    if (e.response?.status === 422) toast.error(e.response.data?.message);
  }
}

onMounted(loadAll);
</script>
