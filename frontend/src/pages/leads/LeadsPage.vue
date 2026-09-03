<template>
  <div class="p-4 md:p-5 max-w-[1500px] mx-auto">

    <div class="flex items-end justify-between mb-4 gap-3">
      <div>
        <div class="text-lg font-medium text-ink dark:text-ink-dark">{{ $t('leads.title') }}</div>
        <div class="text-xs text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ $t('leads.subtitle') }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary text-xs px-2.5 py-1" :disabled="loading" @click="load">
          <RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('leads.refresh') }}
        </button>
        <button v-if="can('leads.create')" class="btn-primary text-xs px-3 py-1.5" @click="openCreate">
          <Plus :size="12" /> {{ $t('leads.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-2.5 mb-3">
      <button
        v-for="s in statTiles" :key="s.key"
        class="card p-3 text-left transition-colors"
        :class="isActiveTile(s) ? 'ring-1 ring-primary-500' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
        @click="applyTile(s)"
      >
        <div class="text-[11px] text-ink-muted dark:text-ink-dark-muted">{{ $t(s.label) }}</div>
        <div class="text-lg font-semibold text-ink dark:text-ink-dark mt-0.5">
          {{ s.key === 'pipeline_value' ? compact(stats[s.key]) : (stats[s.key] ?? 0) }}
        </div>
      </button>
    </div>

    <!-- Filters -->
    <div class="card p-2.5 mb-3 flex flex-wrap gap-2 items-center">
      <input v-model="filters.q" class="input text-sm w-56" :placeholder="$t('leads.search')" @keyup.enter="load" />
      <select v-model="filters.status_id" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('leads.all_statuses') }}</option>
        <option v-for="s in meta.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="filters.source_id" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('leads.all_sources') }}</option>
        <option v-for="s in meta.sources" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="filters.rating" class="input text-sm w-auto" @change="load">
        <option value="">{{ $t('leads.all_ratings') }}</option>
        <option v-for="r in meta.ratings" :key="r" :value="r">{{ $t(`leads.rating.${r}`) }}</option>
      </select>
      <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted ml-1">
        <input type="checkbox" class="rounded border-slate-300" :checked="filters.owner_id === 'me'"
               @change="filters.owner_id = $event.target.checked ? 'me' : ''; load()" />
        {{ $t('leads.mine_only') }}
      </label>
      <button class="btn-secondary text-xs px-2.5 py-1 ml-auto" @click="resetFilters">{{ $t('leads.reset') }}</button>
    </div>

    <div class="flex gap-3">
      <!-- List -->
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="text-sm text-ink-subtle py-16 text-center">{{ $t('leads.empty') }}</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-ink-subtle dark:text-ink-dark-subtle bg-slate-50 dark:bg-surface-dark-subtle">
              <tr>
                <th class="text-left font-medium px-3 py-2">{{ $t('leads.col.score') }}</th>
                <th class="text-left font-medium px-3 py-2">{{ $t('leads.col.name') }}</th>
                <th class="text-left font-medium px-3 py-2 hidden md:table-cell">{{ $t('leads.col.status') }}</th>
                <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">{{ $t('leads.col.source') }}</th>
                <th class="text-right font-medium px-3 py-2 hidden lg:table-cell">{{ $t('leads.col.value') }}</th>
                <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">{{ $t('leads.col.owner') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in rows" :key="r.id"
                class="border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
                :class="selected?.id === r.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
                @click="openDetail(r.id)"
              >
                <td class="px-3 py-2">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-semibold tabular-nums text-ink dark:text-ink-dark w-6">{{ r.score }}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded" :class="ratingClass(r.rating)">
                      {{ $t(`leads.rating.${r.rating}`) }}
                    </span>
                  </div>
                </td>
                <td class="px-3 py-2">
                  <div class="flex items-center gap-1.5">
                    <span class="text-ink dark:text-ink-dark truncate max-w-[13rem]">{{ r.name }}</span>
                    <CheckCircle2 v-if="r.is_converted" :size="12" class="text-emerald-500 shrink-0" :title="$t('leads.converted')" />
                  </div>
                  <div class="text-[11px] text-ink-subtle truncate max-w-[13rem]">{{ r.company_name || r.email || r.phone }}</div>
                </td>
                <td class="px-3 py-2 hidden md:table-cell">
                  <span v-if="r.status" class="text-[10px] px-1.5 py-0.5 rounded"
                        :style="{ backgroundColor: r.status.color + '22', color: r.status.color }">
                    {{ r.status.name }}
                  </span>
                </td>
                <td class="px-3 py-2 hidden lg:table-cell text-ink-muted dark:text-ink-dark-muted">{{ r.source?.name || '—' }}</td>
                <td class="px-3 py-2 hidden lg:table-cell text-right tabular-nums text-ink-muted dark:text-ink-dark-muted">
                  {{ money(r.estimated_value, r.currency) }}
                </td>
                <td class="px-3 py-2 hidden lg:table-cell text-ink-muted dark:text-ink-dark-muted">{{ r.owner?.name || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
          <span class="text-ink-subtle">{{ $t('leads.showing', { from: pagination.from, to: pagination.to, total: pagination.total }) }}</span>
          <div class="flex gap-1">
            <button class="btn-secondary text-xs px-2 py-0.5" :disabled="page <= 1" @click="page--; load()">‹</button>
            <button class="btn-secondary text-xs px-2 py-0.5" :disabled="page >= pagination.last_page" @click="page++; load()">›</button>
          </div>
        </div>
      </div>

      <!-- Detail drawer -->
      <div v-if="selected" class="card w-full sm:w-96 shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-18rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2 shrink-0">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark truncate">{{ selected.name }}</div>
            <div class="text-[11px] text-ink-subtle font-mono">{{ selected.lead_no }}</div>
          </div>
          <button v-if="can('leads.update')" class="btn-secondary text-[11px] px-2 py-0.5" @click="openEdit(selected)">
            {{ $t('leads.edit') }}
          </button>
          <button v-if="can('activities.create')" class="btn-secondary text-[10px] px-2 py-0.5" @click="addFollowUp(selected)">{{ $t('activities.quick_follow_up') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <!-- Converted banner, else the convert action -->
        <div v-if="selected.is_converted"
             class="px-3 py-2 text-[11px] bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300 shrink-0">
          {{ $t('leads.converted_to', { no: selected.customer?.customer_no || '—' }) }}
        </div>
        <div v-else-if="can('leads.convert')" class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 shrink-0 flex gap-2">
          <button class="btn-primary text-[11px] px-2.5 py-1" :disabled="converting" @click="openConvert">
            <UserPlus :size="11" /> {{ converting ? $t('leads.converting') : $t('leads.convert') }}
          </button>
          <button v-if="can('leads.assign')" class="btn-secondary text-[11px] px-2.5 py-1" @click="doAutoAssign">
            {{ $t('leads.auto_assign') }}
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <!-- Score, with its working shown -->
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('leads.score_label') }}</span>
              <span class="text-sm font-semibold text-ink dark:text-ink-dark tabular-nums">{{ selected.score }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="ratingClass(selected.rating)">
                {{ $t(`leads.rating.${selected.rating}`) }}
              </span>
            </div>
            <div v-if="selected.score_breakdown" class="grid grid-cols-2 gap-x-3 gap-y-0.5">
              <template v-for="(v, k) in selected.score_breakdown" :key="k">
                <span class="text-ink-subtle">{{ $t(`leads.sb.${k}`) }}</span>
                <span class="tabular-nums text-right"
                      :class="v > 0 ? 'text-emerald-600 dark:text-emerald-400' : v < 0 ? 'text-red-600 dark:text-red-400' : 'text-ink-subtle'">
                  {{ v > 0 ? '+' : '' }}{{ v }}
                </span>
              </template>
            </div>
          </div>

          <dl class="grid grid-cols-3 gap-y-1.5">
            <dt class="text-ink-subtle">{{ $t('leads.col.status') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.status?.name || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('leads.col.source') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.source?.name || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('leads.col.owner') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.owner?.name || $t('leads.unassigned') }}</dd>
            <dt class="text-ink-subtle">{{ $t('leads.col.value') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark tabular-nums">{{ money(selected.estimated_value, selected.currency) }}</dd>
            <dt class="text-ink-subtle">{{ $t('leads.last_contact') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.last_contacted_human || $t('leads.never') }}</dd>
          </dl>

          <!-- Attachments -->
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('leads.attachments') }}</span>
              <button v-if="can('leads.update')" class="text-[10px] text-primary-600 hover:underline ml-auto"
                      @click="fileInput?.click()">{{ $t('leads.attach') }}</button>
              <input ref="fileInput" type="file" class="hidden" @change="uploadFile" />
            </div>
            <div v-if="!selected.attachments?.length" class="text-ink-subtle">{{ $t('leads.no_attachments') }}</div>
            <a v-for="a in selected.attachments" :key="a.id" :href="a.url" target="_blank" rel="noopener"
               class="flex items-center gap-1.5 py-0.5 text-ink-muted dark:text-ink-dark-muted hover:text-primary-600">
              <Paperclip :size="11" class="shrink-0" />
              <span class="truncate flex-1">{{ a.name }}</span>
              <span class="text-[10px] opacity-70">{{ humanSize(a.size) }}</span>
            </a>
          </div>

          <!-- Timeline -->
          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('leads.timeline') }}</div>
            <div v-if="can('leads.update')" class="flex gap-1.5 mb-2">
              <select v-model="noteType" class="input text-xs w-auto">
                <option value="note">{{ $t('leads.tl.note') }}</option>
                <option value="call">{{ $t('leads.tl.call') }}</option>
                <option value="email">{{ $t('leads.tl.email') }}</option>
                <option value="meeting">{{ $t('leads.tl.meeting') }}</option>
              </select>
              <input v-model="noteDraft" class="input text-xs" :placeholder="$t('leads.add_note')" @keyup.enter="submitNote" />
              <button class="btn-primary text-[11px] px-2" :disabled="!noteDraft.trim() || savingNote" @click="submitNote">
                <Send :size="11" />
              </button>
            </div>
            <div v-for="t in selected.timeline" :key="t.id" class="py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <div class="flex items-center gap-1.5">
                <span class="text-[9px] px-1 py-0.5 rounded" :class="timelineClass(t.type)">{{ $t(`leads.tl.${t.type}`) }}</span>
                <span class="text-ink dark:text-ink-dark truncate">{{ t.title }}</span>
                <span class="text-ink-subtle ml-auto shrink-0">{{ t.occurred_human }}</span>
              </div>
              <div v-if="t.body" class="text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ t.body }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lead conversion wizard -->
    <div v-if="conversion.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="conversion.open = false">
      <div class="card w-full max-w-2xl p-4 mt-6">
        <div class="flex items-start gap-2 mb-4">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('leads.convert_title') }}</div>
            <div class="text-[11px] text-ink-subtle">{{ selected?.name }} · {{ selected?.lead_no }}</div>
          </div>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="conversion.open = false"><X :size="14" /></button>
        </div>

        <div class="space-y-4">
          <section>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1.5">1. {{ $t('leads.convert_account') }}</div>
            <div class="grid grid-cols-2 gap-2.5">
              <div>
                <label class="label">{{ $t('leads.account_action') }}</label>
                <select v-model="conversion.data.account_mode" class="input text-sm">
                  <option value="new">{{ $t('leads.create_account') }}</option>
                  <option value="existing">{{ $t('leads.use_existing_account') }}</option>
                </select>
              </div>
              <template v-if="conversion.data.account_mode === 'new'">
                <div><label class="label">{{ $t('leads.account_name') }}</label><input v-model="conversion.data.account.name" class="input text-sm" /></div>
                <div><label class="label">{{ $t('leads.account_type') }}</label><select v-model="conversion.data.account.type" class="input text-sm"><option value="company">{{ $t('customers.type.company') }}</option><option value="individual">{{ $t('customers.type.individual') }}</option></select></div>
              </template>
              <div v-else class="col-span-1">
                <label class="label">{{ $t('leads.existing_account') }}</label>
                <select v-model="conversion.data.account_id" class="input text-sm">
                  <option :value="null">{{ $t('leads.choose_account') }}</option>
                  <option v-for="account in conversion.accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                </select>
              </div>
            </div>
            <div v-if="conversion.matches.length" class="mt-2 rounded border border-amber-200 dark:border-amber-800/60 bg-amber-50/60 dark:bg-amber-950/20 p-2">
              <div class="text-[10px] font-medium text-amber-800 dark:text-amber-300 mb-1">{{ $t('leads.matching_accounts') }}</div>
              <button v-for="match in conversion.matches" :key="match.id" class="w-full text-left flex items-center gap-2 py-1 text-xs hover:text-primary-600" @click="useMatchingAccount(match.id)">
                <span class="truncate">{{ match.label }}</span>
                <span class="text-[10px] text-ink-subtle truncate">{{ match.secondary }}</span>
                <span class="ml-auto text-[10px] text-primary-600 shrink-0">{{ $t('leads.use_account') }}</span>
              </button>
            </div>
          </section>

          <section>
            <label class="flex items-center gap-2 text-xs font-medium text-ink dark:text-ink-dark mb-2">
              <input v-model="conversion.data.create_contact" type="checkbox" class="rounded border-slate-300" />
              2. {{ $t('leads.create_contact') }}
            </label>
            <div v-if="conversion.data.create_contact" class="grid grid-cols-2 gap-2.5 pl-5">
              <div><label class="label">{{ $t('leads.contact_name') }}</label><input v-model="conversion.data.contact.name" class="input text-sm" /></div>
              <div><label class="label">{{ $t('leads.job_title') }}</label><input v-model="conversion.data.contact.title" class="input text-sm" /></div>
              <div><label class="label">{{ $t('leads.email') }}</label><input v-model="conversion.data.contact.email" class="input text-sm" /></div>
              <div><label class="label">{{ $t('leads.phone') }}</label><input v-model="conversion.data.contact.phone" class="input text-sm" /></div>
            </div>
          </section>

          <section>
            <label class="flex items-center gap-2 text-xs font-medium text-ink dark:text-ink-dark mb-2">
              <input v-model="conversion.data.create_deal" type="checkbox" class="rounded border-slate-300" />
              3. {{ $t('leads.create_deal_optional') }}
            </label>
            <div v-if="conversion.data.create_deal" class="grid grid-cols-2 gap-2.5 pl-5">
              <div class="col-span-2"><label class="label">{{ $t('pipeline.col.title') }}</label><input v-model="conversion.data.deal.title" class="input text-sm" /></div>
              <div><label class="label">{{ $t('pipeline.col.stage') }}</label><select v-model="conversion.data.deal.stage_id" class="input text-sm"><option :value="null">{{ $t('pipeline.first_stage') }}</option><option v-for="stage in conversionStages" :key="stage.id" :value="stage.id">{{ stage.pipeline }} · {{ stage.name }}</option></select></div>
              <div><label class="label">{{ $t('pipeline.col.amount') }}</label><input v-model.number="conversion.data.deal.amount" type="number" min="0" class="input text-sm" /></div>
              <div><label class="label">{{ $t('pipeline.close_date') }}</label><input v-model="conversion.data.deal.expected_close_date" type="date" class="input text-sm" /></div>
            </div>
          </section>
        </div>

        <p v-if="conversion.error" class="text-[11px] text-red-500 mt-3">{{ conversion.error }}</p>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="conversion.open = false">{{ $t('leads.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="converting || conversion.loading_options" @click="submitConversion">
            {{ converting ? $t('leads.converting') : $t('leads.convert_confirm') }}
          </button>
        </div>
      </div>
    </div>

    <DuplicateWarningModal
      :open="duplicateGuard.state.open"
      :candidates="duplicateGuard.state.candidates"
      :primary-id="can('leads.update') && can('leads.delete') ? form.id : null"
      @cancel="duplicateGuard.cancel"
      @proceed="duplicateGuard.proceed"
      @merge="startMerge"
    />
    <RecordMergeModal :state="mergeGuard.state" @close="mergeGuard.close" @confirm="mergeGuard.confirm" />

    <!-- Create / edit modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">
          {{ form.id ? $t('leads.edit_title') : $t('leads.new_title') }}
        </div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2">
            <label class="label">{{ $t('leads.col.name') }} *</label>
            <input v-model="form.data.name" class="input text-sm" />
            <p v-if="form.errors.name" class="text-[11px] text-red-500 mt-0.5">{{ form.errors.name[0] }}</p>
          </div>
          <div class="col-span-2">
            <label class="label">{{ $t('leads.company_name') }}</label>
            <input v-model="form.data.company_name" class="input text-sm" />
          </div>
          <div><label class="label">{{ $t('leads.job_title') }}</label><input v-model="form.data.title" class="input text-sm" /></div>
          <div><label class="label">{{ $t('leads.email') }}</label><input v-model="form.data.email" class="input text-sm" /></div>
          <div><label class="label">{{ $t('leads.phone') }}</label><input v-model="form.data.phone" class="input text-sm" /></div>
          <div>
            <label class="label">{{ $t('leads.col.source') }}</label>
            <select v-model="form.data.source_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="s in meta.sources" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">{{ $t('leads.col.status') }}</label>
            <select v-model="form.data.status_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="s in meta.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">{{ $t('leads.col.value') }}</label>
            <input v-model="form.data.estimated_value" type="number" min="0" class="input text-sm" />
          </div>
        </div>
        <p class="text-[11px] text-ink-subtle mt-2">{{ $t('leads.assign_hint') }}</p>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary text-xs px-3 py-1.5" @click="form.open = false">{{ $t('leads.cancel') }}</button>
          <button class="btn-primary text-xs px-3 py-1.5" :disabled="form.saving" @click="submitForm">
            {{ form.saving ? $t('leads.saving') : $t('leads.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/leads';
import customerApi from '@/services/customers';
import dealApi from '@/services/deals';
import duplicateApi from '@/services/duplicates';
import DuplicateWarningModal from '@/components/crm/DuplicateWarningModal.vue';
import RecordMergeModal from '@/components/crm/RecordMergeModal.vue';
import { useDuplicateGuard } from '@/composables/useDuplicateGuard';
import { useRecordMerge } from '@/composables/useRecordMerge';
import { RefreshCw, Plus, X, Send, Paperclip, UserPlus, CheckCircle2 } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const can = (p) => auth.can(p);

function addFollowUp(lead) {
  router.push({ name: 'activities', query: { new: 'task', related_type: 'lead', related_id: lead.id } });
}

const statTiles = [
  { key: 'open',       label: 'leads.stat.open',       set: { converted: 'open', rating: '' } },
  { key: 'hot',        label: 'leads.stat.hot',        set: { converted: 'open', rating: 'hot' } },
  { key: 'warm',       label: 'leads.stat.warm',       set: { converted: 'open', rating: 'warm' } },
  { key: 'unassigned', label: 'leads.stat.unassigned', set: { converted: 'open', rating: '' } },
  { key: 'converted',  label: 'leads.stat.converted',  set: { converted: 'converted', rating: '' } },
  { key: 'pipeline_value', label: 'leads.stat.pipeline', set: null },
];

const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ sources: [], statuses: [], ratings: [] });
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const selected   = ref(null);
const loading    = ref(false);
const converting = ref(false);
const page       = ref(1);
const noteDraft  = ref('');
const noteType   = ref('note');
const savingNote = ref(false);
const fileInput  = ref(null);
const filters    = reactive({ q: '', converted: 'open', status_id: '', source_id: '', rating: '', owner_id: '' });
const form = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });
const duplicateGuard = useDuplicateGuard();
const mergeGuard = useRecordMerge(async () => {
  form.open = false;
  toast.success(t('duplicates.merged'));
  await load();
});
const conversion = reactive({ open: false, loading_options: false, accounts: [], pipelines: [], matches: [], error: '', data: {} });

function startMerge(candidate) {
  duplicateGuard.cancel();
  mergeGuard.open('lead', form.id, candidate);
}
const conversionStages = computed(() => conversion.pipelines.flatMap((pipeline) =>
  (pipeline.stages || []).map((stage) => ({ ...stage, pipeline: pipeline.name })),
));

const isActiveTile = (s) => s.set && filters.converted === s.set.converted && filters.rating === s.set.rating;

async function load() {
  loading.value = true;
  try {
    const params = { page: page.value, per_page: 25 };
    Object.entries(filters).forEach(([k, v]) => { if (v !== '' && v !== null) params[k] = v; });
    const { data } = await api.list(params);
    rows.value = data.data || [];
    Object.assign(pagination, data.meta || {});
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

async function openDetail(id) {
  try {
    const { data } = await api.show(id);
    selected.value = data.data;
  } catch { /* interceptor surfaces the error */ }
}

function applyTile(s) {
  if (!s.set) return;               // pipeline value is a figure, not a filter
  Object.assign(filters, s.set);
  page.value = 1;
  load();
}
function resetFilters() {
  Object.assign(filters, { q: '', converted: 'open', status_id: '', source_id: '', rating: '', owner_id: '' });
  page.value = 1; load();
}

function openCreate() {
  form.id = null; form.errors = {};
  form.data = { name: '', company_name: '', title: '', email: '', phone: '',
                source_id: null, status_id: null, estimated_value: 0 };
  form.open = true;
}
function openEdit(l) {
  form.id = l.id; form.errors = {};
  form.data = {
    name: l.name, company_name: l.company_name ?? '', title: l.title ?? '',
    email: l.email ?? '', phone: l.phone ?? '',
    source_id: l.source?.id ?? null, status_id: l.status?.id ?? null,
    estimated_value: l.estimated_value ?? 0,
  };
  form.open = true;
}

async function submitForm(force = false) {
  form.saving = true; form.errors = {};
  try {
    const payload = { ...form.data };
    ['company_name','title','email','phone'].forEach((k) => { if (!payload[k]) delete payload[k]; });
    if (!force) {
      const clear = await duplicateGuard.check('lead', payload, form.id, () => submitForm(true));
      if (!clear) { form.saving = false; return; }
    }
    const { data } = form.id ? await api.update(form.id, payload) : await api.create(payload);
    toast.success(form.id ? t('leads.updated') : t('leads.created'));
    form.open = false;
    await Promise.all([load(), loadAux()]);
    if (selected.value?.id === data.data.id) selected.value = data.data;
  } catch (e) {
    if (e.response?.status === 422) form.errors = e.response.data?.errors || {};
  } finally { form.saving = false; }
}

async function submitNote() {
  const body = noteDraft.value.trim();
  if (!body || !selected.value) return;
  savingNote.value = true;
  try {
    await api.addNote(selected.value.id, body, noteType.value);
    noteDraft.value = '';
    await Promise.all([openDetail(selected.value.id), load()]);   // logging contact moves the score
  } catch { /* interceptor surfaces the error */ }
  finally { savingNote.value = false; }
}

async function openConvert() {
  if (!selected.value) return;
  conversion.error = '';
  conversion.matches = [];
  conversion.data = {
    account_mode: 'new',
    account_id: null,
    account: {
      name: selected.value.company_name || selected.value.name,
      type: selected.value.company_name ? 'company' : 'individual',
    },
    create_contact: true,
    contact: {
      name: selected.value.name,
      title: selected.value.title || '',
      email: selected.value.email || '',
      phone: selected.value.phone || '',
      mobile: selected.value.mobile || '',
    },
    create_deal: false,
    deal: {
      title: `${selected.value.company_name || selected.value.name} Opportunity`,
      stage_id: null,
      amount: selected.value.estimated_value || 0,
      currency: selected.value.currency || 'USD',
      expected_close_date: selected.value.expected_close_date || '',
    },
  };
  conversion.open = true;
  conversion.loading_options = true;
  try {
    const [accounts, dealMeta] = await Promise.all([customerApi.list({ per_page: 100 }), dealApi.meta()]);
    conversion.accounts = accounts.data.data || [];
    conversion.pipelines = dealMeta.data.data?.pipelines || [];
    try {
      const matches = await duplicateApi.check('account', {
        name: conversion.data.account.name,
        email: selected.value.email || undefined,
        phone: selected.value.phone || undefined,
      });
      conversion.matches = matches.data.data || [];
    } catch { /* suggestions are advisory */ }
  } catch { conversion.error = t('leads.convert_options_failed'); }
  finally { conversion.loading_options = false; }
}
function useMatchingAccount(accountId) {
  conversion.data.account_mode = 'existing';
  conversion.data.account_id = accountId;
}

async function submitConversion() {
  if (!selected.value) return;
  if (conversion.data.account_mode === 'existing' && !conversion.data.account_id) {
    conversion.error = t('leads.choose_account_required');
    return;
  }
  converting.value = true;
  conversion.error = '';
  try {
    const payload = JSON.parse(JSON.stringify(conversion.data));
    if (payload.account_mode === 'new') delete payload.account_id;
    else delete payload.account;
    if (!payload.create_contact) delete payload.contact;
    if (!payload.create_deal) delete payload.deal;
    else if (!payload.deal.expected_close_date) delete payload.deal.expected_close_date;
    const { data } = await api.convert(selected.value.id, payload);
    toast.success(t('leads.convert_ok', { no: data.data.customer.customer_no }));
    selected.value = data.data.lead;
    conversion.open = false;
    await Promise.all([load(), loadAux()]);
  } catch (e) {
    if (e.response?.status === 422) conversion.error = e.response.data?.message || t('leads.convert_failed');
  } finally { converting.value = false; }
}

async function doAutoAssign() {
  if (!selected.value) return;
  try {
    await api.assign(selected.value.id);
    toast.success(t('leads.assigned'));
    await Promise.all([openDetail(selected.value.id), load()]);
  } catch (e) {
    if (e.response?.status === 422) toast.error(e.response.data?.message);
  }
}

async function uploadFile(e) {
  const file = e.target.files?.[0];
  e.target.value = '';
  if (!file || !selected.value) return;
  try {
    await api.upload(selected.value.id, file);
    toast.success(t('leads.uploaded'));
    await openDetail(selected.value.id);
  } catch (err) {
    if (err.response?.status === 422) toast.error(t('leads.upload_rejected'));
  }
}

const money = (v, ccy) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { style: 'currency', currency: ccy || 'USD', maximumFractionDigits: 0 }).format(v);
const compact = (v) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v);

function humanSize(bytes) {
  if (!bytes) return '';
  const u = ['B','KB','MB','GB']; let n = bytes, i = 0;
  while (n >= 1024 && i < u.length - 1) { n /= 1024; i += 1; }
  return `${n < 10 && i > 0 ? n.toFixed(1) : Math.round(n)} ${u[i]}`;
}

const ratingClass = (r) => ({
  hot:  'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  warm: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  cold: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
}[r] || 'bg-slate-100 text-slate-700');

const timelineClass = (ty) => ({
  note:          'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  call:          'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  email:         'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  meeting:       'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  status_change: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  system:        'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
}[ty] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([load(), loadAux()]); });
</script>
