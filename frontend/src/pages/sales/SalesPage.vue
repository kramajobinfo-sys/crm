<template>
  <div class="page">

    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('sales.title') }}</h1>
        <p class="page-sub">{{ $t('sales.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('sales.refresh') }}
        </button>
        <button v-if="canCreateActive" class="btn-primary btn-sm" @click="openCreate">
          <Plus :size="12" /> {{ $t(`sales.new_${tabSingular}`) }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="stat">
        <span class="stat-label">{{ $t(s.label) }}</span>
        <span class="stat-value">
          <span v-if="s.money">{{ compact(stats[s.key]) }}</span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-3 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
      <button v-for="tb in visibleTabs" :key="tb" class="px-3 py-1.5 text-xs -mb-px border-b-2 whitespace-nowrap"
              :class="tab === tb ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
              @click="switchTab(tb)">
        {{ $t(`sales.tab.${tb}`) }}
      </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="toolbar">
        <input v-model="filters.q" class="input input-sm w-52" :placeholder="$t('sales.search')" @keyup.enter="load" />
        <select v-if="tab !== 'payments'" v-model="filters.status" class="input input-sm w-auto" @change="load">
          <option value="all">{{ $t('sales.all_statuses') }}</option>
          <option v-for="s in statusesForTab" :key="s" :value="s">{{ $t(`sales.st.${s}`) }}</option>
        </select>
      </div>
    </div>

    <!-- Products are managed under Inventory → Products (shared catalogue). -->

    <!-- ============ PAYMENTS ============ -->
    <div v-if="tab === 'payments'" class="panel">
      <TableStates :loading="loading" :empty="!rows.length" />
      <div v-if="!loading && rows.length" class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('sales.pay.no') }}</th>
              <th>{{ $t('sales.pay.customer') }}</th>
              <th class="hidden md:table-cell">{{ $t('sales.pay.invoice') }}</th>
              <th class="hidden lg:table-cell">{{ $t('sales.pay.method') }}</th>
              <th>{{ $t('sales.pay.amount') }}</th>
              <th class="hidden lg:table-cell">{{ $t('sales.pay.date') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in rows" :key="p.id">
              <td class="font-mono text-[11px]">{{ p.payment_no }}</td>
              <td class="text-ink dark:text-ink-dark">{{ p.customer?.name || '—' }}</td>
              <td class="hidden md:table-cell font-mono text-[11px] text-ink-muted">{{ p.invoice?.invoice_no || '—' }}</td>
              <td class="hidden lg:table-cell text-ink-muted">{{ $t(`sales.method.${p.method}`) }}</td>
              <td class="td-num text-ink dark:text-ink-dark">{{ money(p.amount, p.currency) }}</td>
              <td class="hidden lg:table-cell text-ink-muted">{{ formatDate(p.received_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ============ DOCUMENTS (quotations / orders / invoices) ============ -->
    <div v-else class="flex gap-3 items-start">
      <div class="panel flex-1 min-w-0">
        <TableStates :loading="loading" :empty="!rows.length" />
        <div v-if="!loading && rows.length" class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('sales.d.number') }}</th>
                <th>{{ $t('sales.d.customer') }}</th>
                <th class="hidden md:table-cell">{{ $t('sales.d.status') }}</th>
                <th>{{ $t('sales.d.total') }}</th>
                <th v-if="tab === 'invoices'" class="th-num hidden lg:table-cell">{{ $t('sales.d.balance') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in rows" :key="d.id"
                  class="cursor-pointer"
                  :class="selected?.id === d.id && 'is-selected'"
                  @click="openDetail(d.id)">
                <td class="font-mono text-[11px] text-ink dark:text-ink-dark">{{ docNo(d) }}</td>
                <td class="text-ink dark:text-ink-dark truncate max-w-[12rem]">{{ d.customer?.name || '—' }}</td>
                <td class="hidden md:table-cell">
                  <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(d.status)">{{ $t(`sales.st.${d.status}`) }}</span>
                </td>
                <td class="td-num text-ink dark:text-ink-dark">{{ money(d.grand_total, d.currency) }}</td>
                <td v-if="tab === 'invoices'" class="td-num hidden lg:table-cell"
                    :class="d.balance > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ money(d.balance, d.currency) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
          <span class="text-ink-subtle">{{ $t('sales.showing', { from: pagination.from, to: pagination.to, total: pagination.total }) }}</span>
          <div class="flex gap-1">
            <button class="btn-secondary btn-xs" :disabled="page <= 1" @click="page--; load()">‹</button>
            <button class="btn-secondary btn-xs" :disabled="page >= pagination.last_page" @click="page++; load()">›</button>
          </div>
        </div>
      </div>

      <!-- Document detail drawer -->
      <div v-if="selected" class="card w-full sm:w-[26rem] shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-16rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2 shrink-0">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark font-mono">{{ docNo(selected) }}</div>
            <div class="text-[11px] text-ink-subtle">{{ selected.customer?.name || '—' }}</div>
          </div>
          <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(selected.status)">{{ $t(`sales.st.${selected.status}`) }}</span>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <!-- Actions -->
        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 shrink-0 flex flex-wrap gap-1.5">
          <button v-if="can(`${permBase}.update`)" class="btn-secondary btn-xs" @click="openEditDoc(selected)">{{ $t('sales.edit') }}</button>
          <button v-if="tab === 'quotations' && can('quotations.view')" class="btn-secondary btn-xs" :disabled="pdfBusy" @click="downloadQuotePdf">
            <Download :size="12" /> PDF
          </button>
          <button v-if="tab === 'quotations' && selected.status === 'draft' && can('quotations.update')" class="btn-secondary btn-xs" :disabled="approvalBusy" @click="submitForApproval">Submit for approval</button>
          <span v-if="tab === 'quotations' && selected.status === 'pending_approval'" class="badge-warning self-center">Awaiting approval</span>
          <button v-if="tab === 'quotations' && selected.status === 'draft' && can('quotations.send')" class="btn-primary btn-xs" @click="sendQuotation">{{ $t('sales.send_quote') }}</button>
          <button v-if="tab === 'quotations' && !selected.converted_order_id && can('orders.create')" class="btn-primary btn-xs" @click="convertDoc">{{ $t('sales.to_order') }}</button>
          <button v-if="tab === 'orders' && !selected.converted_invoice_id && can('invoices.create')" class="btn-primary btn-xs" @click="convertDoc">{{ $t('sales.to_invoice') }}</button>
          <button v-if="tab === 'invoices' && selected.balance > 0 && can('payments.create')" class="btn-primary btn-xs" @click="openPay">{{ $t('sales.record_payment') }}</button>
          <button v-if="tab === 'invoices' && selected.balance > 0 && can('credits.apply')" class="btn-secondary btn-xs" @click="openApplyCredit">{{ $t('credits.apply_credit') }}</button>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <!-- Line items -->
          <table class="data-table">
            <tbody>
              <tr v-for="it in selected.items" :key="it.id" class="border-b border-slate-100 dark:border-slate-700/60 last:border-0">
                <td class="py-1 text-ink dark:text-ink-dark">
                  {{ it.name }} <span class="text-ink-subtle">× {{ it.quantity }}</span>
                  <span v-if="it.discount_pct > 0" class="text-emerald-600">−{{ it.discount_pct }}%</span>
                </td>
                <td class="py-1 text-right tabular-nums text-ink-muted">{{ money(it.line_total, selected.currency) }}</td>
              </tr>
            </tbody>
          </table>
          <!-- Totals -->
          <dl class="grid grid-cols-2 gap-y-1 border-t border-slate-200 dark:border-slate-700 pt-2">
            <dt class="text-ink-subtle">{{ $t('sales.subtotal') }}</dt>
            <dd class="text-right tabular-nums text-ink dark:text-ink-dark">{{ money(selected.subtotal, selected.currency) }}</dd>
            <template v-if="selected.discount_total > 0">
              <dt class="text-ink-subtle">{{ $t('sales.discount') }}</dt>
              <dd class="text-right tabular-nums text-emerald-600">−{{ money(selected.discount_total, selected.currency) }}</dd>
            </template>
            <dt class="text-ink-subtle">{{ $t('sales.tax') }}</dt>
            <dd class="text-right tabular-nums text-ink dark:text-ink-dark">{{ money(selected.tax_total, selected.currency) }}</dd>
            <dt class="text-ink dark:text-ink-dark font-medium">{{ $t('sales.grand_total') }}</dt>
            <dd class="text-right tabular-nums font-semibold text-ink dark:text-ink-dark">{{ money(selected.grand_total, selected.currency) }}</dd>
            <template v-if="tab === 'invoices'">
              <dt class="text-ink-subtle">{{ $t('sales.paid') }}</dt>
              <dd class="text-right tabular-nums text-emerald-600">{{ money(selected.amount_paid, selected.currency) }}</dd>
              <dt class="text-ink dark:text-ink-dark font-medium">{{ $t('sales.balance') }}</dt>
              <dd class="text-right tabular-nums font-semibold" :class="selected.balance > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ money(selected.balance, selected.currency) }}</dd>
            </template>
          </dl>

          <!-- Quotation signature -->
          <div v-if="tab === 'quotations' && selected.signed_at" class="border-t border-slate-200 dark:border-slate-700 pt-2">
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('sales.signed_by') }}</div>
            <div class="text-ink dark:text-ink-dark">{{ selected.signed_name }} · {{ formatDate(selected.signed_at) }}</div>
            <img v-if="selected.signature_data" :src="selected.signature_data" class="mt-1.5 h-16 border border-slate-200 dark:border-slate-700 rounded bg-white" />
          </div>

          <!-- Invoice payments list -->
          <div v-if="tab === 'invoices' && selected.payments?.length">
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('sales.payments_received') }}</div>
            <div v-for="p in selected.payments" :key="p.id" class="flex justify-between py-0.5">
              <span class="text-ink-muted">{{ $t(`sales.method.${p.method}`) }} · {{ formatDate(p.received_at) }}</span>
              <span class="tabular-nums text-ink dark:text-ink-dark">{{ money(p.amount, selected.currency) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>


    <!-- ============ DOCUMENT modal (quote/order/invoice) ============ -->
    <div v-if="docForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="docForm.open = false">
      <div class="card w-full max-w-3xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ docForm.id ? $t('sales.edit_doc') : $t(`sales.new_${tabSingular}`) }}</div>
        <div class="grid grid-cols-3 gap-2.5">
          <div><label class="label">{{ $t('sales.d.customer') }}</label>
            <select v-model="docForm.data.customer_id" class="input text-sm" @change="onCustomerChange">
              <option :value="null">—</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div v-if="tab === 'quotations'"><label class="label">{{ $t('sales.valid_until') }}</label><input v-model="docForm.data.valid_until" type="date" class="input text-sm" /></div>
          <div v-if="tab === 'invoices'"><label class="label">{{ $t('sales.due_date') }}</label><input v-model="docForm.data.due_date" type="date" class="input text-sm" /></div>
          <div v-if="tab === 'orders'"><label class="label">{{ $t('sales.expected_date') }}</label><input v-model="docForm.data.expected_date" type="date" class="input text-sm" /></div>
        </div>

        <!-- Line items -->
        <div class="mt-3">
          <div class="flex items-center mb-2 gap-2">
            <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('sales.line_items') }}</span>
            <span class="text-[10px] text-ink-subtle">{{ $t('sales.mix_items_hint') }}</span>
            <button class="btn-secondary btn-xs ml-auto" @click="addProductLine">+ {{ $t('sales.add_product') }}</button>
            <button class="btn-secondary btn-xs" @click="addCustomLine">+ {{ $t('sales.add_custom_item') }}</button>
          </div>
          <div v-if="!docForm.data.items.length" class="rounded border border-dashed border-slate-300 dark:border-slate-700 py-5 text-center text-xs text-ink-subtle">
            {{ $t('sales.no_line_items') }}
          </div>
          <div v-for="(line, i) in docForm.data.items" :key="i" class="rounded border border-slate-200 dark:border-slate-700 p-2 mb-2">
            <div class="flex gap-2 items-start">
              <span class="text-[9px] px-1.5 py-0.5 rounded shrink-0 mt-1" :class="line.product_id !== null || line._kind === 'product' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'">
                {{ line.product_id !== null || line._kind === 'product' ? $t('sales.inventory_product') : $t('sales.custom_item') }}
              </span>
              <select v-if="line.product_id !== null || line._kind === 'product'" v-model="line.product_id" class="input text-xs flex-1" @change="onPickProduct(line, line.product_id)">
                <option :value="null" disabled>{{ $t('sales.choose_product') }}</option>
                <option v-for="p in productList" :key="p.id" :value="p.id">{{ p.sku }} · {{ p.name }}</option>
              </select>
              <input v-else v-model="line.name" class="input text-xs flex-1" :placeholder="$t('sales.custom_item_name')" />
              <button class="p-1 text-ink-subtle hover:text-red-500" :title="$t('sales.remove_line')" @click="docForm.data.items.splice(i, 1)"><X :size="13" /></button>
            </div>
            <div v-if="selectedProduct(line)" class="ml-[5.5rem] mt-1 text-[10px] text-ink-subtle flex flex-wrap gap-x-3">
              <span>{{ $t('sales.sku') }}: {{ selectedProduct(line).sku }}</span>
              <span>{{ $t('sales.unit') }}: {{ selectedProduct(line).unit }}</span>
              <span>{{ $t('sales.product_type') }}: {{ selectedProduct(line).type }}</span>
              <span>{{ $t('sales.tax') }}: {{ selectedProduct(line).tax_rate?.rate ?? taxRateVal(line.tax_rate_id) }}%</span>
            </div>
            <div class="grid grid-cols-[1fr_90px_110px_85px_100px] gap-1.5 mt-2 items-end">
              <div><label class="label">{{ $t('sales.item_name') }}</label><input v-model="line.name" class="input text-xs w-full" :readonly="!!line.product_id" /></div>
              <div><label class="label">{{ $t('sales.qty') }}</label><input v-model.number="line.quantity" type="number" min="0" class="input text-xs w-full" /></div>
              <div><label class="label">{{ $t('sales.price') }}</label><input v-model.number="line.unit_price" type="number" min="0" class="input text-xs w-full" /></div>
              <div><label class="label">{{ $t('sales.discount') }}</label><input v-model.number="line.discount_pct" type="number" min="0" max="100" class="input text-xs w-full" /></div>
              <div class="text-right pb-1"><div class="text-[10px] text-ink-subtle">{{ $t('sales.line_total') }}</div><div class="text-xs font-medium tabular-nums">{{ compact(lineNet(line)) }}</div></div>
            </div>
          </div>
          <p v-if="docForm.errors.items" class="text-[11px] text-red-500">{{ $t('sales.need_line') }}</p>
          <div v-if="docForm.data.items.length" class="text-right text-xs text-ink dark:text-ink-dark mt-1 space-y-0.5">
            <div class="text-ink-subtle">{{ $t('sales.tax') }}: <span class="tabular-nums">{{ money(formTax, 'AED') }}</span></div>
            <div>{{ $t('sales.grand_total') }}: <span class="font-semibold tabular-nums">{{ money(formGrand, 'AED') }}</span></div>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="docForm.open = false">{{ $t('sales.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="docForm.saving" @click="submitDoc">{{ docForm.saving ? $t('sales.saving') : $t('sales.save') }}</button>
        </div>
      </div>
    </div>

    <!-- ============ PAY modal ============ -->
    <div v-if="payForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="payForm.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-1">{{ $t('sales.record_payment') }}</div>
        <div class="text-[11px] text-ink-subtle mb-3">{{ $t('sales.balance') }}: {{ money(selected?.balance, selected?.currency) }}</div>
        <div class="space-y-2.5">
          <div><label class="label">{{ $t('sales.pay.amount') }} *</label><input v-model.number="payForm.data.amount" type="number" min="0" step="0.01" class="input text-sm" /></div>
          <div><label class="label">{{ $t('sales.pay.method') }}</label>
            <select v-model="payForm.data.method" class="input text-sm">
              <option v-for="m in ['cash','card','bank_transfer','cheque','online']" :key="m" :value="m">{{ $t(`sales.method.${m}`) }}</option>
            </select>
          </div>
          <div><label class="label">{{ $t('sales.pay.reference') }}</label><input v-model="payForm.data.reference" class="input text-sm" /></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="payForm.open = false">{{ $t('sales.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="payForm.saving || !payForm.data.amount" @click="submitPay">{{ payForm.saving ? $t('sales.saving') : $t('sales.pay_now') }}</button>
        </div>
      </div>
    </div>

    <!-- ============ APPLY CREDIT modal ============ -->
    <div v-if="creditForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="creditForm.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-1">{{ $t('credits.apply_title') }}</div>
        <div class="text-[11px] text-ink-subtle mb-3">{{ $t('sales.balance') }}: {{ money(selected?.balance, selected?.currency) }}</div>

        <div v-if="!creditForm.options.length" class="text-[11px] text-ink-subtle py-3">{{ $t('credits.apply_none') }}</div>
        <div v-else class="space-y-2.5">
          <div>
            <label class="label">{{ $t('credits.credit_no') }} *</label>
            <select v-model="creditForm.credit_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="c in creditForm.options" :key="c.id" :value="c.id">
                {{ c.credit_no }} — {{ money(c.remaining, c.currency) }} {{ $t('credits.credit_available') }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">{{ $t('credits.apply_amount') }}</label>
            <input v-model.number="creditForm.amount" type="number" min="0" step="0.01" class="input text-sm" />
            <div class="text-[10px] text-ink-subtle mt-0.5">{{ $t('credits.apply_max') }}</div>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="creditForm.open = false">{{ $t('sales.cancel') }}</button>
          <button v-if="creditForm.options.length" class="btn-primary btn-sm"
                  :disabled="creditForm.saving || !creditForm.credit_id" @click="submitApplyCredit">
            {{ creditForm.saving ? $t('sales.saving') : $t('credits.apply_credit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, h } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/sales';
import customerApi from '@/services/customers';
import creditsApi from '@/services/credits';
import http from '@/services/http';
import { RefreshCw, Plus, X, Download } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const can = (p) => auth.can(p);

// Tiny inline loading/empty states component to avoid repeating markup.
const TableStates = (props) => props.loading
  ? h('div', { class: 'text-sm text-ink-subtle py-16 text-center' }, t('app.loading'))
  : props.empty ? h('div', { class: 'text-sm text-ink-subtle py-16 text-center' }, t('sales.empty')) : null;
TableStates.props = ['loading', 'empty'];

const allTabs = ['quotations', 'orders', 'invoices', 'payments'];
const tabPerm = { quotations: 'quotations.view', orders: 'orders.view', invoices: 'invoices.view', payments: 'payments.view' };
const visibleTabs = computed(() => allTabs.filter((tb) => can(tabPerm[tb])));

const statTiles = [
  { key: 'revenue_mtd',       label: 'sales.stat.revenue', money: true },
  { key: 'collected_mtd',     label: 'sales.stat.collected', money: true },
  { key: 'outstanding_value', label: 'sales.stat.outstanding', money: true },
  { key: 'invoices_unpaid',   label: 'sales.stat.unpaid' },
  { key: 'quotations_open',   label: 'sales.stat.quotations' },
  { key: 'orders_open',       label: 'sales.stat.orders' },
];

const tab        = ref(visibleTabs.value[0] || 'quotations');
const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ categories: [], tax_rates: [], types: [] });
const customers  = ref([]);
const productList = ref([]);
const selected   = ref(null);
const loading    = ref(false);
const page       = ref(1);
const filters    = reactive({ q: '', status: 'all', type: '' });
const docForm     = reactive({ open: false, id: null, saving: false, data: { items: [] }, errors: {} });
const payForm     = reactive({ open: false, saving: false, data: {} });
// Apply-credit modal: `options` is filled from the invoice's available credits (same
// customer + currency), so the picker can never offer one the server would reject.
const creditForm  = reactive({ open: false, saving: false, credit_id: null, amount: null, options: [] });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });

const tabSingular = computed(() => ({ quotations: 'quotation', orders: 'order', invoices: 'invoice' }[tab.value] || tab.value));
const permBase = computed(() => ({ quotations: 'quotations', orders: 'orders', invoices: 'invoices' }[tab.value] || tab.value));
const canCreateActive = computed(() => {
  const map = { quotations: 'quotations.create', orders: 'orders.create', invoices: 'invoices.create' };
  return map[tab.value] ? can(map[tab.value]) : false;
});
const statusesByTab = {
  quotations: ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'],
  orders: ['draft', 'confirmed', 'processing', 'fulfilled', 'cancelled'],
  invoices: ['draft', 'issued', 'partially_paid', 'paid', 'void'],
};
const statusesForTab = computed(() => statusesByTab[tab.value] || []);

const listFn = { quotations: api.quotations, orders: api.orders, invoices: api.invoices, payments: api.payments };

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    if (filters.q) params.q = filters.q;
    if (['quotations', 'orders', 'invoices'].includes(tab.value) && filters.status !== 'all') params.status = filters.status;
    const { data } = await listFn[tab.value](params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}

async function loadAux() {
  try {
    const [inv, pmeta] = await Promise.all([api.invoiceStats(), api.productMeta()]);
    Object.assign(stats, inv.data.data || {});
    Object.assign(meta, pmeta.data.data || {});
  } catch { /* non-critical */ }
}

async function loadRefs() {
  try {
    const [c, p] = await Promise.all([customerApi.list({ per_page: 100 }), api.products({ per_page: 100, status: 'active' })]);
    customers.value = c.data.data || [];
    productList.value = p.data.data || [];
  } catch { /* non-critical */ }
}

function switchTab(tb) { tab.value = tb; page.value = 1; selected.value = null; load(); }
function reload() { page.value = 1; return Promise.all([load(), loadAux()]); }

async function openDetail(id) {
  const fn = { quotations: api.quotation, orders: api.order, invoices: api.invoice }[tab.value];
  try { const { data } = await fn(id); selected.value = data.data; } catch { /* noop */ }
}

const docNo = (d) => d.quote_no || d.order_no || d.invoice_no || d.payment_no || `#${d.id}`;

function openCreate() {
  docForm.id = null; docForm.errors = {};
  docForm.data = { customer_id: null, valid_until: '', due_date: '', expected_date: '', items: [] };
  docForm.open = true;
}

const defaultTaxId = () => meta.tax_rates.find((r) => r.is_default)?.id ?? null;

// ---- documents ----
function openEditDoc(d) {
  docForm.id = d.id; docForm.errors = {};
  docForm.data = {
    customer_id: d.customer?.id ?? null,
    valid_until: d.valid_until ?? '', due_date: d.due_date ?? '', expected_date: d.expected_date ?? '',
    items: (d.items || []).map((it) => ({ _kind: it.product_id ? 'product' : 'custom', product_id: it.product_id, name: it.name, quantity: it.quantity, unit_price: it.unit_price, discount_pct: it.discount_pct, tax_rate_id: it.tax_rate_id })),
  };
  docForm.open = true;
}
function addProductLine() { docForm.data.items.push({ _kind: 'product', product_id: null, name: '', quantity: 1, unit_price: 0, discount_pct: 0, tax_rate_id: defaultTaxId() }); }
function addCustomLine() { docForm.data.items.push({ _kind: 'custom', product_id: null, name: '', quantity: 1, unit_price: 0, discount_pct: 0, tax_rate_id: defaultTaxId() }); }
const selectedProduct = (line) => productList.value.find((product) => product.id === Number(line.product_id));
async function onPickProduct(line, id) {
  const p = productList.value.find((x) => x.id === Number(id));
  if (!p) { line.product_id = null; return; }
  line._kind = 'product'; line.product_id = p.id; line.name = p.name; line.unit_price = p.sale_price;
  line.tax_rate_id = p.tax_rate?.id ?? defaultTaxId();
  // Price Books (convenience): pre-fill the suggested price for this customer. The value stays
  // fully editable, and any failure silently keeps the list sale_price already set above.
  if (can('price_books.view') && docForm.data.customer_id) {
    try {
      const { data } = await api.resolvePrices({ customer_id: docForm.data.customer_id, product_ids: [p.id] });
      const price = data.data?.prices?.[p.id];
      if (price !== undefined && price !== null) line.unit_price = price;
    } catch { /* keep sale_price */ }
  }
}
// When the customer changes, re-suggest prices for every product line already on the document.
async function onCustomerChange() {
  if (!can('price_books.view') || !docForm.data.customer_id) return;
  const ids = docForm.data.items.map((l) => l.product_id).filter(Boolean);
  if (!ids.length) return;
  try {
    const { data } = await api.resolvePrices({ customer_id: docForm.data.customer_id, product_ids: ids });
    const prices = data.data?.prices || {};
    let applied = 0;
    for (const l of docForm.data.items) {
      if (l.product_id && prices[l.product_id] !== undefined) { l.unit_price = prices[l.product_id]; applied += 1; }
    }
    if (data.data?.price_book && applied) toast.info(t('sales.price_book_applied', { name: data.data.price_book.name }));
  } catch { /* noop */ }
}
const taxRateVal = (id) => meta.tax_rates.find((r) => r.id === id)?.rate ?? 0;
const lineNet = (l) => Math.round((l.quantity || 0) * (l.unit_price || 0) * (1 - (l.discount_pct || 0) / 100) * 100) / 100;
const lineTax = (l) => Math.round(lineNet(l) * taxRateVal(l.tax_rate_id) / 100 * 100) / 100;
const formTax = computed(() => docForm.data.items.reduce((s, l) => s + lineTax(l), 0));
const formGrand = computed(() => docForm.data.items.reduce((s, l) => s + lineNet(l) + lineTax(l), 0));

async function submitDoc() {
  docForm.saving = true; docForm.errors = {};
  try {
    const d = docForm.data;
    const payload = { customer_id: d.customer_id || undefined, items: d.items };
    if (tab.value === 'quotations' && d.valid_until) payload.valid_until = d.valid_until;
    if (tab.value === 'invoices' && d.due_date) payload.due_date = d.due_date;
    if (tab.value === 'orders' && d.expected_date) payload.expected_date = d.expected_date;
    const create = { quotations: api.createQuotation, orders: api.createOrder, invoices: api.createInvoice }[tab.value];
    const update = { quotations: api.updateQuotation, orders: api.updateOrder, invoices: api.updateInvoice }[tab.value];
    const { data } = docForm.id ? await update(docForm.id, payload) : await create(payload);
    toast.success(t('sales.saved'));
    docForm.open = false;
    await Promise.all([load(), loadAux()]);
    if (selected.value?.id === data.data.id) selected.value = data.data;
  } catch (e) { if (e.response?.status === 422) docForm.errors = e.response.data?.errors || {}; }
  finally { docForm.saving = false; }
}

async function convertDoc() {
  const fn = tab.value === 'quotations' ? api.convertQuotation : api.convertOrder;
  try {
    await fn(selected.value.id);
    toast.success(t(tab.value === 'quotations' ? 'sales.converted_order' : 'sales.converted_invoice'));
    await Promise.all([openDetail(selected.value.id), load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

async function sendQuotation() {
  try {
    const { data } = await api.sendQuotation(selected.value.id);
    toast[data.data.emailed ? 'success' : 'warning'](data.message);
    await Promise.all([openDetail(selected.value.id), load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

const approvalBusy = ref(false);
async function submitForApproval() {
  approvalBusy.value = true;
  try {
    const { data } = await http.post(`/quotations/${selected.value.id}/submit-approval`);
    toast.success(data.message || 'Submitted for approval');
    await Promise.all([openDetail(selected.value.id), load()]);
  } catch (e) { toast.error(e.response?.data?.message || 'Could not submit for approval'); }
  finally { approvalBusy.value = false; }
}

const pdfBusy = ref(false);
async function downloadQuotePdf() {
  pdfBusy.value = true;
  try {
    const res = await http.get(`/quotations/${selected.value.id}/pdf`, { responseType: 'blob' });
    const url = URL.createObjectURL(res.data);
    const a = document.createElement('a');
    a.href = url; a.download = `quote-${docNo(selected.value)}.pdf`;
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  } catch { toast.error('Could not generate the PDF'); }
  finally { pdfBusy.value = false; }
}

function openPay() { payForm.open = true; payForm.data = { amount: selected.value.balance, method: 'bank_transfer', reference: '' }; }
async function submitPay() {
  payForm.saving = true;
  try {
    const { data } = await api.pay(selected.value.id, payForm.data);
    selected.value = data.data.invoice;
    payForm.open = false;
    toast.success(t('sales.payment_recorded'));
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { payForm.saving = false; }
}

async function openApplyCredit() {
  Object.assign(creditForm, { open: true, saving: false, credit_id: null, amount: null, options: [] });
  try {
    const { data } = await creditsApi.availableForInvoice(selected.value.id);
    creditForm.options = data.data || [];
  } catch { /* interceptor toasts */ }
}

async function submitApplyCredit() {
  creditForm.saving = true;
  try {
    await creditsApi.applyToInvoice(selected.value.id, creditForm.credit_id, creditForm.amount);
    creditForm.open = false;
    toast.success(t('credits.applied_ok'));
    // Re-fetch the invoice so amount_paid / balance / status reflect the application.
    const { data } = await api.invoice(selected.value.id);
    selected.value = data.data;
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { creditForm.saving = false; }
}

const money = (v, ccy) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { style: 'currency', currency: ccy || 'AED', maximumFractionDigits: 0 }).format(v);
const compact = (v) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v);
const formatDate = (iso) => iso ? new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' }) : '—';

const statusClass = (s) => ({
  draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  sent: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  accepted: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  expired: 'bg-slate-100 text-slate-500',
  converted: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  confirmed: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  processing: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  fulfilled: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  cancelled: 'bg-slate-100 text-slate-500',
  issued: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  partially_paid: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  void: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => {
  await Promise.all([load(), loadAux(), loadRefs()]);
  // Deep link from an Account/Deal "New quote" button: open a pre-filled quotation.
  if (route.query.new === 'quotation' && can('quotations.create')) {
    tab.value = 'quotations';
    await load();
    openCreate();
    if (route.query.customer_id) docForm.data.customer_id = Number(route.query.customer_id);
    await router.replace({ name: 'sales' });
  }
});
</script>
