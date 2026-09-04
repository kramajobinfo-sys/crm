<template>
  <div class="page">

    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('dynamics.title') }}</h1>
        <p class="page-sub">{{ $t('dynamics.subtitle') }}</p>
      </div>
      <button class="btn-secondary btn-sm" :disabled="loading" @click="load">
        <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('dynamics.refresh') }}
      </button>
    </div>

    <!-- This module has never completed a call against a live BC tenant. Say so in the UI. -->
    <div class="card p-3 mb-3 flex items-start gap-2 border-l-2 border-amber-400">
      <AlertTriangle :size="15" class="text-amber-500 shrink-0 mt-0.5" />
      <div class="text-xs text-ink-muted dark:text-ink-dark-muted">{{ $t('dynamics.unverified_notice') }}</div>
    </div>

    <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>

    <template v-else>
      <div v-if="!connections.length" class="card p-8 text-center">
        <Plug :size="22" class="mx-auto mb-3 text-ink-subtle" />
        <div class="text-sm text-ink dark:text-ink-dark mb-1">{{ $t('dynamics.none_title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mb-4">{{ $t('dynamics.none_body') }}</div>
        <button class="btn-primary btn-sm" @click="showForm = true">
          <Plus :size="12" /> {{ $t('dynamics.add_connection') }}
        </button>
      </div>

      <div v-for="c in connections" :key="c.id" class="card p-4 mb-3">
        <!-- Connection header -->
        <div class="flex items-start justify-between gap-3 mb-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium text-ink dark:text-ink-dark truncate">{{ c.name }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(c.status)">
                {{ $t(`dynamics.status.${c.status}`) }}
              </span>
            </div>
            <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">
              {{ c.environment }} · {{ c.bc_company_name || $t('dynamics.no_company_bound') }}
            </div>
            <div class="text-[11px] text-ink-subtle dark:text-ink-dark-subtle mt-1">
              {{ $t('dynamics.credentials_from', { source: $t(`dynamics.source.${c.credential_source}`) }) }}
            </div>
          </div>
          <div class="flex gap-2 shrink-0">
            <button class="btn-secondary btn-sm" :disabled="testing === c.id" @click="test(c)">
              <Plug :size="12" /> {{ testing === c.id ? $t('dynamics.testing') : $t('dynamics.test') }}
            </button>
          </div>
        </div>

        <div v-if="c.last_error"
             class="text-[11px] px-2.5 py-2 rounded bg-red-50 dark:bg-red-900/20 text-red-700
                    dark:text-red-300 mb-3 break-words">{{ c.last_error }}</div>

        <!-- Company picker appears only after a successful probe returns companies -->
        <div v-if="probe[c.id]?.companies?.length" class="mb-3">
          <div class="text-xs text-ink-muted dark:text-ink-dark-muted mb-1">{{ $t('dynamics.pick_company') }}</div>
          <select class="input text-xs w-auto" @change="bindCompany(c, $event.target.value)">
            <option value="">{{ $t('dynamics.select') }}</option>
            <option v-for="bc in probe[c.id].companies" :key="bc.id" :value="bc.id">
              {{ bc.display_name || bc.name }}
            </option>
          </select>
        </div>

        <!-- Mappings -->
        <div class="text-[10px] tracking-wider text-ink-subtle dark:text-ink-dark-subtle mb-1">
          {{ $t('dynamics.mappings') }}
        </div>
        <div v-if="!c.mappings?.length" class="empty">{{ $t('dynamics.no_mappings') }}</div>
        <div v-else class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('dynamics.crm_entity') }}</th>
                <th>{{ $t('dynamics.bc_entity') }}</th>
                <th>{{ $t('dynamics.direction') }}</th>
                <th>{{ $t('dynamics.last_run') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in c.mappings" :key="m.id">
                <td class="text-ink dark:text-ink-dark">
                  {{ m.crm_entity }}
                  <span v-if="!implemented.includes(m.crm_entity)"
                        class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-ink-subtle"
                        :title="$t('dynamics.not_implemented_hint')">{{ $t('dynamics.not_implemented') }}</span>
                </td>
                <td class="text-ink-muted dark:text-ink-dark-muted">{{ m.bc_entity }}</td>
                <td class="text-ink-muted dark:text-ink-dark-muted">{{ $t(`dynamics.dir.${m.direction}`) }}</td>
                <td class="text-ink-subtle">{{ m.last_run_human || '—' }}</td>
                <td class="text-right">
                  <button
                    class="btn-secondary btn-xs"
                    :disabled="!implemented.includes(m.crm_entity) || syncing === m.id"
                    :title="implemented.includes(m.crm_entity) ? '' : $t('dynamics.not_implemented_hint')"
                    @click="runSync(c, m)"
                  >
                    <Play :size="10" /> {{ $t('dynamics.sync_now') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Recent runs -->
        <div v-if="runs[c.id]?.length" class="mt-3">
          <div class="text-[10px] tracking-wider text-ink-subtle dark:text-ink-dark-subtle mb-1">
            {{ $t('dynamics.recent_runs') }}
          </div>
          <div v-for="r in runs[c.id]" :key="r.id"
               class="flex items-center gap-2 text-[11px] py-1 border-b border-slate-100 dark:border-slate-700/60">
            <span class="px-1.5 py-0.5 rounded shrink-0" :class="runClass(r.status)">{{ $t(`dynamics.run.${r.status}`) }}</span>
            <span class="text-ink-muted dark:text-ink-dark-muted shrink-0">{{ r.mapping?.bc_entity }}</span>
            <span class="text-ink-subtle shrink-0">
              +{{ r.counts.created }} ~{{ r.counts.updated }} ={{ r.counts.skipped }} !{{ r.counts.failed }}
            </span>
            <span class="text-ink-subtle truncate flex-1" :title="r.error">{{ r.error }}</span>
            <span class="text-ink-subtle shrink-0">{{ r.started_human }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import dynamicsApi from '@/services/dynamics';
import { RefreshCw, AlertTriangle, Plug, Plus, Play } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();

const connections = ref([]);
const implemented = ref([]);
const runs        = reactive({});
const probe       = reactive({});
const loading     = ref(false);
const testing     = ref(null);
const syncing     = ref(null);
const showForm    = ref(false);

async function load() {
  loading.value = true;
  try {
    const { data } = await dynamicsApi.index();
    connections.value = data.data.connections || [];
    implemented.value = data.data.implemented_crm_entities || [];
    await Promise.all(connections.value.map((c) => loadRuns(c.id)));
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}

async function loadRuns(id) {
  try {
    const { data } = await dynamicsApi.runs(id, { per_page: 5 });
    runs[id] = data.data || [];
  } catch { /* non-critical */ }
}

async function test(c) {
  testing.value = c.id;
  try {
    const { data } = await dynamicsApi.test(c.id);
    probe[c.id] = data.data;
    data.data.ok ? toast.success(data.data.message) : toast.error(data.data.message);
    await load();
  } catch { /* interceptor surfaces the error */ }
  finally { testing.value = null; }
}

async function bindCompany(c, bcId) {
  if (!bcId) return;
  const picked = probe[c.id]?.companies?.find((x) => x.id === bcId);
  try {
    await dynamicsApi.update(c.id, {
      bc_company_id: bcId,
      bc_company_name: picked?.display_name || picked?.name || null,
    });
    toast.success(t('dynamics.company_bound'));
    await load();
  } catch { /* interceptor surfaces the error */ }
}

async function runSync(c, m) {
  syncing.value = m.id;
  try {
    await dynamicsApi.sync(c.id, m.id);
    toast.success(t('dynamics.sync_queued'));
    // The job runs on the queue; give the worker a beat before refreshing.
    setTimeout(() => loadRuns(c.id), 4000);
  } catch (e) {
    if (e.response?.status === 422) toast.error(e.response.data?.message);
  } finally { syncing.value = null; }
}

const statusClass = (s) => ({
  ok:           'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  auth_failed:  'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  unreachable:  'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  unconfigured: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
}[s] || 'bg-slate-100 text-slate-700');

const runClass = (s) => ({
  success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  failed:  'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  running: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
  queued:  'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(load);
</script>
