<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">

    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('inventory.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('inventory.subtitle') }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('inventory.refresh') }}
        </button>
        <button v-if="tab === 'warehouses' && can('warehouses.create')" class="btn-primary text-xs px-3 py-1.5" @click="openWarehouse()">
          <Plus :size="12" /> {{ $t('inventory.new_warehouse') }}
        </button>
        <button v-if="tab === 'transfers' && can('inventory.transfer')" class="btn-primary text-xs px-3 py-1.5" @click="openTransfer()">
          <Plus :size="12" /> {{ $t('inventory.new_transfer') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="card p-3">
        <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">{{ $t(s.label) }}</div>
        <div class="text-lg font-semibold mt-0.5" :class="(s.key === 'low_stock' && stats.low_stock) || (s.key === 'out_of_stock' && stats.out_of_stock) ? 'text-amber-600' : 'text-ink dark:text-ink-dark'">
          <span v-if="s.money">{{ compact(stats[s.key]) }}</span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-3 border-b border-slate-200 dark:border-slate-700">
      <button v-for="tb in tabs" :key="tb" class="px-3 py-1.5 text-xs -mb-px border-b-2"
              :class="tab === tb ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
              @click="switchTab(tb)">
        {{ $t(`inventory.tab.${tb}`) }}
      </button>
    </div>

    <!-- Filters -->
    <div v-if="tab === 'stock' || tab === 'movements'" class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <input v-if="tab === 'stock'" v-model="filters.q" class="input text-sm w-52" :placeholder="$t('inventory.search')" @keyup.enter="load" />
      <select v-model="filters.warehouse_id" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('inventory.all_warehouses') }}</option>
        <option v-for="w in meta.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
      </select>
      <select v-if="tab === 'stock'" v-model="filters.filter" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('inventory.all_stock') }}</option>
        <option value="low">{{ $t('inventory.low') }}</option>
        <option value="out">{{ $t('inventory.out') }}</option>
      </select>
    </div>

    <!-- ===== PRODUCTS ===== -->
    <ProductsTab v-if="tab === 'products'" />

    <!-- ===== STOCK ===== -->
    <div v-if="tab === 'stock'" class="card overflow-hidden">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('inventory.empty') }}</div>
      <table v-else class="w-full text-sm">
        <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
          <tr>
            <th class="text-left font-medium px-3 py-2">{{ $t('inventory.s.product') }}</th>
            <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('inventory.s.warehouse') }}</th>
            <th class="text-right font-medium px-3 py-2">{{ $t('inventory.s.on_hand') }}</th>
            <th class="text-right font-medium px-3 py-2 hidden lg:table-cell">{{ $t('inventory.s.available') }}</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2">
              <div class="text-ink dark:text-ink-dark">{{ r.product?.name }}</div>
              <div class="text-[11px] text-ink-subtle font-mono">{{ r.product?.sku }}</div>
            </td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ r.warehouse?.name }}</td>
            <td class="px-3 py-2 text-right tabular-nums">
              <span :class="r.is_out ? 'text-red-600' : r.is_low ? 'text-amber-600' : 'text-ink dark:text-ink-dark'">{{ r.quantity }}</span>
              <span class="text-[10px] text-ink-subtle ml-1">{{ r.product?.unit }}</span>
            </td>
            <td class="px-3 py-2 text-right hidden lg:table-cell tabular-nums text-ink-muted">{{ r.available }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('inventory.adjust')" class="text-[11px] text-primary-600 hover:underline" @click="openMove(r, 'receive')">{{ $t('inventory.receive') }}</button>
              <button v-if="can('inventory.adjust')" class="text-[11px] text-primary-600 hover:underline ml-2" @click="openMove(r, 'issue')">{{ $t('inventory.issue') }}</button>
              <button v-if="can('inventory.adjust')" class="text-[11px] text-ink-muted hover:underline ml-2" @click="openAdjust(r)">{{ $t('inventory.adjust') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(d)=>{page+=d;load()}" />
    </div>

    <!-- ===== WAREHOUSES ===== -->
    <div v-else-if="tab === 'warehouses'" class="card overflow-hidden">
      <div v-if="!warehouses.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('inventory.empty') }}</div>
      <table v-else class="w-full text-sm">
        <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
          <tr>
            <th class="text-left font-medium px-3 py-2">{{ $t('inventory.w.name') }}</th>
            <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('inventory.w.address') }}</th>
            <th class="text-right font-medium px-3 py-2">{{ $t('inventory.w.skus') }}</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="w in warehouses" :key="w.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2">
              <span class="text-ink dark:text-ink-dark">{{ w.name }}</span>
              <span v-if="w.is_default" class="text-[10px] px-1.5 py-0.5 rounded bg-primary-100 text-primary-700 ml-1.5">{{ $t('inventory.default') }}</span>
              <div class="text-[11px] text-ink-subtle font-mono">{{ w.code }}</div>
            </td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ w.address || '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-ink-muted">{{ w.stock_items_count ?? 0 }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('warehouses.update')" class="text-[11px] text-primary-600 hover:underline" @click="openWarehouse(w)">{{ $t('inventory.edit') }}</button>
              <button v-if="can('warehouses.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeWarehouse(w.id)">{{ $t('inventory.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ===== TRANSFERS ===== -->
    <div v-else-if="tab === 'transfers'" class="flex gap-3 items-start">
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('inventory.empty') }}</div>
        <table v-else class="w-full text-sm">
          <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
            <tr>
              <th class="text-left font-medium px-3 py-2">{{ $t('inventory.t.number') }}</th>
              <th class="text-left font-medium px-3 py-2">{{ $t('inventory.t.route') }}</th>
              <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('inventory.t.status') }}</th>
              <th class="text-right font-medium px-3 py-2">{{ $t('inventory.t.items') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tr in rows" :key="tr.id"
                class="border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
                :class="selected?.id === tr.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
                @click="openTransferDetail(tr.id)">
              <td class="px-3 py-2 font-mono text-[11px] text-ink dark:text-ink-dark">{{ tr.transfer_no }}</td>
              <td class="px-3 py-2 text-ink dark:text-ink-dark text-xs">{{ tr.from_warehouse?.code }} → {{ tr.to_warehouse?.code }}</td>
              <td class="px-3 py-2 hidden md:table-cell"><span class="text-[10px] px-1.5 py-0.5 rounded" :class="transferStatusClass(tr.status)">{{ $t(`inventory.ts.${tr.status}`) }}</span></td>
              <td class="px-3 py-2 text-right tabular-nums text-ink-muted">{{ tr.items_count ?? 0 }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="selected" class="card w-full sm:w-96 shrink-0 flex flex-col overflow-hidden">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark font-mono">{{ selected.transfer_no }}</div>
            <div class="text-[11px] text-ink-subtle">{{ selected.from_warehouse?.name }} → {{ selected.to_warehouse?.name }}</div>
          </div>
          <span class="text-[10px] px-1.5 py-0.5 rounded" :class="transferStatusClass(selected.status)">{{ $t(`inventory.ts.${selected.status}`) }}</span>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>
        <div v-if="can('inventory.transfer')" class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 flex gap-1.5">
          <button v-if="selected.status === 'draft'" class="btn-primary text-[11px] px-2 py-1" @click="transferAction('ship')">{{ $t('inventory.ship') }}</button>
          <button v-if="selected.status === 'in_transit'" class="btn-primary text-[11px] px-2 py-1" @click="transferAction('receive')">{{ $t('inventory.receive_transfer') }}</button>
          <button v-if="['draft','in_transit'].includes(selected.status)" class="btn-secondary text-[11px] px-2 py-1 text-red-600" @click="transferAction('cancel')">{{ $t('inventory.cancel_transfer') }}</button>
        </div>
        <div class="flex-1 overflow-y-auto p-3 text-xs">
          <div v-for="it in selected.items" :key="it.id" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <span class="text-ink dark:text-ink-dark">{{ it.name }} <span class="text-ink-subtle font-mono">{{ it.sku }}</span></span>
            <span class="tabular-nums text-ink-muted">{{ it.quantity }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MOVEMENTS ===== -->
    <div v-else-if="tab === 'movements'" class="card overflow-hidden">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('inventory.empty') }}</div>
      <table v-else class="w-full text-sm">
        <thead class="text-xs text-ink-subtle bg-slate-50 dark:bg-surface-dark-subtle">
          <tr>
            <th class="text-left font-medium px-3 py-2">{{ $t('inventory.m.type') }}</th>
            <th class="text-left font-medium px-3 py-2">{{ $t('inventory.s.product') }}</th>
            <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('inventory.s.warehouse') }}</th>
            <th class="text-right font-medium px-3 py-2">{{ $t('inventory.m.qty') }}</th>
            <th class="text-right font-medium px-3 py-2 hidden lg:table-cell">{{ $t('inventory.m.balance') }}</th>
            <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">{{ $t('inventory.m.when') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in rows" :key="m.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2"><span class="text-[10px] px-1.5 py-0.5 rounded" :class="moveTypeClass(m.type)">{{ $t(`inventory.mt.${m.type}`) }}</span></td>
            <td class="px-3 py-2 text-ink dark:text-ink-dark">{{ m.product?.name }} <span class="text-[11px] text-ink-subtle font-mono">{{ m.product?.sku }}</span></td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ m.warehouse?.name }}</td>
            <td class="px-3 py-2 text-right tabular-nums" :class="m.quantity < 0 ? 'text-red-600' : 'text-emerald-600'">{{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}</td>
            <td class="px-3 py-2 text-right hidden lg:table-cell tabular-nums text-ink-muted">{{ m.balance_after }}</td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-subtle">{{ m.occurred_human }}</td>
          </tr>
        </tbody>
      </table>
      <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(d)=>{page+=d;load()}" />
    </div>

    <!-- Move / adjust modal -->
    <div v-if="moveForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="moveForm.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-1">{{ $t(`inventory.${moveForm.kind}`) }}</div>
        <div class="text-[11px] text-ink-subtle mb-3">{{ moveForm.label }} · {{ $t('inventory.s.on_hand') }} {{ moveForm.current }}</div>
        <div class="space-y-2.5">
          <div v-if="moveForm.kind === 'adjust'">
            <label class="label">{{ $t('inventory.new_qty') }}</label>
            <input v-model.number="moveForm.value" type="number" class="input text-sm" />
          </div>
          <div v-else>
            <label class="label">{{ $t('inventory.quantity') }}</label>
            <input v-model.number="moveForm.value" type="number" min="0" class="input text-sm" />
          </div>
          <div><label class="label">{{ $t('inventory.note') }}</label><input v-model="moveForm.note" class="input text-sm" /></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="moveForm.open = false">{{ $t('inventory.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="moveForm.saving" @click="submitMove">{{ moveForm.saving ? $t('inventory.saving') : $t('inventory.confirm') }}</button>
        </div>
      </div>
    </div>

    <!-- Warehouse modal -->
    <div v-if="whForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="whForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ whForm.id ? $t('inventory.edit_warehouse') : $t('inventory.new_warehouse') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div><label class="label">{{ $t('inventory.w.name') }} *</label><input v-model="whForm.data.name" class="input text-sm" />
            <p v-if="whForm.errors.name" class="text-[11px] text-red-500">{{ whForm.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('inventory.w.code') }} *</label><input v-model="whForm.data.code" class="input text-sm" />
            <p v-if="whForm.errors.code" class="text-[11px] text-red-500">{{ whForm.errors.code[0] }}</p></div>
          <div class="col-span-2"><label class="label">{{ $t('inventory.w.address') }}</label><input v-model="whForm.data.address" class="input text-sm" /></div>
          <label class="col-span-2 flex items-center gap-1.5 text-xs text-ink-muted">
            <input type="checkbox" class="rounded border-slate-300" v-model="whForm.data.is_default" /> {{ $t('inventory.set_default') }}
          </label>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="whForm.open = false">{{ $t('inventory.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="whForm.saving" @click="submitWarehouse">{{ whForm.saving ? $t('inventory.saving') : $t('inventory.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Transfer create modal -->
    <div v-if="trForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="trForm.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('inventory.new_transfer') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div><label class="label">{{ $t('inventory.from') }} *</label>
            <select v-model="trForm.data.from_warehouse_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="w in meta.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div><label class="label">{{ $t('inventory.to') }} *</label>
            <select v-model="trForm.data.to_warehouse_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="w in meta.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <div class="flex items-center mb-1">
            <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('inventory.items') }}</span>
            <button class="text-[11px] text-primary-600 hover:underline ml-auto" @click="trForm.data.items.push({ product_id: null, quantity: 1 })">+ {{ $t('inventory.add_line') }}</button>
          </div>
          <div v-for="(line, i) in trForm.data.items" :key="i" class="flex gap-1.5 mb-1.5 items-center">
            <select v-model.number="line.product_id" class="input text-xs flex-1">
              <option :value="null">—</option>
              <option v-for="p in productList" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-model.number="line.quantity" type="number" min="0" class="input text-xs w-20" :placeholder="$t('inventory.qty')" />
            <button class="p-1 text-ink-subtle hover:text-red-500" @click="trForm.data.items.splice(i, 1)"><X :size="12" /></button>
          </div>
          <p v-if="trForm.error" class="text-[11px] text-red-500">{{ trForm.error }}</p>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="trForm.open = false">{{ $t('inventory.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="trForm.saving" @click="submitTransfer">{{ trForm.saving ? $t('inventory.saving') : $t('inventory.save') }}</button>
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
import api from '@/services/inventory';
import salesApi from '@/services/sales';
import ProductsTab from './ProductsTab.vue';
import { RefreshCw, Plus, X } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('inventory.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
  h('div', { class: 'flex gap-1' }, [
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page <= 1, onClick: () => emit('go', -1) }, '‹'),
    h('button', { class: 'btn-secondary text-xs px-2 py-0.5', disabled: props.page >= props.p.last_page, onClick: () => emit('go', 1) }, '›'),
  ]),
]);
Pager.props = ['p', 'page'];
Pager.emits = ['go'];

const tabs = ['products', 'stock', 'warehouses', 'transfers', 'movements'];
const statTiles = [
  { key: 'warehouses',   label: 'inventory.stat.warehouses' },
  { key: 'tracked_skus', label: 'inventory.stat.skus' },
  { key: 'low_stock',    label: 'inventory.stat.low' },
  { key: 'out_of_stock', label: 'inventory.stat.out' },
  { key: 'stock_value',  label: 'inventory.stat.value', money: true },
];

const tab        = ref('stock');
const rows       = ref([]);
const warehouses = ref([]);
const productList = ref([]);
const stats      = reactive({});
const meta       = reactive({ warehouses: [] });
const selected   = ref(null);
const loading    = ref(false);
const page       = ref(1);
const filters    = reactive({ q: '', warehouse_id: '', filter: '' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const moveForm = reactive({ open: false, kind: 'receive', saving: false, item: null, value: 0, note: '', current: 0, label: '' });
const whForm   = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });
const trForm   = reactive({ open: false, saving: false, error: '', data: { items: [] } });

async function load() {
  if (tab.value === 'products') { loading.value = false; return; }   // ProductsTab self-loads
  loading.value = true;
  try {
    if (tab.value === 'warehouses') { const { data } = await api.warehouses(); warehouses.value = data.data || []; return; }
    const params = { page: page.value, per_page: 25 };
    if (tab.value === 'stock') {
      if (filters.q) params.q = filters.q;
      if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
      if (filters.filter) params.filter = filters.filter;
    } else if (tab.value === 'movements') {
      if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
    }
    const fn = { stock: api.stock, movements: api.movements, transfers: api.transfers }[tab.value];
    const { data } = await fn(params);
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
async function loadProducts() {
  try { const { data } = await salesApi.products({ per_page: 100, status: 'active' }); productList.value = data.data || []; } catch { /* noop */ }
}

function switchTab(tb) { tab.value = tb; page.value = 1; selected.value = null; load(); }
function reload() { page.value = 1; return Promise.all([load(), loadAux()]); }

// --- stock moves ---
function openMove(item, kind) {
  moveForm.open = true; moveForm.kind = kind; moveForm.item = item; moveForm.value = 0; moveForm.note = '';
  moveForm.current = item.quantity; moveForm.label = `${item.product?.name} @ ${item.warehouse?.name}`;
}
function openAdjust(item) {
  moveForm.open = true; moveForm.kind = 'adjust'; moveForm.item = item; moveForm.value = item.quantity; moveForm.note = '';
  moveForm.current = item.quantity; moveForm.label = `${item.product?.name} @ ${item.warehouse?.name}`;
}
async function submitMove() {
  moveForm.saving = true;
  try {
    const it = moveForm.item;
    if (moveForm.kind === 'adjust') {
      await api.adjust({ product_id: it.product.id, warehouse_id: it.warehouse.id, value: moveForm.value, mode: 'set', note: moveForm.note || undefined });
    } else {
      await api.move({ product_id: it.product.id, warehouse_id: it.warehouse.id, direction: moveForm.kind, quantity: moveForm.value, note: moveForm.note || undefined });
    }
    toast.success(t('inventory.stock_updated'));
    moveForm.open = false;
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { moveForm.saving = false; }
}

// --- warehouses ---
function openWarehouse(w) {
  whForm.id = w?.id ?? null; whForm.errors = {};
  whForm.data = { name: w?.name ?? '', code: w?.code ?? '', address: w?.address ?? '', is_default: w?.is_default ?? false };
  whForm.open = true;
}
async function submitWarehouse() {
  whForm.saving = true; whForm.errors = {};
  try {
    whForm.id ? await api.updateWarehouse(whForm.id, whForm.data) : await api.createWarehouse(whForm.data);
    toast.success(t('inventory.saved'));
    whForm.open = false;
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) whForm.errors = e.response.data?.errors || {}; }
  finally { whForm.saving = false; }
}
async function removeWarehouse(id) {
  try { await api.removeWarehouse(id); await Promise.all([load(), loadAux()]); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

// --- transfers ---
async function openTransferDetail(id) { try { const { data } = await api.transfer(id); selected.value = data.data; } catch { /* noop */ } }
function openTransfer() { trForm.open = true; trForm.error = ''; trForm.data = { from_warehouse_id: null, to_warehouse_id: null, items: [{ product_id: null, quantity: 1 }] }; }
async function submitTransfer() {
  trForm.saving = true; trForm.error = '';
  try {
    const items = trForm.data.items.filter((l) => l.product_id && l.quantity > 0);
    if (!items.length) { trForm.error = t('inventory.need_item'); return; }
    await api.createTransfer({ ...trForm.data, items });
    toast.success(t('inventory.saved'));
    trForm.open = false;
    await Promise.all([load(), loadAux()]);
  } catch (e) {
    if (e.response?.status === 422) trForm.error = e.response.data?.message || t('inventory.check_fields');
  } finally { trForm.saving = false; }
}
async function transferAction(action) {
  try {
    const { data } = await api.transferAction(selected.value.id, action);
    selected.value = data.data;
    toast.success(t('inventory.transfer_updated'));
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

const compact = (v) => v == null ? '—' : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v);

const transferStatusClass = (s) => ({
  draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  in_transit: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  received: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  cancelled: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');
const moveTypeClass = (ty) => ({
  receipt: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  purchase: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  transfer_in: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  issue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  sale: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  transfer_out: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  adjustment: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
}[ty] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux(), loadProducts()]); });
</script>
