<template>
  <div class="page">
    <div class="page-header">
      <div class="min-w-0">
        <h1 class="page-title">{{ $t('projects.title') }}</h1>
        <p class="page-sub">{{ $t('projects.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap justify-end gap-2"><button v-if="can('project_reports.view')" class="btn-secondary btn-sm" @click="openReport"><BarChart3 :size="13" /> {{ $t('projects.reports') }}</button><button v-if="can('project_automation.view')" class="btn-secondary btn-sm" @click="openAutomations"><Workflow :size="13" /> {{ $t('projects.automation') }}</button><button v-if="can('project_templates.view')" class="btn-secondary btn-sm" @click="openTemplates"><Copy :size="13" /> {{ $t('projects.templates') }}</button><button v-if="can('projects.view')" class="btn-secondary btn-sm" @click="openWorkload"><Users :size="13" /> {{ $t('projects.workload') }}</button><button v-if="can('projects.create')" class="btn-primary btn-sm" @click="openProject()"><Plus :size="13" /> {{ $t('projects.new') }}</button></div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2.5 mb-3">
      <div v-for="item in statItems" :key="item.key" class="stat">
        <span class="stat-label">{{ $t(item.label) }}</span>
        <span class="stat-value">{{ stats[item.key] ?? 0 }}</span>
      </div>
    </div>

    <div class="card mb-4">
      <div class="toolbar">
        <input v-model="filters.q" class="input input-sm w-64" :placeholder="$t('projects.search')" @keyup.enter="loadProjects" />
        <select v-model="filters.status" class="input input-sm w-auto" @change="loadProjects">
          <option value="all">{{ $t('projects.all_statuses') }}</option>
          <option v-for="status in meta.statuses" :key="status" :value="status">{{ label(status) }}</option>
        </select>
        <label class="flex items-center gap-1.5 text-xs text-ink-muted ml-1"><input v-model="filters.mine" type="checkbox" @change="loadProjects" /> {{ $t('projects.mine') }}</label>
        <button class="btn-secondary btn-sm ml-auto" :disabled="loading" @click="loadAll"><RefreshCw :size="12" :class="loading && 'animate-spin'" /> {{ $t('projects.refresh') }}</button>
      </div>
    </div>

    <div class="panel">
      <div v-if="loading" class="py-16 text-center text-sm text-ink-subtle">{{ $t('app.loading') }}</div>
      <div v-else-if="!projects.length" class="empty">
        <div class="empty-icon"><BriefcaseBusiness :size="18" /></div>
        <div class="text-sm text-ink-muted">{{ $t('projects.empty') }}</div>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="data-table">
          <thead><tr>
            <th>{{ $t('projects.project') }}</th><th>{{ $t('projects.account') }}</th><th>{{ $t('projects.owner') }}</th><th>{{ $t('projects.status') }}</th><th class="w-44">{{ $t('projects.progress') }}</th><th>{{ $t('projects.due') }}</th>
          </tr></thead>
          <tbody><tr v-for="project in projects" :key="project.id" class="cursor-pointer" @click="openDetail(project.id)">
            <td><div class="font-medium text-ink dark:text-ink-dark">{{ project.name }}</div><div class="font-mono text-[10px] text-ink-subtle">{{ project.project_no }}</div></td>
            <td class="text-ink-muted">{{ project.customer?.name || '—' }}</td><td class="text-ink-muted">{{ project.owner?.name || '—' }}</td>
            <td><span class="px-1.5 py-0.5 rounded text-[10px]" :class="statusClass(project.status)">{{ label(project.status) }}</span></td>
            <td><div class="flex items-center gap-2"><div class="h-1.5 flex-1 rounded bg-slate-200 dark:bg-slate-700"><div class="h-full rounded bg-primary-500" :style="{ width: `${project.progress}%` }" /></div><span class="tabular-nums text-[10px] text-ink-muted">{{ project.progress }}%</span></div></td>
            <td :class="isOverdue(project) ? 'text-red-600' : 'text-ink-muted'">{{ project.due_date || '—' }}</td>
          </tr></tbody>
        </table>
      </div>
    </div>

    <div v-if="selected" class="fixed inset-0 z-40 flex justify-end bg-black/30" @click.self="selected=null">
      <div class="bg-white dark:bg-surface-dark-muted w-full md:w-[44rem] h-full shadow-xl flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-start gap-2">
          <div class="min-w-0 flex-1"><div class="text-sm font-medium text-ink dark:text-ink-dark">{{ selected.name }}</div><div class="font-mono text-[10px] text-ink-subtle">{{ selected.project_no }}</div></div>
          <button v-if="can('projects.update')" class="btn-secondary btn-xs" @click="openProject(selected)">{{ $t('projects.edit') }}</button>
          <button v-if="can('projects.delete')" class="text-red-500 p-1" :title="$t('projects.delete')" @click="deleteProject"><Trash2 :size="14" /></button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected=null"><X :size="15" /></button>
        </div>
        <div class="px-4 pt-3 flex gap-1 border-b border-slate-200 dark:border-slate-700">
          <button v-for="tab in tabs" :key="tab" class="px-3 py-2 text-xs border-b-2" :class="detailTab===tab?'border-primary-500 text-primary-600':'border-transparent text-ink-muted'" @click="selectDetailTab(tab)">{{ $t(`projects.tabs.${tab}`) }}</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <div v-if="detailTab==='overview'" class="space-y-4">
            <div class="grid grid-cols-2 gap-3 text-xs">
              <InfoItem :label="$t('projects.status')" :value="label(selected.status)" /><InfoItem :label="$t('projects.priority')" :value="label(selected.priority)" /><InfoItem :label="$t('projects.owner')" :value="selected.owner?.name || '—'" /><InfoItem :label="$t('projects.account')" :value="selected.customer?.name || '—'" /><InfoItem :label="$t('projects.start')" :value="selected.start_date || '—'" /><InfoItem :label="$t('projects.due')" :value="selected.due_date || '—'" /><InfoItem :label="$t('projects.budget')" :value="money(selected.budget,selected.currency)" /><InfoItem :label="$t('projects.source_deal')" :value="selected.deal ? `${selected.deal.deal_no} · ${selected.deal.title}` : '—'" />
            </div>
            <div><div class="text-[10px] uppercase tracking-wide text-ink-subtle mb-1">{{ $t('projects.description') }}</div><p class="text-xs text-ink-muted whitespace-pre-wrap">{{ selected.description || '—' }}</p></div>
          </div>

          <div v-if="detailTab==='tasks'" class="space-y-3">
            <form v-if="can('project_tasks.create')" class="card p-2 flex flex-wrap gap-2" @submit.prevent="addTask">
              <input v-model="taskForm.title" required class="input text-xs flex-1 min-w-48" :placeholder="$t('projects.task_title')" />
              <select v-model="taskForm.assigned_to" class="input text-xs w-36"><option :value="null">{{ $t('projects.unassigned') }}</option><option v-for="u in meta.users" :key="u.id" :value="u.id">{{ u.name }}</option></select>
              <input v-model="taskForm.due_date" type="date" class="input text-xs w-auto" />
              <button class="btn-primary text-xs px-2" :disabled="saving"><Plus :size="12" /></button>
            </form>
            <div class="flex items-center gap-2">
              <span class="text-[11px] text-ink-subtle">{{ selected.tasks?.length || 0 }} {{ $t('projects.tasks_lc') }}</span>
              <div class="ml-auto flex rounded border border-slate-200 dark:border-slate-700 p-0.5">
                <button class="px-2 py-1 text-[10px] rounded" :class="taskView==='list'&&'bg-primary-100 text-primary-700'" @click="taskView='list'">{{ $t('projects.list') }}</button>
                <button class="px-2 py-1 text-[10px] rounded" :class="taskView==='board'&&'bg-primary-100 text-primary-700'" @click="taskView='board'">{{ $t('projects.kanban') }}</button>
              </div>
            </div>
            <div v-if="!selected.tasks?.length" class="py-10 text-center text-xs text-ink-subtle">{{ $t('projects.no_tasks') }}</div>
            <div v-if="taskView==='list'" v-for="task in selected.tasks" :key="task.id" class="card p-2.5 flex items-center gap-2">
              <button v-if="can('project_tasks.update')" class="w-4 h-4 rounded border flex items-center justify-center" :class="task.status==='done'&&'bg-emerald-500 border-emerald-500 text-white'" @click="setTaskStatus(task,task.status==='done'?'todo':'done')"><Check v-if="task.status==='done'" :size="11" /></button>
              <button class="min-w-0 flex-1 text-left" @click="openTask(task)"><div class="text-xs text-ink dark:text-ink-dark" :class="task.status==='done'&&'line-through text-ink-subtle'">{{ task.title }}</div><div class="text-[10px] text-ink-subtle">{{ task.assignee?.name || $t('projects.unassigned') }} · {{ task.due_date || $t('projects.no_due') }}<span v-if="task.comments_count"> · {{ task.comments_count }} {{ $t('projects.comments_lc') }}</span><span v-if="task.attachments_count"> · {{ task.attachments_count }} {{ $t('projects.files_lc') }}</span></div></button>
              <select v-if="can('project_tasks.update')" :value="task.status" class="input text-[10px] w-auto py-1" @change="setTaskStatus(task,$event.target.value)"><option v-for="s in meta.task_statuses" :key="s" :value="s">{{ label(s) }}</option></select>
              <button v-if="can('project_tasks.delete')" class="text-red-500 p-1" @click="deleteTask(task)"><Trash2 :size="12" /></button>
            </div>
            <div v-if="taskView==='board'&&selected.tasks?.length" class="flex gap-2 overflow-x-auto pb-2">
              <div v-for="status in boardStatuses" :key="status" class="w-52 shrink-0 rounded-lg bg-slate-50 dark:bg-surface-dark-subtle border border-slate-200 dark:border-slate-700" @dragover.prevent @drop="dropTask(status)">
                <div class="px-2.5 py-2 text-[10px] uppercase tracking-wide text-ink-muted border-b border-slate-200 dark:border-slate-700 flex"><span>{{ label(status) }}</span><span class="ml-auto">{{ tasksByStatus(status).length }}</span></div>
                <div class="p-2 space-y-2 min-h-28">
                  <button v-for="task in tasksByStatus(status)" :key="task.id" type="button" class="card p-2 w-full text-left cursor-grab" :draggable="can('project_tasks.update')" @dragstart="dragTask=task" @click="openTask(task)"><div class="text-xs text-ink dark:text-ink-dark">{{ task.title }}</div><div class="text-[10px] text-ink-subtle mt-1 truncate">{{ task.assignee?.name || $t('projects.unassigned') }}</div><div v-if="task.due_date" class="text-[9px] mt-1" :class="taskDueClass(task)">{{ task.due_date }}</div></button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="detailTab==='milestones'" class="space-y-3">
            <form v-if="can('projects.update')" class="card p-2 flex gap-2" @submit.prevent="addMilestone"><input v-model="milestoneForm.name" required class="input text-xs flex-1" :placeholder="$t('projects.milestone_name')" /><input v-model="milestoneForm.due_date" type="date" class="input text-xs w-auto" /><button class="btn-primary text-xs px-2"><Plus :size="12" /></button></form>
            <div v-if="!selected.milestones?.length" class="py-10 text-center text-xs text-ink-subtle">{{ $t('projects.no_milestones') }}</div>
            <div v-for="m in selected.milestones" :key="m.id" class="card p-3 flex items-center gap-2"><Milestone :size="15" class="text-primary-500" /><div class="flex-1"><div class="text-xs font-medium text-ink dark:text-ink-dark">{{ m.name }}</div><div class="text-[10px] text-ink-subtle">{{ m.due_date || $t('projects.no_due') }}</div></div><button v-if="can('projects.update')" class="btn-secondary btn-xs" @click="toggleMilestone(m)">{{ m.status==='completed'?$t('projects.reopen'):$t('projects.complete') }}</button><button v-if="can('projects.update')" class="text-red-500 p-1" @click="deleteMilestone(m)"><Trash2 :size="12" /></button></div>
          </div>

          <div v-if="detailTab==='team'" class="space-y-3">
            <form v-if="can('projects.manage_members')" class="card p-2 grid grid-cols-2 md:grid-cols-5 gap-2" @submit.prevent="addMember"><select v-model="memberForm.user_id" required class="input text-xs md:col-span-2"><option :value="null" disabled>{{ $t('projects.select_member') }}</option><option v-for="u in availableUsers" :key="u.id" :value="u.id">{{ u.name }}</option></select><select v-model="memberForm.role" class="input text-xs"><option v-for="r in meta.member_roles" :key="r" :value="r">{{ label(r) }}</option></select><input v-model.number="memberForm.cost_rate" type="number" min="0" step="0.01" class="input text-xs" :placeholder="$t('projects.cost_rate')" /><div class="flex gap-2"><input v-model.number="memberForm.bill_rate" type="number" min="0" step="0.01" class="input text-xs min-w-0" :placeholder="$t('projects.bill_rate')" /><button class="btn-primary text-xs px-2"><Plus :size="12" /></button></div></form>
            <div v-for="member in selected.members" :key="member.id" class="card p-3 flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-medium">{{ initials(member.user?.name) }}</div><div class="flex-1"><div class="text-xs font-medium text-ink dark:text-ink-dark">{{ member.user?.name }}</div><div class="text-[10px] text-ink-subtle">{{ label(member.role) }} · {{ member.allocation_percent }}% · {{ money(member.cost_rate,selected.currency) }}/h cost · {{ money(member.bill_rate,selected.currency) }}/h bill</div></div><button v-if="can('projects.manage_members')&&member.user?.id!==selected.owner?.id" class="text-red-500 p-1" @click="removeMember(member)"><Trash2 :size="12" /></button></div>
          </div>

          <div v-if="detailTab==='time'" class="space-y-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2"><div v-for="card in timeCards" :key="card.key" class="card p-3"><div class="text-[10px] text-ink-subtle">{{ $t(card.label) }}</div><div class="text-sm font-semibold mt-1">{{ card.money?money(timeData.summary[card.key],selected.currency):(timeData.summary[card.key]||0) }}</div></div></div>
            <form v-if="can('timesheets.create')" class="card p-3 grid grid-cols-2 md:grid-cols-6 gap-2" @submit.prevent="addTime"><input v-model="timeForm.work_date" required type="date" class="input text-xs" /><select v-model="timeForm.project_task_id" class="input text-xs md:col-span-2"><option :value="null">{{ $t('projects.general_project_time') }}</option><option v-for="task in selected.tasks" :key="task.id" :value="task.id">{{ task.title }}</option></select><input v-model.number="timeForm.hours" required type="number" min="0.25" max="24" step="0.25" class="input text-xs" :placeholder="$t('projects.hours')" /><label class="flex items-center gap-1 text-xs"><input v-model="timeForm.billable" type="checkbox" /> {{ $t('projects.billable') }}</label><button class="btn-primary text-xs px-2">{{ $t('projects.log_time') }}</button><input v-model="timeForm.notes" class="input text-xs col-span-2 md:col-span-6" :placeholder="$t('projects.time_notes')" /></form>
            <div v-if="!timeData.entries.length" class="py-10 text-center text-xs text-ink-subtle">{{ $t('projects.no_time') }}</div>
            <div v-for="entry in timeData.entries" :key="entry.id" class="card p-3 flex flex-wrap items-center gap-2"><div class="w-12 text-sm font-semibold tabular-nums">{{ entry.hours }}h</div><div class="min-w-0 flex-1"><div class="text-xs text-ink dark:text-ink-dark">{{ entry.user?.name }} · {{ entry.task?.title || $t('projects.general_project_time') }}</div><div class="text-[10px] text-ink-subtle">{{ entry.work_date }} · {{ entry.notes || '—' }}</div></div><span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100" :class="entry.status==='approved'&&'text-emerald-700 bg-emerald-100'">{{ label(entry.status) }}</span><template v-if="entry.status==='submitted'&&can('timesheets.approve')"><button class="text-[10px] text-emerald-600" @click="decideTime(entry,'approved')">{{ $t('projects.approve') }}</button><button class="text-[10px] text-red-600" @click="decideTime(entry,'rejected')">{{ $t('projects.reject') }}</button></template><button v-if="entry.status!=='approved'&&can('timesheets.delete')" class="text-red-500 p-1" @click="deleteTime(entry)"><Trash2 :size="12" /></button></div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="workloadModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="workloadModal=false"><div class="card w-full max-w-3xl max-h-[90vh] overflow-y-auto p-4"><div class="flex items-center mb-3"><div><h2 class="text-sm font-medium">{{ $t('projects.team_workload') }}</h2><p class="text-[11px] text-ink-subtle">{{ workload.from }} — {{ workload.to }}</p></div><button class="ml-auto" @click="workloadModal=false"><X :size="15" /></button></div><div v-if="!workload.items.length" class="py-12 text-center text-xs text-ink-subtle">{{ $t('projects.no_workload') }}</div><div v-for="row in workload.items" :key="row.user.id" class="card p-3 mb-2"><div class="flex items-center"><div class="text-xs font-medium flex-1">{{ row.user.name }}</div><span class="text-xs" :class="Math.max(row.utilization_percent,row.allocation_percent)>100?'text-red-600':'text-ink-muted'">{{ row.utilization_percent }}%</span></div><div class="h-2 bg-slate-200 rounded mt-2 overflow-hidden"><div class="h-full rounded" :class="Math.max(row.utilization_percent,row.allocation_percent)>100?'bg-red-500':'bg-primary-500'" :style="{width:`${Math.min(row.utilization_percent,100)}%`}" /></div><div class="text-[10px] text-ink-subtle mt-1">{{ row.planned_hours }}h {{ $t('projects.planned') }} / {{ row.capacity_hours }}h {{ $t('projects.capacity') }} · {{ row.allocation_percent }}% {{ $t('projects.allocated') }}</div></div></div></div>

    <div v-if="templatesModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="templatesModal=false"><div class="card w-full max-w-3xl max-h-[92vh] overflow-y-auto p-4"><div class="flex items-center mb-4"><div><h2 class="text-sm font-medium">{{ $t('projects.project_templates') }}</h2><p class="text-[11px] text-ink-subtle">{{ $t('projects.templates_help') }}</p></div><button class="ml-auto" @click="templatesModal=false"><X :size="15" /></button></div>
      <form v-if="can('project_templates.create')&&projects.length" class="card p-3 grid grid-cols-1 md:grid-cols-3 gap-2 mb-4" @submit.prevent="saveTemplate"><select v-model="templateForm.project_id" required class="input text-xs"><option :value="null" disabled>{{ $t('projects.source_project') }}</option><option v-for="p in projects" :key="p.id" :value="p.id">{{ p.project_no }} · {{ p.name }}</option></select><input v-model="templateForm.name" required class="input text-xs" :placeholder="$t('projects.template_name')" /><button class="btn-primary text-xs px-3">{{ $t('projects.save_as_template') }}</button><input v-model="templateForm.description" class="input text-xs md:col-span-3" :placeholder="$t('projects.template_description')" /></form>
      <div v-if="!templates.length" class="py-12 text-center text-xs text-ink-subtle">{{ $t('projects.no_templates') }}</div><div v-for="item in templates" :key="item.id" class="card p-3 mb-2 flex flex-wrap items-center gap-3"><div class="w-9 h-9 rounded bg-primary-100 text-primary-700 flex items-center justify-center"><Copy :size="16" /></div><div class="min-w-0 flex-1"><div class="text-xs font-medium">{{ item.name }} <span v-if="!item.is_active" class="text-ink-subtle">({{ $t('projects.inactive') }})</span></div><div class="text-[10px] text-ink-subtle">{{ item.tasks_count }} {{ $t('projects.tasks_lc') }} · {{ item.milestones_count }} {{ $t('projects.milestones_lc') }} · {{ item.duration_days }} {{ $t('projects.days') }}</div><p v-if="item.description" class="text-[10px] text-ink-muted mt-1">{{ item.description }}</p></div><button v-if="item.is_active&&can('projects.create')" class="btn-primary btn-xs" @click="useTemplate(item)">{{ $t('projects.use_template') }}</button><button v-if="can('project_templates.update')" class="btn-secondary btn-xs" @click="toggleTemplate(item)">{{ item.is_active?$t('projects.disable'):$t('projects.enable') }}</button><button v-if="can('project_templates.delete')" class="text-red-500 p-1" @click="deleteTemplate(item)"><Trash2 :size="12" /></button></div>
    </div></div>

    <div v-if="automationModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="automationModal=false"><div class="card w-full max-w-4xl max-h-[92vh] overflow-y-auto p-4"><div class="flex items-center mb-4"><div><h2 class="text-sm font-medium">{{ $t('projects.automation_rules') }}</h2><p class="text-[11px] text-ink-subtle">{{ $t('projects.automation_help') }}</p></div><button class="ml-auto" @click="automationModal=false"><X :size="15" /></button></div>
      <form v-if="can('project_automation.create')" class="card p-3 grid grid-cols-1 md:grid-cols-4 gap-2 mb-4" @submit.prevent="saveAutomation"><input v-model="automationForm.name" required class="input text-xs" :placeholder="$t('projects.rule_name')" /><select v-model="automationForm.project_id" class="input text-xs"><option :value="null">{{ $t('projects.all_projects') }}</option><option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option></select><select v-model="automationForm.trigger" class="input text-xs"><option value="task_created">{{ $t('projects.when_task_created') }}</option><option value="task_completed">{{ $t('projects.when_task_completed') }}</option><option value="task_overdue">{{ $t('projects.when_task_overdue') }}</option><option value="project_completed">{{ $t('projects.when_project_completed') }}</option></select><select v-model="automationForm.action" class="input text-xs"><option value="notify_user">{{ $t('projects.notify_user') }}</option><option value="create_follow_up">{{ $t('projects.create_follow_up') }}</option><option value="set_priority">{{ $t('projects.set_priority') }}</option></select>
        <select v-if="automationForm.action==='notify_user'" v-model="automationForm.user_id" class="input text-xs md:col-span-2"><option :value="null">{{ $t('projects.default_recipient') }}</option><option v-for="u in meta.users" :key="u.id" :value="u.id">{{ u.name }}</option></select><template v-if="automationForm.action==='create_follow_up'"><input v-model="automationForm.follow_up_title" class="input text-xs md:col-span-2" :placeholder="$t('projects.follow_up_title')" /><input v-model.number="automationForm.due_days" type="number" min="0" max="365" class="input text-xs" :placeholder="$t('projects.due_in_days')" /></template><select v-if="automationForm.action==='set_priority'||automationForm.action==='create_follow_up'" v-model="automationForm.priority" class="input text-xs"><option v-for="p in meta.priorities" :key="p" :value="p">{{ label(p) }}</option></select><button class="btn-primary text-xs px-3 md:col-start-4">{{ $t('projects.add_rule') }}</button></form>
      <div v-if="!automationRules.length" class="py-12 text-center text-xs text-ink-subtle">{{ $t('projects.no_rules') }}</div><div v-for="rule in automationRules" :key="rule.id" class="card p-3 mb-2 flex items-center gap-3"><div class="w-8 h-8 rounded-full flex items-center justify-center" :class="rule.is_active?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500'"><Workflow :size="14" /></div><div class="min-w-0 flex-1"><div class="text-xs font-medium">{{ rule.name }}</div><div class="text-[10px] text-ink-subtle">{{ rule.project?.name || $t('projects.all_projects') }} · {{ label(rule.trigger) }} → {{ label(rule.action) }}<span v-if="rule.last_run_at"> · {{ $t('projects.last_run') }} {{ dateTime(rule.last_run_at) }}</span></div></div><button v-if="can('project_automation.update')" class="btn-secondary btn-xs" @click="toggleAutomation(rule)">{{ rule.is_active?$t('projects.disable'):$t('projects.enable') }}</button><button v-if="can('project_automation.delete')" class="text-red-500 p-1" @click="deleteAutomation(rule)"><Trash2 :size="12" /></button></div>
    </div></div>

    <div v-if="reportModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="reportModal=false"><div class="card w-full max-w-5xl max-h-[94vh] overflow-y-auto p-4"><div class="flex flex-wrap items-center gap-2 mb-4"><div class="mr-auto"><h2 class="text-sm font-medium">{{ $t('projects.management_report') }}</h2><p class="text-[11px] text-ink-subtle">{{ $t('projects.report_help') }}</p></div><input v-model="reportFilters.from" type="date" class="input text-xs w-auto" /><input v-model="reportFilters.to" type="date" class="input text-xs w-auto" /><button class="btn-secondary btn-xs" @click="loadReport"><RefreshCw :size="12" /></button><button @click="reportModal=false"><X :size="15" /></button></div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4"><div v-for="card in reportCards" :key="card.key" class="card p-3"><div class="text-[10px] text-ink-subtle">{{ $t(card.label) }}</div><div class="text-base font-semibold mt-1" :class="card.key==='budget_variance'&&report.summary?.[card.key]<0?'text-red-600':''">{{ card.money?money(report.summary?.[card.key],reportCurrency):`${report.summary?.[card.key]??0}${card.percent?'%':''}` }}</div></div></div>
      <div class="grid md:grid-cols-2 gap-3 mb-4"><div class="card p-3"><div class="text-xs font-medium mb-2">{{ $t('projects.project_health') }}</div><div v-for="(count,key) in report.health_breakdown" :key="key" class="flex items-center text-xs py-1.5"><span class="w-2 h-2 rounded-full mr-2" :class="key==='overdue'?'bg-red-500':key==='at_risk'?'bg-amber-500':'bg-emerald-500'" /><span class="flex-1">{{ label(key) }}</span><b>{{ count }}</b></div></div><div class="card p-3"><div class="text-xs font-medium mb-2">{{ $t('projects.status_breakdown') }}</div><div v-for="(count,key) in report.status_breakdown" :key="key" class="flex items-center text-xs py-1.5"><span class="flex-1">{{ label(key) }}</span><b>{{ count }}</b></div></div></div>
      <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>{{ $t('projects.project') }}</th><th>{{ $t('projects.status') }}</th><th class="th-num">{{ $t('projects.progress') }}</th><th class="th-num">{{ $t('projects.overdue_tasks') }}</th><th class="th-num">{{ $t('projects.actual_cost') }}</th><th class="th-num">{{ $t('projects.budget') }}</th></tr></thead><tbody><tr v-for="p in report.projects" :key="p.id"><td><button class="text-left text-primary-600" @click="openReportProject(p)">{{ p.project_no }} · {{ p.name }}</button></td><td>{{ label(p.status) }}</td><td class="td-num">{{ p.progress }}%</td><td class="td-num" :class="p.overdue_tasks?'text-red-600':''">{{ p.overdue_tasks }}</td><td class="td-num">{{ money(p.actual_cost,p.currency) }}</td><td class="td-num">{{ money(p.budget,p.currency) }}</td></tr></tbody></table></div>
    </div></div>

    <div v-if="taskModal.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-3" @click.self="closeTask">
      <div class="card w-full max-w-3xl max-h-[94vh] flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <div class="min-w-0 flex-1"><div class="text-sm font-medium text-ink dark:text-ink-dark truncate">{{ taskDetail?.title }}</div><div class="text-[10px] text-ink-subtle">{{ selected?.project_no }} · {{ label(taskDetail?.status) }}</div></div>
          <button class="p-1 text-ink-subtle" @click="closeTask"><X :size="15" /></button>
        </div>
        <div class="px-4 pt-2 flex gap-1 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
          <button v-for="tab in taskTabs" :key="tab" class="px-3 py-2 text-xs border-b-2 whitespace-nowrap" :class="taskTab===tab?'border-primary-500 text-primary-600':'border-transparent text-ink-muted'" @click="taskTab=tab">{{ $t(`projects.task_tabs.${tab}`) }}</button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <form v-if="taskTab==='details'" class="space-y-3" @submit.prevent="saveTask">
            <div><label class="text-[11px] text-ink-muted">{{ $t('projects.task_name') }} *</label><input v-model="taskEdit.title" required class="input text-sm w-full mt-1" /></div>
            <div><label class="text-[11px] text-ink-muted">{{ $t('projects.description') }}</label><textarea v-model="taskEdit.description" rows="3" class="input text-sm w-full mt-1" /></div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div><label class="text-[11px] text-ink-muted">{{ $t('projects.status') }}</label><select v-model="taskEdit.status" class="input text-xs w-full mt-1"><option v-for="s in meta.task_statuses" :key="s" :value="s">{{ label(s) }}</option></select></div>
              <div><label class="text-[11px] text-ink-muted">{{ $t('projects.priority') }}</label><select v-model="taskEdit.priority" class="input text-xs w-full mt-1"><option v-for="p in meta.priorities" :key="p" :value="p">{{ label(p) }}</option></select></div>
              <div><label class="text-[11px] text-ink-muted">{{ $t('projects.assignee') }}</label><select v-model="taskEdit.assigned_to" class="input text-xs w-full mt-1"><option :value="null">{{ $t('projects.unassigned') }}</option><option v-for="u in meta.users" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
              <div><label class="text-[11px] text-ink-muted">{{ $t('projects.milestone') }}</label><select v-model="taskEdit.milestone_id" class="input text-xs w-full mt-1"><option :value="null">—</option><option v-for="m in selected?.milestones" :key="m.id" :value="m.id">{{ m.name }}</option></select></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3"><div><label class="text-[11px] text-ink-muted">{{ $t('projects.start') }}</label><input v-model="taskEdit.start_date" type="date" class="input text-xs w-full mt-1" /></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.due') }}</label><input v-model="taskEdit.due_date" type="date" class="input text-xs w-full mt-1" /></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.estimated_hours') }}</label><input v-model.number="taskEdit.estimated_hours" type="number" min="0" step="0.25" class="input text-xs w-full mt-1" /></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.actual_hours') }}</label><input v-model.number="taskEdit.actual_hours" type="number" min="0" step="0.25" class="input text-xs w-full mt-1" /></div></div>
            <div class="card p-3"><div class="flex items-center gap-2 mb-2"><Repeat2 :size="14" class="text-primary-500" /><span class="text-[11px] font-medium">{{ $t('projects.recurrence') }}</span></div><div class="grid grid-cols-1 md:grid-cols-3 gap-3"><div><label class="text-[11px] text-ink-muted">{{ $t('projects.frequency') }}</label><select v-model="taskEdit.recurrence_frequency" class="input text-xs w-full mt-1"><option :value="null">{{ $t('projects.does_not_repeat') }}</option><option value="daily">{{ $t('projects.daily') }}</option><option value="weekly">{{ $t('projects.weekly') }}</option><option value="monthly">{{ $t('projects.monthly') }}</option></select></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.repeat_every') }}</label><input v-model.number="taskEdit.recurrence_interval" type="number" min="1" max="52" class="input text-xs w-full mt-1" :disabled="!taskEdit.recurrence_frequency" /></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.recurrence_ends') }}</label><input v-model="taskEdit.recurrence_end_date" type="date" class="input text-xs w-full mt-1" :disabled="!taskEdit.recurrence_frequency" /></div></div><p class="text-[10px] text-ink-subtle mt-2">{{ $t('projects.recurrence_help') }}</p></div>
            <div><label class="text-[11px] text-ink-muted">{{ $t('projects.parent_task') }}</label><select v-model="taskEdit.parent_id" class="input text-xs w-full mt-1"><option :value="null">—</option><option v-for="t in otherTasks" :key="t.id" :value="t.id">{{ t.title }}</option></select></div>
            <div class="flex justify-end"><button v-if="can('project_tasks.update')" class="btn-primary btn-sm" :disabled="taskModal.saving">{{ taskModal.saving?$t('projects.saving'):$t('projects.save_task') }}</button></div>
            <div class="border-t border-slate-100 dark:border-slate-700 pt-3">
              <div class="text-[11px] font-medium mb-2">{{ $t('projects.subtasks') }}</div>
              <div v-if="can('project_tasks.create')" class="flex gap-2 mb-2"><input v-model="subtaskTitle" class="input text-xs flex-1" :placeholder="$t('projects.add_subtask')" @keyup.enter.prevent="addSubtask" /><button type="button" class="btn-secondary text-xs px-2" :disabled="!subtaskTitle.trim()" @click="addSubtask"><Plus :size="12" /></button></div>
              <button v-for="child in taskDetail?.children" :key="child.id" class="w-full flex text-left text-xs py-1.5 border-b border-slate-100 dark:border-slate-700/60" @click="openTask(child)"><span class="flex-1">{{ child.title }}</span><span class="text-ink-subtle">{{ label(child.status) }}</span></button>
            </div>
          </form>

          <div v-if="taskTab==='dependencies'" class="space-y-3">
            <form v-if="can('project_tasks.update')" class="flex gap-2" @submit.prevent="addDependency"><select v-model="dependencyId" required class="input text-xs flex-1"><option :value="null" disabled>{{ $t('projects.select_dependency') }}</option><option v-for="t in availableDependencies" :key="t.id" :value="t.id">{{ t.title }}</option></select><button class="btn-primary text-xs px-3">{{ $t('projects.add') }}</button></form>
            <div class="text-[10px] uppercase text-ink-subtle">{{ $t('projects.blocked_by') }}</div>
            <div v-if="!taskDetail?.dependencies?.length" class="text-xs text-ink-subtle py-6 text-center">{{ $t('projects.no_dependencies') }}</div>
            <div v-for="dep in taskDetail?.dependencies" :key="dep.id" class="card p-2.5 flex items-center"><button class="text-xs flex-1 text-left" @click="openTask(dep)">{{ dep.title }} <span class="text-ink-subtle">· {{ label(dep.status) }}</span></button><button v-if="can('project_tasks.update')" class="text-red-500 p-1" @click="removeDependency(dep)"><X :size="12" /></button></div>
            <div v-if="taskDetail?.dependents?.length"><div class="text-[10px] uppercase text-ink-subtle mt-4 mb-2">{{ $t('projects.blocks') }}</div><div v-for="dep in taskDetail.dependents" :key="dep.id" class="text-xs py-1.5">{{ dep.title }}</div></div>
          </div>

          <div v-if="taskTab==='comments'" class="space-y-3">
            <form v-if="can('project_tasks.comment')" class="flex gap-2" @submit.prevent="addComment"><textarea v-model="commentDraft" required rows="2" class="input text-xs flex-1" :placeholder="$t('projects.write_comment')" /><button class="btn-primary text-xs px-3"><Send :size="12" /></button></form>
            <div v-if="!taskDetail?.comments?.length" class="text-xs text-ink-subtle py-8 text-center">{{ $t('projects.no_comments') }}</div>
            <div v-for="comment in taskDetail?.comments" :key="comment.id" class="card p-3"><div class="flex text-[10px] text-ink-subtle"><span class="font-medium text-ink-muted">{{ comment.user?.name || '—' }}</span><span class="ml-auto">{{ comment.created_human }}</span></div><p class="text-xs text-ink dark:text-ink-dark mt-2 whitespace-pre-wrap">{{ comment.body }}</p></div>
          </div>

          <div v-if="taskTab==='files'" class="space-y-3">
            <label v-if="can('project_tasks.upload')" class="card p-4 border-dashed flex items-center justify-center gap-2 text-xs text-primary-600 cursor-pointer"><Upload :size="14" /> {{ uploading?$t('projects.uploading'):$t('projects.attach_file') }}<input type="file" class="hidden" :disabled="uploading" @change="uploadFile" /></label>
            <div v-if="!taskDetail?.attachments?.length" class="text-xs text-ink-subtle py-8 text-center">{{ $t('projects.no_files') }}</div>
            <div v-for="file in taskDetail?.attachments" :key="file.id" class="card p-2.5 flex items-center gap-2"><Paperclip :size="14" class="text-ink-subtle" /><a :href="file.url" target="_blank" rel="noopener" class="text-xs text-primary-600 flex-1 truncate">{{ file.name }}</a><span class="text-[10px] text-ink-subtle">{{ fileSize(file.size) }}</span><button v-if="can('project_tasks.upload')" class="text-red-500 p-1" @click="removeFile(file)"><Trash2 :size="12" /></button></div>
          </div>

          <div v-if="taskTab==='activity'" class="space-y-0">
            <div v-if="!taskDetail?.timeline?.length" class="text-xs text-ink-subtle py-8 text-center">{{ $t('projects.no_activity') }}</div>
            <div v-for="event in taskDetail?.timeline" :key="event.id" class="flex gap-3 py-2.5 border-b border-slate-100 dark:border-slate-700/60"><div class="w-2 h-2 mt-1 rounded-full bg-primary-400 shrink-0" /><div class="min-w-0 flex-1"><div class="text-xs text-ink dark:text-ink-dark">{{ event.title }}</div><div v-if="event.body" class="text-[11px] text-ink-muted mt-0.5 whitespace-pre-wrap">{{ event.body }}</div><div class="text-[10px] text-ink-subtle mt-1">{{ event.user?.name || $t('projects.system') }} · {{ event.occurred_human }}</div></div></div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="projectForm.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="projectForm.open=false">
      <form class="card w-full max-w-xl p-4 space-y-3 max-h-[90vh] overflow-y-auto" @submit.prevent="saveProject">
        <div class="flex items-center"><h2 class="text-sm font-medium flex-1">{{ projectForm.id ? $t('projects.edit_project') : $t('projects.new_project') }}</h2><button type="button" @click="projectForm.open=false"><X :size="15" /></button></div>
        <div v-if="!projectForm.id&&can('project_templates.view')&&activeTemplates.length"><label class="text-[11px] text-ink-muted">{{ $t('projects.start_from_template') }}</label><select v-model="projectForm.data.template_id" class="input text-sm w-full mt-1" @change="applySelectedTemplate"><option :value="null">{{ $t('projects.blank_project') }}</option><option v-for="item in activeTemplates" :key="item.id" :value="item.id">{{ item.name }} · {{ item.tasks_count }} {{ $t('projects.tasks_lc') }}</option></select></div>
        <div><label class="text-[11px] text-ink-muted">{{ $t('projects.name') }} *</label><input v-model="projectForm.data.name" required class="input text-sm w-full mt-1" /></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="text-[11px] text-ink-muted">{{ $t('projects.account') }}</label><select v-model="projectForm.data.customer_id" class="input text-sm w-full mt-1"><option :value="null">—</option><option v-for="c in meta.customers" :key="c.id" :value="c.id">{{ c.name }}</option></select></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.owner') }}</label><select v-model="projectForm.data.owner_id" class="input text-sm w-full mt-1"><option v-for="u in meta.users" :key="u.id" :value="u.id">{{ u.name }}</option></select></div></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="text-[11px] text-ink-muted">{{ $t('projects.status') }}</label><select v-model="projectForm.data.status" class="input text-sm w-full mt-1"><option v-for="s in meta.statuses" :key="s" :value="s">{{ label(s) }}</option></select></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.priority') }}</label><select v-model="projectForm.data.priority" class="input text-sm w-full mt-1"><option v-for="p in meta.priorities" :key="p" :value="p">{{ label(p) }}</option></select></div></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="text-[11px] text-ink-muted">{{ $t('projects.start') }}</label><input v-model="projectForm.data.start_date" type="date" class="input text-sm w-full mt-1" /></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.due') }}</label><input v-model="projectForm.data.due_date" type="date" class="input text-sm w-full mt-1" /></div></div>
        <div class="grid grid-cols-3 gap-3"><div class="col-span-2"><label class="text-[11px] text-ink-muted">{{ $t('projects.budget') }}</label><input v-model.number="projectForm.data.budget" type="number" min="0" step="0.01" class="input text-sm w-full mt-1" /></div><div><label class="text-[11px] text-ink-muted">{{ $t('projects.currency') }}</label><input v-model="projectForm.data.currency" maxlength="3" class="input text-sm w-full mt-1 uppercase" /></div></div>
        <div><label class="text-[11px] text-ink-muted">{{ $t('projects.description') }}</label><textarea v-model="projectForm.data.description" rows="3" class="input text-sm w-full mt-1" /></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn-secondary btn-sm" @click="projectForm.open=false">{{ $t('projects.cancel') }}</button><button class="btn-primary btn-sm" :disabled="projectForm.saving">{{ projectForm.saving ? $t('projects.saving') : $t('projects.save') }}</button></div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/projects';
