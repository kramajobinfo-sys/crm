<template>
  <div class="page">

    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('kb.title') }}</h1>
        <p class="page-sub">{{ $t('kb.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="reload">
          <RefreshCw :size="14" :class="loading && 'animate-spin'" /> {{ $t('kb.refresh') }}
        </button>
        <button v-if="can('kb.create')" class="btn-primary btn-sm" @click="openArticle()">
          <Plus :size="14" /> {{ $t('kb.new') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="toolbar">
        <input v-model="filters.q" class="input input-sm w-52" :placeholder="$t('kb.search')" @keyup.enter="reload" />
        <select v-model="filters.category_id" class="input input-sm w-auto" @change="reload">
          <option value="">{{ $t('kb.all_categories') }}</option>
          <option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filters.status" class="input input-sm w-auto" @change="reload">
          <option value="all">{{ $t('kb.all_statuses') }}</option>
          <option value="draft">{{ $t('kb.draft') }}</option>
          <option value="published">{{ $t('kb.published') }}</option>
        </select>
        <select v-model="filters.visibility" class="input input-sm w-auto" @change="reload">
          <option value="all">{{ $t('kb.all_visibility') }}</option>
          <option value="internal">{{ $t('kb.internal') }}</option>
          <option value="public">{{ $t('kb.public') }}</option>
        </select>
      </div>
    </div>

    <!-- List -->
    <div class="panel">
      <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
      <div v-else-if="!rows.length" class="empty">{{ $t('kb.empty') }}</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('kb.article') }}</th>
            <th class="hidden md:table-cell">{{ $t('kb.category') }}</th>
            <th>{{ $t('kb.status') }}</th>
            <th class="hidden lg:table-cell">{{ $t('kb.visibility') }}</th>
            <th class="th-num hidden lg:table-cell">{{ $t('kb.views') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in rows" :key="a.id">
            <td>
              <div class="text-ink dark:text-ink-dark">{{ a.title }}</div>
              <div v-if="a.excerpt" class="text-[11px] text-ink-subtle truncate max-w-md">{{ a.excerpt }}</div>
            </td>
            <td class="hidden md:table-cell text-ink-muted">{{ a.category?.name || '—' }}</td>
            <td>
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="a.status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ $t(`kb.${a.status}`) }}</span>
            </td>
            <td class="hidden lg:table-cell">
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="a.visibility === 'public' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'">{{ $t(`kb.${a.visibility}`) }}</span>
            </td>
            <td class="td-num text-ink-muted hidden lg:table-cell">{{ a.view_count }}</td>
            <td class="td-num whitespace-nowrap">
              <button v-if="can('kb.update')" class="text-[11px] text-primary-600 hover:underline" @click="openArticle(a)">{{ $t('kb.edit') }}</button>
              <button v-if="can('kb.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeArticle(a.id)">{{ $t('kb.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
      <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(d)=>{page+=d;load()}" />
    </div>

    <!-- Article form modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-2xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ form.id ? $t('kb.edit_article') : $t('kb.new') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2"><label class="label">{{ $t('kb.field_title') }} *</label>
            <input v-model="form.data.title" class="input text-sm" />
            <p v-if="form.errors.title" class="text-[11px] text-red-500">{{ form.errors.title[0] }}</p></div>
          <div><label class="label">{{ $t('kb.category') }}</label>
            <select v-model="form.data.category_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="c in meta.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select></div>
          <div class="grid grid-cols-2 gap-2.5">
            <div><label class="label">{{ $t('kb.status') }}</label>
              <select v-model="form.data.status" class="input text-sm">
                <option value="draft">{{ $t('kb.draft') }}</option>
                <option value="published">{{ $t('kb.published') }}</option>
              </select></div>
            <div><label class="label">{{ $t('kb.visibility') }}</label>
              <select v-model="form.data.visibility" class="input text-sm">
                <option value="internal">{{ $t('kb.internal') }}</option>
                <option value="public">{{ $t('kb.public') }}</option>
              </select></div>
          </div>
          <div class="col-span-2"><label class="label">{{ $t('kb.excerpt') }}</label>
            <input v-model="form.data.excerpt" class="input text-sm" :placeholder="$t('kb.excerpt_hint')" /></div>
          <div class="col-span-2"><label class="label">{{ $t('kb.body') }} *</label>
            <textarea v-model="form.data.body" rows="10" class="input text-sm font-mono resize-y" :placeholder="$t('kb.body_hint')"></textarea>
            <p v-if="form.errors.body" class="text-[11px] text-red-500">{{ form.errors.body[0] }}</p>
            <p class="text-[11px] text-ink-subtle">{{ $t('kb.body_plain_note') }}</p></div>
        </div>
        <div class="flex items-center justify-between mt-4">
          <button v-if="form.id && form.data.status === 'published' && form.data.visibility === 'public'"
                  class="text-[11px] text-primary-600 hover:underline" @click="previewPortal">{{ $t('kb.portal_hint') }}</button>
          <span v-else></span>
          <div class="flex gap-2">
            <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('kb.cancel') }}</button>
            <button class="btn-primary btn-sm" :disabled="form.saving" @click="submit">{{ form.saving ? $t('kb.saving') : $t('kb.save') }}</button>
          </div>
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
import api from '@/services/kb';
import { RefreshCw, Plus } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('kb.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
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
const filters = reactive({ q: '', category_id: '', status: 'all', visibility: 'all' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const meta = reactive({ categories: [], statuses: [], visibilities: [] });
const form = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    if (filters.q) params.q = filters.q;
    if (filters.category_id) params.category_id = filters.category_id;
    if (filters.status !== 'all') params.status = filters.status;
    if (filters.visibility !== 'all') params.visibility = filters.visibility;
    const { data } = await api.articles(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
function reload() { page.value = 1; return load(); }
async function loadMeta() { try { const { data } = await api.meta(); Object.assign(meta, data.data || {}); } catch { /* noop */ } }

function openArticle(a) {
  form.id = a?.id ?? null; form.errors = {};
  form.data = {
    title: a?.title ?? '', category_id: a?.category?.id ?? a?.category_id ?? null,
    status: a?.status ?? 'draft', visibility: a?.visibility ?? 'internal',
    excerpt: a?.excerpt ?? '', body: a?.body ?? '',
  };
  form.open = true;
  if (a?.id && !a.body) hydrate(a.id);   // list rows may lack body
}
async function hydrate(id) {
  try { const { data } = await api.article(id); form.data.body = data.data.body ?? form.data.body; } catch { /* noop */ }
}
async function submit() {
  form.saving = true; form.errors = {};
  try {
    form.id ? await api.updateArticle(form.id, form.data) : await api.createArticle(form.data);
    toast.success(t('kb.saved'));
    form.open = false;
    await load();
  } catch (e) {
    if (e.response?.status === 422) { form.errors = e.response.data?.errors || {}; if (e.response.data?.message && !Object.keys(form.errors).length) toast.error(e.response.data.message); }
  } finally { form.saving = false; }
}
async function removeArticle(id) {
  if (!window.confirm(t('kb.confirm_delete'))) return;
  try { await api.removeArticle(id); await load(); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}
function previewPortal() { toast.info(t('kb.portal_note')); }

onMounted(() => { load(); loadMeta(); });
</script>
