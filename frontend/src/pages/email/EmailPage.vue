<template>
  <div class="page">

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('email.title') }}</h1>
        <p class="page-sub">{{ $t('email.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="reload">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('email.refresh') }}
        </button>
        <button v-if="can('email.manage_templates')" class="btn-secondary btn-sm" @click="openAccounts">
          <Settings2 :size="12" /> {{ $t('email.accounts_btn') }}
        </button>
        <button v-if="can('email.send')" class="btn-primary btn-sm" @click="openCompose()">
          <Plus :size="12" /> {{ $t('email.compose') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="stat">
        <div class="stat-label">{{ $t(s.label) }}</div>
        <div class="stat-value text-xl">
          <span v-if="s.pct">{{ stats[s.key] == null ? '—' : stats[s.key] + '%' }}</span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </div>
      </div>
    </div>

    <div class="flex gap-3 items-start">
      <div class="card flex-1 min-w-0 overflow-hidden">
        <!-- Folder tabs -->
        <div class="flex items-center gap-1 px-2.5 pt-2 border-b border-slate-200 dark:border-slate-700">
          <button v-for="f in folders" :key="f" class="px-3 py-1.5 text-xs -mb-px border-b-2"
                  :class="folder === f ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
                  @click="folder = f; page = 1; load()">
            {{ $t(`email.folder.${f}`) }}
          </button>
          <input v-model="q" class="input text-xs w-44 ml-auto my-1" :placeholder="$t('email.search')" @keyup.enter="load" />
        </div>

        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('email.empty') }}</div>
        <div v-else>
          <div v-for="m in rows" :key="m.id"
               class="flex items-center gap-2.5 px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
               :class="selected?.id === m.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
               @click="openDetail(m.id)">
            <component :is="m.direction === 'inbound' ? Inbox : SendHorizontal" :size="14" class="shrink-0 text-ink-subtle" />
            <div class="min-w-0 flex-1">
              <div class="text-sm text-ink dark:text-ink-dark truncate">{{ m.subject || $t('email.no_subject') }}</div>
              <div class="text-[11px] text-ink-subtle truncate">
                {{ m.direction === 'inbound' ? m.from_address : (m.to?.[0] || '—') }}
                <span v-if="m.related" class="text-primary-600"> · {{ m.related.label }}</span>
              </div>
            </div>
            <span v-if="m.status === 'draft'" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $t('email.draft') }}</span>
            <span v-else-if="m.opens > 0" class="text-[10px] text-emerald-600 flex items-center gap-0.5"><Eye :size="11" /> {{ m.opens }}</span>
            <span class="text-[11px] text-ink-subtle shrink-0 w-20 text-right">{{ m.created_human }}</span>
          </div>
        </div>
        <Pager v-if="pagination.last_page > 1" :p="pagination" :page="page" @go="(x)=>{page+=x;load()}" />
      </div>

      <!-- Reading pane -->
      <div v-if="selected" class="card w-full sm:w-[28rem] shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-14rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ selected.subject || $t('email.no_subject') }}</div>
            <div class="text-[11px] text-ink-subtle">{{ selected.from_name || selected.from_address }} → {{ (selected.to || []).join(', ') }}</div>
          </div>
          <button v-if="['draft','failed'].includes(selected.status) && can('email.send')" class="btn-primary btn-xs" @click="sendDraft">
            {{ selected.status === 'failed' ? $t('email.retry') : $t('email.send') }}
          </button>
          <button v-if="can('email.send')" class="p-1 text-ink-subtle hover:text-red-500" @click="removeEmail(selected.id)"><Trash2 :size="13" /></button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>
        <div class="px-3 py-1.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2 text-[11px] text-ink-subtle">
          <span class="px-1.5 py-0.5 rounded" :class="statusClass(selected.status)">{{ $t(`email.st.${selected.status}`) }}</span>
          <span v-if="selected.related" class="text-primary-600">{{ selected.related.label }}</span>
          <span v-if="selected.opens > 0" class="ml-auto flex items-center gap-0.5"><Eye :size="11" /> {{ selected.opens }} · {{ selected.clicks }} {{ $t('email.clicks') }}</span>
        </div>
        <p v-if="selected.status === 'failed' && selected.error" class="mx-3 mt-2 text-[11px] text-red-600 bg-red-50 dark:bg-red-900/20 rounded px-2 py-1.5">
          {{ selected.error }}
        </p>
        <div class="flex-1 overflow-y-auto p-3 text-sm text-ink dark:text-ink-dark email-body" v-html="sanitized(selected.body_html)"></div>
      </div>
    </div>

    <!-- Compose modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-2xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('email.compose') }}</div>
        <div class="space-y-2.5">
          <div class="grid grid-cols-2 gap-2.5">
            <div><label class="label">{{ $t('email.account') }}</label>
              <select v-model="form.data.email_account_id" class="input text-sm">
                <option v-for="a in meta.accounts" :key="a.id" :value="a.id">{{ a.name }} ({{ a.email_address }})</option>
              </select>
            </div>
            <div><label class="label">{{ $t('email.template') }}</label>
              <select v-model="templateId" class="input text-sm" @change="applyTemplate">
                <option :value="null">—</option>
                <option v-for="tp in meta.templates" :key="tp.id" :value="tp.id">{{ tp.name }}</option>
              </select>
            </div>
          </div>
          <div><label class="label">{{ $t('email.to') }} *</label>
            <input v-model="form.toRaw" class="input text-sm" placeholder="a@x.com, b@y.com" />
            <p v-if="form.errors.to" class="text-[11px] text-red-500">{{ form.errors.to[0] }}</p></div>
          <div class="grid grid-cols-2 gap-2.5">
            <div><label class="label">{{ $t('email.subject') }} *</label>
              <input v-model="form.data.subject" class="input text-sm" />
              <p v-if="form.errors.subject" class="text-[11px] text-red-500">{{ form.errors.subject[0] }}</p></div>
            <div><label class="label">{{ $t('email.link_to') }}</label>
              <div class="flex gap-1.5">
                <select v-model="form.data.related_type" class="input text-sm w-28" @change="form.data.related_id = null; loadRelated()">
                  <option :value="null">—</option>
                  <option v-for="rt in meta.related_types" :key="rt" :value="rt">{{ $t(`email.rt.${rt}`) }}</option>
                </select>
                <select v-if="form.data.related_type" v-model="form.data.related_id" class="input text-sm flex-1">
                  <option :value="null">—</option>
                  <option v-for="o in relatedOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
                </select>
              </div>
            </div>
          </div>
          <div><label class="label">{{ $t('email.body') }}</label>
            <textarea v-model="form.data.body_html" rows="7" class="input text-sm font-mono"></textarea></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('email.cancel') }}</button>
          <button class="btn-secondary btn-sm" :disabled="form.saving" @click="submit(false)">{{ $t('email.save_draft') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving" @click="submit(true)">{{ form.saving ? $t('email.sending') : $t('email.send') }}</button>
        </div>
      </div>
    </div>

    <!-- Accounts modal -->
    <div v-if="accModal.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="accModal.open = false">
      <div class="card w-full max-w-3xl p-4 mt-8 flex gap-3">
        <div class="w-56 shrink-0 border-r border-slate-100 dark:border-slate-700/60 pr-3">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('email.accounts_btn') }}</span>
            <button class="btn-secondary btn-xs" @click="openAccountForm()"><Plus :size="11" /></button>
          </div>
          <div v-for="a in accounts" :key="a.id"
               class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer text-xs"
               :class="accForm.id === a.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
               @click="openAccountForm(a)">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="a.configured ? 'bg-emerald-500' : 'bg-slate-300'"></span>
            <span class="truncate flex-1 text-ink dark:text-ink-dark">{{ a.name }}</span>
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-ink dark:text-ink-dark">{{ accForm.id ? accForm.name : $t('email.new_account') }}</span>
            <button class="p-1 text-ink-subtle hover:text-ink" @click="accModal.open = false"><X :size="14" /></button>
          </div>
          <div class="space-y-2 text-xs max-h-[65vh] overflow-y-auto pr-1">
            <div class="grid grid-cols-2 gap-2">
              <div><label class="label">{{ $t('email.acc.name') }} *</label><input v-model="accForm.name" class="input text-sm" /></div>
              <div><label class="label">{{ $t('email.acc.email') }} *</label><input v-model="accForm.email_address" class="input text-sm" /></div>
              <div><label class="label">{{ $t('email.acc.from_name') }}</label><input v-model="accForm.from_name" class="input text-sm" /></div>
              <div class="flex items-end gap-3 pb-1">
                <label class="flex items-center gap-1"><input type="checkbox" v-model="accForm.is_default" /> {{ $t('email.acc.default') }}</label>
                <label class="flex items-center gap-1"><input type="checkbox" v-model="accForm.is_active" /> {{ $t('email.acc.active') }}</label>
              </div>
            </div>
            <div class="pt-1 text-[10px] tracking-wider text-ink-subtle uppercase">{{ $t('email.acc.smtp') }}</div>
            <div class="grid grid-cols-2 gap-2">
              <div class="col-span-2"><label class="label">{{ $t('email.acc.host') }} *</label>
                <input v-model="accForm.config.host" class="input text-sm" placeholder="smtp.example.com" /></div>
              <div><label class="label">{{ $t('email.acc.port') }} *</label><input v-model.number="accForm.config.port" type="number" class="input text-sm" placeholder="587" /></div>
              <div><label class="label">{{ $t('email.acc.encryption') }}</label>
                <select v-model="accForm.config.encryption" class="input text-sm">
                  <option value="">{{ $t('email.acc.none') }}</option>
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                </select></div>
              <div><label class="label">{{ $t('email.acc.username') }}</label><input v-model="accForm.config.username" class="input text-sm" /></div>
              <div><label class="label">{{ $t('email.acc.password') }}</label>
                <input v-model="accForm.config.password" type="password" class="input text-sm"
                       :placeholder="accForm.passwordSet ? $t('email.acc.password_set') : ''" /></div>
            </div>

            <div class="pt-1 text-[10px] tracking-wider text-ink-subtle uppercase">{{ $t('email.acc.imap') }}</div>
            <p class="text-[11px] text-ink-subtle -mt-1">{{ $t('email.acc.imap_hint') }}</p>
            <div class="grid grid-cols-2 gap-2">
              <div class="col-span-2"><label class="label">{{ $t('email.acc.imap_host') }}</label>
                <input v-model="accForm.config.imap_host" class="input text-sm" placeholder="imap.example.com" /></div>
              <div><label class="label">{{ $t('email.acc.imap_port') }}</label><input v-model.number="accForm.config.imap_port" type="number" class="input text-sm" placeholder="993" /></div>
              <div><label class="label">{{ $t('email.acc.encryption') }}</label>
                <select v-model="accForm.config.imap_encryption" class="input text-sm">
                  <option value="">{{ $t('email.acc.none') }}</option>
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                </select></div>
              <div><label class="label">{{ $t('email.acc.username') }}</label><input v-model="accForm.config.imap_username" class="input text-sm" /></div>
              <div><label class="label">{{ $t('email.acc.password') }}</label>
                <input v-model="accForm.config.imap_password" type="password" class="input text-sm"
                       :placeholder="accForm.imapPasswordSet ? $t('email.acc.password_set') : ''" /></div>
            </div>
            <p v-if="testResult" class="text-[11px] px-2 py-1.5 rounded" :class="testResult.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">
              {{ testResult.message }}
            </p>
          </div>
          <div class="flex justify-end gap-2 mt-3">
            <button v-if="accForm.id" class="btn-secondary btn-sm" :disabled="accForm.fetching" @click="fetchAccount">
              {{ accForm.fetching ? $t('email.acc.fetching') : $t('email.acc.fetch') }}
            </button>
            <button v-if="accForm.id" class="btn-secondary btn-sm" :disabled="accForm.testing" @click="testAccount">
              {{ accForm.testing ? $t('email.acc.testing') : $t('email.acc.test') }}
            </button>
            <button class="btn-primary btn-sm" :disabled="accForm.saving" @click="saveAccount">
              {{ accForm.saving ? $t('settings.saving') : $t('settings.save') }}
            </button>
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
import api from '@/services/email';
import dealApi from '@/services/deals';
import leadApi from '@/services/leads';
import customerApi from '@/services/customers';
import { RefreshCw, Plus, X, Trash2, Eye, Inbox, SendHorizontal, Settings2 } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const Pager = (props, { emit }) => h('div', { class: 'flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs' }, [
  h('span', { class: 'text-ink-subtle' }, t('email.showing', { from: props.p.from, to: props.p.to, total: props.p.total })),
  h('div', { class: 'flex gap-1' }, [
    h('button', { class: 'btn-secondary btn-xs', disabled: props.page <= 1, onClick: () => emit('go', -1) }, '‹'),
    h('button', { class: 'btn-secondary btn-xs', disabled: props.page >= props.p.last_page, onClick: () => emit('go', 1) }, '›'),
  ]),
]);
Pager.props = ['p', 'page'];
Pager.emits = ['go'];