import { BarChart3, BriefcaseBusiness, Check, Copy, Milestone, Paperclip, Plus, RefreshCw, Repeat2, Send, Trash2, Upload, Users, Workflow, X } from 'lucide-vue-next';

const InfoItem = defineComponent({ props: { label: String, value: String }, setup: (props) => () => h('div', [h('div',{class:'text-[10px] uppercase tracking-wide text-ink-subtle'},props.label),h('div',{class:'text-xs text-ink dark:text-ink-dark mt-0.5'},props.value)]) });
const auth=useAuthStore(); const toast=useToast(); const can=(p)=>auth.can(p);
const route=useRoute(); const router=useRouter();
const projects=ref([]), selected=ref(null), loading=ref(false), saving=ref(false), detailTab=ref('overview');
const taskView=ref('list'), dragTask=ref(null), taskDetail=ref(null), taskTab=ref('details');
const taskModal=reactive({open:false,saving:false});
const taskEdit=reactive({}), commentDraft=ref(''), dependencyId=ref(null), subtaskTitle=ref(''), uploading=ref(false);
const timeData=reactive({entries:[],summary:{}}), workload=reactive({from:'',to:'',items:[]});
const workloadModal=ref(false);
const templates=ref([]), automationRules=ref([]), templatesModal=ref(false), automationModal=ref(false), reportModal=ref(false);
const templateForm=reactive({project_id:null,name:'',description:''});
const automationForm=reactive({name:'',project_id:null,trigger:'task_overdue',action:'notify_user',user_id:null,follow_up_title:'',due_days:3,priority:'urgent'});
const report=reactive({summary:{},health_breakdown:{},status_breakdown:{},projects:[],currency:'USD'});
const reportFilters=reactive({from:`${new Date().getFullYear()}-01-01`,to:new Date().toISOString().slice(0,10)});
const stats=reactive({}), filters=reactive({q:'',status:'all',mine:false});
const meta=reactive({statuses:[],priorities:[],task_statuses:[],milestone_statuses:[],member_roles:[],users:[],customers:[]});
const taskForm=reactive({title:'',assigned_to:null,due_date:null}), milestoneForm=reactive({name:'',due_date:null}), memberForm=reactive({user_id:null,role:'member',cost_rate:0,bill_rate:0});
const timeForm=reactive({work_date:new Date().toISOString().slice(0,10),project_task_id:null,hours:null,billable:true,notes:'',status:'submitted'});
const projectForm=reactive({open:false,id:null,saving:false,data:{}});
const activeTemplates=computed(()=>templates.value.filter(t=>t.is_active));
const reportCurrency=computed(()=>report.currency||'USD');
const tabs=computed(()=>['overview','tasks','milestones','team',...(can('timesheets.view')?['time']:[])]);
const taskTabs=['details','dependencies','comments','files','activity'];
const boardStatuses=computed(()=>meta.task_statuses.filter(s=>s!=='cancelled'));
const otherTasks=computed(()=>selected.value?.tasks?.filter(t=>t.id!==taskDetail.value?.id)||[]);
const availableDependencies=computed(()=>otherTasks.value.filter(t=>!taskDetail.value?.dependencies?.some(d=>d.id===t.id)));
const statItems=[{key:'active',label:'projects.stats.active'},{key:'planned',label:'projects.stats.planned'},{key:'overdue',label:'projects.stats.overdue'},{key:'completed',label:'projects.stats.completed'},{key:'my_open_tasks',label:'projects.stats.my_tasks'}];
const timeCards=[{key:'approved_hours',label:'projects.approved_hours'},{key:'pending_hours',label:'projects.pending_hours'},{key:'actual_cost',label:'projects.actual_cost',money:true},{key:'billable_value',label:'projects.billable_value',money:true}];
const reportCards=[{key:'active_projects',label:'projects.active_projects'},{key:'overdue_projects',label:'projects.overdue_projects'},{key:'task_completion_rate',label:'projects.task_completion_rate',percent:true},{key:'approved_hours',label:'projects.approved_hours'},{key:'actual_cost',label:'projects.actual_cost',money:true},{key:'billable_value',label:'projects.billable_value',money:true},{key:'budget_total',label:'projects.budget_total',money:true},{key:'budget_variance',label:'projects.budget_variance',money:true}];
const availableUsers=computed(()=>meta.users.filter(u=>!selected.value?.members?.some(m=>m.user?.id===u.id)));
const label=(v)=>String(v||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
const initials=(v)=>String(v||'?').split(' ').slice(0,2).map(x=>x[0]).join('').toUpperCase();
const money=(v,c)=>new Intl.NumberFormat(undefined,{style:'currency',currency:c||'USD',maximumFractionDigits:0}).format(v||0);
const dateTime=(v)=>v?new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(v)):'—';
const statusClass=(s)=>({active:'bg-emerald-100 text-emerald-800',planned:'bg-sky-100 text-sky-800',on_hold:'bg-amber-100 text-amber-800',completed:'bg-violet-100 text-violet-800',cancelled:'bg-slate-100 text-slate-600'}[s]||'bg-slate-100');
const isOverdue=(p)=>p.due_date&&!['completed','cancelled'].includes(p.status)&&new Date(p.due_date)<new Date(new Date().toDateString());

