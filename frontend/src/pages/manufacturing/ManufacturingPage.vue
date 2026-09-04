<template>
  <div class="page">

    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('mfg.title') }}</h1>
        <p class="page-sub">{{ $t('mfg.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="load">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('mfg.refresh') }}
        </button>
        <button v-if="tab === 'boms' && can('manufacturing.manage')" class="btn-primary btn-sm" @click="openBom()">
          <Plus :size="12" /> {{ $t('mfg.define_bom') }}
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-3 border-b border-slate-200 dark:border-slate-700">
      <button v-for="tb in ['boms', 'builds']" :key="tb" class="px-3 py-1.5 text-xs -mb-px border-b-2"
              :class="tab === tb ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
              @click="switchTab(tb)">
        {{ $t(`mfg.tab.${tb}`) }}
      </button>
    </div>

    <!-- ===== BOMs ===== -->
    <div v-if="tab === 'boms'" class="panel">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="empty">{{ $t('mfg.empty_boms') }}</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('mfg.finished_good') }}</th>
            <th class="hidden md:table-cell">{{ $t('mfg.category') }}</th>
            <th class="th-num">{{ $t('mfg.components') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in rows" :key="p.id">
            <td>
              <div class="text-ink dark:text-ink-dark">{{ p.name }}</div>
              <div class="text-[11px] text-ink-subtle font-mono">{{ p.sku }}</div>
            </td>
            <td class="hidden md:table-cell text-ink-muted">{{ p.category || '—' }}</td>
            <td class="td-num text-ink-muted">{{ p.components_count }}</td>
            <td class="text-right whitespace-nowrap">
              <button v-if="can('manufacturing.build')" class="text-[11px] text-primary-600 hover:underline" @click="openBuild(p)">{{ $t('mfg.build') }}</button>
              <button v-if="can('manufacturing.manage')" class="text-[11px] text-primary-600 hover:underline ml-2" @click="openBom(p)">{{ $t('mfg.edit_bom') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ===== BUILDS ===== -->
    <div v-else class="panel">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="empty">{{ $t('mfg.empty_builds') }}</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('mfg.build_no') }}</th>
            <th>{{ $t('mfg.finished_good') }}</th>
            <th class="th-num">{{ $t('mfg.qty') }}</th>
            <th class="hidden md:table-cell">{{ $t('mfg.warehouse') }}</th>
            <th class="th-num hidden lg:table-cell">{{ $t('mfg.total_cost') }}</th>
            <th class="hidden lg:table-cell">{{ $t('mfg.when') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in rows" :key="b.id" class="cursor-pointer" @click="openBuildDetail(b.id)">
            <td class="font-mono text-[11px] text-ink dark:text-ink-dark">{{ b.build_no }}</td>
            <td class="text-ink dark:text-ink-dark">{{ b.product?.name }}</td>
            <td class="td-num">{{ b.quantity }}</td>
            <td class="hidden md:table-cell text-ink-muted">{{ b.warehouse?.name }}</td>
            <td class="td-num text-ink-muted hidden lg:table-cell">{{ b.total_cost }}</td>
            <td class="hidden lg:table-cell text-ink-subtle">{{ b.built_human }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- BOM editor modal -->
    <div v-if="bomForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="bomForm.open = false">
      <div class="card w-full max-w-xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('mfg.bom_for') }}</div>
        <div class="mb-3">
          <label class="label">{{ $t('mfg.finished_good') }}</label>
          <select v-model="bomForm.product_id" class="input text-sm" :disabled="bomForm.locked" @change="onFinishedChange">
            <option :value="null">{{ $t('mfg.pick_product') }}</option>
            <option v-for="p in meta.products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
          </select>
        </div>
        <div class="flex items-center mb-1.5">
          <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('mfg.components') }}</span>
          <button class="text-[11px] text-primary-600 hover:underline ml-auto" @click="bomForm.lines.push({ component_product_id: null, quantity: 1 })">+ {{ $t('mfg.add_component') }}</button>
        </div>
        <div class="space-y-1.5">
          <div v-if="!bomForm.lines.length" class="text-[11px] text-ink-subtle">{{ $t('mfg.no_components') }}</div>
          <div v-for="(l, i) in bomForm.lines" :key="i" class="flex gap-1.5 items-center">
            <select v-model.number="l.component_product_id" class="input text-xs flex-1">
              <option :value="null">{{ $t('mfg.pick_component') }}</option>
              <option v-for="p in meta.products" :key="p.id" :value="p.id" :disabled="p.id === bomForm.product_id">{{ p.name }}</option>
            </select>
            <input v-model.number="l.quantity" type="number" min="0" step="0.001" class="input text-xs w-24" :placeholder="$t('mfg.qty_per_unit')" />
            <button class="p-1 text-ink-subtle hover:text-red-500" @click="bomForm.lines.splice(i, 1)"><X :size="12" /></button>
          </div>
        </div>
        <p class="text-[11px] text-ink-subtle mt-1">{{ $t('mfg.qty_hint') }}</p>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="bomForm.open = false">{{ $t('mfg.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="bomForm.saving || !bomForm.product_id" @click="submitBom">{{ bomForm.saving ? $t('mfg.saving') : $t('mfg.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Build modal -->
    <div v-if="buildForm.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="buildForm.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-1">{{ $t('mfg.build') }}: {{ buildForm.product?.name }}</div>
        <div class="text-[11px] text-ink-subtle mb-3">{{ $t('mfg.build_hint') }}</div>
        <div class="space-y-2.5">
          <div><label class="label">{{ $t('mfg.warehouse') }}</label>
            <select v-model="buildForm.warehouse_id" class="input text-sm" @change="checkAvailability">
              <option :value="null">—</option>
              <option v-for="w in meta.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select></div>
          <div><label class="label">{{ $t('mfg.quantity') }}</label>
            <input v-model.number="buildForm.quantity" type="number" min="1" class="input text-sm" @input="checkAvailability" /></div>
          <div v-if="buildForm.avail" class="text-[11px]" :class="buildForm.avail.shortfalls.length ? 'text-red-600' : 'text-emerald-600'">
            <span v-if="!buildForm.avail.shortfalls.length">{{ $t('mfg.buildable', { n: buildForm.avail.buildable }) }}</span>
            <div v-else>
              <div>{{ $t('mfg.short') }}:</div>
              <div v-for="(s, i) in buildForm.avail.shortfalls" :key="i">• {{ s.component }} — {{ $t('mfg.need_have', { need: s.required, have: s.available }) }}</div>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="buildForm.open = false">{{ $t('mfg.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="buildForm.saving || !canBuild" @click="submitBuild">{{ buildForm.saving ? $t('mfg.building') : $t('mfg.do_build') }}</button>
        </div>
      </div>
    </div>

    <!-- Build detail drawer -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="detail = null">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="flex items-start justify-between mb-2">
          <div>
            <div class="text-sm font-medium text-ink dark:text-ink-dark font-mono">{{ detail.build_no }}</div>
            <div class="text-[11px] text-ink-subtle">{{ detail.product?.name }} × {{ detail.quantity }} @ {{ detail.warehouse?.name }}</div>
          </div>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="detail = null"><X :size="14" /></button>
        </div>
        <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('mfg.consumed') }}</div>
        <div class="text-xs">
          <div v-for="it in detail.items" :key="it.component_product_id" class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <span class="text-ink dark:text-ink-dark">{{ it.name }} <span class="text-ink-subtle font-mono">{{ it.sku }}</span></span>
            <span class="tabular-nums text-ink-muted">{{ it.quantity }} × {{ it.unit_cost }}</span>
          </div>
        </div>
        <div class="flex justify-between text-xs mt-2 pt-2 border-t border-slate-200 dark:border-slate-700">
          <span class="text-ink-subtle">{{ $t('mfg.unit_cost') }} {{ detail.unit_cost }}</span>
          <span class="font-semibold text-ink dark:text-ink-dark">{{ $t('mfg.total_cost') }}: {{ detail.total_cost }}</span>
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
import api from '@/services/manufacturing';
import { RefreshCw, Plus, X } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const tab = ref('boms');
const rows = ref([]);
const loading = ref(false);
const meta = reactive({ products: [], warehouses: [] });
const detail = ref(null);
const bomForm = reactive({ open: false, product_id: null, locked: false, saving: false, lines: [] });
const buildForm = reactive({ open: false, product: null, warehouse_id: null, quantity: 1, saving: false, avail: null });

const canBuild = computed(() => buildForm.warehouse_id && buildForm.quantity > 0 && buildForm.avail && !buildForm.avail.shortfalls.length);

async function load() {
  loading.value = true;
  try {
    const { data } = tab.value === 'boms' ? await api.boms() : await api.builds();
    rows.value = data.data || [];
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
function switchTab(tb) { tab.value = tb; rows.value = []; load(); }
async function loadMeta() { try { const { data } = await api.meta(); Object.assign(meta, data.data || {}); } catch { /* noop */ } }

// --- BOM editor ---
async function openBom(p) {
  bomForm.open = true; bomForm.saving = false;
  bomForm.product_id = p?.id ?? null;
  bomForm.locked = !!p;
  bomForm.lines = [];
  if (p) {
    try { const { data } = await api.bom(p.id); bomForm.lines = (data.data.items || []).map((i) => ({ component_product_id: i.component_product_id, quantity: i.quantity })); }
    catch { /* noop */ }
  }
}
async function onFinishedChange() {
  bomForm.lines = [];
  if (!bomForm.product_id) return;
  try { const { data } = await api.bom(bomForm.product_id); bomForm.lines = (data.data.items || []).map((i) => ({ component_product_id: i.component_product_id, quantity: i.quantity })); }
  catch { /* noop */ }
}
async function submitBom() {
  bomForm.saving = true;
  try {
    const lines = bomForm.lines.filter((l) => l.component_product_id && l.quantity > 0);
    await api.setBom(bomForm.product_id, lines);
    toast.success(t('mfg.bom_saved'));
    bomForm.open = false;
    await load();
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message || t('mfg.bom_failed')); }
  finally { bomForm.saving = false; }
}

// --- Build ---
function openBuild(p) {
  buildForm.open = true; buildForm.saving = false; buildForm.product = p;
  buildForm.warehouse_id = meta.warehouses.find((w) => w.is_default)?.id ?? meta.warehouses[0]?.id ?? null;
  buildForm.quantity = 1; buildForm.avail = null;
  checkAvailability();
}
async function checkAvailability() {
  if (!buildForm.product || !buildForm.warehouse_id || !(buildForm.quantity > 0)) { buildForm.avail = null; return; }
  try { const { data } = await api.availability(buildForm.product.id, buildForm.warehouse_id, buildForm.quantity); buildForm.avail = data.data; }
  catch { buildForm.avail = null; }
}
async function submitBuild() {
  buildForm.saving = true;
  try {
    await api.runBuild({ product_id: buildForm.product.id, warehouse_id: buildForm.warehouse_id, quantity: buildForm.quantity });
    toast.success(t('mfg.build_done'));
    buildForm.open = false;
    if (tab.value === 'builds') await load();
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
  finally { buildForm.saving = false; }
}

async function openBuildDetail(id) { try { const { data } = await api.build(id); detail.value = data.data; } catch { /* noop */ } }

onMounted(() => { load(); loadMeta(); });
</script>