const folders = ['inbox', 'sent', 'drafts'];
const statTiles = [
  { key: 'sent_this_month', label: 'email.stat.sent' },
  { key: 'drafts',          label: 'email.stat.drafts' },
  { key: 'inbound',         label: 'email.stat.inbound' },
  { key: 'templates',       label: 'email.stat.templates' },
  { key: 'open_rate',       label: 'email.stat.open_rate', pct: true },
];

const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ accounts: [], templates: [], related_types: [] });
const selected   = ref(null);
const loading    = ref(false);
const folder     = ref('sent');
const q          = ref('');
const page       = ref(1);
const templateId = ref(null);
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const form = reactive({ open: false, saving: false, toRaw: '', data: {}, errors: {} });
const relatedCache = reactive({ deal: [], lead: [], customer: [] });
const accModal = reactive({ open: false });
const accounts = ref([]);
const testResult = ref(null);
const accForm = reactive({
  id: null, name: '', email_address: '', from_name: '', is_default: false, is_active: true,
  passwordSet: false, imapPasswordSet: false, saving: false, testing: false, fetching: false,
  config: { host: '', port: 587, encryption: '', username: '', password: '',
            imap_host: '', imap_port: 993, imap_encryption: 'ssl', imap_username: '', imap_password: '' },
});
let accFormSeq = 0; // guards against a stale account fetch resolving after a newer selection
const relatedOptions = computed(() => relatedCache[form.data.related_type] || []);

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25, folder: folder.value };
    if (q.value) params.q = q.value;
    const { data } = await api.list(params);
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
function reload() { page.value = 1; return Promise.all([load(), loadAux()]); }

