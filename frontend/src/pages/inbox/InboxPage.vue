<template>
  <div class="h-[calc(100vh-3.25rem)] flex flex-col">

    <!-- Header -->
    <div class="flex items-end justify-between px-4 md:px-5 pt-4 pb-3 shrink-0">
      <div>
        <h1 class="page-title">{{ $t('inbox.title') }}</h1>
        <p class="page-sub">{{ $t('inbox.subtitle') }}</p>
      </div>
      <button class="btn-secondary btn-sm" :disabled="loadingList" @click="refresh">
        <RefreshCw :size="12" :class="loadingList && 'animate-spin'" /> {{ $t('inbox.refresh') }}
      </button>
    </div>

    <div class="flex-1 min-h-0 flex gap-3 px-4 md:px-5 pb-4">

      <!-- Filter rail -->
      <aside class="hidden lg:flex flex-col w-44 shrink-0 gap-1">
        <button
          v-for="f in filterTabs" :key="f.key"
          class="flex items-center justify-between px-3 py-2 rounded-md text-sm text-left transition-colors"
          :class="activeFilter === f.key
            ? 'bg-primary-600 text-white'
            : 'text-ink dark:text-ink-dark hover:bg-slate-100 dark:hover:bg-surface-dark-subtle'"
          @click="applyFilter(f.key)"
        >
          <span class="flex items-center gap-2 truncate">
            <component :is="f.icon" :size="14" class="shrink-0" />
            <span class="truncate">{{ $t(f.label) }}</span>
          </span>
          <span
            v-if="counts[f.count] != null"
            class="text-[10px] px-1.5 py-0.5 rounded-full shrink-0"
            :class="activeFilter === f.key ? 'bg-white/20' : 'bg-slate-200 dark:bg-slate-700'"
          >{{ counts[f.count] }}</span>
        </button>

        <div class="text-[10px] tracking-wider text-ink-subtle dark:text-ink-dark-subtle px-3 pt-4 pb-1">
          {{ $t('inbox.channels') }}
        </div>
        <div class="overflow-y-auto -mr-1 pr-1">
          <button
            class="w-full flex items-center gap-2 px-3 py-1.5 rounded-md text-sm text-left"
            :class="!channelType && !channelId ? 'bg-slate-100 dark:bg-surface-dark-subtle text-ink dark:text-ink-dark'
                                 : 'text-ink-muted dark:text-ink-dark-muted hover:bg-slate-100 dark:hover:bg-surface-dark-subtle'"
            @click="pickChannel(null, null)"
          >{{ $t('inbox.all_channels') }}</button>

          <!-- One group per provider; a company runs several accounts per provider. -->
          <div v-for="g in channelGroups" :key="g.type" class="mt-1">
            <button
              class="w-full flex items-center gap-2 px-3 py-1.5 rounded-md text-sm text-left"
              :class="channelType === g.type && !channelId
                ? 'bg-slate-100 dark:bg-surface-dark-subtle text-ink dark:text-ink-dark'
                : 'text-ink-muted dark:text-ink-dark-muted hover:bg-slate-100 dark:hover:bg-surface-dark-subtle'"
              @click="pickChannel(g.type, null)"
            >
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="channelDot(g.type)" />
              <span class="truncate flex-1">{{ $t(`inbox.channel.${g.type}`) }}</span>
              <span class="text-[10px] text-ink-subtle shrink-0">{{ g.accounts.length }}</span>
            </button>
            <button
              v-for="a in g.accounts" :key="a.id"
              class="w-full flex items-center gap-2 pl-7 pr-3 py-1 rounded-md text-xs text-left"
              :class="channelId === a.id
                ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                : 'text-ink-subtle dark:text-ink-dark-subtle hover:bg-slate-100 dark:hover:bg-surface-dark-subtle'"
              :title="a.name"
              @click="pickChannel(null, a.id)"
            >
              <span class="truncate flex-1">{{ a.name }}</span>
              <span v-if="a.open_count" class="text-[10px] shrink-0 opacity-70">{{ a.open_count }}</span>
            </button>
          </div>
        </div>
      </aside>

      <!-- Conversation list -->
      <div class="card w-full sm:w-80 shrink-0 flex flex-col overflow-hidden">
        <div class="p-2 border-b border-slate-200 dark:border-slate-700 shrink-0">
          <input
            v-model="search" class="input text-sm" :placeholder="$t('inbox.search')"
            @keyup.enter="loadList()"
          />
        </div>
        <div class="flex-1 overflow-y-auto">
          <div v-if="loadingList && !conversations.length" class="text-sm text-ink-subtle py-10 text-center">
            {{ $t('app.loading') }}
          </div>
          <div v-else-if="!conversations.length" class="text-sm text-ink-subtle py-10 text-center px-4">
            {{ $t('inbox.empty') }}
          </div>
          <button
            v-for="c in conversations" :key="c.id"
            class="w-full text-left px-3 py-2.5 border-b border-slate-100 dark:border-slate-700/60 transition-colors"
            :class="selectedId === c.id
              ? 'bg-primary-50 dark:bg-primary-900/20'
              : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
            @click="openThread(c.id)"
          >
            <div class="flex items-center gap-2 mb-0.5">
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="channelDot(c.channel?.type)" />
              <span class="text-sm font-medium text-ink dark:text-ink-dark truncate flex-1">
                {{ c.contact?.name || $t('inbox.unknown_contact') }}
              </span>
              <span v-if="c.unread_count" class="text-[10px] bg-primary-600 text-white px-1.5 py-0.5 rounded-full shrink-0">
                {{ c.unread_count }}
              </span>
            </div>
            <!-- Which account this landed in — with 5 Facebook pages the contact name alone is ambiguous. -->
            <div class="text-[10px] text-ink-subtle dark:text-ink-dark-subtle truncate mb-0.5">{{ c.channel?.name }}</div>
            <div class="text-xs text-ink-muted dark:text-ink-dark-muted truncate">{{ c.last_message_preview }}</div>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(c.status)">{{ $t(`inbox.status.${c.status}`) }}</span>
              <span v-if="c.priority === 'urgent' || c.priority === 'high'"
                    class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                {{ $t(`inbox.priority.${c.priority}`) }}
              </span>
              <span class="text-[10px] text-ink-subtle ml-auto shrink-0">{{ c.last_message_human }}</span>
            </div>
          </button>
        </div>
      </div>

      <!-- Thread -->
      <div class="card flex-1 min-w-0 flex flex-col overflow-hidden">
        <div v-if="!thread" class="flex-1 flex items-center justify-center text-sm text-ink-subtle px-6 text-center">
          {{ $t('inbox.select_prompt') }}
        </div>

        <template v-else>
          <!-- Thread header -->
          <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-center gap-3 shrink-0">
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-ink dark:text-ink-dark truncate">
                {{ thread.contact?.name }}
                <span class="text-xs text-ink-subtle font-normal">· {{ thread.channel?.name }}</span>
              </div>
              <div class="text-xs text-ink-muted dark:text-ink-dark-muted truncate">{{ thread.subject }}</div>
            </div>
            <button class="btn-secondary btn-xs" @click="openCrm"><ContactRound :size="13" /> {{ $t('inbox.crm') }}</button>
            <select
              class="input text-xs py-1 w-auto" :value="thread.status"
              :disabled="!can('chat.close')" @change="changeStatus($event.target.value)"
            >
              <option v-for="s in statuses" :key="s" :value="s">{{ $t(`inbox.status.${s}`) }}</option>
            </select>
          </div>

          <!-- Messages -->
          <div ref="scroller" class="flex-1 overflow-y-auto p-4 space-y-2.5">
            <div v-for="m in thread.messages" :key="m.id" class="flex"
                 :class="m.direction === 'inbound' ? 'justify-start' : 'justify-end'">
              <div class="max-w-[75%] rounded-lg px-3 py-2 text-sm"
                   :class="bubbleClass(m.direction)">
                <div v-if="m.direction === 'note'" class="text-[10px] font-medium uppercase tracking-wide mb-0.5 opacity-70">
                  {{ $t('inbox.internal_note') }}
                </div>
                <!-- Media renders by kind; unknown types fall back to a download link. -->
                <div v-if="m.attachments?.length" class="space-y-1.5" :class="m.body && 'mb-1.5'">
                  <div v-for="a in m.attachments" :key="a.id">
                    <a v-if="a.kind === 'image'" :href="a.url" target="_blank" rel="noopener">
                      <img :src="a.url" :alt="a.name"
                           class="rounded max-h-56 w-auto object-cover border border-black/10" />
                    </a>
                    <video v-else-if="a.kind === 'video'" :src="a.url" controls preload="metadata"
                           class="rounded max-h-56 w-full border border-black/10" />
                    <audio v-else-if="a.kind === 'audio'" :src="a.url" controls class="w-56 max-w-full" />
                    <a v-else :href="a.url" target="_blank" rel="noopener"
                       class="flex items-center gap-2 px-2 py-1.5 rounded bg-black/10 hover:bg-black/20 text-xs">
                      <Paperclip :size="12" class="shrink-0" />
                      <span class="truncate flex-1">{{ a.name }}</span>
                      <span class="opacity-70 shrink-0">{{ humanSize(a.size) }}</span>
                    </a>
                  </div>
                </div>
                <div v-if="m.body" class="whitespace-pre-wrap break-words">{{ m.body }}</div>
                <div class="text-[10px] mt-1 opacity-60 flex items-center gap-1.5">
                  <span v-if="m.sender">{{ m.sender.name }} ·</span>
                  <span>{{ m.sent_human }}</span>
                  <span v-if="m.direction === 'outbound'">· {{ $t(`inbox.msg_status.${m.status}`) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Service-window notice -->
          <div v-if="!thread.service_window_open"
               class="px-4 py-2 text-xs bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300
                      border-t border-amber-200 dark:border-amber-900/40 flex items-start gap-2 shrink-0">
            <AlertTriangle :size="14" class="shrink-0 mt-0.5" />
            <span>{{ $t('inbox.window_closed') }}</span>
          </div>

          <!-- Composer -->
          <div class="border-t border-slate-200 dark:border-slate-700 p-2.5 shrink-0">
            <div v-if="canned.length" class="flex gap-1.5 mb-2 flex-wrap">
              <button
                v-for="r in canned" :key="r.id"
                class="text-[10px] px-2 py-1 rounded border border-slate-200 dark:border-slate-700
                       text-ink-muted dark:text-ink-dark-muted hover:bg-slate-100 dark:hover:bg-surface-dark-subtle"
                :title="r.body" @click="draft = r.body"
              >/{{ r.shortcut }}</button>
            </div>
            <!-- Staged uploads, removable before send -->
            <div v-if="pending.length" class="flex gap-1.5 mb-2 flex-wrap">
              <div v-for="(f, i) in pending" :key="i"
                   class="flex items-center gap-1.5 pl-2 pr-1 py-1 rounded border border-slate-200
                          dark:border-slate-700 text-[11px] text-ink-muted dark:text-ink-dark-muted">
                <Paperclip :size="11" class="shrink-0" />
                <span class="truncate max-w-[9rem]">{{ f.name }}</span>
                <span class="opacity-60">{{ humanSize(f.size) }}</span>
                <button class="p-0.5 hover:text-red-500" @click="pending.splice(i, 1)"><X :size="11" /></button>
              </div>
            </div>

            <textarea
              v-model="draft" rows="2" class="input text-sm resize-none"
              :placeholder="asNote ? $t('inbox.note_placeholder') : $t('inbox.reply_placeholder')"
              :disabled="!canSend" @keydown.ctrl.enter="send"
            />
            <div class="flex items-center justify-between mt-2">
              <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted">
                  <input v-model="asNote" type="checkbox" class="rounded border-slate-300" />
                  {{ $t('inbox.send_as_note') }}
                </label>
                <button
                  class="flex items-center gap-1 text-xs text-ink-muted dark:text-ink-dark-muted hover:text-primary-600 disabled:opacity-40"
                  :disabled="!canSend" :title="$t('inbox.attach_hint')" @click="fileInput?.click()"
                >
                  <Paperclip :size="13" /> {{ $t('inbox.attach') }}
                </button>
                <input
                  ref="fileInput" type="file" multiple class="hidden"
                  accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm,audio/mpeg,audio/ogg,audio/wav,audio/mp4,application/pdf"
                  @change="stageFiles"
                />
              </div>
              <button
                class="btn-primary btn-sm"
                :disabled="(!draft.trim() && !pending.length) || sending || !canSend" @click="send"
              >
                <Send :size="12" /> {{ sending ? $t('inbox.sending') : $t('inbox.send') }}
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>

    <div v-if="crmModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="crmModal=false">
      <div class="card w-full max-w-3xl max-h-[92vh] overflow-y-auto p-4">
        <div class="flex items-start gap-3 mb-4"><div class="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center"><ContactRound :size="18" /></div><div class="min-w-0 flex-1"><h2 class="text-sm font-medium">{{ $t('inbox.crm_identity') }}</h2><p class="text-[11px] text-ink-subtle">{{ crmContext.identity?.name || $t('inbox.unknown_contact') }} · {{ crmContext.identity?.email || crmContext.identity?.phone || $t('inbox.no_identity_details') }}</p></div><button @click="crmModal=false"><X :size="15" /></button></div>
        <div v-if="crmLoading" class="py-12 text-center text-xs text-ink-subtle">{{ $t('app.loading') }}</div>
        <template v-else>
          <div v-if="crmContext.linked" class="card p-4 border-emerald-200 bg-emerald-50/60 dark:bg-emerald-900/10 mb-4"><div class="text-[10px] uppercase tracking-wide text-emerald-700 mb-1">{{ $t('inbox.linked_record') }}</div><div class="flex items-center gap-3"><div class="min-w-0 flex-1"><div class="text-sm font-medium">{{ crmContext.linked.name }}</div><div class="text-[11px] text-ink-muted">{{ crmTypeLabel(crmContext.linked.type) }}<span v-if="crmContext.linked.number"> · {{ crmContext.linked.number }}</span><span v-if="crmContext.linked.company_name"> · {{ crmContext.linked.company_name }}</span></div></div><button class="btn-primary btn-sm" @click="openLinked">{{ $t('inbox.open_in_crm') }}</button><button v-if="can('chat.link_crm')" class="btn-secondary btn-sm text-red-600" @click="unlinkCrm">{{ $t('inbox.unlink') }}</button></div></div>
          <template v-else>
            <div class="flex gap-2 mb-3"><input v-model="crmSearch" class="input text-sm flex-1" :placeholder="$t('inbox.search_crm')" @keyup.enter="searchCrm" /><button class="btn-secondary text-xs px-3" @click="searchCrm"><Search :size="13" /> {{ $t('inbox.search_action') }}</button></div>
            <div v-if="!crmMatches.length" class="card p-5 text-center text-xs text-ink-subtle mb-4">{{ $t('inbox.no_crm_matches') }}</div>
            <div v-else class="space-y-2 mb-4"><div class="text-[10px] uppercase tracking-wide text-ink-subtle">{{ $t('inbox.possible_matches') }}</div><div v-for="item in crmMatches" :key="`${item.type}-${item.id}`" class="card p-3 flex items-center gap-3"><div class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700 flex items-center justify-center"><Building2 v-if="item.type==='account'" :size="14" /><ContactRound v-else :size="14" /></div><div class="min-w-0 flex-1"><div class="text-xs font-medium">{{ item.name }}</div><div class="text-[10px] text-ink-subtle">{{ crmTypeLabel(item.type) }}<span v-if="item.number"> · {{ item.number }}</span><span v-if="item.company_name"> · {{ item.company_name }}</span><span v-if="item.email"> · {{ item.email }}</span></div></div><button v-if="can('chat.link_crm')" class="btn-primary btn-xs" @click="linkCrm(item)">{{ $t('inbox.link') }}</button></div></div>
            <form v-if="can('chat.link_crm')&&can('leads.create')" class="card p-3 space-y-3" @submit.prevent="createLead"><div><div class="text-xs font-medium">{{ $t('inbox.create_new_lead') }}</div><p class="text-[10px] text-ink-subtle">{{ $t('inbox.create_lead_help') }}</p></div><div class="grid grid-cols-1 md:grid-cols-2 gap-2"><input v-model="leadForm.name" required class="input text-xs" :placeholder="$t('inbox.person_name')" /><input v-model="leadForm.company_name" class="input text-xs" :placeholder="$t('inbox.company_name')" /><input v-model="leadForm.email" type="email" class="input text-xs" :placeholder="$t('inbox.email')" /><input v-model="leadForm.phone" class="input text-xs" :placeholder="$t('inbox.phone')" /></div><div class="flex justify-end"><button class="btn-primary btn-sm" :disabled="crmSaving"><Plus :size="12" /> {{ crmSaving?$t('inbox.creating_lead'):$t('inbox.create_and_link') }}</button></div></form>
          </template>
          <div v-if="crmContext.history?.length" class="mt-4"><div class="text-[10px] uppercase tracking-wide text-ink-subtle mb-2">{{ $t('inbox.link_history') }}</div><div v-for="entry in crmContext.history" :key="entry.id" class="text-[11px] py-1.5 border-t border-slate-100 dark:border-slate-700"><span>{{ entry.title }}</span><span class="text-ink-subtle"> · {{ entry.user || $t('projects.system') }} · {{ dateTime(entry.occurred_at) }}</span></div></div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import chatApi from '@/services/chat';
import {
  AlertTriangle, Building2, CheckCircle2, ContactRound, Inbox, Mail, Paperclip, Plus, RefreshCw, Search, Send, User, UserX, X,
} from 'lucide-vue-next';

// Mirrors ChatController::MAX_UPLOAD_KB — rejecting oversize files client-side
// avoids a long upload that only fails at the end.
const MAX_UPLOAD_BYTES = 15 * 1024 * 1024;

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const can = (p) => auth.can(p);

const statuses = ['open', 'pending', 'snoozed', 'resolved', 'closed'];
const filterTabs = [
  { key: 'open',       label: 'inbox.filter.open',       count: 'open',       icon: Inbox },
  { key: 'mine',       label: 'inbox.filter.mine',       count: 'mine',       icon: User },
  { key: 'unassigned', label: 'inbox.filter.unassigned', count: 'unassigned', icon: UserX },
  { key: 'unread',     label: 'inbox.filter.unread',     count: 'unread',     icon: Mail },
  { key: 'resolved',   label: 'inbox.filter.resolved',   count: 'resolved',   icon: CheckCircle2 },
  { key: 'all',        label: 'inbox.filter.all',        count: 'all',        icon: Inbox },
];

const conversations = ref([]);
const channelGroups = ref([]);
const canned        = ref([]);
const pending       = ref([]);   // files staged for the next send
const fileInput     = ref(null);
const channelId     = ref(null);
const counts        = reactive({});
const thread        = ref(null);
const selectedId    = ref(null);
const activeFilter  = ref('open');
const channelType   = ref(null);
const search        = ref('');
const draft         = ref('');
const asNote        = ref(false);
const loadingList   = ref(false);
const sending       = ref(false);
const scroller      = ref(null);
const crmModal=ref(false), crmLoading=ref(false), crmSaving=ref(false), crmSearch=ref('');
const crmContext=reactive({identity:null,linked:null,matches:{leads:[],contacts:[],accounts:[]},history:[]});
const leadForm=reactive({name:'',company_name:'',email:'',phone:''});
let poller = null;

const crmMatches=computed(()=>[...(crmContext.matches?.leads||[]),...(crmContext.matches?.contacts||[]),...(crmContext.matches?.accounts||[])]);

// Replies are blocked outside Meta's 24h window, but internal notes stay allowed.
const canSend = computed(() => can('chat.reply') && (asNote.value || thread.value?.service_window_open !== false));

const params = () => {
  const p = {};
  if (['open', 'pending', 'resolved', 'closed'].includes(activeFilter.value)) p.status = activeFilter.value;
  else p.status = 'all';
  if (activeFilter.value === 'mine')       p.assigned_to = 'me';
  if (activeFilter.value === 'unassigned') p.assigned_to = 'unassigned';
  if (activeFilter.value === 'unread')     p.unread = 1;
  // channel_id targets one account; channel_type rolls up every account of a provider.
  if (channelId.value)   p.channel_id = channelId.value;
  else if (channelType.value) p.channel_type = channelType.value;
  if (search.value.trim()) p.q = search.value.trim();
  return p;
};

function pickChannel(type, id) {
  channelType.value = type;
  channelId.value = id;
  loadList();
}

function stageFiles(e) {
  const chosen = [...(e.target.files || [])];
  const tooBig = chosen.filter((f) => f.size > MAX_UPLOAD_BYTES);
  if (tooBig.length) toast.error(t('inbox.file_too_large', { name: tooBig[0].name }));
  pending.value.push(...chosen.filter((f) => f.size <= MAX_UPLOAD_BYTES));
  e.target.value = '';   // allow re-picking the same file after removing it
}

function humanSize(bytes) {
  if (!bytes) return '';
  const units = ['B', 'KB', 'MB', 'GB'];
  let n = bytes; let u = 0;
  while (n >= 1024 && u < units.length - 1) { n /= 1024; u += 1; }
  return `${n < 10 && u > 0 ? n.toFixed(1) : Math.round(n)} ${units[u]}`;
}

async function loadList(silent = false) {
  if (!silent) loadingList.value = true;
  try {
    const { data } = await chatApi.conversations(params());
    conversations.value = data.data || [];
  } catch { /* interceptor surfaces the error */ }
  finally { loadingList.value = false; }
}

async function loadCounts() {
  try {
    const { data } = await chatApi.counts();
    Object.assign(counts, data.data || {});
  } catch { /* non-critical */ }
}

async function openThread(id) {
  selectedId.value = id;
  try {
    const { data } = await chatApi.thread(id);
    thread.value = data.data;
    // Opening marks read server-side; mirror it locally so the badge clears immediately.
    const row = conversations.value.find((c) => c.id === id);
    if (row) row.unread_count = 0;
    loadCounts();
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
  } catch { /* interceptor surfaces the error */ }
}

async function send() {
  const body = draft.value.trim();
  if ((!body && !pending.value.length) || !thread.value) return;   // media-only is valid
  sending.value = true;
  try {
    const { data } = await chatApi.reply(
      thread.value.id, body, asNote.value ? 'note' : 'outbound', pending.value,
    );
    thread.value.messages.push(data.data);
    draft.value = '';
    pending.value = [];
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
    loadList(true);
  } catch (e) {
    // 422 is the service-window refusal — show the server's explanation verbatim.
    if (e.response?.status === 422) toast.error(e.response.data?.message || t('inbox.send_failed'));
  } finally { sending.value = false; }
}

async function changeStatus(status) {
  if (!thread.value) return;
  try {
    await chatApi.setStatus(thread.value.id, status);
    thread.value.status = status;
    loadList(true); loadCounts();
  } catch { /* interceptor surfaces the error */ }
}

function applyFilter(key) { activeFilter.value = key; loadList(); }

const channelDot = (type) => ({
  whatsapp:  'bg-emerald-500', messenger: 'bg-blue-500',   instagram: 'bg-pink-500',
  telegram:  'bg-sky-500',     tiktok:    'bg-rose-500',   sms:       'bg-violet-500',
  webchat:   'bg-slate-400',
}[type] || 'bg-slate-400');

const statusClass = (s) => ({
  open:     'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  pending:  'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  snoozed:  'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  resolved: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  closed:   'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
}[s] || 'bg-slate-100 text-slate-700');

const bubbleClass = (dir) => ({
  inbound:  'bg-slate-100 dark:bg-surface-dark-subtle text-ink dark:text-ink-dark',
  outbound: 'bg-primary-600 text-white',
  note:     'bg-amber-50 dark:bg-amber-900/20 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-900/40',
}[dir]);

const crmTypeLabel=(type)=>t(`inbox.crm_type.${type}`);
const dateTime=(value)=>value?new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(value)):'—';
async function loadCrmContext(params={}) {
  if(!thread.value)return; crmLoading.value=true;
  try{const {data}=await chatApi.crmContext(thread.value.id,params);Object.assign(crmContext,data.data||{});}
  finally{crmLoading.value=false;}
}
async function openCrm(){crmModal.value=true;crmSearch.value='';await loadCrmContext();Object.assign(leadForm,{name:crmContext.identity?.name||'',company_name:'',email:crmContext.identity?.email||'',phone:crmContext.identity?.phone||''});}
async function searchCrm(){await loadCrmContext({q:crmSearch.value.trim()||undefined});}
async function linkCrm(item){const {data}=await chatApi.linkCrm(thread.value.id,item.type,item.id);Object.assign(crmContext,data.data||{});toast.success(t('inbox.linked_success'));}
async function unlinkCrm(){if(!window.confirm(t('inbox.unlink_confirm')))return;const {data}=await chatApi.unlinkCrm(thread.value.id);Object.assign(crmContext,data.data||{});toast.success(t('inbox.unlinked_success'));}
async function createLead(){crmSaving.value=true;try{const {data}=await chatApi.createLead(thread.value.id,{...leadForm});Object.assign(crmContext,data.data||{});toast.success(t('inbox.lead_created'));}finally{crmSaving.value=false;}}
function openLinked(){if(!crmContext.linked)return;crmModal.value=false;router.push(crmContext.linked.url);}

onMounted(async () => {
  await Promise.all([loadList(), loadCounts()]);
  try {
    const [ch, cr] = await Promise.all([chatApi.channels(), chatApi.cannedResponses()]);
    channelGroups.value = ch.data.data || [];
    canned.value        = cr.data.data || [];
  } catch { /* optional extras */ }
  // No websocket transport in this stack yet, so the inbox polls.
  poller = setInterval(() => { loadList(true); loadCounts(); }, 20000);
});
onUnmounted(() => poller && clearInterval(poller));
</script>
