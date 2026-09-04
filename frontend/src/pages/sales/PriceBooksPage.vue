<template>
  <div class="page">

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('price_books.title') }}</h1>
        <p class="page-sub">{{ $t('price_books.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="load">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('price_books.refresh') }}
        </button>
        <button v-if="can('price_books.create')" class="btn-primary btn-sm" @click="openBook()">
          <Plus :size="12" /> {{ $t('price_books.new') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <input v-model="filters.q" class="input text-sm w-52" :placeholder="$t('price_books.search')" @keyup.enter="reload" />
      <select v-model="filters.status" class="input text-sm w-auto" @change="reload">
        <option value="">{{ $t('price_books.all') }}</option>
        <option value="active">{{ $t('price_books.active') }}</option>
        <option value="inactive">{{ $t('price_books.inactive') }}</option>
      </select>
    </div>

    <!-- List -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('price_books.empty') }}</div>
      <table class="data-table" v-else>
        <thead>
          <tr>
            <th>{{ $t('price_books.name') }}</th>
            <th>{{ $t('price_books.currency') }}</th>
            <th class="th-num">{{ $t('price_books.products') }}</th>
            <th class="hidden md:table-cell">{{ $t('price_books.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in rows" :key="b.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2">
              <div class="text-ink dark:text-ink-dark">{{ b.name }}</div>
              <div v-if="b.description" class="text-[11px] text-ink-subtle">{{ b.description }}</div>
            </td>
            <td class="px-3 py-2"><span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-surface-dark-subtle font-mono">{{ b.currency }}</span></td>
            <td class="px-3 py-2 text-right tabular-nums text-ink-muted">{{ b.entries_count ?? 0 }}</td>
            <td class="px-3 py-2 hidden md:table-cell">
              <span v-if="b.is_active" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700">{{ $t('price_books.active') }}</span>
              <span v-else class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">{{ $t('price_books.inactive') }}</span>
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('price_books.update')" class="text-[11px] text-primary-600 hover:underline" @click="openPrices(b)">{{ $t('price_books.prices') }}</button>
              <button v-if="can('price_books.update')" class="text-[11px] text-primary-600 hover:underline ml-2" @click="openBook(b)">{{ $t('price_books.edit') }}</button>
              <button v-if="can('price_books.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeBook(b.id)">{{ $t('price_books.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(d)=>{page+=d;load()}" />
    </div>

    <!-- Book form modal -->
    <div v-if="bookForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="bookForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ bookForm.id ? $t('price_books.edit_book') : $t('price_books.new') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2"><label class="label">{{ $t('price_books.name') }} *</label>
            <input v-model="bookForm.data.name" class="input text-sm" />
            <p v-if="bookForm.errors.name" class="text-[11px] text-red-500">{{ bookForm.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('price_books.currency') }} *</label>
            <select v-model="bookForm.data.currency" class="input text-sm" :disabled="!!bookForm.id && bookForm.hadEntries">
              <option value="">—</option>
              <option v-for="c in meta.currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
            </select>
            <p v-if="bookForm.errors.currency" class="text-[11px] text-red-500">{{ bookForm.errors.currency[0] }}</p>
            <p v-else-if="!!bookForm.id && bookForm.hadEntries" class="text-[11px] text-ink-subtle">{{ $t('price_books.currency_locked') }}</p></div>
          <label class="flex items-end gap-1.5 text-xs text-ink-muted pb-1.5">
            <input type="checkbox" class="rounded border-slate-300" v-model="bookForm.data.is_active" /> {{ $t('price_books.active') }}
          </label>
          <div class="col-span-2"><label class="label">{{ $t('price_books.description') }}</label>
            <input v-model="bookForm.data.description" class="input text-sm" /></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="bookForm.open = false">{{ $t('price_books.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="bookForm.saving" @click="submitBook">{{ bookForm.saving ? $t('price_books.saving') : $t('price_books.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Prices editor modal -->
    <div v-if="priceForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="priceForm.open = false">
      <div class="card w-full max-w-2xl p-4 mt-8">
        <div class="flex items-center justify-between mb-1">
          <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('price_books.prices_for', { name: priceForm.book?.name }) }}</div>
          <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-surface-dark-subtle font-mono">{{ priceForm.book?.currency }}</span>
        </div>
        <div class="text-[11px] text-ink-subtle mb-3">{{ $t('price_books.prices_hint') }}</div>
        <input v-model="priceForm.q" class="input text-sm w-full mb-2" :placeholder="$t('price_books.search_products')" />
        <div class="max-h-[50vh] overflow-y-auto border border-slate-100 dark:border-slate-700/60 rounded">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('price_books.product') }}</th>
                <th class="hidden md:table-cell th-num">{{ $t('price_books.list_price') }}</th>
                <th class="w-40 th-num">{{ $t('price_books.book_price') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in filteredProducts" :key="p.id" class="border-t border-slate-100 dark:border-slate-700/60">
                <td class="px-3 py-1.5">
                  <div class="text-ink dark:text-ink-dark">{{ p.name }}</div>
                  <div class="text-[11px] text-ink-subtle font-mono">{{ p.sku }}</div>
                </td>
                <td class="px-3 py-1.5 text-right tabular-nums text-ink-subtle hidden md:table-cell">{{ p.sale_price }}</td>
                <td class="px-3 py-1.5 text-right">
                  <input v-model.number="priceMap[p.id]" type="number" min="0" step="0.01"
                         class="input text-xs text-right w-32" :placeholder="String(p.sale_price)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex items-center justify-between mt-4">
          <div class="text-[11px] text-ink-subtle">{{ $t('price_books.entries_set', { n: setCount }) }}</div>
          <div class="flex gap-2">
            <button class="btn-secondary btn-sm" @click="priceForm.open = false">{{ $t('price_books.cancel') }}</button>
            <button class="btn-primary btn-sm" :disabled="priceForm.saving" @click="submitPrices">{{ priceForm.saving ? $t('price_books.saving') : $t('price_books.save_prices') }}</button>
          </div>
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
import api from '@/services/sales';
import { RefreshCw, Plus } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('price_books.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
  h('div', { class: 'flex gap-1' }, [
    h('button', { class: 'btn-secondary btn-xs', disabled: props.page <= 1, onClick: () => emit('go', -1) }, '‹'),
    h('button', { class: 'btn-secondary btn-xs', disabled: props.page >= props.p.last_page, onClick: () => emit('go', 1) }, '›'),
  ]),
]);
Pager.props = ['p', 'page'];
Pager.emits = ['go'];

const rows = ref([]);
const loading = ref(false);
const page = ref(1);
const filters = reactive({ q: '', status: '' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const meta = reactive({ currencies: [] });

const bookForm = reactive({ open: false, id: null, saving: false, hadEntries: false, data: {}, errors: {} });
const priceForm = reactive({ open: false, saving: false, book: null, q: '' });
const products = ref([]);
const priceMap = reactive({});   // product_id => unit_price (empty/undefined = not in book)

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    if (filters.q) params.q = filters.q;
    if (filters.status) params.status = filters.status;
    const { data } = await api.priceBooks(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
function reload() { page.value = 1; return load(); }

async function loadMeta() {
  try { const { data } = await api.priceBookMeta(); Object.assign(meta, data.data || {}); } catch { /* noop */ }
}

// --- book create/edit ---
function openBook(b) {
  bookForm.id = b?.id ?? null;
  bookForm.errors = {};
  bookForm.hadEntries = (b?.entries_count ?? 0) > 0;
  bookForm.data = {
    name: b?.name ?? '',
    currency: b?.currency ?? '',
    description: b?.description ?? '',
    is_active: b?.is_active ?? true,
  };
  bookForm.open = true;
}
async function submitBook() {
  bookForm.saving = true; bookForm.errors = {};
  try {
    bookForm.id ? await api.updatePriceBook(bookForm.id, bookForm.data) : await api.createPriceBook(bookForm.data);
    toast.success(t('price_books.saved'));
    bookForm.open = false;
    await load();
  } catch (e) {
    if (e.response?.status === 422) { bookForm.errors = e.response.data?.errors || {}; if (e.response.data?.message && !Object.keys(bookForm.errors).length) toast.error(e.response.data.message); }
  } finally { bookForm.saving = false; }
}
async function removeBook(id) {
  if (!window.confirm(t('price_books.confirm_delete'))) return;
  try { await api.removePriceBook(id); await load(); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

// --- prices editor ---
async function openPrices(b) {
  priceForm.book = b; priceForm.q = ''; priceForm.open = true;
  Object.keys(priceMap).forEach((k) => delete priceMap[k]);
  try {
    // Load the full book (with entries) and the product catalogue in parallel.
    const [full, prods] = await Promise.all([
      api.priceBook(b.id),
      api.products({ per_page: 100, status: 'active' }),
    ]);
    products.value = prods.data.data || [];
    for (const e of (full.data.data?.entries || [])) priceMap[e.product_id] = e.unit_price;
  } catch { /* interceptor surfaces the error */ }
}
const filteredProducts = computed(() => {
  const q = priceForm.q.trim().toLowerCase();
  if (!q) return products.value;
  return products.value.filter((p) => p.name.toLowerCase().includes(q) || (p.sku || '').toLowerCase().includes(q));
});
const setCount = computed(() => Object.values(priceMap).filter((v) => v !== '' && v !== null && v !== undefined).length);
async function submitPrices() {
  priceForm.saving = true;
  try {
    const entries = Object.entries(priceMap)
      .filter(([, v]) => v !== '' && v !== null && v !== undefined && !Number.isNaN(Number(v)))
      .map(([product_id, unit_price]) => ({ product_id: Number(product_id), unit_price: Number(unit_price) }));
    await api.syncPriceBookEntries(priceForm.book.id, entries);
    toast.success(t('price_books.prices_saved'));
    priceForm.open = false;
    await load();
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { priceForm.saving = false; }
}

onMounted(() => { load(); loadMeta(); });
</script>
