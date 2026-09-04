<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('channels.title') }}</h1>
        <p class="page-sub">{{ $t('channels.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="load"><RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('channels.refresh') }}</button>
        <button class="btn-primary btn-sm" @click="openCreate"><Plus :size="12" /> {{ $t('channels.connect') }}</button>
      </div>
    </div>

    <!-- Summary tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 mb-4">
      <div v-for="s in statTiles" :key="s.key" class="stat">
        <div class="stat-label">{{ $t(s.label) }}</div>
        <div class="stat-value text-xl">{{ s.value }}</div>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
    <div v-else-if="!channels.length" class="card p-10 text-center text-sm text-ink-subtle">{{ $t('channels.empty') }}</div>

    <!-- Channel cards grouped by type -->
    <div v-else class="space-y-4">
      <div v-for="group in grouped" :key="group.type">
        <div class="flex items-center gap-2 mb-1.5">
          <component :is="typeIcon(group.type)" :size="14" class="text-ink-subtle" />
          <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ typeLabel(group.type) }}</span>
          <span class="text-[11px] text-ink-subtle">· {{ group.items.length }}</span>
        </div>
        <div class="grid gap-2.5 md:grid-cols-2">
          <div v-for="c in group.items" :key="c.id" class="card p-3">
            <div class="flex items-start gap-2">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :class="typeBg(c.type)">
                <component :is="typeIcon(c.type)" :size="16" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-sm text-ink dark:text-ink-dark truncate">{{ c.name }}</div>
                <div class="text-[11px] text-ink-subtle truncate">{{ c.external_account_id || $t('channels.no_account') }}</div>
              </div>
              <div class="flex flex-col items-end gap-1">
                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="c.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ c.is_active ? $t('channels.active') : $t('channels.inactive') }}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="c.configured ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'">{{ c.configured ? $t('channels.configured') : $t('channels.incomplete') }}</span>
              </div>
            </div>
            <div v-if="c.webhook" class="mt-2 flex items-center gap-1.5 text-[10px] text-ink-subtle">
              <span class="shrink-0">{{ $t('channels.webhook') }}:</span>
              <code class="truncate bg-slate-50 dark:bg-surface-dark-subtle px-1.5 py-0.5 rounded flex-1">{{ webhookUrl(c.webhook) }}</code>
              <button class="p-0.5 hover:text-ink" :title="$t('channels.copy')" @click="copy(webhookUrl(c.webhook))"><Copy :size="11" /></button>
            </div>
            <div class="flex items-center gap-2 mt-2.5 text-[11px]">
              <span class="text-ink-subtle">{{ c.conversations_count }} {{ $t('channels.chats') }}</span>
              <div class="ml-auto flex gap-2">
                <button class="text-primary-600 hover:underline" @click="testChannel(c)">{{ $t('channels.test') }}</button>
                <button class="text-primary-600 hover:underline" @click="openEdit(c)">{{ $t('channels.edit') }}</button>
                <button class="text-red-500 hover:underline" @click="remove(c)">{{ $t('channels.delete') }}</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Connect / edit modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ form.id ? $t('channels.edit_title') : $t('channels.connect') }}</div>

        <div v-if="!form.id" class="mb-3">
          <label class="label">{{ $t('channels.type') }}</label>
          <div class="grid grid-cols-4 gap-1.5">
            <button v-for="ty in meta.types" :key="ty.type" type="button"
                    class="flex flex-col items-center gap-1 py-2 rounded-md border text-[11px]"
                    :class="form.type === ty.type ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700' : 'border-slate-200 dark:border-slate-700 text-ink-muted'"
                    @click="pickType(ty.type)">
              <component :is="typeIcon(ty.type)" :size="16" />
              <span class="truncate w-full text-center">{{ ty.label.split(' ')[0] }}</span>
            </button>
          </div>
        </div>

        <template v-if="currentSchema">
          <div class="mb-3">
            <label class="label">{{ $t('channels.name') }} *</label>
            <input v-model="form.name" class="input text-sm" :placeholder="$t('channels.name_ph')" />
            <p v-if="form.errors.name" class="text-[11px] text-red-500">{{ form.errors.name[0] }}</p>
          </div>
          <div class="space-y-2.5">
            <div v-for="f in currentSchema.fields" :key="f.key">
              <label class="label">{{ f.label }} <span v-if="f.required" class="text-red-500">*</span></label>
              <input v-model="form.config[f.key]" :type="f.secret ? 'password' : 'text'"
                     :placeholder="f.secret && form.id ? $t('channels.keep_secret') : ''"
                     autocomplete="off" class="input text-sm" />
              <span v-if="f.secret" class="text-[10px] text-ink-subtle">{{ $t('channels.secret_hint') }}</span>
            </div>
          </div>
          <label class="flex items-center gap-1.5 text-xs text-ink-muted mt-3">
            <input type="checkbox" class="rounded border-slate-300" v-model="form.is_active" /> {{ $t('channels.enable') }}
          </label>
          <div v-if="currentSchema.webhook" class="mt-2 text-[11px] text-ink-subtle">
            {{ $t('channels.webhook_hint') }} <code class="bg-slate-50 dark:bg-surface-dark-subtle px-1 rounded">{{ webhookUrl(currentSchema.webhook) }}</code>
          </div>
        </template>

        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('channels.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving || !form.type" @click="submit">{{ form.saving ? $t('channels.saving') : $t('channels.save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import api from '@/services/channels';
import { RefreshCw, Plus, Copy, MessageCircle, MessagesSquare, Instagram, Send, Music2, Phone, Globe } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();

const channels = ref([]);
const meta = reactive({ types: [] });
const loading = ref(false);
const form = reactive({ open: false, id: null, saving: false, type: '', name: '', config: {}, is_active: true, errors: {} });

const ICONS = { whatsapp: MessageCircle, messenger: MessagesSquare, instagram: Instagram, telegram: Send, tiktok: Music2, sms: Phone, webchat: Globe };
const BG = {
  whatsapp: 'bg-emerald-100 text-emerald-700', messenger: 'bg-sky-100 text-sky-700', instagram: 'bg-pink-100 text-pink-700',
  telegram: 'bg-blue-100 text-blue-700', tiktok: 'bg-slate-800 text-white', sms: 'bg-violet-100 text-violet-700', webchat: 'bg-amber-100 text-amber-700',
};
const typeIcon = (t) => ICONS[t] || Globe;
const typeBg = (t) => BG[t] || 'bg-slate-100 text-slate-700';
const typeLabel = (type) => meta.types.find((x) => x.type === type)?.label || type;
const currentSchema = computed(() => meta.types.find((x) => x.type === form.type) || null);

const grouped = computed(() => {
  const map = {};
  channels.value.forEach((c) => { (map[c.type] ||= []).push(c); });
  return Object.keys(map).sort().map((type) => ({ type, items: map[type] }));
});

const statTiles = computed(() => [
  { key: 'total', label: 'channels.stat.total', value: channels.value.length },
  { key: 'active', label: 'channels.stat.active', value: channels.value.filter((c) => c.is_active).length },
  { key: 'configured', label: 'channels.stat.configured', value: channels.value.filter((c) => c.configured).length },
  { key: 'types', label: 'channels.stat.types', value: new Set(channels.value.map((c) => c.type)).size },
]);

function webhookUrl(path) { return path ? `${location.origin}${path}` : ''; }
function copy(text) { navigator.clipboard?.writeText(text).then(() => toast.success(t('channels.copied'))).catch(() => {}); }

async function load() {
  loading.value = true;
  try { const { data } = await api.list(); channels.value = data.data || []; }
  catch { /* interceptor surfaces the error */ } finally { loading.value = false; }
}
async function loadMeta() { try { const { data } = await api.meta(); Object.assign(meta, data.data || {}); } catch { /* noop */ } }

function pickType(type) { form.type = type; form.config = {}; }

function openCreate() {
  form.id = null; form.errors = {}; form.type = ''; form.name = ''; form.config = {}; form.is_active = true;
  form.open = true;
}
async function openEdit(c) {
  form.errors = {};
  try {
    const { data } = await api.show(c.id);
    const ch = data.data;
    form.id = ch.id; form.type = ch.type; form.name = ch.name; form.is_active = ch.is_active;
    // Prefill non-secret values; secrets stay blank (write-only).
    form.config = {};
    Object.entries(ch.credentials || {}).forEach(([k, v]) => { if (!v.secret && v.value != null) form.config[k] = v.value; });
    form.open = true;
  } catch { /* noop */ }
}

async function submit() {
  form.saving = true; form.errors = {};
  try {
    const payload = { name: form.name, config: form.config, is_active: form.is_active };
    if (!form.id) payload.type = form.type;
    form.id ? await api.update(form.id, payload) : await api.create(payload);
    toast.success(form.id ? t('channels.updated') : t('channels.connected'));
    form.open = false;
    await load();
  } catch (e) { if (e.response?.status === 422) form.errors = e.response.data?.errors || {}; if (e.response?.data?.message && !e.response?.data?.errors) toast.error(e.response.data.message); }
  finally { form.saving = false; }
}

async function testChannel(c) {
  try { const { data } = await api.test(c.id); toast.success(data.message || t('channels.test_ok')); }
  catch (e) { toast.error(e.response?.data?.message || t('channels.test_fail')); }
}

async function remove(c) {
  try { await api.remove(c.id); channels.value = channels.value.filter((x) => x.id !== c.id); toast.success(t('channels.removed')); }
  catch { /* noop */ }
}

onMounted(async () => { await Promise.all([load(), loadMeta()]); });
</script>
