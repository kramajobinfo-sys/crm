<template>
  <div class="page">
    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('hr.title') }}</h1>
        <p class="page-sub">{{ $t('hr.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="reload"><RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('hr.refresh') }}</button>
        <button v-if="tab === 'employees' && can('employees.create')" class="btn-primary btn-sm" @click="openEmployee()"><Plus :size="12" /> {{ $t('hr.new_employee') }}</button>
        <button v-if="tab === 'leave' && can('leave.create')" class="btn-primary btn-sm" @click="openLeave()"><Plus :size="12" /> {{ $t('hr.request_leave') }}</button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2.5 mb-3">
      <div v-for="s in statTiles" :key="s.key" class="stat">
        <span class="stat-label">{{ $t(s.label) }}</span>
        <span class="stat-value">{{ stats[s.key] ?? 0 }}</span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-3 border-b border-slate-200 dark:border-slate-700">
      <button v-for="tb in visibleTabs" :key="tb" class="px-3 py-1.5 text-xs -mb-px border-b-2"
              :class="tab === tb ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
              @click="switchTab(tb)">
        {{ $t(`hr.tab.${tb}`) }}
        <span v-if="tb === 'leave' && stats.pending_leave" class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-primary-100 text-primary-700">{{ stats.pending_leave }}</span>
      </button>
    </div>

    <!-- ===== EMPLOYEES ===== -->
    <div v-if="tab === 'employees'" class="flex gap-3 items-start">
      <div class="panel flex-1 min-w-0">
        <div class="toolbar border-b border-line dark:border-line-dark">
          <input v-model="empFilters.q" class="input input-sm w-52" :placeholder="$t('hr.search')" @keyup.enter="loadEmployees" />
          <select v-model="empFilters.status" class="input input-sm w-auto" @change="loadEmployees">
            <option value="all">{{ $t('hr.all_statuses') }}</option>
            <option value="active">{{ $t('hr.st.active') }}</option>
            <option value="on_leave">{{ $t('hr.st.on_leave') }}</option>
            <option value="terminated">{{ $t('hr.st.terminated') }}</option>
          </select>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('hr.e.name') }}</th>
              <th class="hidden md:table-cell">{{ $t('hr.e.department') }}</th>
              <th class="hidden lg:table-cell">{{ $t('hr.e.title') }}</th>
              <th>{{ $t('hr.e.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in employees" :key="e.id"
                class="cursor-pointer"
                :class="selectedEmp?.id === e.id && 'is-selected'"
                @click="openEmpDetail(e.id)">
              <td><div class="text-ink dark:text-ink-dark">{{ e.full_name }}</div><div class="text-[11px] text-ink-subtle font-mono">{{ e.employee_no }}</div></td>
              <td class="hidden md:table-cell text-ink-muted">{{ e.department?.name || '—' }}</td>
              <td class="hidden lg:table-cell text-ink-muted">{{ e.job_title || '—' }}</td>
              <td><span class="text-[10px] px-1.5 py-0.5 rounded" :class="empStatusClass(e.status)">{{ $t(`hr.st.${e.status}`) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Employee detail -->
      <div v-if="selectedEmp" class="card w-full sm:w-96 shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-16rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ selectedEmp.full_name }}</div>
            <div class="text-[11px] text-ink-subtle">{{ selectedEmp.job_title }} · {{ selectedEmp.employee_no }}</div>
          </div>
          <button v-if="can('employees.update')" class="btn-secondary btn-xs" @click="openEmployee(selectedEmp)">{{ $t('hr.edit') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selectedEmp = null"><X :size="14" /></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <dl class="grid grid-cols-3 gap-y-1.5">
            <dt class="text-ink-subtle">{{ $t('hr.e.department') }}</dt><dd class="col-span-2 text-ink dark:text-ink-dark">{{ selectedEmp.department?.name || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('hr.e.email') }}</dt><dd class="col-span-2 text-ink dark:text-ink-dark">{{ selectedEmp.email || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('hr.e.hire_date') }}</dt><dd class="col-span-2 text-ink dark:text-ink-dark">{{ selectedEmp.hire_date || '—' }}</dd>
            <dt class="text-ink-subtle">{{ $t('hr.e.type') }}</dt><dd class="col-span-2 text-ink dark:text-ink-dark">{{ $t(`hr.et.${selectedEmp.employment_type}`) }}</dd>
          </dl>
          <div>
            <div class="text-[10px] tracking-wider text-ink-subtle mb-1">{{ $t('hr.leave_balances') }}</div>
            <div v-for="b in selectedEmp.leave_balances" :key="b.id" class="flex items-center gap-2 py-1">
              <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: b.color }" />
              <span class="text-ink dark:text-ink-dark flex-1">{{ b.type }}</span>
              <span class="text-ink-muted tabular-nums">{{ b.remaining }} / {{ b.entitled }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== ATTENDANCE ===== -->
    <div v-else-if="tab === 'attendance'" class="panel">
      <div class="toolbar border-b border-line dark:border-line-dark">
        <input v-model="attDate" type="date" class="input input-sm w-auto" @change="loadAttendance" />
        <span class="text-[11px] text-ink-subtle">{{ $t('hr.attendance_for') }}</span>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('hr.e.name') }}</th>
            <th>{{ $t('hr.a.check_in') }}</th>
            <th>{{ $t('hr.a.check_out') }}</th>
            <th>{{ $t('hr.a.hours') }}</th>
            <th>{{ $t('hr.a.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in attendance" :key="a.id">
            <td class="text-ink dark:text-ink-dark">{{ a.employee }}</td>
            <td class="text-ink-muted tabular-nums">{{ a.check_in || '—' }}</td>
            <td class="text-ink-muted tabular-nums">{{ a.check_out || '—' }}</td>
            <td class="td-num text-ink-muted">{{ a.hours_worked }}</td>
            <td><span class="text-[10px] px-1.5 py-0.5 rounded" :class="attStatusClass(a.status)">{{ $t(`hr.as.${a.status}`) }}</span></td>
          </tr>
          <tr v-if="!attendance.length"><td colspan="5" class="text-center text-ink-subtle py-10">{{ $t('hr.no_attendance') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ===== LEAVE ===== -->
    <div v-else class="card overflow-hidden">
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex gap-2">
        <select v-model="leaveFilters.status" class="input text-sm w-auto" @change="loadLeave">
          <option value="all">{{ $t('hr.all_statuses') }}</option>
          <option v-for="s in ['pending','approved','rejected','cancelled']" :key="s" :value="s">{{ $t(`hr.ls.${s}`) }}</option>
        </select>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('hr.e.name') }}</th>
            <th class="hidden md:table-cell">{{ $t('hr.l.type') }}</th>
            <th>{{ $t('hr.l.dates') }}</th>
            <th class="th-num">{{ $t('hr.l.days') }}</th>
            <th>{{ $t('hr.l.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in leaveRequests" :key="r.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2 text-ink dark:text-ink-dark">{{ r.employee?.name }}</td>
            <td class="px-3 py-2 hidden md:table-cell">
              <span class="text-[10px] px-1.5 py-0.5 rounded" :style="{ backgroundColor: (r.leave_type?.color || '#64748B') + '22', color: r.leave_type?.color }">{{ r.leave_type?.name }}</span>
            </td>
            <td class="px-3 py-2 text-ink-muted text-[11px]">{{ r.start_date }} → {{ r.end_date }}</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ r.days }}</td>
            <td class="px-3 py-2"><span class="text-[10px] px-1.5 py-0.5 rounded" :class="leaveStatusClass(r.status)">{{ $t(`hr.ls.${r.status}`) }}</span></td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <template v-if="r.status === 'pending' && can('leave.approve')">
                <button class="text-[11px] text-emerald-600 hover:underline" @click="decide(r.id, 'approve')">{{ $t('hr.approve') }}</button>
                <button class="text-[11px] text-red-500 hover:underline ml-2" @click="decide(r.id, 'reject')">{{ $t('hr.reject') }}</button>
              </template>
              <button v-else-if="r.status === 'approved' && can('leave.approve')" class="text-[11px] text-ink-muted hover:underline" @click="decide(r.id, 'cancel')">{{ $t('hr.cancel') }}</button>
            </td>
          </tr>
          <tr v-if="!leaveRequests.length"><td colspan="6" class="text-center text-ink-subtle py-10">{{ $t('hr.no_leave') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Employee modal -->
    <div v-if="empForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="empForm.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ empForm.id ? $t('hr.edit_employee') : $t('hr.new_employee') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div><label class="label">{{ $t('hr.e.first_name') }} *</label><input v-model="empForm.data.first_name" class="input text-sm" />
            <p v-if="empForm.errors.first_name" class="text-[11px] text-red-500">{{ empForm.errors.first_name[0] }}</p></div>
          <div><label class="label">{{ $t('hr.e.last_name') }}</label><input v-model="empForm.data.last_name" class="input text-sm" /></div>
          <div><label class="label">{{ $t('hr.e.email') }}</label><input v-model="empForm.data.email" class="input text-sm" /></div>
          <div><label class="label">{{ $t('hr.e.phone') }}</label><input v-model="empForm.data.phone" class="input text-sm" /></div>
          <div><label class="label">{{ $t('hr.e.department') }}</label>
            <select v-model="empForm.data.department_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="d in meta.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select></div>
          <div><label class="label">{{ $t('hr.e.title') }}</label><input v-model="empForm.data.job_title" class="input text-sm" /></div>
          <div><label class="label">{{ $t('hr.e.type') }}</label>
            <select v-model="empForm.data.employment_type" class="input text-sm">
              <option v-for="ty in meta.employment_types" :key="ty" :value="ty">{{ $t(`hr.et.${ty}`) }}</option>
            </select></div>
          <div><label class="label">{{ $t('hr.e.hire_date') }}</label><input v-model="empForm.data.hire_date" type="date" class="input text-sm" /></div>
          <div><label class="label">{{ $t('hr.e.salary') }}</label><input v-model.number="empForm.data.salary" type="number" min="0" class="input text-sm" /></div>
          <div><label class="label">{{ $t('hr.e.status') }}</label>
            <select v-model="empForm.data.status" class="input text-sm">
              <option v-for="s in meta.statuses" :key="s" :value="s">{{ $t(`hr.st.${s}`) }}</option>
            </select></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="empForm.open = false">{{ $t('hr.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="empForm.saving" @click="submitEmployee">{{ empForm.saving ? $t('hr.saving') : $t('hr.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Leave request modal -->
    <div v-if="leaveForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="leaveForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('hr.request_leave') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div class="col-span-2"><label class="label">{{ $t('hr.e.name') }} *</label>
            <select v-model="leaveForm.data.employee_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }}</option>
            </select></div>
          <div class="col-span-2"><label class="label">{{ $t('hr.l.type') }} *</label>
            <select v-model="leaveForm.data.leave_type_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.name }} ({{ lt.days_per_year }}d)</option>
            </select></div>
          <div><label class="label">{{ $t('hr.l.start') }} *</label><input v-model="leaveForm.data.start_date" type="date" class="input text-sm" /></div>
          <div><label class="label">{{ $t('hr.l.end') }} *</label><input v-model="leaveForm.data.end_date" type="date" class="input text-sm" /></div>
          <div class="col-span-2"><label class="label">{{ $t('hr.l.reason') }}</label><input v-model="leaveForm.data.reason" class="input text-sm" /></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="leaveForm.open = false">{{ $t('hr.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="leaveForm.saving" @click="submitLeave">{{ leaveForm.saving ? $t('hr.saving') : $t('hr.submit') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/hr';
import { RefreshCw, Plus, X } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const can = (p) => auth.can(p);

const tabPerm = { employees: 'employees.view', attendance: 'attendance.view', leave: 'leave.view' };
const allTabs = ['employees', 'attendance', 'leave'];
const visibleTabs = computed(() => allTabs.filter((tb) => can(tabPerm[tb])));
const statTiles = [
  { key: 'headcount', label: 'hr.stat.headcount' },
  { key: 'present_today', label: 'hr.stat.present' },
  { key: 'on_leave', label: 'hr.stat.on_leave' },
  { key: 'pending_leave', label: 'hr.stat.pending' },
  { key: 'new_this_month', label: 'hr.stat.new' },
];

const tab = ref(visibleTabs.value[0] || 'employees');
const employees = ref([]);
const attendance = ref([]);
const leaveRequests = ref([]);
const leaveTypes = ref([]);
const stats = reactive({});
const meta = reactive({ departments: [], branches: [], employment_types: [], statuses: [] });
const selectedEmp = ref(null);
const loading = ref(false);
const attDate = ref(new Date().toISOString().slice(0, 10));
const empFilters = reactive({ q: '', status: 'all' });
const leaveFilters = reactive({ status: 'all' });
const empForm = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });
const leaveForm = reactive({ open: false, saving: false, data: {} });

function switchTab(tb) { tab.value = tb; selectedEmp.value = null; loadTab(); }
async function loadTab() {
  if (tab.value === 'employees') await loadEmployees();
  else if (tab.value === 'attendance') await loadAttendance();
  else await loadLeave();
}

async function loadEmployees() {
  loading.value = true;
  try { const { data } = await api.employees({ q: empFilters.q || undefined, status: empFilters.status !== 'all' ? empFilters.status : undefined, per_page: 100 }); employees.value = data.data || []; }
  catch { /* noop */ } finally { loading.value = false; }
}
async function loadAttendance() {
  loading.value = true;
  try { const { data } = await api.attendance({ date: attDate.value }); attendance.value = data.data || []; }
  catch { /* noop */ } finally { loading.value = false; }
}
async function loadLeave() {
  loading.value = true;
  try { const { data } = await api.leaveRequests({ status: leaveFilters.status !== 'all' ? leaveFilters.status : undefined, per_page: 100 }); leaveRequests.value = data.data || []; }
  catch { /* noop */ } finally { loading.value = false; }
}
async function loadAux() {
  try {
    const [s, m, lt] = await Promise.all([api.employeeStats(), api.employeeMeta(), api.leaveTypes()]);
    Object.assign(stats, s.data.data || {}); Object.assign(meta, m.data.data || {}); leaveTypes.value = lt.data.data || [];
  } catch { /* noop */ }
}
function reload() { return Promise.all([loadTab(), loadAux()]); }

async function openEmpDetail(id) { try { const { data } = await api.employee(id); selectedEmp.value = data.data; } catch { /* noop */ } }

function openEmployee(e) {
  empForm.id = e?.id ?? null; empForm.errors = {};
  empForm.data = {
    first_name: e?.first_name ?? '', last_name: e?.last_name ?? '', email: e?.email ?? '', phone: e?.phone ?? '',
    department_id: e?.department?.id ?? null, job_title: e?.job_title ?? '',
    employment_type: e?.employment_type ?? 'full_time', hire_date: e?.hire_date ?? '',
    salary: e?.salary ?? 0, status: e?.status ?? 'active',
  };
  empForm.open = true;
}
async function submitEmployee() {
  empForm.saving = true; empForm.errors = {};
  try {
    empForm.id ? await api.updateEmployee(empForm.id, empForm.data) : await api.createEmployee(empForm.data);
    toast.success(t('hr.saved'));
    empForm.open = false;
    await Promise.all([loadEmployees(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) empForm.errors = e.response.data?.errors || {}; }
  finally { empForm.saving = false; }
}

function openLeave() { leaveForm.open = true; leaveForm.data = { employee_id: null, leave_type_id: null, start_date: '', end_date: '', reason: '' }; }
async function submitLeave() {
  leaveForm.saving = true;
  try {
    await api.createLeave(leaveForm.data);
    toast.success(t('hr.requested'));
    leaveForm.open = false;
    await Promise.all([loadLeave(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message || t('hr.check_fields')); }
  finally { leaveForm.saving = false; }
}
async function decide(id, action) {
  try {
    await api.decideLeave(id, action, null);
    toast.success(t('hr.decided'));
    await Promise.all([loadLeave(), loadAux()]);
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

const empStatusClass = (s) => ({
  active: 'bg-emerald-100 text-emerald-700', on_leave: 'bg-amber-100 text-amber-700', terminated: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');
const attStatusClass = (s) => ({
  present: 'bg-emerald-100 text-emerald-700', late: 'bg-amber-100 text-amber-700', half_day: 'bg-amber-100 text-amber-700',
  absent: 'bg-red-100 text-red-700', leave: 'bg-sky-100 text-sky-700', holiday: 'bg-violet-100 text-violet-700', weekend: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');
const leaveStatusClass = (s) => ({
  pending: 'bg-amber-100 text-amber-700', approved: 'bg-emerald-100 text-emerald-700',
  rejected: 'bg-red-100 text-red-700', cancelled: 'bg-slate-100 text-slate-500',
}[s] || 'bg-slate-100 text-slate-700');

onMounted(async () => { await Promise.all([loadTab(), loadAux()]); });
</script>
