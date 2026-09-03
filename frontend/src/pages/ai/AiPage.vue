<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">
    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark flex items-center gap-1.5"><Sparkles :size="16" class="text-primary-500" /> {{ $t('ai.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('ai.subtitle') }}</div>
      </div>
    </div>

    <div class="flex gap-3 items-start" style="height: calc(100vh - 11rem)">
      <!-- Conversations -->
      <div class="card w-52 shrink-0 hidden lg:flex flex-col overflow-hidden">
        <button class="m-2 btn-primary text-xs px-2.5 py-1.5" @click="newConversation"><Plus :size="12" /> {{ $t('ai.new_chat') }}</button>
        <div class="flex-1 overflow-y-auto">
          <button v-for="c in conversations" :key="c.id"
                  class="w-full text-left px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 hover:bg-slate-50 dark:hover:bg-surface-dark-subtle"
                  :class="active?.id === c.id && 'bg-primary-50 dark:bg-primary-900/20'"
                  @click="openConversation(c.id)">
            <div class="text-xs text-ink dark:text-ink-dark truncate">{{ c.title }}</div>
          </button>
        </div>
      </div>

      <!-- Chat -->
      <div class="card flex-1 min-w-0 flex flex-col overflow-hidden">
        <div class="flex-1 overflow-y-auto p-4 space-y-3" ref="thread">
          <div v-if="!active || !active.messages.length" class="h-full flex flex-col items-center justify-center text-center text-ink-subtle">
            <Sparkles :size="28" class="mb-2 text-primary-400" />
            <div class="text-sm">{{ $t('ai.welcome') }}</div>
            <div class="flex flex-wrap gap-2 justify-center mt-3 max-w-md">
              <button v-for="s in suggestions" :key="s" class="btn-secondary text-[11px] px-2.5 py-1" @click="quickAsk(s)">{{ s }}</button>
            </div>
          </div>
          <div v-for="m in active?.messages || []" :key="m.id" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
            <div class="max-w-[80%] rounded-lg px-3 py-2 text-sm whitespace-pre-wrap"
                 :class="m.role === 'user' ? 'bg-primary-600 text-white' : 'bg-slate-100 dark:bg-surface-dark-subtle text-ink dark:text-ink-dark'">
              {{ m.content }}
            </div>
          </div>
          <div v-if="sending" class="flex justify-start"><div class="bg-slate-100 dark:bg-surface-dark-subtle rounded-lg px-3 py-2 text-sm text-ink-subtle">{{ $t('ai.thinking') }}</div></div>
        </div>
        <div class="border-t border-slate-200 dark:border-slate-700 p-2.5 flex gap-2">
          <input v-model="draft" class="input text-sm flex-1" :placeholder="$t('ai.ask_ph')" :disabled="sending" @keyup.enter="sendMessage" />
          <button class="btn-primary text-xs px-3" :disabled="!draft.trim() || sending" @click="sendMessage"><Send :size="13" /></button>
        </div>
      </div>

      <!-- Insights + predictions -->
      <div class="w-72 shrink-0 hidden xl:flex flex-col gap-3 overflow-y-auto">
        <div class="card overflow-hidden">
          <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
            <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('ai.insights') }}</span>
            <button class="ml-auto text-[10px] text-primary-600 hover:underline" @click="refreshInsights"><RefreshCw :size="10" :class="refreshingI && 'animate-spin inline'" /> {{ $t('ai.refresh') }}</button>
          </div>
          <div v-if="!insights.length" class="text-[11px] text-ink-subtle text-center py-4">{{ $t('ai.no_insights') }}</div>
          <div v-for="i in insights" :key="i.id" class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <div class="flex items-start gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full mt-1 shrink-0" :class="levelDot(i.level)" />
              <div class="min-w-0 flex-1">
                <div class="text-xs text-ink dark:text-ink-dark">{{ i.title }}</div>
                <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">{{ i.body }}</div>
              </div>
              <button class="text-ink-subtle hover:text-ink" @click="dismiss(i.id)"><X :size="12" /></button>
            </div>
          </div>
        </div>

        <div class="card overflow-hidden">
          <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
            <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('ai.predictions') }}</span>
            <button class="ml-auto text-[10px] text-primary-600 hover:underline" @click="refreshPredictions"><RefreshCw :size="10" :class="refreshingP && 'animate-spin inline'" /> {{ $t('ai.refresh') }}</button>
          </div>
          <div v-if="!predictions.length" class="text-[11px] text-ink-subtle text-center py-4">{{ $t('ai.no_predictions') }}</div>
          <div v-for="p in predictions" :key="p.id" class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
            <div class="text-xs text-ink dark:text-ink-dark">{{ p.title }}</div>
            <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">
              <span v-if="p.value?.amount != null">{{ compact(p.value.amount) }}<span v-if="p.value.confidence"> · {{ p.value.confidence }}% {{ $t('ai.confidence') }}</span></span>
              <span v-else-if="p.value?.probability != null">{{ p.value.probability }}% {{ $t('ai.likely') }}</span>
              <span v-else-if="p.value?.overdue_balance != null">{{ compact(p.value.overdue_balance) }} {{ $t('ai.overdue') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import api from '@/services/ai';
import { Sparkles, Plus, Send, RefreshCw, X } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();

const conversations = ref([]);
const active = ref(null);
const insights = ref([]);
const predictions = ref([]);
const draft = ref('');
const sending = ref(false);
const refreshingI = ref(false);
const refreshingP = ref(false);
const thread = ref(null);
const suggestions = ['Give me a summary', 'What deals are open?', 'Any overdue invoices?', 'Show hot leads'];

async function loadConversations() {
  try { const { data } = await api.conversations(); conversations.value = data.data || []; } catch { /* noop */ }
}
async function openConversation(id) {
  try { const { data } = await api.conversation(id); active.value = data.data; scrollDown(); } catch { /* noop */ }
}
async function newConversation() {
  try { const { data } = await api.createConversation(); active.value = data.data; await loadConversations(); } catch { /* noop */ }
}

async function ensureConversation() {
  if (active.value?.id) return active.value.id;
  const { data } = await api.createConversation();
  active.value = data.data;
  await loadConversations();
  return active.value.id;
}

async function sendMessage() {
  const content = draft.value.trim();
  if (!content || sending.value) return;
  draft.value = '';
  sending.value = true;
  // optimistic user bubble
  if (!active.value) active.value = { id: null, title: content, messages: [] };
  active.value.messages.push({ id: 'tmp', role: 'user', content });
  scrollDown();
  try {
    const id = await ensureConversation();
    const { data } = await api.send(id, content);
    active.value = data.data;
    await loadConversations();
    scrollDown();
  } catch { toast.error(t('ai.error')); }
  finally { sending.value = false; }
}
function quickAsk(s) { draft.value = s; sendMessage(); }

async function loadInsights() { try { const { data } = await api.insights(); insights.value = data.data || []; } catch { /* noop */ } }
async function refreshInsights() {
  refreshingI.value = true;
  try { const { data } = await api.generateInsights(); insights.value = data.data || []; } catch { /* noop */ }
  finally { refreshingI.value = false; }
}
async function dismiss(id) { try { await api.dismissInsight(id); insights.value = insights.value.filter((i) => i.id !== id); } catch { /* noop */ } }

async function loadPredictions() { try { const { data } = await api.predictions(); predictions.value = data.data || []; } catch { /* noop */ } }
async function refreshPredictions() {
  refreshingP.value = true;
  try { const { data } = await api.generatePredictions(); predictions.value = data.data || []; } catch { /* noop */ }
  finally { refreshingP.value = false; }
}

function scrollDown() { nextTick(() => { if (thread.value) thread.value.scrollTop = thread.value.scrollHeight; }); }
const compact = (v) => v == null ? '—' : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v);
const levelDot = (l) => ({ critical: 'bg-red-500', warning: 'bg-amber-500', info: 'bg-sky-500' }[l] || 'bg-slate-400');

onMounted(async () => { await Promise.all([loadConversations(), loadInsights(), loadPredictions()]); });
</script>