async function openDetail(id) { try { const { data } = await api.show(id); selected.value = data.data; } catch { /* noop */ } }

function openCompose() {
  form.errors = {}; form.toRaw = ''; templateId.value = null;
  form.data = { email_account_id: meta.accounts.find((a) => a.is_default)?.id ?? meta.accounts[0]?.id ?? null,
                subject: '', body_html: '', related_type: null, related_id: null };
  form.open = true;
}
function applyTemplate() {
  const tp = meta.templates.find((x) => x.id === templateId.value);
  if (!tp) return;
  form.data.subject = tp.subject;
  form.data.body_html = tp.body_html;
}
async function loadRelated() {
  const type = form.data.related_type;
  if (!type || relatedCache[type].length) return;
  try {
    const apiByType = { deal: dealApi, lead: leadApi, customer: customerApi }[type];
    const { data } = await apiByType.list({ per_page: 100 });
    relatedCache[type] = (data.data || []).map((x) => ({ id: x.id, label: x.title || x.name }));
  } catch { /* noop */ }
}

async function submit(send) {
  form.saving = true; form.errors = {};
  try {
    const to = form.toRaw.split(',').map((s) => s.trim()).filter(Boolean);
    const payload = { ...form.data, to, send,
      related_type: form.data.related_type || undefined, related_id: form.data.related_id || undefined };
    const { data } = await api.compose(payload);
    if (!send) toast.success(t('email.draft_saved'));
    else data.data.status === 'sent' ? toast.success(t('email.sent_ok')) : toast.error(data.message);
    form.open = false;
    folder.value = send ? 'sent' : 'drafts';
    await Promise.all([load(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) form.errors = e.response.data?.errors || {}; }
  finally { form.saving = false; }
}

async function openAccounts() {
  accModal.open = true;
  await loadAccountsList();
  await openAccountForm(accounts.value[0]);
}
async function loadAccountsList() {
  try { const { data } = await api.accounts(); accounts.value = data.data || []; } catch { /* noop */ }
}
function resetAccountForm() {
  accForm.id = null; accForm.name = ''; accForm.email_address = ''; accForm.from_name = '';
  accForm.is_default = false; accForm.is_active = true; accForm.passwordSet = false; accForm.imapPasswordSet = false;
  accForm.config = { host: '', port: 587, encryption: '', username: '', password: '',
                     imap_host: '', imap_port: 993, imap_encryption: 'ssl', imap_username: '', imap_password: '' };
  testResult.value = null;
}
async function openAccountForm(a) {
  testResult.value = null;
  const seq = ++accFormSeq; // bump even on reset, so any in-flight fetch from a prior selection is ignored
  if (!a) { resetAccountForm(); return; }
  try {
    const { data } = await api.account(a.id);
    if (seq !== accFormSeq) return; // superseded by a newer selection — don't clobber it
    const d = data.data;
    accForm.id = d.id; accForm.name = d.name; accForm.email_address = d.email_address;
    accForm.from_name = d.from_name || ''; accForm.is_default = d.is_default; accForm.is_active = d.is_active;
    accForm.passwordSet = d.config.password.set;
    accForm.imapPasswordSet = d.config.imap_password?.set ?? false;
    accForm.config = {
      host: d.config.host.value || '', port: d.config.port.value || 587,
      encryption: d.config.encryption.value || '', username: d.config.username.value || '', password: '',
      imap_host: d.config.imap_host?.value || '', imap_port: d.config.imap_port?.value || 993,
      imap_encryption: d.config.imap_encryption?.value || 'ssl', imap_username: d.config.imap_username?.value || '',
      imap_password: '',
    };
  } catch { /* noop */ }
}
async function saveAccount() {
  accForm.saving = true; testResult.value = null;
  try {
    const payload = {
      name: accForm.name, email_address: accForm.email_address, from_name: accForm.from_name || undefined,
      is_default: accForm.is_default, is_active: accForm.is_active, config: { ...accForm.config },
    };
    const { data } = accForm.id ? await api.updateAccount(accForm.id, payload) : await api.createAccount(payload);
    toast.success(t('settings.saved'));
    await loadAccountsList();
    await openAccountForm({ id: data.data.id });
    await loadAux();
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message || t('settings.check_fields')); }
  finally { accForm.saving = false; }
}
async function testAccount() {
  accForm.testing = true; testResult.value = null;
  try {
    const { data } = await api.testAccount(accForm.id);
    testResult.value = { ok: true, message: data.message };
  } catch (e) {
    testResult.value = { ok: false, message: e.response?.data?.message || 'Test failed' };
  } finally { accForm.testing = false; }
}
async function fetchAccount() {
  accForm.fetching = true; testResult.value = null;
  try {
    const { data } = await api.fetchAccount(accForm.id);
    testResult.value = { ok: data.data.ok, message: data.message };
    if (data.data.ok && data.data.ingested > 0) await Promise.all([load(), loadAux()]);
  } catch (e) {
    testResult.value = { ok: false, message: e.response?.data?.message || 'Fetch failed' };
  } finally { accForm.fetching = false; }
}

async function sendDraft() {
  try {
    const { data } = await api.send(selected.value.id);
    selected.value = data.data;
    data.data.status === 'sent' ? toast.success(t('email.sent_ok')) : toast.error(data.message);
    await Promise.all([load(), loadAux()]);
  } catch { /* noop */ }
}
async function removeEmail(id) {
  try { await api.remove(id); selected.value = null; await Promise.all([load(), loadAux()]); } catch { /* noop */ }
}

// Minimal allow-list sanitiser: strip <script>/<style> and inline event handlers before v-html.
function sanitized(html) {
  if (!html) return '';
  return String(html)
    .replace(/<\s*(script|style)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
    .replace(/\son\w+\s*=\s*(["'])[\s\S]*?\1/gi, '')
    .replace(/(href|src)\s*=\s*(["'])\s*javascript:[^"']*\2/gi, '$1="#"');
}

const statusClass = (s) => ({
  draft: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  sent: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  queued: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  received: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux()]); });
</script>

<style scoped>
.email-body :deep(p) { margin: 0 0 0.6rem; }
.email-body :deep(a) { color: rgb(59 130 246); text-decoration: underline; }
</style>
