<template>
  <div class="p-4 md:p-5 max-w-[1600px] mx-auto">

    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('purchase.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('purchase.subtitle') }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('purchase.refresh') }}
        </button>
        <button v-if="createBtn" class="btn-primary text-xs px-3 py-1.5" @click="openCreate">
          <Plus :size="12" /> {{ $t(createBtn) }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="card p-3">
        <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">{{ $t(s.label) }}</div>
        <div class="text-lg font-semibold mt-0.5" :class="s.key === 'my_approvals' && stats.my_approvals ? 'text-primary-600' : 'text-ink dark:text-ink-dark'">
          <span v-if="s.money">{{ compact(stats[s.key]) }}</span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-3 border-b border-slate-200 dark:border-slate-700">
      <button v-for="tb in visibleTabs" :key="tb" class="px-3 py-1.5 text-xs -mb-px border-b-2"
              :class="tab === tb ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
              @click="switchTab(tb)">
        {{ $t(`purchase.tab.${tb}`) }}
        <span v-if="tb === 'approvals' && stats.my_approvals" class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-primary-100 text-primary-700">{{ stats.my_approvals }}</span>
      </button>
    </div>

    <!-- ===== APPROVALS ===== -->
    <div v-if="tab === 'approvals'" class="card overflow-hidden">
      <div v-if="!approvals.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('purchase.no_approvals') }}</div>
      <div v-for="a in approvals" :key="a.id" class="flex items-center gap-3 px-3 py-2.5 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
        <div class="min-w-0 flex-1">
          <div class="text-sm text-ink dark:text-ink-dark font-mono">{{ a.document_no }} <span class="text-[11px] text-ink-subtle font-sans">{{ a.document_type }}</span></div>
          <div class="text-[11px] text-ink-subtle">{{ a.workflow }} · {{ $t('purchase.step') }} {{ a.step }}/{{ a.total_steps }} · {{ a.requester }}</div>
        </div>
        <span class="text-sm tabular-nums text-ink dark:text-ink-dark">{{ money(a.amount) }}</span>
        <button class="btn-primary text-[11px] px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700" @click="act(a.id, 'approve')">{{ $t('purchase.approve') }}</button>
        <button class="btn-secondary text-[11px] px-2.5 py-1 text-red-600" @click="act(a.id, 'reject')">{{ $t('purchase.reject') }}</button>
      </div>
    </div>

    <!-- ===== VENDORS ===== -->
    <div v-else-if="tab === 'vendors'" class="card overflow-hidden">
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex gap-2">
        <input v-model="filters.q" class="input text-sm w-52" :placeholder="$t('purchase.search')" @keyup.enter="load" />
      </div>
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('purchase.empty') }}</div>
      <table v-else class="w-full text-sm">
        <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
          <tr>
            <th class="text-left font-medium px-3 py-2">{{ $t('purchase.v.no') }}</th>
            <th class="text-left font-medium px-3 py-2">{{ $t('purchase.v.name') }}</th>
            <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('purchase.v.email') }}</th>
            <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">{{ $t('purchase.v.terms') }}</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in rows" :key="v.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2 font-mono text-[11px] text-ink-muted">{{ v.vendor_no }}</td>
            <td class="px-3 py-2 text-ink dark:text-ink-dark">{{ v.name }}</td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ v.email || '—' }}</td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-muted">{{ v.payment_terms_days != null ? v.payment_terms_days + 'd' : '—' }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('vendors.update')" class="text-[11px] text-primary-600 hover:underline" @click="openVendor(v)">{{ $t('purchase.edit') }}</button>
              <button v-if="can('vendors.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeVendor(v.id)">{{ $t('purchase.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ===== ORDERS / REQUESTS (documents) ===== -->
    <div v-else class="flex gap-3 items-start">
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex flex-wrap gap-2">
          <input v-model="filters.q" class="input text-sm w-48" :placeholder="$t('purchase.search')" @keyup.enter="load" />
          <select v-model="filters.status" class="input text-sm w-auto" @change="load">
            <option value="all">{{ $t('purchase.all_statuses') }}</option>
            <option v-for="s in statusesForTab" :key="s" :value="s">{{ $t(`purchase.st.${s}`) }}</option>
          </select>
        </div>
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('purchase.empty') }}</div>
        <table v-else class="w-full text-sm">
          <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
            <tr>
              <th class="text-left font-medium px-3 py-2">{{ $t('purchase.d.number') }}</th>
              <th class="text-left font-medium px-3 py-2">{{ tab === 'orders' ? $t('purchase.d.vendor') : $t('purchase.d.requester') }}</th>
              <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('purchase.d.status') }}</th>
              <th class="text-right font-medium px-3 py-2">{{ $t('purchase.d.total') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in rows" :key="d.id"
                class="border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
                :class="selected?.id === d.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
                @click="openDetail(d.id)">
              <td class="px-3 py-2 font-mono text-[11px] text-ink dark:text-ink-dark">{{ d.po_no || d.pr_no }}</td>
              <td class="px-3 py-2 text-ink dark:text-ink-dark truncate max-w-[12rem]">{{ tab === 'orders' ? (d.vendor?.name || '—') : (d.requester?.name || '—') }}</td>
              <td class="px-3 py-2 hidden md:table-cell"><span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(d.status)">{{ $t(`purchase.st.${d.status}`) }}</span></td>
              <td class="px-3 py-2 text-right tabular-nums text-ink dark:text-ink-dark">{{ money(d.grand_total ?? d.estimated_total, d.currency) }}</td>
            </tr>
          </tbody>
        </table>
        <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(x)=>{page+=x;load()}" />
      </div>

      <!-- Detail drawer -->
      <div v-if="selected" class="card w-full sm:w-[26rem] shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-16rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark font-mono">{{ selected.po_no || selected.pr_no }}</div>
            <div class="text-[11px] text-ink-subtle">{{ tab === 'orders' ? selected.vendor?.name : selected.requester?.name }}</div>
          </div>
          <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(selected.status)">{{ $t(`purchase.st.${selected.status}`) }}</span>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <!-- Actions -->
        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 flex flex-wrap gap-1.5">
          <template v-if="tab === 'requests'">
            <button v-if="['draft','rejected'].includes(selected.status) && can('purchase_requests.update')" class="btn-primary text-[11px] px-2 py-1" @click="submitDoc">{{ $t('purchase.submit') }}</button>
            <button v-if="selected.status === 'approved' && !selected.converted_po_id && can('purchase_orders.create')" class="btn-primary text-[11px] px-2 py-1" @click="openConvert">{{ $t('purchase.to_po') }}</button>
          </template>
          <template v-else>
            <button v-if="selected.status === 'draft' && can('purchase_orders.update')" class="btn-primary text-[11px] px-2 py-1" @click="poAction('confirm')">{{ $t('purchase.confirm') }}</button>
            <button v-if="selected.status === 'confirmed' && can('inventory.adjust')" class="btn-primary text-[11px] px-2 py-1" @click="poAction('receive')">{{ $t('purchase.receive') }}</button>
            <button v-if="['confirmed','received'].includes(selected.status) && can('purchase_orders.update')" class="btn-secondary text-[11px] px-2 py-1" @click="poAction('close')">{{ $t('purchase.close') }}</button>
            <button v-if="['draft','submitted','confirmed'].includes(selected.status) && can('purchase_orders.update')" class="btn-secondary text-[11px] px-2 py-1 text-red-600" @click="poAction('cancel')">{{ $t('purchase.cancel') }}</button>
          </template>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <!-- Approval progress -->
          <div v-if="selected.approval" class="rounded-md bg-slate-50 dark:bg-surface-dark-subtle p-2">
            <div class="flex items-center gap-1.5">
              <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('purchase.approval') }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="approvalClass(selected.approval.status)">{{ $t(`purchase.ap.${selected.approval.status}`) }}</span>
              <span class="text-[11px] text-ink-subtle ml-auto">{{ $t('purchase.step') }} {{ Math.min(selected.approval.current_step + 1, selected.approval.total_steps) }}/{{ selected.approval.total_steps }}</span>
            </div>
            <div v-for="ac in selected.approval.actions" :key="ac.step" class="text-[11px] text-ink-muted mt-1">
              {{ ac.approver }} · {{ $t(`purchase.${ac.action}d`) }}<span v-if="ac.comment"> — {{ ac.comment }}</span>
            </div>
          </div>

          <!-- Items -->
          <table class="w-full">
            <tbody>
              <tr v-for="it in selected.items" :key="it.id" class="border-b border-slate-100 dark:border-slate-700/60 last:border-0">
                <td class="py-1 text-ink dark:text-ink-dark">
                  {{ it.name }} <span class="text-ink-subtle">× {{ it.quantity }}</span>
                  <span v-if="tab === 'orders' && it.received_quantity > 0" class="text-emerald-600 text-[10px]">({{ it.received_quantity }} {{ $t('purchase.received_lower') }})</span>
                </td>
                <td class="py-1 text-right tabular-nums text-ink-muted">{{ money((it.line_total ?? (it.quantity * it.estimated_price)), selected.currency) }}</td>
              </tr>
            </tbody>
          </table>

          <dl v-if="tab === 'orders'" class="grid grid-cols-2 gap-y-1 border-t border-slate-200 dark:border-slate-700 pt-2">
            <dt class="text-ink-subtle">{{ $t('purchase.subtotal') }}</dt><dd class="text-right tabular-nums">{{ money(selected.subtotal, selected.currency) }}</dd>
            <dt class="text-ink-subtle">{{ $t('purchase.tax') }}</dt><dd class="text-right tabular-nums">{{ money(selected.tax_total, selected.currency) }}</dd>
            <dt class="text-ink dark:text-ink-dark font-medium">{{ $t('purchase.grand_total') }}</dt><dd class="text-right tabular-nums font-semibold">{{ money(selected.grand_total, selected.currency) }}</dd>
          </dl>
          <div v-else class="text-right text-xs border-t border-slate-200 dark:border-slate-700 pt-2">
            {{ $t('purchase.estimated') }}: <span class="font-semibold tabular-nums">{{ money(selected.estimated_total, selected.currency) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Vendor modal -->
    <div v-if="vForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="vForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ vForm.id ? $t('purchase.edit_vendor') : $t('purchase.new_vendor') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2"><label class="label">{{ $t('purchase.v.name') }} *</label><input v-model="vForm.data.name" class="input text-sm" />
            <p v-if="vForm.errors.name" class="text-[11px] text-red-500">{{ vForm.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('purchase.v.email') }}</label><input v-model="vForm.data.email" class="input text-sm" /></div>
          <div><label class="label">{{ $t('purchase.v.phone') }}</label><input v-model="vForm.data.phone" class="input text-sm" /></div>
          <div><label class="label">{{ $t('purchase.v.terms') }}</label><input v-model.number="vForm.data.payment_terms_days" type="number" min="0" class="input text-sm" /></div>
          <div><label class="label">{{ $t('purchase.v.tax_id') }}</label><input v-model="vForm.data.tax_id" class="input text-sm" /></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="vForm.open = false">{{ $t('purchase.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="vForm.saving" @click="submitVendor">{{ vForm.saving ? $t('purchase.saving') : $t('purchase.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Document create modal (request / order) -->
    <div v-if="docForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="docForm.open = false">
      <div class="card w-full max-w-2xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t(tab === 'orders' ? 'purchase.new_order' : 'purchase.new_request') }}</div>
        <div class="grid grid-cols-3 gap-2.5">
          <div v-if="tab === 'orders'"><label class="label">{{ $t('purchase.d.vendor') }} *</label>
            <select v-model="docForm.data.vendor_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="v in vendorList" :key="v.id" :value="v.id">{{ v.name }}</option>
            </select>
          </div>
          <div v-if="tab === 'orders'"><label class="label">{{ $t('purchase.warehouse') }}</label>
            <select v-model="docForm.data.warehouse_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="w in warehouseList" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div><label class="label">{{ tab === 'orders' ? $t('purchase.expected') : $t('purchase.needed_by') }}</label>
            <input v-model="docForm.data.date" type="date" class="input text-sm" /></div>
        </div>
        <div class="mt-3">
          <div class="flex items-center mb-1">
            <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('purchase.line_items') }}</span>
            <button class="text-[11px] text-primary-600 hover:underline ml-auto" @click="addLine">+ {{ $t('purchase.add_line') }}</button>
          </div>
          <div v-for="(line, i) in docForm.data.items" :key="i" class="flex gap-1.5 mb-1.5 items-center">
            <select class="input text-xs w-40" @change="onPickProduct(line, $event.target.value)">
              <option value="">{{ $t('purchase.custom') }}</option>
              <option v-for="p in productList" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-model="line.name" class="input text-xs flex-1" :placeholder="$t('purchase.item_name')" />
            <input v-model.number="line.quantity" type="number" min="0" class="input text-xs w-14" :placeholder="$t('purchase.qty')" />
            <input v-model.number="line.price" type="number" min="0" class="input text-xs w-24" :placeholder="$t('purchase.price')" />
            <span class="text-[11px] tabular-nums text-ink-muted w-16 text-right">{{ compact((line.quantity||0)*(line.price||0)) }}</span>
            <button class="p-1 text-ink-subtle hover:text-red-500" @click="docForm.data.items.splice(i, 1)"><X :size="12" /></button>
          </div>
          <p v-if="docForm.error" class="text-[11px] text-red-500">{{ docForm.error }}</p>
          <div v-if="docForm.data.items.length" class="text-right text-xs text-ink dark:text-ink-dark mt-1">
            {{ $t('purchase.total') }}: <span class="font-semibold tabular-nums">{{ money(formTotal, 'AED') }}</span>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="docForm.open = false">{{ $t('purchase.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="docForm.saving" @click="submitDocForm">{{ docForm.saving ? $t('purchase.saving') : $t('purchase.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Convert PR modal -->
    <div v-if="convert.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="convert.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('purchase.to_po') }}</div>
        <label class="label">{{ $t('purchase.d.vendor') }} *</label>
        <select v-model="convert.vendor_id" class="input text-sm">
          <option :value="null">—</option>
          <option v-for="v in vendorList" :key="v.id" :value="v.id">{{ v.name }}</option>
        </select>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="convert.open = false">{{ $t('purchase.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="!convert.vendor_id || convert.saving" @click="submitConvert">{{ $t('purchase.convert') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/purchase';
import salesApi from '@/services/sales';
import inventoryApi from '@/services/inventory';
import { RefreshCw, Plus, X } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('purchase.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
  h('div', { class: 'flex gap-1' }, [
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page <= 1, onClick: () => emit('go', -1) }, '‹'),
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page >= props.p.last_page, onClick: () => emit('go', 1) }, '›'),
  ]),
]);
Pager.props = ['p', 'page'];
Pager.emits = ['go'];

const tabPerm = { orders: 'purchase_orders.view', requests: 'purchase_requests.view', vendors: 'vendors.view', approvals: null };
const allTabs = ['orders', 'requests', 'vendors', 'approvals'];
const visibleTabs = computed(() => allTabs.filter((tb) => !tabPerm[tb] || can(tabPerm[tb])));
const statTiles = [
  { key: 'open_orders',      label: 'purchase.stat.open_orders' },
  { key: 'awaiting_receipt', label: 'purchase.stat.awaiting' },
  { key: 'pending_requests', label: 'purchase.stat.pending' },
  { key: 'my_approvals',     label: 'purchase.stat.approvals' },
  { key: 'vendors',          label: 'purchase.stat.vendors' },
  { key: 'spend_mtd',        label: 'purchase.stat.spend', money: true },
];

const tab        = ref(visibleTabs.value[0] || 'orders');
const rows       = ref([]);
const approvals  = ref([]);
const vendorList = ref([]);
const productList = ref([]);
const warehouseList = ref([]);
const stats      = reactive({});
const selected   = ref(null);
const loading    = ref(false);
const page       = ref(1);
const filters    = reactive({ q: '', status: 'all' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const vForm   = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });
const docForm = reactive({ open: false, saving: false, error: '', data: { items: [] } });
const convert = reactive({ open: false, saving: false, vendor_id: null, pr_id: null });

const statusesByTab = {
  orders: ['draft', 'submitted', 'confirmed', 'received', 'closed', 'cancelled'],
  requests: ['draft', 'submitted', 'approved', 'rejected', 'converted', 'cancelled'],
};
const statusesForTab = computed(() => statusesByTab[tab.value] || []);
const createBtn = computed(() => ({
  orders: can('purchase_orders.create') ? 'purchase.new_order' : null,
  requests: can('purchase_requests.create') ? 'purchase.new_request' : null,
  vendors: can('vendors.create') ? 'purchase.new_vendor' : null,
  approvals: null,
}[tab.value]));

async function load() {
  loading.value = true;
  try {
    if (tab.value === 'approvals') { const { data } = await api.myApprovals(); approvals.value = data.data || []; return; }
    const params = { page: page.value, per_page: 25 };
    if (filters.q) params.q = filters.q;
    if (['orders', 'requests'].includes(tab.value) && filters.status !== 'all') params.status = filters.status;
    const fn = { orders: api.orders, requests: api.requests, vendors: api.vendors }[tab.value];
    const { data } = await fn(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
async function loadAux() { try { const s = await api.poStats(); Object.assign(stats, s.data.data || {}); } catch { /* noop */ } }
async function loadRefs() {
  try {
    const [v, p] = await Promise.all([api.vendors({ per_page: 100 }), salesApi.products({ per_page: 100, status: 'active' })]);
    vendorList.value = v.data.data || [];
    productList.value = p.data.data || [];
  } catch { /* noop */ }
  if (can('inventory.view')) { try { const m = await inventoryApi.meta(); warehouseList.value = m.data.data?.warehouses || []; } catch { /* noop */ } }
}

function switchTab(tb) { tab.value = tb; page.value = 1; selected.value = null; load(); }
function reload() { page.value = 1; return Promise.all([load(), loadAux()]); }

async function openDetail(id) {
  const fn = tab.value === 'orders' ? api.order : api.request;
  try { const { data } = await fn(id); selected.value = data.data; } catch { /* noop */ }
}

// approvals
async function act(id, action) {
  try {
    await api.actApproval(id, action, null);
    toast.success(t('purchase.decision_recorded'));
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

// PO lifecycle
async function poAction(action) {
  const fn = { confirm: api.confirmOrder, receive: api.receiveOrder, close: api.closeOrder, cancel: api.cancelOrder }[action];
  try {
    const { data } = await fn(selected.value.id);
    selected.value = data.data;
    toast.success(t('purchase.updated'));
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

// PR submit / convert
async function submitDoc() {
  try {
    const { data } = await api.submitRequest(selected.value.id);
    selected.value = data.data;
    toast.success(t('purchase.submitted_ok'));
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}
function openConvert() { convert.open = true; convert.vendor_id = null; convert.pr_id = selected.value.id; }
async function submitConvert() {
  convert.saving = true;
  try {
    await api.convertRequest(convert.pr_id, convert.vendor_id);
    convert.open = false;
    toast.success(t('purchase.converted_ok'));
    await Promise.all([openDetail(convert.pr_id), load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { convert.saving = false; }
}

// vendors
function openVendor(v) {
  vForm.id = v?.id ?? null; vForm.errors = {};
  vForm.data = { name: v?.name ?? '', email: v?.email ?? '', phone: v?.phone ?? '', payment_terms_days: v?.payment_terms_days ?? null, tax_id: v?.tax_id ?? '' };
  vForm.open = true;
}
async function submitVendor() {
  vForm.saving = true; vForm.errors = {};
  try {
    vForm.id ? await api.updateVendor(vForm.id, vForm.data) : await api.createVendor(vForm.data);
    toast.success(t('purchase.saved'));
    vForm.open = false;
    await Promise.all([load(), loadRefs()]);
  } catch (e) { if (e.response?.status === 422) vForm.errors = e.response.data?.errors || {}; }
  finally { vForm.saving = false; }
}
async function removeVendor(id) { try { await api.removeVendor(id); await load(); } catch { /* noop */ } }

// document create
function openCreate() {
  if (tab.value === 'vendors') return openVendor();
  docForm.error = ''; docForm.data = { vendor_id: null, warehouse_id: null, date: '', items: [{ product_id: null, name: '', quantity: 1, price: 0 }] };
  docForm.open = true;
}
function addLine() { docForm.data.items.push({ product_id: null, name: '', quantity: 1, price: 0 }); }
function onPickProduct(line, id) {
  const p = productList.value.find((x) => x.id === Number(id));
  if (!p) { line.product_id = null; return; }
  line.product_id = p.id; line.name = p.name; line.price = tab.value === 'orders' ? p.cost_price : p.cost_price;
}
const formTotal = computed(() => docForm.data.items.reduce((s, l) => s + (l.quantity || 0) * (l.price || 0), 0));

async function submitDocForm() {
  docForm.saving = true; docForm.error = '';
  try {
    const items = docForm.data.items.filter((l) => l.name && l.quantity > 0);
    if (!items.length) { docForm.error = t('purchase.need_line'); return; }
    if (tab.value === 'orders') {
      if (!docForm.data.vendor_id) { docForm.error = t('purchase.need_vendor'); return; }
      const payload = {
        vendor_id: docForm.data.vendor_id, warehouse_id: docForm.data.warehouse_id || undefined,
        expected_date: docForm.data.date || undefined,
        items: items.map((l) => ({ product_id: l.product_id, name: l.name, quantity: l.quantity, unit_price: l.price })),
      };
      await api.createOrder(payload);
    } else {
      const payload = {
        needed_by: docForm.data.date || undefined,
        items: items.map((l) => ({ product_id: l.product_id, name: l.name, quantity: l.quantity, estimated_price: l.price })),
      };
      await api.createRequest(payload);
    }
    toast.success(t('purchase.saved'));
    docForm.open = false;
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) docForm.error = e.response.data?.message || t('purchase.check_fields'); }
  finally { docForm.saving = false; }
}

const money = (v, ccy) => v == null ? '—' : new Intl.NumberFormat(undefined, { style: 'currency', currency: ccy || 'AED', maximumFractionDigits: 0 }).format(v);
const compact = (v) => v == null ? '—' : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v);

const statusClass = (s) => ({
  draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  submitted: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  converted: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  confirmed: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  received: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  closed: 'bg-slate-200 text-slate-600',
  cancelled: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');
const approvalClass = (s) => ({
  pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux(), loadRefs()]); });
</script>
