<template>
  <div class="page">
    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('platform.title') }}</h1>
        <p class="page-sub">{{ $t('platform.subtitle') }}</p>
      </div>
    </div>

    <div class="flex gap-3 items-start">
      <div class="panel flex-1 min-w-0">
        <div class="toolbar border-b border-line dark:border-line-dark">
          <input v-model="q" class="input input-sm w-56" :placeholder="$t('platform.search')" @keyup.enter="loadCompanies" />
        </div>
        <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('platform.c.name') }}</th>
              <th class="hidden md:table-cell">{{ $t('platform.c.plan') }}</th>
              <th class="th-num">{{ $t('platform.c.users') }}</th>
              <th class="hidden lg:table-cell">{{ $t('platform.c.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in companies" :key="c.id"
                class="cursor-pointer"
                :class="selected?.id === c.id && 'is-selected'"
                @click="openCompany(c)">
              <td>
                <div class="text-ink dark:text-ink-dark flex items-center gap-1.5">
                  {{ c.name }}
                  <span v-if="c.is_platform" class="text-[10px] px-1.5 py-0.5 rounded bg-primary-100 text-primary-700">{{ $t('platform.master') }}</span>
                </div>
                <div class="text-[11px] text-ink-subtle font-mono">{{ c.code }}</div>
              </td>
              <td class="hidden md:table-cell text-ink-muted">{{ c.plan?.name || '—' }}</td>
              <td class="td-num text-ink-muted">{{ c.users_count }}</td>
              <td class="hidden lg:table-cell">
                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="c.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                  {{ c.is_active ? $t('settings.active') : $t('settings.inactive') }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <!-- Detail panel -->
      <div v-if="selected" class="card w-full sm:w-[26rem] shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-12rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <span class="text-sm font-medium text-ink dark:text-ink-dark flex-1">{{ selected.name }}</span>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null; grants = []"><X :size="14" /></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-4 text-xs">
          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle uppercase mb-1.5">{{ $t('platform.c.plan') }}</div>
            <div class="flex gap-2">
              <select v-model="planCode" class="input text-sm flex-1">
                <option value="starter">Starter</option>
                <option value="professional">Professional</option>
                <option value="enterprise">Enterprise</option>
              </select>
              <button class="btn-primary btn-sm" :disabled="savingPlan || planCode === selected.plan?.code" @click="savePlan">
                {{ savingPlan ? $t('settings.saving') : $t('settings.save') }}
              </button>
            </div>
          </div>

          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle uppercase mb-1.5">{{ $t('platform.grant_access') }}</div>
            <input v-model="grantForm.reason" class="input text-sm w-full mb-1.5" :placeholder="$t('platform.reason')" />
            <div class="flex gap-2">
              <select v-model.number="grantForm.hours" class="input text-sm w-24">
                <option v-for="h in [1,2,4,8]" :key="h" :value="h">{{ h }}h</option>
              </select>
              <button class="btn-secondary btn-sm flex-1" :disabled="grantForm.saving || !grantForm.reason" @click="submitGrant">
                {{ $t('platform.grant_access') }}
              </button>
            </div>
          </div>

          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle uppercase mb-1.5">{{ $t('platform.grant_history') }}</div>
            <div v-if="!grants.length" class="text-ink-subtle">{{ $t('platform.no_grants') }}</div>
            <div v-for="g in grants" :key="g.id" class="flex items-center gap-2 py-1.5 border-t border-slate-100 dark:border-slate-700/60 first:border-t-0">
              <div class="flex-1 min-w-0">
                <div class="text-ink dark:text-ink-dark truncate">{{ g.reason }}</div>
                <div class="text-[11px] text-ink-subtle">{{ g.granted_by?.name }} · {{ new Date(g.expires_at).toLocaleString() }}</div>
              </div>
              <span v-if="g.revoked_at" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">{{ $t('platform.revoked') }}</span>
              <span v-else-if="new Date(g.expires_at) < new Date()" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">{{ $t('platform.expired') }}</span>
              <button v-else class="text-[11px] text-red-500 hover:underline shrink-0" @click="revoke(g)">{{ $t('platform.revoke') }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import api from '@/services/platform';
import { X } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();

const q = ref('');
const companies = ref([]);
const selected = ref(null);
const planCode = ref('');
const savingPlan = ref(false);
const grants = ref([]);
const grantForm = reactive({ reason: '', hours: 2, saving: false });

async function loadCompanies() {
  try { const { data } = await api.companies({ q: q.value || undefined, per_page: 100 }); companies.value = data.data || []; }
  catch { /* noop */ }
}

async function openCompany(c) {
  selected.value = c;
  planCode.value = c.plan?.code || '';
  try { const { data } = await api.company(c.id); grants.value = data.data.grants || []; }
  catch { grants.value = []; }
}

async function savePlan() {
  savingPlan.value = true;
  try {
    const { data } = await api.updatePlan(selected.value.id, { plan_code: planCode.value });
    toast.success(t('settings.saved'));
    Object.assign(selected.value, data.data);
    await loadCompanies();
  } catch { /* noop */ } finally { savingPlan.value = false; }
}

async function submitGrant() {
  grantForm.saving = true;
  try {
    await api.grantAccess(selected.value.id, { reason: grantForm.reason, hours: grantForm.hours });
    toast.success(t('platform.granted'));
    grantForm.reason = '';
    await openCompany(selected.value);
  } catch { /* noop */ } finally { grantForm.saving = false; }
}

async function revoke(g) {
  try { await api.revokeGrant(g.id); await openCompany(selected.value); }
  catch { /* noop */ }
}

loadCompanies();
</script>