async function loadProjects(){loading.value=true;try{const {data}=await api.list({q:filters.q||undefined,status:filters.status,owner_id:filters.mine?'me':undefined,per_page:100});projects.value=data.data||[];}finally{loading.value=false;}}
async function loadAll(){await Promise.all([loadProjects(),api.stats().then(({data})=>Object.assign(stats,data.data||{}))]);}
async function openDetail(id){const {data}=await api.show(id);selected.value=data.data;}
async function selectDetailTab(tab){detailTab.value=tab;if(tab==='time')await loadTime();}
function openProject(p=null,template=null){projectForm.id=p?.id||null;projectForm.data={template_id:template?.id||null,name:p?.name||'',description:p?.description||template?.description||'',status:p?.status||'planned',priority:p?.priority||template?.default_priority||'medium',customer_id:p?.customer?.id||null,owner_id:p?.owner?.id||auth.user?.id||null,start_date:p?.start_date||new Date().toISOString().slice(0,10),due_date:p?.due_date||null,budget:p?.budget||template?.default_budget||0,currency:p?.currency||template?.currency||'USD'};projectForm.open=true;}
function applySelectedTemplate(){const item=templates.value.find(t=>t.id===projectForm.data.template_id);if(!item)return;projectForm.data.description=item.description||projectForm.data.description;projectForm.data.priority=item.default_priority;projectForm.data.budget=item.default_budget;projectForm.data.currency=item.currency;}
async function saveProject(){projectForm.saving=true;try{const payload={...projectForm.data};delete payload.template_id;let response;if(projectForm.id)response=await api.update(projectForm.id,payload);else if(projectForm.data.template_id){delete payload.status;response=await api.createFromTemplate(projectForm.data.template_id,payload);}else response=await api.create(payload);const {data}=response;projectForm.open=false;toast.success(projectForm.id?'Project updated':'Project created');await loadAll();if(selected.value?.id===data.data.id)selected.value=data.data;}finally{projectForm.saving=false;}}
async function deleteProject(){if(!window.confirm('Delete this project?'))return;await api.remove(selected.value.id);selected.value=null;toast.success('Project deleted');await loadAll();}
async function addTask(){saving.value=true;try{const {data}=await api.addTask(selected.value.id,{...taskForm,assigned_to:taskForm.assigned_to||null});selected.value=data.data;taskForm.title='';taskForm.due_date=null;await loadAll();}finally{saving.value=false;}}
async function setTaskStatus(task,status){const {data}=await api.updateTask(selected.value.id,task.id,{status});selected.value=data.data;await loadAll();}
const tasksByStatus=(status)=>selected.value?.tasks?.filter(t=>t.status===status)||[];
const taskDueClass=(task)=>task.due_date&&task.status!=='done'&&task.due_date<new Date().toISOString().slice(0,10)?'text-red-600':'text-ink-subtle';
async function dropTask(status){if(!dragTask.value||dragTask.value.status===status)return;await setTaskStatus(dragTask.value,status);dragTask.value=null;}
async function openTask(task){taskModal.open=true;taskTab.value='details';const {data}=await api.showTask(selected.value.id,task.id);taskDetail.value=data.data;Object.assign(taskEdit,{title:data.data.title,description:data.data.description||'',status:data.data.status,priority:data.data.priority,assigned_to:data.data.assigned_to||null,milestone_id:data.data.milestone_id||null,parent_id:data.data.parent_id||null,start_date:data.data.start_date||null,due_date:data.data.due_date||null,estimated_hours:data.data.estimated_hours||0,actual_hours:data.data.actual_hours||0,recurrence_frequency:data.data.recurrence_frequency||null,recurrence_interval:data.data.recurrence_interval||1,recurrence_end_date:data.data.recurrence_end_date||null});}
function closeTask(){taskModal.open=false;taskDetail.value=null;}
async function saveTask(){taskModal.saving=true;try{const {data}=await api.updateTask(selected.value.id,taskDetail.value.id,{...taskEdit});selected.value=data.data;await openTask({id:taskDetail.value.id});await loadAll();toast.success('Task updated');}finally{taskModal.saving=false;}}
async function addSubtask(){const title=subtaskTitle.value.trim();if(!title)return;const {data}=await api.addTask(selected.value.id,{title,parent_id:taskDetail.value.id,status:'todo'});selected.value=data.data;subtaskTitle.value='';await openTask({id:taskDetail.value.id});await loadAll();}
async function addDependency(){const {data}=await api.addDependency(selected.value.id,taskDetail.value.id,dependencyId.value);taskDetail.value=data.data;dependencyId.value=null;}
async function removeDependency(dep){const {data}=await api.removeDependency(selected.value.id,taskDetail.value.id,dep.id);taskDetail.value=data.data;}
async function addComment(){const body=commentDraft.value.trim();if(!body)return;const {data}=await api.addComment(selected.value.id,taskDetail.value.id,body);taskDetail.value=data.data;commentDraft.value='';await openDetail(selected.value.id);}
async function uploadFile(event){const file=event.target.files?.[0];if(!file)return;uploading.value=true;try{const {data}=await api.uploadTaskFile(selected.value.id,taskDetail.value.id,file);taskDetail.value=data.data;await openDetail(selected.value.id);}finally{uploading.value=false;event.target.value='';}}
async function removeFile(file){if(!window.confirm('Remove this file?'))return;const {data}=await api.removeTaskFile(selected.value.id,taskDetail.value.id,file.id);taskDetail.value=data.data;await openDetail(selected.value.id);}
const fileSize=(bytes)=>bytes<1024?`${bytes} B`:bytes<1048576?`${(bytes/1024).toFixed(1)} KB`:`${(bytes/1048576).toFixed(1)} MB`;
async function deleteTask(task){if(!window.confirm('Delete this task?'))return;const {data}=await api.removeTask(selected.value.id,task.id);selected.value=data.data;await loadAll();}
async function addMilestone(){const {data}=await api.addMilestone(selected.value.id,{...milestoneForm});selected.value=data.data;milestoneForm.name='';milestoneForm.due_date=null;}
async function toggleMilestone(m){const {data}=await api.updateMilestone(selected.value.id,m.id,{status:m.status==='completed'?'pending':'completed'});selected.value=data.data;}
async function deleteMilestone(m){if(!window.confirm('Delete this milestone?'))return;const {data}=await api.removeMilestone(selected.value.id,m.id);selected.value=data.data;}
async function addMember(){const {data}=await api.addMember(selected.value.id,{...memberForm,allocation_percent:100});selected.value=data.data;memberForm.user_id=null;}
async function removeMember(m){const {data}=await api.removeMember(selected.value.id,m.user.id);selected.value=data.data;}
async function loadTime(){if(!selected.value||!can('timesheets.view'))return;const {data}=await api.timeEntries(selected.value.id);timeData.entries=data.data?.entries||[];timeData.summary=data.data?.summary||{};}
async function addTime(){await api.addTime(selected.value.id,{...timeForm,user_id:auth.user.id});timeForm.hours=null;timeForm.notes='';await loadTime();}
async function decideTime(entry,decision){await api.decideTime(selected.value.id,entry.id,decision);await loadTime();}
async function deleteTime(entry){if(!window.confirm('Delete this time entry?'))return;await api.removeTime(selected.value.id,entry.id);await loadTime();}
async function openWorkload(){const {data}=await api.workload();Object.assign(workload,data.data||{});workloadModal.value=true;}
async function loadTemplates(){if(!can('project_templates.view'))return;const {data}=await api.templates();templates.value=data.data||[];}
async function openTemplates(){await loadTemplates();templateForm.project_id=selected.value?.id||projects.value[0]?.id||null;templatesModal.value=true;}
async function saveTemplate(){await api.saveTemplate(templateForm.project_id,{name:templateForm.name,description:templateForm.description||null});templateForm.name='';templateForm.description='';toast.success('Project template saved');await loadTemplates();}
async function toggleTemplate(item){await api.updateTemplate(item.id,{is_active:!item.is_active});await loadTemplates();}
async function deleteTemplate(item){if(!window.confirm('Delete this project template?'))return;await api.removeTemplate(item.id);await loadTemplates();}
function useTemplate(item){templatesModal.value=false;openProject(null,item);}
async function loadAutomations(){const {data}=await api.automationRules();automationRules.value=data.data||[];}
async function openAutomations(){await loadAutomations();automationModal.value=true;}
async function saveAutomation(){const config=automationForm.action==='notify_user'?{user_id:automationForm.user_id}:automationForm.action==='create_follow_up'?{title:automationForm.follow_up_title||undefined,due_days:automationForm.due_days,priority:automationForm.priority}:{priority:automationForm.priority};await api.createAutomationRule({name:automationForm.name,project_id:automationForm.project_id||null,trigger:automationForm.trigger,action:automationForm.action,action_config:config,is_active:true});automationForm.name='';toast.success('Automation rule created');await loadAutomations();}
async function toggleAutomation(rule){await api.updateAutomationRule(rule.id,{is_active:!rule.is_active});await loadAutomations();}
async function deleteAutomation(rule){if(!window.confirm('Delete this automation rule?'))return;await api.removeAutomationRule(rule.id);await loadAutomations();}
async function loadReport(){const {data}=await api.managementReport(reportFilters);Object.assign(report,data.data||{});}
async function openReport(){await loadReport();reportModal.value=true;}
async function openReportProject(project){reportModal.value=false;await openDetail(project.id);}
onMounted(async()=>{const {data}=await api.meta();Object.assign(meta,data.data||{});await Promise.all([loadAll(),loadTemplates()]);if(route.query.project){await openDetail(route.query.project);if(route.query.task)await openTask({id:Number(route.query.task)});router.replace({name:'projects'});}});
</script>
