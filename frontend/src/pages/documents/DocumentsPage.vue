<template>
  <div class="page">

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('documents.title') }}</h1>
        <p class="page-sub">{{ $t('documents.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('documents.refresh') }}
        </button>
        <button v-if="can('documents.create')" class="btn-primary btn-sm" :disabled="uploading" @click="pickFile">
          <Upload :size="12" /> {{ uploading ? $t('documents.uploading') : $t('documents.upload') }}
        </button>
        <input ref="fileInput" type="file" class="hidden" @change="onFilePicked" />
      </div>
    </div>

    <div class="flex gap-4 items-start">
      <!-- Folders rail -->
      <div class="card p-2 w-48 shrink-0 hidden md:block">
        <button class="w-full text-left px-2 py-1.5 rounded text-sm" :class="folderId === '' ? 'bg-primary-50 text-primary-700 dark:bg-surface-dark-subtle' : 'text-ink-muted hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'" @click="selectFolder('')">
          {{ $t('documents.all_documents') }}
        </button>
        <button v-for="f in meta.folders" :key="f.id" class="w-full text-left px-2 py-1.5 rounded text-sm flex items-center justify-between group"
                :class="folderId === f.id ? 'bg-primary-50 text-primary-700 dark:bg-surface-dark-subtle' : 'text-ink-muted hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'" @click="selectFolder(f.id)">
          <span class="flex items-center gap-1.5 truncate"><Folder :size="13" /> {{ f.name }}</span>
          <span class="text-[10px] text-ink-subtle">{{ f.documents_count }}</span>
        </button>
        <button v-if="can('documents.create')" class="w-full text-left px-2 py-1.5 mt-1 rounded text-[11px] text-primary-600 hover:underline" @click="newFolder">
          + {{ $t('documents.new_folder') }}
        </button>
      </div>

      <!-- File list -->
      <div class="flex-1 min-w-0">
        <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
          <input v-model="filters.q" class="input text-sm w-52" :placeholder="$t('documents.search')" @keyup.enter="reload" />
          <select v-model="folderId" class="input text-sm w-auto md:hidden" @change="reload">
            <option value="">{{ $t('documents.all_documents') }}</option>
            <option v-for="f in meta.folders" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
          <button v-if="can('documents.update') && folderId" class="text-[11px] text-ink-muted hover:underline ml-auto" @click="renameFolder">{{ $t('documents.rename_folder') }}</button>
          <button v-if="can('documents.delete') && folderId" class="text-[11px] text-red-500 hover:underline" @click="deleteFolder">{{ $t('documents.delete_folder') }}</button>
        </div>

        <div class="card overflow-hidden">
          <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
          <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('documents.empty') }}</div>
          <table class="data-table" v-else>
            <thead>
              <tr>
                <th>{{ $t('documents.name') }}</th>
                <th class="hidden md:table-cell">{{ $t('documents.folder') }}</th>
                <th class="hidden lg:table-cell th-num">{{ $t('documents.size') }}</th>
                <th class="hidden lg:table-cell">{{ $t('documents.uploaded_by') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in rows" :key="d.id" class="border-t border-slate-100 dark:border-slate-700/60">
                <td class="px-3 py-2">
                  <div class="flex items-center gap-2">
                    <FileText :size="14" class="text-ink-subtle shrink-0" />
                    <div class="min-w-0">
                      <div class="text-ink dark:text-ink-dark truncate">{{ d.name }}</div>
                      <div v-if="d.description" class="text-[11px] text-ink-subtle truncate">{{ d.description }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ d.folder?.name || '—' }}</td>
                <td class="px-3 py-2 text-right tabular-nums text-ink-muted hidden lg:table-cell">{{ humanSize(d.size) }}</td>
                <td class="px-3 py-2 hidden lg:table-cell text-ink-subtle">{{ d.uploader?.name || '—' }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">
                  <button class="text-[11px] text-primary-600 hover:underline" @click="download(d)">{{ $t('documents.download') }}</button>
                  <button v-if="can('documents.update')" class="text-[11px] text-primary-600 hover:underline ml-2" @click="openEdit(d)">{{ $t('documents.edit') }}</button>
                  <button v-if="can('documents.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeDoc(d.id)">{{ $t('documents.delete') }}</button>
                </td>
              </tr>
            </tbody>
          </table>
          <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(d)=>{page+=d;load()}" />
        </div>
      </div>
    </div>

    <!-- Edit metadata modal -->
    <div v-if="editForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="editForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('documents.edit_document') }}</div>
        <div class="space-y-2.5">
          <div><label class="label">{{ $t('documents.name') }} *</label>
            <input v-model="editForm.data.name" class="input text-sm" />
            <p v-if="editForm.errors.name" class="text-[11px] text-red-500">{{ editForm.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('documents.folder') }}</label>
            <select v-model="editForm.data.folder_id" class="input text-sm">
              <option :value="null">{{ $t('documents.unfiled') }}</option>
              <option v-for="f in meta.folders" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select></div>
          <div><label class="label">{{ $t('documents.description') }}</label>
            <input v-model="editForm.data.description" class="input text-sm" /></div>
          <div class="pt-1 border-t border-slate-100 dark:border-slate-700/60">
            <label class="label">{{ $t('documents.replace_file') }}</label>
            <input ref="replaceInput" type="file" class="text-xs" @change="onReplacePicked" />
            <p class="text-[11px] text-ink-subtle">{{ $t('documents.replace_hint') }}</p>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="editForm.open = false">{{ $t('documents.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="editForm.saving" @click="submitEdit">{{ editForm.saving ? $t('documents.saving') : $t('documents.save') }}</button>
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
import api from '@/services/documents';
import { RefreshCw, Upload, Folder, FileText } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('documents.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
  h('div', { class: 'flex gap-1' }, [
    h('button', { class: 'btn-secondary btn-xs', disabled: props.page <= 1, onClick: () => emit('go', -1) }, '‹'),
    h('button', { class: 'btn-secondary btn-xs', disabled: props.page >= props.p.last_page, onClick: () => emit('go', 1) }, '›'),
  ]),
]);
Pager.props = ['p', 'page'];
Pager.emits = ['go'];

const rows = ref([]);
const loading = ref(false);
const uploading = ref(false);
const page = ref(1);
const folderId = ref('');
const filters = reactive({ q: '' });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const meta = reactive({ folders: [] });
const fileInput = ref(null);
const replaceInput = ref(null);
const editForm = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    if (filters.q) params.q = filters.q;
    if (folderId.value !== '') params.folder_id = folderId.value;
    const { data } = await api.list(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || { last_page: 1 });
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}
function reload() { page.value = 1; return load(); }
async function loadMeta() { try { const { data } = await api.meta(); Object.assign(meta, data.data || {}); } catch { /* noop */ } }
function selectFolder(id) { folderId.value = id; reload(); }

function pickFile() { fileInput.value?.click(); }
async function onFilePicked(e) {
  const file = e.target.files?.[0];
  e.target.value = '';
  if (!file) return;
  uploading.value = true;
  try {
    await api.upload(file, folderId.value ? { folder_id: folderId.value } : {});
    toast.success(t('documents.uploaded'));
    await Promise.all([load(), loadMeta()]);
  } catch (err) { if (err.response?.status === 422) toast.error(err.response.data?.message || t('documents.upload_failed')); }
  finally { uploading.value = false; }
}

async function download(d) {
  try { await api.download(d.id, d.name); }
  catch { toast.error(t('documents.download_failed')); }
}

function openEdit(d) {
  editForm.id = d.id; editForm.errors = {};
  editForm.data = { name: d.name, folder_id: d.folder?.id ?? d.folder_id ?? null, description: d.description ?? '' };
  editForm.open = true;
}
async function onReplacePicked(e) {
  const file = e.target.files?.[0];
  e.target.value = '';
  if (!file || !editForm.id) return;
  try {
    await api.replaceFile(editForm.id, file);
    toast.success(t('documents.file_replaced'));
    await load();
  } catch (err) { if (err.response?.status === 422) toast.error(err.response.data?.message || t('documents.upload_failed')); }
}
async function submitEdit() {
  editForm.saving = true; editForm.errors = {};
  try {
    await api.update(editForm.id, editForm.data);
    toast.success(t('documents.saved'));
    editForm.open = false;
    await Promise.all([load(), loadMeta()]);
  } catch (e) { if (e.response?.status === 422) editForm.errors = e.response.data?.errors || {}; }
  finally { editForm.saving = false; }
}
async function removeDoc(id) {
  if (!window.confirm(t('documents.confirm_delete'))) return;
  try { await api.remove(id); await Promise.all([load(), loadMeta()]); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

async function newFolder() {
  const name = window.prompt(t('documents.folder_name'));
  if (!name) return;
  try { await api.createFolder(name); await loadMeta(); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}
async function renameFolder() {
  const current = meta.folders.find((f) => f.id === folderId.value);
  const name = window.prompt(t('documents.folder_name'), current?.name || '');
  if (!name) return;
  try { await api.updateFolder(folderId.value, name); await loadMeta(); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}
async function deleteFolder() {
  if (!window.confirm(t('documents.confirm_delete_folder'))) return;
  try { await api.removeFolder(folderId.value); folderId.value = ''; await Promise.all([load(), loadMeta()]); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

function humanSize(bytes) {
  if (!bytes) return '0 B';
  const u = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0)} ${u[i]}`;
}

onMounted(() => { load(); loadMeta(); });
</script>
