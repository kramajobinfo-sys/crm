<template>
  <div>
    <!-- Toolbar -->
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <input v-model="filters.q" class="input text-sm w-52" :placeholder="$t('invproducts.search')" @keyup.enter="reload" />
      <select v-model="filters.type" class="input text-sm w-auto" @change="reload">
        <option value="">{{ $t('invproducts.all_types') }}</option>
        <option value="goods">{{ $t('invproducts.goods') }}</option>
        <option value="service">{{ $t('invproducts.service') }}</option>
      </select>
      <button v-if="can('products.create')" class="btn-primary btn-sm ml-auto" @click="openForm()">
        <Plus :size="12" /> {{ $t('invproducts.new') }}
      </button>
    </div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('invproducts.empty') }}</div>
      <table class="data-table" v-else>
        <thead>
          <tr>
            <th>{{ $t('invproducts.product') }}</th>
            <th class="hidden md:table-cell">{{ $t('invproducts.category') }}</th>
            <th class="hidden lg:table-cell">{{ $t('invproducts.preferred_supplier') }}</th>
            <th class="th-num">{{ $t('invproducts.sale_price') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in rows" :key="p.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2">
              <div class="text-ink dark:text-ink-dark">{{ p.name }}</div>
              <div class="text-[11px] text-ink-subtle font-mono">{{ p.sku }} · {{ $t(`invproducts.${p.type}`) }}</div>
            </td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ p.category?.name || '—' }}</td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-muted">{{ p.preferred_supplier?.vendor || '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-ink dark:text-ink-dark">{{ p.sale_price }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('products.update')" class="text-[11px] text-primary-600 hover:underline" @click="openForm(p)">{{ $t('invproducts.edit') }}</button>
              <button v-if="can('products.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeProduct(p.id)">{{ $t('invproducts.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
        <span class="text-ink-subtle">{{ $t('invproducts.showing', { from: pagination.from, to: pagination.to, total: pagination.total }) }}</span>
        <div class="flex gap-1">
          <button class="btn-secondary btn-xs" :disabled="page <= 1" @click="page--; load()">‹</button>
          <button class="btn-secondary btn-xs" :disabled="page >= pagination.last_page" @click="page++; load()">›</button>
        </div>
      </div>
    </div>

    <!-- Product form modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-2xl p-4 my-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ form.id ? $t('invproducts.edit_product') : $t('invproducts.new') }}</div>

        <!-- Section: Product -->
        <div class="text-[10px] tracking-wider text-ink-subtle mb-1.5">{{ $t('invproducts.sec_product') }}</div>
        <div class="grid grid-cols-2 gap-2.5 mb-3">
          <div class="col-span-2"><label class="label">{{ $t('invproducts.name') }} *</label>
            <input v-model="form.data.name" class="input text-sm" />
            <p v-if="form.errors.name" class="text-[11px] text-red-500">{{ form.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('invproducts.type') }}</label>
            <select v-model="form.data.type" class="input text-sm">
              <option value="goods">{{ $t('invproducts.goods') }}</option>
              <option value="service">{{ $t('invproducts.service') }}</option>
            </select></div>
          <div><label class="label">{{ $t('invproducts.unit') }}</label><input v-model="form.data.unit" class="input text-sm" /></div>
          <div><label class="label">{{ $t('invproducts.cost_price') }}</label><input v-model.number="form.data.cost_price" type="number" min="0" step="0.01" class="input text-sm" /></div>
          <div><label class="label">{{ $t('invproducts.sale_price') }}</label><input v-model.number="form.data.sale_price" type="number" min="0" step="0.01" class="input text-sm" /></div>
          <div><label class="label">{{ $t('invproducts.tax_rate') }}</label>
            <select v-model="form.data.tax_rate_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="r in meta.tax_rates" :key="r.id" :value="r.id">{{ r.name }} ({{ r.rate }}%)</option>
            </select></div>
          <label class="flex items-end gap-1.5 text-xs text-ink-muted pb-1.5">
            <input type="checkbox" class="rounded border-slate-300" v-model="form.data.track_inventory" /> {{ $t('invproducts.track_inventory') }}
          </label>
          <div v-if="form.data.track_inventory"><label class="label">{{ $t('invproducts.reorder_level') }}</label><input v-model.number="form.data.reorder_level" type="number" min="0" class="input text-sm" /></div>
          <label class="flex items-end gap-1.5 text-xs text-ink-muted pb-1.5">
            <input type="checkbox" class="rounded border-slate-300" v-model="form.data.is_active" /> {{ $t('invproducts.active') }}
          </label>
        </div>

        <!-- Section: Category hierarchy -->
        <div class="text-[10px] tracking-wider text-ink-subtle mb-1.5">{{ $t('invproducts.sec_category') }}</div>
        <div class="grid grid-cols-2 gap-2.5 mb-3">
          <div><label class="label">{{ $t('invproducts.category') }}</label>
            <select v-model="parentCatId" class="input text-sm" @change="childCatId = null">
              <option :value="null">—</option>
              <option v-for="c in parentCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select></div>
          <div><label class="label">{{ $t('invproducts.subcategory') }}</label>
            <select v-model="childCatId" class="input text-sm" :disabled="!childCategories.length">
              <option :value="null">{{ childCategories.length ? '—' : $t('invproducts.no_sub') }}</option>
              <option v-for="c in childCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select></div>
          <button v-if="can('products.create')" type="button" class="col-span-2 text-[11px] text-primary-600 hover:underline text-left" @click="addCategory">
            + {{ $t('invproducts.new_category') }}
          </button>
        </div>

        <!-- Section: Suppliers -->
        <div class="flex items-center mb-1.5">
          <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('invproducts.sec_suppliers') }}</span>
          <button type="button" class="text-[11px] text-primary-600 hover:underline ml-auto" @click="addSupplier">+ {{ $t('invproducts.add_supplier') }}</button>
        </div>
        <div class="mb-3 space-y-1.5">
          <div v-if="!form.data.suppliers.length" class="text-[11px] text-ink-subtle">{{ $t('invproducts.no_suppliers') }}</div>
          <div v-for="(s, i) in form.data.suppliers" :key="i" class="flex gap-1.5 items-center">
            <select v-model.number="s.vendor_id" class="input text-xs flex-1">
              <option :value="null">{{ $t('invproducts.pick_vendor') }}</option>
              <option v-for="v in meta.vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
            </select>
            <input v-model="s.supplier_sku" class="input text-xs w-24" :placeholder="$t('invproducts.supplier_sku')" />
            <input v-model.number="s.cost" type="number" min="0" step="0.01" class="input text-xs w-20" :placeholder="$t('invproducts.cost')" />
            <input v-model.number="s.lead_time_days" type="number" min="0" class="input text-xs w-16" :placeholder="$t('invproducts.lead')" />
            <label class="flex items-center gap-1 text-[11px] text-ink-muted whitespace-nowrap">
              <input type="radio" name="preferred" :checked="s.is_preferred" @change="setPreferred(i)" /> {{ $t('invproducts.preferred') }}
            </label>
            <button type="button" class="p-1 text-ink-subtle hover:text-red-500" @click="form.data.suppliers.splice(i, 1)"><X :size="12" /></button>
          </div>
        </div>

        <!-- Section: Opening stock (create only, goods only) -->
        <template v-if="!form.id && form.data.track_inventory">
          <div class="text-[10px] tracking-wider text-ink-subtle mb-1.5">{{ $t('invproducts.sec_stock') }}</div>
          <div class="grid grid-cols-4 gap-2.5 mb-2">
            <div class="col-span-2"><label class="label">{{ $t('invproducts.warehouse') }}</label>
              <select v-model="form.data.opening_stock.warehouse_id" class="input text-sm">
                <option :value="null">{{ $t('invproducts.no_opening') }}</option>
                <option v-for="w in meta.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select></div>
            <div><label class="label">{{ $t('invproducts.quantity') }}</label><input v-model.number="form.data.opening_stock.quantity" type="number" min="0" class="input text-sm" /></div>
            <div><label class="label">{{ $t('invproducts.bin') }}</label><input v-model="form.data.opening_stock.bin_location" class="input text-sm" /></div>
          </div>
        </template>

        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('invproducts.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving" @click="submit">{{ form.saving ? $t('invproducts.saving') : $t('invproducts.save') }}</button>
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
import api from '@/services/sales';
import { Plus, X } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const rows = ref([]);
const loading = ref(false);
const page = ref(1);
const filters = reactive({ q: '', type: '' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const meta = reactive({ categories: [], tax_rates: [], vendors: [], warehouses: [] });
const form = reactive({ open: false, id: null, saving: false, data: emptyForm(), errors: {} });
const parentCatId = ref(null);
const childCatId = ref(null);

function emptyForm() {
  return {
    name: '', type: 'goods', unit: 'pcs', cost_price: 0, sale_price: 0, tax_rate_id: null,
    track_inventory: true, reorder_level: 0, is_active: true,
    suppliers: [], opening_stock: { warehouse_id: null, quantity: 0, bin_location: '' },
  };
}

const parentCategories = computed(() => meta.categories.filter((c) => !c.parent_id));
const childCategories = computed(() => parentCatId.value ? meta.categories.filter((c) => c.parent_id === parentCatId.value) : []);

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25, status: 'all' };
    if (filters.q) params.q = filters.q;
    if (filters.type) params.type = filters.type;
    const { data } = await api.products(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
function reload() { page.value = 1; return load(); }
async function loadMeta() { try { const { data } = await api.productMeta(); Object.assign(meta, data.data || {}); } catch { /* noop */ } }

function openForm(p) {
  form.id = p?.id ?? null; form.errors = {};
  form.data = emptyForm();
  parentCatId.value = null; childCatId.value = null;
  if (p) {
    Object.assign(form.data, {
      name: p.name, type: p.type, unit: p.unit, cost_price: p.cost_price, sale_price: p.sale_price,
      tax_rate_id: p.tax_rate?.id ?? null, track_inventory: p.track_inventory, reorder_level: p.reorder_level,
      is_active: p.is_active,
      suppliers: (p.suppliers || []).map((s) => ({ vendor_id: s.vendor_id, supplier_sku: s.supplier_sku, cost: s.cost, lead_time_days: s.lead_time_days, is_preferred: s.is_preferred })),
    });
    // Resolve category into parent/child selects.
    const cat = meta.categories.find((c) => c.id === (p.category?.id ?? p.category_id));
    if (cat) {
      if (cat.parent_id) { parentCatId.value = cat.parent_id; childCatId.value = cat.id; }
      else { parentCatId.value = cat.id; childCatId.value = null; }
    }
  }
  form.open = true;
}

function addSupplier() { form.data.suppliers.push({ vendor_id: null, supplier_sku: '', cost: 0, lead_time_days: null, is_preferred: !form.data.suppliers.some((s) => s.is_preferred) }); }
function setPreferred(i) { form.data.suppliers.forEach((s, idx) => { s.is_preferred = idx === i; }); }

async function addCategory() {
  const name = window.prompt(t('invproducts.category_name'));
  if (!name) return;
  const code = name.toUpperCase().replace(/[^A-Z0-9]+/g, '_').slice(0, 30) + '_' + Math.floor(Math.random() * 900 + 100);
  try {
    const payload = { name, code };
    if (parentCatId.value) payload.parent_id = parentCatId.value;   // create as a sub of the selected parent
    const { data } = await api.createCategory(payload);
    await loadMeta();
    if (parentCatId.value) childCatId.value = data.data.id; else parentCatId.value = data.data.id;
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message || t('invproducts.category_failed')); }
}

async function submit() {
  form.saving = true; form.errors = {};
  try {
    const payload = { ...form.data };
    payload.category_id = childCatId.value || parentCatId.value || null;
    payload.suppliers = form.data.suppliers.filter((s) => s.vendor_id);
    // Only send opening_stock on create, with a chosen warehouse + qty.
    if (form.id || !form.data.track_inventory || !form.data.opening_stock.warehouse_id || !(form.data.opening_stock.quantity > 0)) {
      delete payload.opening_stock;
    }
    form.id ? await api.updateProduct(form.id, payload) : await api.createProduct(payload);
    toast.success(t('invproducts.saved'));
    form.open = false;
    await load();
  } catch (e) {
    if (e.response?.status === 422) { form.errors = e.response.data?.errors || {}; if (e.response.data?.message && !Object.keys(form.errors).length) toast.error(e.response.data.message); }
  } finally { form.saving = false; }
}
async function removeProduct(id) {
  if (!window.confirm(t('invproducts.confirm_delete'))) return;
  try { await api.removeProduct(id); await load(); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

onMounted(() => { load(); loadMeta(); });
</script>
