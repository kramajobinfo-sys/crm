<template>
  <div class="page">

    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('pipeline.title') }}</h1>
        <p class="page-sub">{{ $t('pipeline.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="loadBoard">
          <RefreshCw :size="14" :class="loading && 'animate-spin'" /> {{ $t('pipeline.refresh') }}
        </button>
        <button v-if="can('deals.create')" class="btn-primary btn-sm" @click="openCreate">
          <Plus :size="14" /> {{ $t('pipeline.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
      <div v-for="s in statTiles" :key="s.key" class="stat">
        <span class="stat-label">{{ $t(s.label) }}</span>
        <span class="stat-value">
          <span v-if="s.money">{{ compact(stats[s.key]) }}</span>
          <span v-else-if="s.pct">{{ stats[s.key] == null ? '—' : stats[s.key] + '%' }}</span>
          <span v-else>{{ stats[s.key] ?? 0 }}</span>
        </span>
      </div>
    </div>

    <!-- Controls -->
    <div class="card mb-4">
      <div class="toolbar">
        <select v-model="pipelineId" class="input input-sm w-auto" @change="loadBoard">
          <option v-for="p in meta.pipelines" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <label class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted ml-1">
          <input type="checkbox" class="rounded border-slate-300" :checked="mineOnly"
                 @change="mineOnly = $event.target.checked; loadBoard()" />
          {{ $t('pipeline.mine_only') }}
        </label>
        <div class="ml-auto text-[11px] text-ink-subtle">{{ $t('pipeline.drag_hint') }}</div>
      </div>
    </div>

    <!-- Kanban board -->
    <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
    <div v-else class="flex gap-3 overflow-x-auto pb-3">
      <div
        v-for="col in board.columns" :key="col.stage.id"
        class="w-72 shrink-0 flex flex-col rounded-lg bg-slate-50 dark:bg-surface-dark-subtle border border-slate-200 dark:border-slate-700"
        :class="dragOverStage === col.stage.id && 'ring-2 ring-primary-400'"
        @dragover.prevent="dragOverStage = col.stage.id"
        @dragleave="dragOverStage === col.stage.id && (dragOverStage = null)"
        @drop="onDrop(col.stage.id)"
      >
        <div class="px-3 py-2 flex items-center gap-2 border-b border-slate-200 dark:border-slate-700">
          <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: col.stage.color }" />
          <span class="text-xs font-medium text-ink dark:text-ink-dark truncate">{{ col.stage.name }}</span>
          <button v-if="can('pipelines.manage')" class="p-0.5 text-ink-subtle hover:text-ink shrink-0 ml-auto" :title="$t('pipeline.bp.edit')" @click="openBlueprint(col.stage)">
            <Settings2 :size="12" />
          </button>
          <span class="text-[10px] text-ink-subtle bg-white dark:bg-surface-dark px-1.5 py-0.5 rounded-full" :class="!can('pipelines.manage') && 'ml-auto'">{{ col.count }}</span>
        </div>
        <div class="px-3 py-1.5 text-[11px] text-ink-muted dark:text-ink-dark-muted tabular-nums border-b border-slate-100 dark:border-slate-700/60">
          {{ compact(col.total_value) }}
        </div>
        <div class="flex-1 overflow-y-auto p-2 space-y-2 min-h-[6rem] max-h-[calc(100vh-24rem)]">
          <div
            v-for="d in col.deals" :key="d.id"
            class="card p-2.5 cursor-pointer hover:ring-1 hover:ring-primary-300"
            :draggable="can('deals.change_stage')"
            @dragstart="onDragStart(d, col.stage.id)"
            @dragend="dragOverStage = null; dragging = null"
            @click="openDetail(d.id)"
          >
            <div class="text-xs font-medium text-ink dark:text-ink-dark line-clamp-2">{{ d.title }}</div>
            <div class="flex items-center justify-between mt-1.5">
              <span class="text-[11px] tabular-nums text-ink dark:text-ink-dark">{{ money(d.amount, d.currency) }}</span>
              <span class="text-[10px] text-ink-subtle">{{ d.probability }}%</span>
            </div>
            <div class="flex items-center gap-1 mt-1 text-[10px] text-ink-subtle">
              <Building2 :size="10" class="shrink-0" />
              <span class="truncate">{{ d.customer?.name || $t('pipeline.no_customer') }}</span>
            </div>
            <div class="flex items-center justify-between mt-1 text-[10px] text-ink-subtle">
              <span class="font-mono">{{ d.deal_no }}</span>
              <span class="truncate max-w-[8rem]">{{ d.owner?.name || $t('pipeline.unassigned') }}</span>
            </div>
          </div>
          <div v-if="!col.deals.length" class="text-[11px] text-ink-subtle text-center py-4">{{ $t('pipeline.empty_stage') }}</div>
        </div>
      </div>
    </div>

    <!-- Detail drawer -->
    <div v-if="selected" class="fixed inset-0 z-40 flex justify-end bg-black/30" @click.self="selected = null">
      <div class="bg-white dark:bg-surface-dark-muted w-full sm:w-[26rem] h-full shadow-xl flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2 shrink-0">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ selected.title }}</div>
            <div class="text-[11px] text-ink-subtle font-mono">{{ selected.deal_no }}</div>
          </div>
          <span class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(selected.status)">{{ $t(`pipeline.status.${selected.status}`) }}</span>
          <button v-if="can('deals.update')" class="btn-secondary btn-xs" @click="openEdit(selected)">{{ $t('pipeline.edit') }}</button>
          <button v-if="can('activities.create')" class="btn-secondary btn-xs" @click="addFollowUp(selected)">{{ $t('activities.quick_follow_up') }}</button>
          <button v-if="selected.status === 'won' && can('projects.create') && !selected.project" class="btn-primary btn-xs" :disabled="creatingProject" @click="createProjectFromDeal(selected)">{{ creatingProject ? $t('pipeline.creating_project') : $t('pipeline.create_project') }}</button>
          <button v-if="selected.project && can('projects.view')" class="btn-secondary btn-xs" @click="openProject(selected.project)">{{ $t('pipeline.view_project') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <div v-if="selected.status === 'open' && can('deals.change_stage')" class="px-4 py-2 border-b border-slate-100 dark:border-slate-700/60 shrink-0 flex gap-2 items-center">
          <select v-model="moveTarget" class="input text-xs w-auto flex-1" @change="doMove">
            <option v-for="s in activeStages" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <button class="btn-secondary btn-xs text-red-600" @click="openLost">{{ $t('pipeline.mark_lost') }}</button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-4 text-xs">
          <dl class="grid grid-cols-3 gap-y-1.5">
            <dt class="text-ink-subtle">{{ $t('pipeline.col.amount') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark tabular-nums">{{ money(selected.amount, selected.currency) }}</dd>
            <dt class="text-ink-subtle">{{ $t('pipeline.weighted') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark tabular-nums">{{ money(selected.weighted_amount, selected.currency) }} · {{ selected.probability }}%</dd>
            <dt class="text-ink-subtle">{{ $t('pipeline.col.stage') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.stage?.name || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('pipeline.col.customer') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.customer?.name || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('pipeline.col.owner') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.owner?.name || $t('pipeline.unassigned') }}</dd>
            <dt class="text-ink-subtle">{{ $t('pipeline.close_date') }}</dt>
            <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.expected_close_date || '—' }}</dd>
            <template v-if="selected.status === 'lost'">
              <dt class="text-ink-subtle">{{ $t('pipeline.lost_reason') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.lost_reason?.name || '—' }}</dd>
            </template>
          </dl>

          <!-- Deal contacts -->
          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('pipeline.deal_contacts') }}</div>
            <div v-if="!selected.contacts?.length" class="text-ink-subtle">{{ $t('pipeline.no_deal_contacts') }}</div>
            <div v-for="contact in selected.contacts" :key="contact.id" class="py-1.5 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <div class="flex items-center gap-1.5">
                <span class="font-medium text-ink dark:text-ink-dark">{{ contact.name }}</span>
                <span v-if="contact.is_primary" class="text-[9px] px-1 py-0.5 rounded bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $t('pipeline.primary_contact') }}</span>
                <span class="text-[9px] text-ink-subtle ml-auto">{{ $t(`pipeline.contact_role.${contact.role}`) }}</span>
              </div>
              <div class="text-[10px] text-ink-subtle">{{ contact.title || contact.email || contact.phone || '—' }}</div>
            </div>
          </div>

          <!-- Line items -->
          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('pipeline.line_items') }}</div>
            <div v-if="!selected.products?.length" class="text-ink-subtle">{{ $t('pipeline.no_items') }}</div>
            <table class="data-table" v-else>
              <tbody>
                <tr v-for="p in selected.products" :key="p.id" class="border-b border-slate-100 dark:border-slate-700/60 last:border-0">
                  <td class="py-1 text-ink dark:text-ink-dark">
                    {{ p.name }}
                    <span class="text-ink-subtle">× {{ p.quantity }}</span>
                    <span v-if="p.discount_pct > 0" class="text-emerald-600">−{{ p.discount_pct }}%</span>
                  </td>
                  <td class="py-1 text-right tabular-nums text-ink-muted dark:text-ink-dark-muted">{{ money(p.line_total, selected.currency) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <CustomFieldsDisplay :fields="meta.custom_fields" :values="selected.custom_fields" />

          <!-- Timeline -->
          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('pipeline.timeline') }}</div>
            <div v-if="can('deals.update')" class="flex gap-1.5 mb-2">
              <select v-model="noteType" class="input text-xs w-auto">
                <option value="note">{{ $t('pipeline.tl.note') }}</option>
                <option value="call">{{ $t('pipeline.tl.call') }}</option>
                <option value="email">{{ $t('pipeline.tl.email') }}</option>
                <option value="meeting">{{ $t('pipeline.tl.meeting') }}</option>
              </select>
              <input v-model="noteDraft" class="input text-xs" :placeholder="$t('pipeline.add_note')" @keyup.enter="submitNote" />
              <button class="btn-primary text-[11px] px-2" :disabled="!noteDraft.trim() || savingNote" @click="submitNote"><Send :size="11" /></button>
            </div>
            <div v-for="t in selected.timeline" :key="t.id" class="py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <div class="flex items-center gap-1.5">
                <span class="text-[9px] px-1 py-0.5 rounded" :class="timelineClass(t.type)">{{ $t(`pipeline.tl.${t.type}`) }}</span>
                <span class="text-ink dark:text-ink-dark truncate">{{ t.title }}</span>
                <span class="text-ink-subtle ml-auto shrink-0">{{ t.occurred_human }}</span>
              </div>
              <div v-if="t.body" class="text-ink-muted dark:text-ink-dark-muted mt-0.5">{{ t.body }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Mark lost modal -->
    <div v-if="lost.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="lost.open = false">
      <div class="card w-full max-w-sm p-4">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('pipeline.mark_lost') }}</div>
        <label class="label">{{ $t('pipeline.lost_reason') }}</label>
        <select v-model="lost.reason_id" class="input text-sm mb-2">
          <option :value="null">—</option>
          <option v-for="r in meta.lost_reasons" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
        <label class="label">{{ $t('pipeline.note') }}</label>
        <textarea v-model="lost.note" rows="2" class="input text-sm"></textarea>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="lost.open = false">{{ $t('pipeline.cancel') }}</button>
          <button class="btn-primary btn-sm bg-red-600 hover:bg-red-700" :disabled="lost.saving" @click="submitLost">{{ $t('pipeline.confirm_lost') }}</button>
        </div>
      </div>
    </div>

    <!-- Create / edit modal -->
    <div v-if="form.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="form.open = false">
      <div class="card w-full max-w-2xl p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ form.id ? $t('pipeline.edit_title') : $t('pipeline.new_title') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2">
            <label class="label">{{ $t('pipeline.col.title') }} *</label>
            <input v-model="form.data.title" class="input text-sm" />
            <p v-if="form.errors.title" class="text-[11px] text-red-500 mt-0.5">{{ form.errors.title[0] }}</p>
          </div>
          <div v-if="!form.id">
            <label class="label">{{ $t('pipeline.col.stage') }}</label>
            <select v-model="form.data.stage_id" class="input text-sm">
              <option :value="null">{{ $t('pipeline.first_stage') }}</option>
              <option v-for="s in allStages" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">{{ $t('pipeline.col.customer') }}</label>
            <select v-model="form.data.customer_id" class="input text-sm" @change="onAccountChange">
              <option :value="null">—</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">{{ $t('pipeline.probability') }} (%)</label>
            <input v-model.number="form.data.probability" type="number" min="0" max="100" class="input text-sm" />
          </div>
          <div>
            <label class="label">{{ $t('pipeline.close_date') }}</label>
            <input v-model="form.data.expected_close_date" type="date" class="input text-sm" />
          </div>
          <div>
            <label class="label">Forecast category</label>
            <select v-model="form.data.forecast_category" class="input text-sm capitalize">
              <option v-for="c in (meta.forecast_categories || [])" :key="c" :value="c">{{ c.replace('_', ' ') }}</option>
            </select>
          </div>
          <div>
            <label class="label">Competitor</label>
            <input v-model="form.data.competitor" class="input text-sm" placeholder="Who we're up against" />
          </div>
        </div>

        <!-- Deal contacts editor -->
        <div class="mt-3">
          <div class="flex items-center mb-1">
            <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('pipeline.deal_contacts') }}</span>
            <button v-if="form.data.customer_id" class="text-[11px] text-primary-600 hover:underline ml-auto" @click="addDealContact">+ {{ $t('pipeline.add_contact') }}</button>
          </div>
          <p v-if="!form.data.customer_id" class="text-[11px] text-ink-subtle">{{ $t('pipeline.select_account_first') }}</p>
          <p v-else-if="!contactOptions.length" class="text-[11px] text-ink-subtle">{{ $t('pipeline.account_has_no_contacts') }}</p>
          <div v-for="(row, i) in form.data.contacts" :key="i" class="grid grid-cols-[minmax(0,1fr)_9rem_auto_auto] gap-1.5 mb-1.5 items-center">
            <select v-model="row.contact_id" class="input text-xs">
              <option :value="null">{{ $t('pipeline.choose_contact') }}</option>
              <option v-for="contact in contactOptions" :key="contact.id" :value="contact.id" :disabled="isContactUsed(contact.id, i)">{{ contact.name }}</option>
            </select>
            <select v-model="row.role" class="input text-xs">
              <option v-for="role in contactRoles" :key="role" :value="role">{{ $t(`pipeline.contact_role.${role}`) }}</option>
            </select>
            <label class="flex items-center gap-1 text-[10px] text-ink-muted whitespace-nowrap">
              <input type="radio" :checked="row.is_primary" @change="setPrimaryContact(i)" /> {{ $t('pipeline.primary_contact') }}
            </label>
            <button class="p-1 text-ink-subtle hover:text-red-500" @click="form.data.contacts.splice(i, 1)"><X :size="12" /></button>
          </div>
          <p v-if="form.errors.contacts" class="text-[11px] text-red-500 mt-0.5">{{ form.errors.contacts[0] }}</p>
        </div>

        <!-- Line items editor -->
        <div class="mt-3">
          <div class="flex items-center mb-1">
            <span class="text-[10px] tracking-wider text-ink-subtle">{{ $t('pipeline.line_items') }}</span>
            <button class="text-[11px] text-primary-600 hover:underline ml-auto" @click="addLine">+ {{ $t('pipeline.add_line') }}</button>
          </div>
          <div v-for="(line, i) in form.data.products" :key="i" class="flex gap-1.5 mb-1.5 items-center">
            <input v-model="line.name" class="input text-xs flex-1" :placeholder="$t('pipeline.item_name')" />
            <input v-model.number="line.quantity" type="number" min="0" class="input text-xs w-16" :placeholder="$t('pipeline.qty')" />
            <input v-model.number="line.unit_price" type="number" min="0" class="input text-xs w-24" :placeholder="$t('pipeline.price')" />
            <input v-model.number="line.discount_pct" type="number" min="0" max="100" class="input text-xs w-16" placeholder="%" />
            <span class="text-[11px] tabular-nums text-ink-muted w-20 text-right">{{ compact(lineTotal(line)) }}</span>
            <button class="p-1 text-ink-subtle hover:text-red-500" @click="form.data.products.splice(i, 1)"><X :size="12" /></button>
          </div>
          <div v-if="form.data.products.length" class="text-right text-xs text-ink dark:text-ink-dark mt-1">
            {{ $t('pipeline.total') }}: <span class="font-semibold tabular-nums">{{ money(formTotal, form.data.currency || 'AED') }}</span>
          </div>
          <p v-else class="text-[11px] text-ink-subtle">{{ $t('pipeline.no_items_hint') }}</p>
        </div>

        <CustomFieldsInput v-model="form.data.custom_fields" :fields="meta.custom_fields" />

        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('pipeline.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving" @click="submitForm">{{ form.saving ? $t('pipeline.saving') : $t('pipeline.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Blueprint modal -->
    <div v-if="bpModal.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="bpModal.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-1">{{ $t('pipeline.bp.title') }}</div>
        <div class="text-xs text-ink-subtle mb-3">{{ bpModal.stage?.name }}</div>

        <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('pipeline.bp.required') }}</div>
        <div class="grid grid-cols-2 gap-1 mb-3">
          <label v-for="f in blueprintFields" :key="f" class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted">
            <input type="checkbox" class="rounded border-slate-300" :value="f" v-model="bpModal.requiredFields" /> {{ $t(`pipeline.bp.field.${f}`) }}
          </label>
        </div>

        <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('pipeline.bp.allowed_next') }}</div>
        <div class="grid grid-cols-2 gap-1 mb-1">
          <label v-for="s in blueprintOtherStages" :key="s.id" class="flex items-center gap-1.5 text-xs text-ink-muted dark:text-ink-dark-muted">
            <input type="checkbox" class="rounded border-slate-300" :value="s.id" v-model="bpModal.allowedNext" /> {{ s.name }}
          </label>
        </div>
        <p class="text-[11px] text-ink-subtle mb-3">{{ $t('pipeline.bp.allowed_next_hint') }}</p>
        <p class="text-[11px] text-ink-subtle mb-3">{{ $t('pipeline.bp.empty_hint') }}</p>

        <div class="flex justify-end gap-2">
          <button class="btn-secondary btn-sm" @click="bpModal.open = false">{{ $t('pipeline.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="bpModal.saving" @click="saveBlueprint">{{ bpModal.saving ? $t('pipeline.saving') : $t('pipeline.save') }}</button>
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
import api from '@/services/deals';
import projectApi from '@/services/projects';
import customerApi from '@/services/customers';
import contactApi from '@/services/contacts';
import { RefreshCw, Plus, X, Send, Building2, Settings2 } from 'lucide-vue-next';
import CustomFieldsInput from '@/components/crm/CustomFieldsInput.vue';
import CustomFieldsDisplay from '@/components/crm/CustomFieldsDisplay.vue';
import { seedCustomFields, stripBlankCustomFields } from '@/composables/useCustomFields';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const can = (p) => auth.can(p);

function addFollowUp(deal) {
  router.push({ name: 'activities', query: { new: 'task', related_type: 'deal', related_id: deal.id } });
}

const statTiles = [
  { key: 'open',           label: 'pipeline.stat.open' },
  { key: 'won',            label: 'pipeline.stat.won' },
  { key: 'lost',           label: 'pipeline.stat.lost' },
  { key: 'open_value',     label: 'pipeline.stat.open_value', money: true },
  { key: 'weighted_value', label: 'pipeline.stat.weighted', money: true },
  { key: 'win_rate',       label: 'pipeline.stat.win_rate', pct: true },
];

const board      = reactive({ pipeline: null, columns: [] });
const stats      = reactive({});
const meta       = reactive({ pipelines: [], lost_reasons: [], statuses: [], forecast_categories: [], custom_fields: [] });
const customers  = ref([]);
const contactOptions = ref([]);
const selected   = ref(null);
const loading    = ref(false);
const pipelineId = ref(null);
const mineOnly   = ref(false);
const dragging   = ref(null);
const dragOverStage = ref(null);
const moveTarget = ref(null);
const noteDraft  = ref('');
const noteType   = ref('note');
const creatingProject = ref(false);

async function createProjectFromDeal(deal) {
  creatingProject.value = true;
  try {
    const { data } = await projectApi.fromDeal(deal.id);
    toast.success(t('pipeline.project_created'));
    selected.value = { ...selected.value, project: data.data };
    router.push({ name: 'projects' });
  } finally { creatingProject.value = false; }
}

function openProject(project) {
  router.push({ name: 'projects', query: { project: project.id } });
}
const savingNote = ref(false);
const lost = reactive({ open: false, reason_id: null, note: '', saving: false });
const form = reactive({ open: false, id: null, saving: false, data: { products: [] }, errors: {} });
const contactRoles = ['decision_maker', 'champion', 'influencer', 'evaluator', 'billing', 'other'];
const blueprintFields = ['amount', 'customer_id', 'owner_id', 'expected_close_date', 'probability', 'lost_reason_id'];
const bpModal = reactive({ open: false, stage: null, requiredFields: [], allowedNext: [], saving: false });

const currentPipeline = computed(() => meta.pipelines.find((p) => p.id === pipelineId.value));
const allStages   = computed(() => currentPipeline.value?.stages || []);
const activeStages = computed(() => {
  const stages = allStages.value.filter((s) => !s.is_won && !s.is_lost);
  const current = allStages.value.find((s) => s.id === selected.value?.stage?.id);
  const allowed = current?.allowed_next_stage_ids;
  if (!current || !allowed || !allowed.length) return stages;
  return stages.filter((s) => s.id === current.id || allowed.includes(s.id));
});
const blueprintOtherStages = computed(() => allStages.value.filter((s) => s.id !== bpModal.stage?.id));

async function loadBoard() {
  loading.value = true;
  try {
    const params = {};
    if (pipelineId.value) params.pipeline_id = pipelineId.value;
    if (mineOnly.value) params.owner_id = 'me';
    const { data } = await api.board(params);
    Object.assign(board, data.data);
  } catch { /* interceptor surfaces the error */ }
  finally { loading.value = false; }
}

async function loadAux() {
  try {
    const [s, m] = await Promise.all([api.stats(), api.meta()]);
    Object.assign(stats, s.data.data || {});
    Object.assign(meta, m.data.data || {});
    if (!pipelineId.value && meta.pipelines.length) {
      pipelineId.value = (meta.pipelines.find((p) => p.is_default) || meta.pipelines[0]).id;
    }
  } catch { /* non-critical */ }
}

async function loadCustomers() {
  try {
    const { data } = await customerApi.list({ per_page: 100 });
    customers.value = data.data || [];
  } catch { /* non-critical */ }
}

async function openDetail(id) {
  try {
    const { data } = await api.show(id);
    selected.value = data.data;
    moveTarget.value = data.data.stage?.id ?? null;
  } catch { /* interceptor surfaces the error */ }
}

function onDragStart(deal, fromStage) { dragging.value = { deal, fromStage }; }

async function onDrop(stageId) {
  dragOverStage.value = null;
  const d = dragging.value;
  dragging.value = null;
  if (!d || d.fromStage === stageId) return;
  const fromCol = board.columns.find((c) => c.stage.id === d.fromStage);
  const toCol = board.columns.find((c) => c.stage.id === stageId);
  const allowed = fromCol?.stage.allowed_next_stage_ids;
  if (!toCol?.stage.is_lost && allowed?.length && !allowed.includes(stageId)) {
    toast.error(t('pipeline.bp.blocked', { from: fromCol.stage.name, to: toCol?.stage.name }));
    return;
  }
  try {
    await api.move(d.deal.id, stageId);
    await Promise.all([loadBoard(), refreshStats()]);
  } catch (e) {
    if (e.response?.status === 422) toast.error(e.response.data?.message);
  }
}

async function doMove() {
  if (!selected.value || moveTarget.value === selected.value.stage?.id) return;
  try {
    const { data } = await api.move(selected.value.id, moveTarget.value);
    selected.value = data.data;
    await Promise.all([loadBoard(), refreshStats()]);
  } catch (e) {
    if (e.response?.status === 422) toast.error(e.response.data?.message);
  }
}

function openBlueprint(stage) {
  bpModal.stage = stage;
  bpModal.requiredFields = [...(stage.required_fields || [])];
  bpModal.allowedNext = [...(stage.allowed_next_stage_ids || [])];
  bpModal.open = true;
}
async function saveBlueprint() {
  bpModal.saving = true;
  try {
    await api.updateStageBlueprint(pipelineId.value, bpModal.stage.id, {
      required_fields: bpModal.requiredFields.length ? bpModal.requiredFields : null,
      allowed_next_stage_ids: bpModal.allowedNext.length ? bpModal.allowedNext : null,
    });
    toast.success(t('pipeline.bp.saved'));
    bpModal.open = false;
    await Promise.all([loadAux(), loadBoard()]);
  } catch (e) {
    if (e.response?.status === 422) toast.error(e.response.data?.message);
  } finally { bpModal.saving = false; }
}

function openLost() { lost.open = true; lost.reason_id = null; lost.note = ''; }
async function submitLost() {
  lost.saving = true;
  try {
    const { data } = await api.markLost(selected.value.id, { lost_reason_id: lost.reason_id, note: lost.note || undefined });
    selected.value = data.data;
    lost.open = false;
    toast.success(t('pipeline.lost_ok'));
    await Promise.all([loadBoard(), refreshStats()]);
  } catch { /* interceptor surfaces the error */ }
  finally { lost.saving = false; }
}

async function refreshStats() {
  try { const s = await api.stats(); Object.assign(stats, s.data.data || {}); } catch { /* noop */ }
}

function openCreate() {
  form.id = null; form.errors = {};
  contactOptions.value = [];
  form.data = { title: '', stage_id: null, customer_id: null, probability: null, expected_close_date: '', currency: 'AED', forecast_category: 'pipeline', competitor: '', contacts: [], products: [], custom_fields: seedCustomFields(meta.custom_fields) };
  form.open = true;
}
async function openEdit(d) {
  form.id = d.id; form.errors = {};
  form.data = {
    title: d.title, customer_id: d.customer?.id ?? null, probability: d.probability,
    forecast_category: d.forecast_category ?? 'pipeline', competitor: d.competitor ?? '',
    expected_close_date: d.expected_close_date ?? '', currency: d.currency ?? 'AED',
    contacts: (d.contacts || []).map((contact) => ({
      contact_id: contact.id, role: contact.role || 'other', is_primary: !!contact.is_primary,
    })),
    products: (d.products || []).map((p) => ({
      name: p.name, quantity: p.quantity, unit_price: p.unit_price, discount_pct: p.discount_pct,
    })),
    custom_fields: seedCustomFields(meta.custom_fields, d.custom_fields),
  };
  await loadContactOptions(form.data.customer_id);
  form.open = true;
}
async function loadContactOptions(accountId) {
  contactOptions.value = [];
  if (!accountId) return;
  try {
    const { data } = await contactApi.list({ customer_id: accountId, per_page: 100 });
    contactOptions.value = data.data || [];
  } catch { /* interceptor surfaces the error */ }
}
async function onAccountChange() {
  form.data.contacts = [];
  await loadContactOptions(form.data.customer_id);
}
function addDealContact() {
  if (!form.data.customer_id || !contactOptions.value.length) return;
  const available = contactOptions.value.find((contact) => !form.data.contacts.some((row) => row.contact_id === contact.id));
  if (!available) return;
  form.data.contacts.push({
    contact_id: available.id,
    role: 'other',
    is_primary: form.data.contacts.length === 0,
  });
}
function setPrimaryContact(index) {
  form.data.contacts.forEach((row, i) => { row.is_primary = i === index; });
}
function isContactUsed(contactId, currentIndex) {
  return form.data.contacts.some((row, i) => i !== currentIndex && row.contact_id === contactId);
}
function addLine() { form.data.products.push({ name: '', quantity: 1, unit_price: 0, discount_pct: 0 }); }

const lineTotal = (l) => Math.round((l.quantity || 0) * (l.unit_price || 0) * (1 - (l.discount_pct || 0) / 100) * 100) / 100;
const formTotal = computed(() => form.data.products.reduce((sum, l) => sum + lineTotal(l), 0));

async function submitForm() {
  form.saving = true; form.errors = {};
  try {
    const payload = { ...form.data };
    if (!payload.expected_close_date) delete payload.expected_close_date;
    if (payload.stage_id == null) delete payload.stage_id;
    payload.contacts = (payload.contacts || []).filter((row) => row.contact_id);
    if (!payload.products.length) delete payload.products;
    if (payload.custom_fields) payload.custom_fields = stripBlankCustomFields(payload.custom_fields);
    const { data } = form.id ? await api.update(form.id, payload) : await api.create(payload);
    toast.success(form.id ? t('pipeline.updated') : t('pipeline.created'));
    form.open = false;
    await Promise.all([loadBoard(), refreshStats()]);
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
    await openDetail(selected.value.id);
  } catch { /* interceptor surfaces the error */ }
  finally { savingNote.value = false; }
}

const money = (v, ccy) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { style: 'currency', currency: ccy || 'USD', maximumFractionDigits: 0 }).format(v);
const compact = (v) => v == null ? '—'
  : new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(v);

const statusClass = (s) => ({
  open: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  won:  'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  lost: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
}[s] || 'bg-slate-100 text-slate-700');

const timelineClass = (ty) => ({
  note:          'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  call:          'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  email:         'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  meeting:       'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  status_change: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  system:        'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
}[ty] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await loadAux(); await Promise.all([loadBoard(), loadCustomers()]); if (router.currentRoute.value.query.create) openCreate(); });
</script>
