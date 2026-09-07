<template>
  <div class="page">
    <div class="mb-4">
      <h1 class="page-title">{{ $t('settings.title') }}</h1>
      <p class="page-sub">{{ $t('settings.subtitle') }}</p>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-4 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
      <button v-for="tb in visibleTabs" :key="tb" class="px-3 py-1.5 text-xs -mb-px border-b-2 whitespace-nowrap"
              :class="tab === tb ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-muted dark:text-ink-dark-muted'"
              @click="switchTab(tb)">
        {{ $t(`settings.tab.${tb}`) }}
      </button>
    </div>

    <!-- ===== COMPANY ===== -->
    <div v-if="tab === 'company'" class="card p-4 max-w-2xl">
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label class="label">{{ $t('settings.c.name') }}</label><input v-model="company.name" class="input text-sm" :disabled="!canEdit" /></div>
        <div><label class="label">{{ $t('settings.c.legal_name') }}</label><input v-model="company.legal_name" class="input text-sm" :disabled="!canEdit" /></div>
        <div><label class="label">{{ $t('settings.c.tax_id') }}</label><input v-model="company.tax_id" class="input text-sm" :disabled="!canEdit" /></div>
        <div><label class="label">{{ $t('settings.c.currency') }}</label><input v-model="company.base_currency" maxlength="3" class="input text-sm uppercase" :disabled="!canEdit" /></div>
        <div><label class="label">{{ $t('settings.c.language') }}</label>
          <select v-model="company.default_language" class="input text-sm" :disabled="!canEdit">
            <option value="en">English</option><option value="ar">العربية</option>
          </select></div>
        <div><label class="label">{{ $t('settings.c.phone') }}</label><input v-model="company.phone" class="input text-sm" :disabled="!canEdit" /></div>
        <div><label class="label">{{ $t('settings.c.email') }}</label><input v-model="company.email" class="input text-sm" :disabled="!canEdit" /></div>
        <div class="col-span-2"><label class="label">{{ $t('settings.c.address') }}</label><input v-model="company.address_line1" class="input text-sm" :disabled="!canEdit" /></div>
        <div><label class="label">{{ $t('settings.c.city') }}</label><input v-model="company.city" class="input text-sm" :disabled="!canEdit" /></div>
        <div><label class="label">{{ $t('settings.c.country') }}</label><input v-model="company.country" class="input text-sm" :disabled="!canEdit" /></div>
      </div>
      <div v-if="canEdit" class="flex justify-end mt-4">
        <button class="btn-primary btn-sm" :disabled="savingCompany" @click="saveCompany">{{ savingCompany ? $t('settings.saving') : $t('settings.save') }}</button>
      </div>
    </div>

    <!-- ===== SUBSCRIPTION / BILLING ===== -->
    <BillingPanel v-else-if="tab === 'billing'" />

    <!-- ===== USERS ===== -->
    <!-- ===== APPEARANCE ===== -->
    <div v-else-if="tab === 'appearance'" class="card p-4 max-w-2xl space-y-5">
      <div>
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-0.5">{{ $t('theme.mode') }}</div>
        <p class="text-[11px] text-ink-subtle mb-2">{{ $t('settings.appearance.mode_hint') }}</p>
        <div class="flex gap-2">
          <button class="btn-secondary btn-sm" :class="ui.theme === 'light' && '!border-primary-500 !text-primary-600'" @click="ui.setTheme('light')"><Sun :size="14" /> {{ $t('theme.light') }}</button>
          <button class="btn-secondary btn-sm" :class="ui.theme === 'dark' && '!border-primary-500 !text-primary-600'" @click="ui.setTheme('dark')"><Moon :size="14" /> {{ $t('theme.dark') }}</button>
        </div>
      </div>
      <div class="divider" />
      <div>
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-0.5">{{ $t('theme.accent') }}</div>
        <p class="text-[11px] text-ink-subtle mb-2">{{ $t('settings.appearance.accent_hint') }}</p>
        <div class="flex items-center gap-3">
          <button v-for="a in APPEARANCE_ACCENTS" :key="a.key" type="button"
                  class="w-9 h-9 rounded-full ring-2 ring-offset-2 ring-offset-white dark:ring-offset-surface-dark-muted flex items-center justify-center transition"
                  :class="ui.accent === a.key ? 'ring-current' : 'ring-transparent hover:ring-slate-300'"
                  :style="{ backgroundColor: a.color, color: a.color }" :title="a.label" @click="ui.setAccent(a.key)">
            <Check v-if="ui.accent === a.key" :size="16" class="text-white" />
          </button>
        </div>
      </div>
      <div class="divider" />

      <!-- ===== Sidebar background ===== -->
      <div>
        <div class="flex items-center justify-between mb-0.5">
          <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('settings.appearance.sidebar') }}</div>
          <button v-if="ui.chrome.sidebar" class="btn-ghost btn-xs" @click="resetChrome('sidebar')">
            <X :size="12" /> {{ $t('settings.appearance.reset') }}
          </button>
        </div>
        <p class="text-[11px] text-ink-subtle mb-2">{{ $t('settings.appearance.sidebar_hint') }}</p>
        <div class="flex items-center flex-wrap gap-2.5">
          <button type="button" :title="$t('settings.appearance.default')"
                  class="w-9 h-9 rounded-lg border border-line-strong dark:border-line-dark-strong bg-white dark:bg-surface-dark-muted flex items-center justify-center transition ring-2 ring-offset-2 ring-offset-white dark:ring-offset-surface-dark-muted"
                  :class="!ui.chrome.sidebar ? 'ring-primary-500' : 'ring-transparent hover:ring-slate-300'"
                  @click="resetChrome('sidebar')">
            <Check v-if="!ui.chrome.sidebar" :size="15" class="text-primary-600" />
          </button>
          <button v-for="p in CHROME_PRESETS" :key="`sb-${p.key}`" type="button" :title="p.label"
                  class="w-9 h-9 rounded-lg flex items-center justify-center transition ring-2 ring-offset-2 ring-offset-white dark:ring-offset-surface-dark-muted"
                  :class="sameColor(ui.chrome.sidebar, p.color) ? 'ring-current' : 'ring-transparent hover:ring-slate-300'"
                  :style="{ backgroundColor: p.color, color: p.color }" @click="setChrome('sidebar', p.color)">
            <Check v-if="sameColor(ui.chrome.sidebar, p.color)" :size="15" class="text-white" />
          </button>
          <label class="w-9 h-9 rounded-lg border border-dashed border-line-strong dark:border-line-dark-strong flex items-center justify-center cursor-pointer overflow-hidden relative" :title="$t('settings.appearance.custom')">
            <Pipette :size="14" class="text-ink-subtle" />
            <input type="color" class="absolute inset-0 opacity-0 cursor-pointer" :value="ui.chrome.sidebar || '#0F172A'" @input="setChrome('sidebar', $event.target.value)" />
          </label>
        </div>
      </div>

      <div class="divider" />

      <!-- ===== Top bar background ===== -->
      <div>
        <div class="flex items-center justify-between mb-0.5">
          <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('settings.appearance.topbar') }}</div>
          <button v-if="ui.chrome.topbar" class="btn-ghost btn-xs" @click="resetChrome('topbar')">
            <X :size="12" /> {{ $t('settings.appearance.reset') }}
          </button>
        </div>
        <p class="text-[11px] text-ink-subtle mb-2">{{ $t('settings.appearance.topbar_hint') }}</p>
        <div class="flex items-center flex-wrap gap-2.5">
          <button type="button" :title="$t('settings.appearance.default')"
                  class="w-9 h-9 rounded-lg border border-line-strong dark:border-line-dark-strong bg-white dark:bg-surface-dark-muted flex items-center justify-center transition ring-2 ring-offset-2 ring-offset-white dark:ring-offset-surface-dark-muted"
                  :class="!ui.chrome.topbar ? 'ring-primary-500' : 'ring-transparent hover:ring-slate-300'"
                  @click="resetChrome('topbar')">
            <Check v-if="!ui.chrome.topbar" :size="15" class="text-primary-600" />
          </button>
          <button v-for="p in CHROME_PRESETS" :key="`tb-${p.key}`" type="button" :title="p.label"
                  class="w-9 h-9 rounded-lg flex items-center justify-center transition ring-2 ring-offset-2 ring-offset-white dark:ring-offset-surface-dark-muted"
                  :class="sameColor(ui.chrome.topbar, p.color) ? 'ring-current' : 'ring-transparent hover:ring-slate-300'"
                  :style="{ backgroundColor: p.color, color: p.color }" @click="setChrome('topbar', p.color)">
            <Check v-if="sameColor(ui.chrome.topbar, p.color)" :size="15" class="text-white" />
          </button>
          <label class="w-9 h-9 rounded-lg border border-dashed border-line-strong dark:border-line-dark-strong flex items-center justify-center cursor-pointer overflow-hidden relative" :title="$t('settings.appearance.custom')">
            <Pipette :size="14" class="text-ink-subtle" />
            <input type="color" class="absolute inset-0 opacity-0 cursor-pointer" :value="ui.chrome.topbar || '#0F172A'" @input="setChrome('topbar', $event.target.value)" />
          </label>
        </div>
      </div>

      <p class="text-[11px] text-ink-subtle">{{ $t('settings.appearance.note') }}</p>
    </div>

    <div v-else-if="tab === 'users'" class="card overflow-hidden">
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex gap-2">
        <input v-model="userFilters.q" class="input text-sm w-52" :placeholder="$t('settings.search_users')" @keyup.enter="loadUsers" />
        <button v-if="can('users.create')" class="btn-primary btn-sm ml-auto" @click="openUser()"><Plus :size="12" /> {{ $t('settings.new_user') }}</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('settings.u.name') }}</th>
            <th class="hidden md:table-cell">{{ $t('settings.u.roles') }}</th>
            <th class="hidden lg:table-cell">{{ $t('settings.u.branch') }}</th>
            <th>{{ $t('settings.u.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2"><div class="text-ink dark:text-ink-dark">{{ u.name }}</div><div class="text-[11px] text-ink-subtle">{{ u.email }}</div></td>
            <td class="px-3 py-2 hidden md:table-cell">
              <span v-for="r in u.roles" :key="r" class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 mr-1">{{ r }}</span>
            </td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-muted">{{ u.branch?.name || '—' }}</td>
            <td class="px-3 py-2">
              <span class="text-[10px] px-1.5 py-0.5 rounded" :class="u.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                {{ u.is_active ? $t('settings.active') : $t('settings.inactive') }}
              </span>
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('users.update')" class="text-[11px] text-primary-600 hover:underline" @click="openUser(u)">{{ $t('settings.edit') }}</button>
              <button v-if="can('users.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeUser(u.id)">{{ $t('settings.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ===== ROLES ===== -->
    <div v-else-if="tab === 'roles'" class="flex gap-3 items-start">
      <div class="card flex-1 min-w-0 overflow-hidden">
        <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex">
          <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('settings.tab.roles') }}</span>
          <button v-if="can('roles.create')" class="btn-primary btn-xs ml-auto" @click="openRole()"><Plus :size="11" /> {{ $t('settings.new_role') }}</button>
        </div>
        <div v-for="r in roles" :key="r.id"
             class="flex items-center gap-2 px-3 py-2 border-t border-slate-100 dark:border-slate-700/60 cursor-pointer"
             :class="selectedRole?.id === r.id ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-slate-50 dark:hover:bg-surface-dark-subtle'"
             @click="openRoleDetail(r)">
          <ShieldCheck :size="14" class="text-ink-subtle shrink-0" />
          <span class="text-sm text-ink dark:text-ink-dark">{{ r.name }}</span>
          <span v-if="r.is_protected" class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">{{ $t('settings.protected') }}</span>
          <span class="text-[11px] text-ink-subtle ml-auto">{{ r.permissions_count }} {{ $t('settings.perms') }} · {{ r.users_count }} {{ $t('settings.users_lc') }}</span>
        </div>
      </div>

      <!-- Role permission editor -->
      <div v-if="selectedRole" class="card w-full sm:w-[26rem] shrink-0 flex flex-col overflow-hidden max-h-[calc(100vh-12rem)]">
        <div class="px-3 py-2.5 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <input v-if="!selectedRole.is_protected && editingRole" v-model="roleForm.name" class="input text-sm flex-1" />
          <span v-else class="text-sm font-medium text-ink dark:text-ink-dark flex-1">{{ selectedRole.name }}</span>
          <button v-if="!selectedRole.is_protected && can('roles.update') && !editingRole" class="btn-secondary btn-xs" @click="editingRole = true">{{ $t('settings.edit') }}</button>
          <button v-if="selectedRole.id && can('roles.create') && !editingRole" class="btn-secondary btn-xs" @click="openClone(selectedRole)">{{ $t('settings.clone') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selectedRole = null"><X :size="14" /></button>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <div v-for="(perms, module) in permissions" :key="module">
            <div class="text-[10px] tracking-wider text-ink-subtle uppercase mb-1">{{ module }}</div>
            <div class="grid grid-cols-2 gap-1">
              <label v-for="p in perms" :key="p.name" class="flex items-center gap-1.5 text-ink-muted dark:text-ink-dark-muted">
                <input type="checkbox" class="rounded border-slate-300" :value="p.name" v-model="roleForm.permissions"
                       :disabled="!editingRole || selectedRole.is_protected" />
                {{ p.name.split('.')[1] }}
              </label>
            </div>
          </div>
        </div>
        <div v-if="editingRole" class="border-t border-slate-200 dark:border-slate-700 p-2.5 flex justify-end gap-2">
          <button class="btn-secondary btn-sm" @click="cancelRoleEdit">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="savingRole" @click="saveRole">{{ savingRole ? $t('settings.saving') : $t('settings.save') }}</button>
        </div>
      </div>
    </div>

    <!-- ===== API KEYS ===== -->
    <div v-else-if="tab === 'api_keys'" class="card overflow-hidden">
      <div v-if="newKeyPlain" class="p-3 border-b border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800">
        <div class="text-xs font-medium text-amber-800 dark:text-amber-300 mb-1">{{ $t('settings.ak.reveal_title') }}</div>
        <div class="flex items-center gap-2">
          <code class="flex-1 text-xs bg-white dark:bg-surface-dark px-2 py-1 rounded border border-amber-200 dark:border-amber-800 overflow-x-auto">{{ newKeyPlain }}</code>
          <button class="btn-secondary btn-xs" @click="copyKey">{{ $t('settings.ak.copy') }}</button>
          <button class="p-1 text-amber-700 hover:text-amber-900" @click="newKeyPlain = ''"><X :size="14" /></button>
        </div>
      </div>
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex">
        <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('settings.tab.api_keys') }}</span>
        <button v-if="can('api_keys.create')" class="btn-primary btn-xs ml-auto" @click="openApiKey()"><Plus :size="11" /> {{ $t('settings.ak.new') }}</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('settings.ak.name') }}</th>
            <th class="hidden md:table-cell">{{ $t('settings.ak.acts_as') }}</th>
            <th>{{ $t('settings.ak.prefix') }}</th>
            <th class="hidden lg:table-cell">{{ $t('settings.ak.last_used') }}</th>
            <th>{{ $t('settings.ak.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="k in apiKeys" :key="k.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2 text-ink dark:text-ink-dark">{{ k.name }}</td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ k.user?.name || '—' }}</td>
            <td class="px-3 py-2 font-mono text-[11px] text-ink-muted">{{ k.key_prefix }}…</td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-muted">{{ k.last_used_human || $t('settings.ak.never') }}</td>
            <td class="px-3 py-2">
              <span class="text-[10px] px-1.5 py-0.5 rounded"
                    :class="k.is_active && !k.is_expired ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                {{ k.is_expired ? $t('settings.ak.expired') : (k.is_active ? $t('settings.active') : $t('settings.ak.revoked')) }}
              </span>
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('api_keys.update') && k.is_active" class="text-[11px] text-amber-600 hover:underline" @click="revokeApiKey(k)">{{ $t('settings.ak.revoke') }}</button>
              <button v-if="can('api_keys.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeApiKey(k.id)">{{ $t('settings.delete') }}</button>
            </td>
          </tr>
          <tr v-if="!apiKeys.length"><td colspan="6" class="px-3 py-6 text-center text-xs text-ink-subtle">{{ $t('settings.ak.empty') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ===== WEBHOOKS ===== -->
    <div v-else-if="tab === 'webhooks'" class="card overflow-hidden">
      <div v-if="newWebhook" class="p-3 border-b border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 space-y-2">
        <div class="text-xs font-medium text-amber-800 dark:text-amber-300">{{ $t('settings.wh.created_notice') }}</div>
        <div class="flex items-center gap-2">
          <span class="text-[10px] text-ink-subtle w-12 shrink-0">{{ $t('settings.wh.url') }}</span>
          <code class="flex-1 text-xs bg-white dark:bg-surface-dark px-2 py-1 rounded border border-amber-200 dark:border-amber-800 overflow-x-auto">{{ newWebhook.url }}</code>
          <button class="btn-secondary btn-xs" @click="copyText(newWebhook.url, $t('settings.wh.copied'))">{{ $t('settings.ak.copy') }}</button>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[10px] text-ink-subtle w-12 shrink-0">{{ $t('settings.wh.secret') }}</span>
          <code class="flex-1 text-xs bg-white dark:bg-surface-dark px-2 py-1 rounded border border-amber-200 dark:border-amber-800 overflow-x-auto">{{ newWebhook.secret }}</code>
          <button class="btn-secondary btn-xs" @click="copyText(newWebhook.secret, $t('settings.wh.copied'))">{{ $t('settings.ak.copy') }}</button>
          <button class="p-1 text-amber-700 hover:text-amber-900" @click="newWebhook = null"><X :size="14" /></button>
        </div>
      </div>
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex">
        <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('settings.tab.webhooks') }}</span>
        <button v-if="can('api_keys.create')" class="btn-primary btn-xs ml-auto" @click="openWebhook()"><Plus :size="11" /> {{ $t('settings.wh.new') }}</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('settings.wh.name') }}</th>
            <th class="hidden md:table-cell">{{ $t('settings.wh.type') }}</th>
            <th>{{ $t('settings.wh.status') }}</th>
            <th class="hidden lg:table-cell">{{ $t('settings.wh.last_received') }}</th>
            <th class="th-num">{{ $t('settings.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="w in webhooks" :key="w.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2">
              <div class="font-medium text-ink dark:text-ink-dark">{{ w.name }}</div>
              <div class="text-[10px] text-ink-subtle font-mono">{{ w.secret_prefix }}…</div>
            </td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ $t(`settings.wh.type_${w.type}`) }}</td>
            <td class="px-3 py-2"><span class="badge" :class="w.is_active ? 'badge-success' : 'badge-neutral'">{{ w.is_active ? $t('settings.wh.active') : $t('settings.wh.inactive') }}</span></td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-muted text-[11px]">{{ w.last_received_at ? new Date(w.last_received_at).toLocaleString() : $t('settings.wh.never') }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button class="text-[11px] text-primary-600 hover:underline" @click="viewWebhookEvents(w)">{{ $t('settings.wh.events') }}</button>
              <button v-if="can('api_keys.update')" class="text-[11px] text-amber-600 hover:underline ml-2" @click="toggleWebhook(w)">{{ w.is_active ? $t('settings.wh.disable') : $t('settings.wh.enable') }}</button>
              <button v-if="can('api_keys.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeWebhook(w.id)">{{ $t('settings.delete') }}</button>
            </td>
          </tr>
          <tr v-if="!webhooks.length"><td colspan="5" class="px-3 py-6 text-center text-xs text-ink-subtle">{{ $t('settings.wh.empty') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ===== TICKET ROUTING ===== -->
    <div v-else-if="tab === 'routing'" class="card overflow-hidden">
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
        <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('settings.tab.routing') }}</span>
        <button v-if="can('tickets.update')" class="btn-primary btn-xs ml-auto" @click="openRouting()"><Plus :size="11" /> {{ $t('settings.rt.new') }}</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('settings.rt.priority') }}</th>
            <th>{{ $t('settings.rt.name') }}</th>
            <th class="hidden md:table-cell">{{ $t('settings.rt.strategy') }}</th>
            <th class="hidden md:table-cell">{{ $t('settings.rt.target') }}</th>
            <th class="hidden lg:table-cell">{{ $t('settings.rt.matches') }}</th>
            <th>{{ $t('settings.rt.active') }}</th>
            <th class="text-right">{{ $t('settings.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in routingRules" :key="r.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2 tabular-nums text-ink-subtle">
              <div class="flex items-center gap-1">
                <span>{{ r.priority }}</span>
                <span class="flex flex-col leading-none">
                  <button class="text-ink-subtle hover:text-ink disabled:opacity-30" :disabled="i === 0" @click="moveRouting(r, -1)"><ChevronUp :size="11" /></button>
                  <button class="text-ink-subtle hover:text-ink disabled:opacity-30" :disabled="i === routingRules.length - 1" @click="moveRouting(r, 1)"><ChevronDown :size="11" /></button>
                </span>
              </div>
            </td>
            <td class="px-3 py-2 font-medium text-ink dark:text-ink-dark">{{ r.name }}</td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ $t(`settings.rt.strategy_${r.strategy}`) }}</td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ routingTarget(r) }}</td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-muted text-[11px]">{{ r.conditions && r.conditions.length ? `${r.conditions.length} ${$t('settings.rt.conditions').toLowerCase()}` : $t('settings.rt.any') }}</td>
            <td class="px-3 py-2">
              <button @click="toggleRouting(r)"><span class="badge" :class="r.is_active ? 'badge-success' : 'badge-neutral'">{{ r.is_active ? $t('settings.rt.active') : $t('settings.rt.inactive') }}</span></button>
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('tickets.update')" class="text-[11px] text-primary-600 hover:underline" @click="openRouting(r)">{{ $t('settings.edit') }}</button>
              <button v-if="can('tickets.update')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeRouting(r.id)">{{ $t('settings.delete') }}</button>
            </td>
          </tr>
          <tr v-if="!routingRules.length"><td colspan="7" class="px-3 py-6 text-center text-xs text-ink-subtle">{{ $t('settings.rt.empty') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ===== SMS PROVIDERS ===== -->
    <div v-else-if="tab === 'sms'" class="card overflow-hidden">
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2">
        <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t('settings.tab.sms') }}</span>
        <button v-if="can('campaigns.create')" class="btn-primary btn-xs ml-auto" @click="openSms()"><Plus :size="11" /> {{ $t('settings.sms.new') }}</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('settings.sms.name') }}</th>
            <th class="hidden md:table-cell">{{ $t('settings.sms.provider') }}</th>
            <th class="hidden md:table-cell">{{ $t('settings.sms.channel') }}</th>
            <th class="hidden lg:table-cell">{{ $t('settings.sms.sender_id') }}</th>
            <th>{{ $t('settings.sms.configured') }}</th>
            <th class="text-right">{{ $t('settings.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in smsProviders" :key="p.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2 font-medium text-ink dark:text-ink-dark">
              {{ p.name }} <span v-if="p.is_default" class="badge badge-info ml-1">{{ $t('settings.sms.default') }}</span>
            </td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ $t(`settings.sms.provider_${p.provider}`) }}</td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ $t(`settings.sms.channel_${p.channel}`) }}</td>
            <td class="px-3 py-2 hidden lg:table-cell text-ink-muted font-mono text-[11px]">{{ p.sender_id || '—' }}</td>
            <td class="px-3 py-2"><span class="badge" :class="p.configured ? (p.is_active ? 'badge-success' : 'badge-neutral') : 'badge-warning'">{{ p.configured ? (p.is_active ? $t('settings.sms.configured') : $t('settings.sms.off')) : $t('settings.sms.not_configured') }}</span></td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="p.configured && can('campaigns.create')" class="text-[11px] text-primary-600 hover:underline" @click="testSms(p)">{{ $t('settings.sms.test') }}</button>
              <button v-if="can('campaigns.update')" class="text-[11px] text-primary-600 hover:underline ml-2" @click="openSms(p)">{{ $t('settings.edit') }}</button>
              <button v-if="can('campaigns.delete')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeSms(p.id)">{{ $t('settings.delete') }}</button>
            </td>
          </tr>
          <tr v-if="!smsProviders.length"><td colspan="6" class="px-3 py-6 text-center text-xs text-ink-subtle">{{ $t('settings.sms.empty') }}</td></tr>
        </tbody>
      </table>
    </div>

    <!-- ===== BRANCHES / DEPARTMENTS ===== -->
    <div v-else class="card overflow-hidden">
      <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/60 flex">
        <span class="text-xs font-medium text-ink dark:text-ink-dark">{{ $t(`settings.tab.${tab}`) }}</span>
        <button v-if="can('settings.update')" class="btn-primary btn-xs ml-auto" @click="openOrg()"><Plus :size="11" /> {{ $t(tab === 'branches' ? 'settings.new_branch' : 'settings.new_department') }}</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('settings.o.name') }}</th>
            <th>{{ $t('settings.o.code') }}</th>
            <th class="hidden md:table-cell">{{ tab === 'branches' ? $t('settings.o.manager') : $t('settings.o.head') }}</th>
            <th class="th-num">{{ $t('settings.o.users') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orgRows" :key="o.id" class="border-t border-slate-100 dark:border-slate-700/60">
            <td class="px-3 py-2 text-ink dark:text-ink-dark">{{ o.name }}</td>
            <td class="px-3 py-2 font-mono text-[11px] text-ink-muted">{{ o.code }}</td>
            <td class="px-3 py-2 hidden md:table-cell text-ink-muted">{{ o.manager || o.head || '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-ink-muted">{{ o.users_count }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button v-if="can('settings.update')" class="text-[11px] text-primary-600 hover:underline" @click="openOrg(o)">{{ $t('settings.edit') }}</button>
              <button v-if="can('settings.update')" class="text-[11px] text-red-500 hover:underline ml-2" @click="removeOrg(o.id)">{{ $t('settings.delete') }}</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- User modal -->
    <div v-if="userForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="userForm.open = false">
      <div class="card w-full max-w-lg p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ userForm.id ? $t('settings.edit_user') : $t('settings.new_user') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div><label class="label">{{ $t('settings.u.name') }} *</label><input v-model="userForm.data.name" class="input text-sm" />
            <p v-if="userForm.errors.name" class="text-[11px] text-red-500">{{ userForm.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('settings.u.email') }} *</label><input v-model="userForm.data.email" class="input text-sm" />
            <p v-if="userForm.errors.email" class="text-[11px] text-red-500">{{ userForm.errors.email[0] }}</p></div>
          <div><label class="label">{{ userForm.id ? $t('settings.u.new_password') : $t('settings.u.password') }} {{ userForm.id ? '' : '*' }}</label>
            <input v-model="userForm.data.password" type="password" class="input text-sm" autocomplete="new-password" />
            <p v-if="userForm.errors.password" class="text-[11px] text-red-500">{{ userForm.errors.password[0] }}</p></div>
          <div><label class="label">{{ $t('settings.u.phone') }}</label><input v-model="userForm.data.phone" class="input text-sm" /></div>
          <div><label class="label">{{ $t('settings.u.branch') }}</label>
            <select v-model="userForm.data.branch_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="b in meta.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select></div>
          <div><label class="label">{{ $t('settings.u.department') }}</label>
            <select v-model="userForm.data.department_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="d in meta.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select></div>
          <div class="col-span-2"><label class="label">{{ $t('settings.u.roles') }}</label>
            <div class="flex flex-wrap gap-2 mt-1">
              <label v-for="r in meta.roles" :key="r" class="flex items-center gap-1 text-xs text-ink-muted">
                <input type="checkbox" class="rounded border-slate-300" :value="r" v-model="userForm.data.roles" /> {{ r }}
              </label>
            </div>
          </div>
          <label class="col-span-2 flex items-center gap-1.5 text-xs text-ink-muted">
            <input type="checkbox" class="rounded border-slate-300" v-model="userForm.data.is_active" /> {{ $t('settings.active') }}
          </label>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="userForm.open = false">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="userForm.saving" @click="submitUser">{{ userForm.saving ? $t('settings.saving') : $t('settings.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Clone role modal -->
    <div v-if="cloneForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="cloneForm.open = false">
      <div class="card w-full max-w-sm p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-1">{{ $t('settings.clone_role') }}</div>
        <div class="text-xs text-ink-muted mb-3">{{ $t('settings.clone_role_hint', { name: cloneForm.sourceName }) }}</div>
        <label class="label">{{ $t('settings.new_role') }} *</label>
        <input v-model="cloneForm.name" class="input text-sm w-full" @keyup.enter="submitClone" />
        <p v-if="cloneForm.errors.name" class="text-[11px] text-red-500">{{ cloneForm.errors.name[0] }}</p>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="cloneForm.open = false">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="cloneForm.saving" @click="submitClone">{{ cloneForm.saving ? $t('settings.saving') : $t('settings.save') }}</button>
        </div>
      </div>
    </div>

    <!-- API key modal -->
    <div v-if="apiKeyForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="apiKeyForm.open = false">
      <div class="card w-full max-w-sm p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('settings.ak.new') }}</div>
        <label class="label">{{ $t('settings.ak.name') }} *</label>
        <input v-model="apiKeyForm.data.name" class="input text-sm w-full" :placeholder="$t('settings.ak.name_ph')" />
        <p v-if="apiKeyForm.errors.name" class="text-[11px] text-red-500">{{ apiKeyForm.errors.name[0] }}</p>
        <label class="label mt-2.5">{{ $t('settings.ak.acts_as') }}</label>
        <select v-model="apiKeyForm.data.user_id" class="input text-sm w-full">
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <p class="text-[11px] text-ink-subtle mt-1">{{ $t('settings.ak.acts_as_hint') }}</p>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="apiKeyForm.open = false">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="apiKeyForm.saving" @click="submitApiKey">{{ apiKeyForm.saving ? $t('settings.saving') : $t('settings.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Webhook create modal -->
    <div v-if="webhookForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="webhookForm.open = false">
      <div class="card w-full max-w-sm p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t('settings.wh.new') }}</div>
        <label class="label">{{ $t('settings.wh.name') }} *</label>
        <input v-model="webhookForm.data.name" class="input text-sm w-full" :placeholder="$t('settings.wh.name_ph')" />
        <p v-if="webhookForm.errors.name" class="text-[11px] text-red-500">{{ webhookForm.errors.name[0] }}</p>
        <label class="label mt-2.5">{{ $t('settings.wh.type') }}</label>
        <select v-model="webhookForm.data.type" class="input text-sm w-full">
          <option value="web_to_lead">{{ $t('settings.wh.type_web_to_lead') }}</option>
          <option value="email_status">{{ $t('settings.wh.type_email_status') }}</option>
          <option value="call_log">{{ $t('settings.wh.type_call_log') }}</option>
        </select>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="webhookForm.open = false">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="webhookForm.saving" @click="submitWebhook">{{ webhookForm.saving ? $t('settings.saving') : $t('settings.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Webhook events modal -->
    <div v-if="webhookEvents.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="webhookEvents.open = false">
      <div class="card w-full max-w-2xl p-4 mt-10">
        <div class="flex items-center gap-2 mb-3">
          <span class="text-sm font-medium text-ink dark:text-ink-dark">{{ $t('settings.wh.events') }} · {{ webhookEvents.endpoint?.name }}</span>
          <button class="p-1 text-ink-subtle hover:text-ink ml-auto" @click="webhookEvents.open = false"><X :size="14" /></button>
        </div>
        <div v-if="!webhookEvents.events.length" class="text-xs text-ink-subtle py-8 text-center">{{ $t('settings.wh.no_events') }}</div>
        <div v-else class="overflow-x-auto max-h-[60vh]">
          <table class="data-table">
            <thead><tr><th>{{ $t('settings.wh.status') }}</th><th>{{ $t('settings.wh.event_id') }}</th><th>{{ $t('settings.wh.result') }}</th><th>{{ $t('settings.wh.when') }}</th></tr></thead>
            <tbody>
              <tr v-for="ev in webhookEvents.events" :key="ev.id">
                <td class="px-3 py-2"><span class="badge" :class="ev.status === 'processed' ? 'badge-success' : (ev.status === 'failed' ? 'badge-danger' : 'badge-neutral')">{{ ev.status }}</span></td>
                <td class="px-3 py-2 font-mono text-[11px] text-ink-muted truncate max-w-[10rem]">{{ ev.event_id }}</td>
                <td class="px-3 py-2 text-[11px] text-ink-muted">{{ ev.error || (ev.result ? JSON.stringify(ev.result) : '—') }}</td>
                <td class="px-3 py-2 text-[11px] text-ink-subtle whitespace-nowrap">{{ ev.created_at ? new Date(ev.created_at).toLocaleString() : '' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Routing rule modal -->
    <div v-if="routingForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="routingForm.open = false">
      <div class="card w-full max-w-lg p-4 mt-8 space-y-3">
        <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ routingForm.id ? $t('settings.rt.edit') : $t('settings.rt.new') }}</div>
        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2">
            <label class="label">{{ $t('settings.rt.name') }} *</label>
            <input v-model="routingForm.data.name" class="input text-sm w-full" :placeholder="$t('settings.rt.name_ph')" />
            <p v-if="routingForm.errors.name" class="text-[11px] text-red-500">{{ routingForm.errors.name[0] }}</p>
          </div>
          <div>
            <label class="label">{{ $t('settings.rt.priority') }}</label>
            <input v-model.number="routingForm.data.priority" type="number" class="input text-sm w-full" />
          </div>
        </div>
        <div>
          <label class="label">{{ $t('settings.rt.strategy') }}</label>
          <select v-model="routingForm.data.strategy" class="input text-sm w-full">
            <option v-for="s in ROUTE_STRATEGIES" :key="s" :value="s">{{ $t(`settings.rt.strategy_${s}`) }}</option>
          </select>
        </div>
        <div v-if="routingForm.data.strategy === 'specific'">
          <label class="label">{{ $t('settings.rt.assignee') }} *</label>
          <select v-model="routingForm.data.assign_to_user_id" class="input text-sm w-full">
            <option :value="null" disabled>—</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div v-else>
          <label class="label">{{ $t('settings.rt.pool') }} *</label>
          <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto border border-line dark:border-line-dark rounded-lg p-2">
            <button v-for="u in users" :key="u.id" type="button" class="chip cursor-pointer"
                    :class="routingForm.data.pool_user_ids.includes(u.id) && '!bg-primary-50 !text-primary-700 dark:!bg-primary-900/25 dark:!text-primary-300'"
                    @click="toggleRoutingPool(u.id)">{{ u.name }}</button>
          </div>
          <p class="text-[11px] text-ink-subtle mt-1">{{ $t('settings.rt.pool_hint') }}</p>
        </div>
        <div>
          <div class="flex items-center gap-2 mb-1">
            <label class="label mb-0">{{ $t('settings.rt.conditions') }}</label>
            <button type="button" class="btn-secondary btn-xs ml-auto" @click="addRoutingCond"><Plus :size="10" /> {{ $t('settings.rt.add_condition') }}</button>
          </div>
          <p v-if="!routingForm.data.conditions.length" class="text-[11px] text-ink-subtle">{{ $t('settings.rt.cond_hint') }}</p>
          <div v-for="(c, i) in routingForm.data.conditions" :key="i" class="flex items-center gap-1.5 mb-1.5">
            <select v-model="c.field" class="input input-sm w-32"><option v-for="f in ROUTE_FIELDS" :key="f" :value="f">{{ $t(`settings.rt.f_${f}`) }}</option></select>
            <select v-model="c.op" class="input input-sm w-28"><option v-for="o in ROUTE_OPS" :key="o" :value="o">{{ $t(`settings.rt.op_${o}`) }}</option></select>
            <input v-if="!['is_set','is_empty'].includes(c.op)" v-model="c.value" class="input input-sm flex-1" :placeholder="$t('settings.rt.value')" />
            <span v-else class="flex-1" />
            <button type="button" class="p-1 text-ink-subtle hover:text-red-500" @click="removeRoutingCond(i)"><X :size="13" /></button>
          </div>
        </div>
        <label class="flex items-center gap-2 text-xs text-ink-muted"><input type="checkbox" v-model="routingForm.data.is_active" class="rounded border-slate-300" /> {{ $t('settings.rt.active') }}</label>
        <div class="flex justify-end gap-2 pt-1">
          <button class="btn-secondary btn-sm" @click="routingForm.open = false">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="routingForm.saving" @click="submitRouting">{{ routingForm.saving ? $t('settings.saving') : $t('settings.save') }}</button>
        </div>
      </div>
    </div>

    <!-- SMS provider modal -->
    <div v-if="smsForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="smsForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8 space-y-3">
        <div class="text-sm font-medium text-ink dark:text-ink-dark">{{ smsForm.id ? $t('settings.sms.edit') : $t('settings.sms.new') }}</div>
        <div>
          <label class="label">{{ $t('settings.sms.name') }} *</label>
          <input v-model="smsForm.data.name" class="input text-sm w-full" :placeholder="$t('settings.sms.name_ph')" />
          <p v-if="smsForm.errors.name" class="text-[11px] text-red-500">{{ smsForm.errors.name[0] }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">{{ $t('settings.sms.provider') }}</label>
            <select v-model="smsForm.data.provider" class="input text-sm w-full"><option v-for="pr in SMS_PROVIDERS" :key="pr" :value="pr">{{ $t(`settings.sms.provider_${pr}`) }}</option></select>
          </div>
          <div>
            <label class="label">{{ $t('settings.sms.channel') }}</label>
            <select v-model="smsForm.data.config.channel" class="input text-sm w-full"><option v-for="ch in SMS_CHANNELS" :key="ch" :value="ch">{{ $t(`settings.sms.channel_${ch}`) }}</option></select>
          </div>
        </div>
        <div>
          <label class="label">{{ $t('settings.sms.sender_id') }}</label>
          <input v-model="smsForm.data.sender_id" class="input text-sm w-full" :placeholder="$t('settings.sms.sender_ph')" />
        </div>
        <template v-if="smsForm.data.provider === 'twilio'">
          <div>
            <label class="label">{{ $t('settings.sms.account_sid') }}</label>
            <input v-model="smsForm.data.config.account_sid" class="input text-sm w-full" placeholder="AC…" />
          </div>
          <div>
            <label class="label">{{ $t('settings.sms.auth_token') }}</label>
            <input v-model="smsForm.data.config.auth_token" type="password" class="input text-sm w-full" :placeholder="smsForm.id ? $t('settings.sms.auth_token_keep') : ''" />
          </div>
        </template>
        <div v-else>
          <label class="label">{{ $t('settings.sms.url') }}</label>
          <input v-model="smsForm.data.config.url" class="input text-sm w-full" :placeholder="$t('settings.sms.url_ph')" />
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 text-xs text-ink-muted"><input type="checkbox" v-model="smsForm.data.is_default" class="rounded border-slate-300" /> {{ $t('settings.sms.default') }}</label>
          <label class="flex items-center gap-2 text-xs text-ink-muted"><input type="checkbox" v-model="smsForm.data.is_active" class="rounded border-slate-300" /> {{ $t('settings.sms.active') }}</label>
        </div>
        <div class="flex justify-end gap-2 pt-1">
          <button class="btn-secondary btn-sm" @click="smsForm.open = false">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="smsForm.saving" @click="submitSms">{{ smsForm.saving ? $t('settings.saving') : $t('settings.save') }}</button>
        </div>
      </div>
    </div>

    <!-- Org modal -->
    <div v-if="orgForm.open" class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 overflow-y-auto" @click.self="orgForm.open = false">
      <div class="card w-full max-w-md p-4 mt-8">
        <div class="text-sm font-medium text-ink dark:text-ink-dark mb-3">{{ $t(tab === 'branches' ? 'settings.branch' : 'settings.department') }}</div>
        <div class="grid grid-cols-2 gap-2.5">
          <div><label class="label">{{ $t('settings.o.name') }} *</label><input v-model="orgForm.data.name" class="input text-sm" />
            <p v-if="orgForm.errors.name" class="text-[11px] text-red-500">{{ orgForm.errors.name[0] }}</p></div>
          <div><label class="label">{{ $t('settings.o.code') }} *</label><input v-model="orgForm.data.code" class="input text-sm" />
            <p v-if="orgForm.errors.code" class="text-[11px] text-red-500">{{ orgForm.errors.code[0] }}</p></div>
          <template v-if="tab === 'branches'">
            <div><label class="label">{{ $t('settings.c.city') }}</label><input v-model="orgForm.data.city" class="input text-sm" /></div>
            <div><label class="label">{{ $t('settings.c.phone') }}</label><input v-model="orgForm.data.phone" class="input text-sm" /></div>
          </template>
          <div v-else class="col-span-2"><label class="label">{{ $t('settings.u.branch') }}</label>
            <select v-model="orgForm.data.branch_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="b in meta.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select></div>
        </div>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="orgForm.open = false">{{ $t('settings.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="orgForm.saving" @click="submitOrg">{{ orgForm.saving ? $t('settings.saving') : $t('settings.save') }}</button>
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
import { useUiStore } from '@/stores/ui';
import api from '@/services/settings';
import BillingPanel from './BillingPanel.vue';
import { Plus, X, ShieldCheck, ChevronUp, ChevronDown, Sun, Moon, Check, Pipette } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const ui = useUiStore();
const can = (p) => auth.can(p);
const APPEARANCE_ACCENTS = [
  { key: 'blue', color: '#1D6FE0', label: 'Blue' },
  { key: 'indigo', color: '#4F46E5', label: 'Indigo' },
  { key: 'emerald', color: '#059669', label: 'Emerald' },
  { key: 'violet', color: '#7C3AED', label: 'Violet' },
  { key: 'rose', color: '#E11D48', label: 'Rose' },
];
// Curated, guaranteed-legible background presets for the sidebar / top bar.
const CHROME_PRESETS = [
  { key: 'slate',    color: '#0F172A', label: 'Slate' },
  { key: 'navy',     color: '#10203E', label: 'Navy' },
  { key: 'indigo',   color: '#1E1B4B', label: 'Indigo' },
  { key: 'forest',   color: '#0B2E23', label: 'Forest' },
  { key: 'graphite', color: '#1C1F26', label: 'Graphite' },
  { key: 'plum',     color: '#2A1533', label: 'Plum' },
  { key: 'sand',     color: '#F4EFE7', label: 'Sand' },
];
const setChrome = (surface, hex) => ui.setChrome(surface, hex);
const resetChrome = (surface) => ui.resetChrome(surface);
const sameColor = (a, b) => !!a && !!b && a.toLowerCase() === b.toLowerCase();

const tabPerm = { company: 'settings.view', billing: 'settings.view', appearance: 'settings.view', branches: 'settings.view', departments: 'settings.view', users: 'users.view', roles: 'roles.view', api_keys: 'api_keys.view', webhooks: 'api_keys.view', routing: 'tickets.update', sms: 'campaigns.view' };
const allTabs = ['company', 'billing', 'appearance', 'users', 'roles', 'api_keys', 'webhooks', 'routing', 'sms', 'branches', 'departments'];
const visibleTabs = computed(() => allTabs.filter((tb) => can(tabPerm[tb])));
const canEdit = computed(() => can('settings.update'));

const tab = ref(visibleTabs.value[0] || 'company');
const company = reactive({});
const savingCompany = ref(false);
const users = ref([]);
const roles = ref([]);
const permissions = reactive({});
const branches = ref([]);
const departments = ref([]);
const meta = reactive({ roles: [], branches: [], departments: [] });
const userFilters = reactive({ q: '' });
const selectedRole = ref(null);
const editingRole = ref(false);
const savingRole = ref(false);
const roleForm = reactive({ name: '', permissions: [] });
const userForm = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });
const orgForm = reactive({ open: false, id: null, saving: false, data: {}, errors: {} });
const cloneForm = reactive({ open: false, sourceId: null, sourceName: '', name: '', saving: false, errors: {} });
const apiKeys = ref([]);
const newKeyPlain = ref('');
const apiKeyForm = reactive({ open: false, saving: false, data: { name: '', user_id: null }, errors: {} });
const webhooks = ref([]);
const newWebhook = ref(null);
const webhookForm = reactive({ open: false, saving: false, data: { name: '', type: 'web_to_lead' }, errors: {} });
const webhookEvents = reactive({ open: false, endpoint: null, events: [] });
const routingRules = ref([]);
const routingForm = reactive({ open: false, saving: false, id: null, data: {}, errors: {} });
const ROUTE_STRATEGIES = ['least_busy', 'round_robin', 'specific'];
const ROUTE_FIELDS = ['priority', 'channel', 'subject', 'category_id'];
const ROUTE_OPS = ['equals', 'not_equals', 'contains', 'is_set', 'is_empty'];
const smsProviders = ref([]);
const smsForm = reactive({ open: false, saving: false, id: null, data: {}, errors: {} });
const SMS_PROVIDERS = ['twilio', 'generic'];
const SMS_CHANNELS = ['sms', 'whatsapp'];

const orgRows = computed(() => tab.value === 'branches' ? branches.value : departments.value);

function switchTab(tb) { tab.value = tb; selectedRole.value = null; newKeyPlain.value = ''; newWebhook.value = null; loadTab(); }

async function loadTab() {
  if (tab.value === 'company') { const { data } = await api.company(); Object.assign(company, data.data); }
  else if (tab.value === 'users') await loadUsers();
  else if (tab.value === 'roles') await loadRoles();
  else if (tab.value === 'branches') { const { data } = await api.branches(); branches.value = data.data || []; }
  else if (tab.value === 'departments') { const { data } = await api.departments(); departments.value = data.data || []; }
  else if (tab.value === 'api_keys') await loadApiKeys();
  else if (tab.value === 'webhooks') await loadWebhooks();
  else if (tab.value === 'routing') { if (!users.value.length) await loadUsers(); await loadRoutingRules(); }
  else if (tab.value === 'sms') await loadSmsProviders();
}

async function loadMeta() { try { const { data } = await api.usersMeta(); Object.assign(meta, data.data || {}); } catch { /* noop */ } }

async function saveCompany() {
  savingCompany.value = true;
  try { const { data } = await api.updateCompany(company); Object.assign(company, data.data); toast.success(t('settings.saved')); }
  catch { /* noop */ } finally { savingCompany.value = false; }
}

// --- users ---
async function loadUsers() {
  try { const { data } = await api.users({ q: userFilters.q || undefined, per_page: 100 }); users.value = data.data || []; } catch { /* noop */ }
}
function openUser(u) {
  userForm.id = u?.id ?? null; userForm.errors = {};
  userForm.data = { name: u?.name ?? '', email: u?.email ?? '', password: '', phone: u?.phone ?? '',
    branch_id: u?.branch?.id ?? null, department_id: u?.department?.id ?? null,
    roles: u ? [...u.roles] : [], is_active: u?.is_active ?? true };
  userForm.open = true;
}
async function submitUser() {
  userForm.saving = true; userForm.errors = {};
  try {
    const payload = { ...userForm.data };
    if (userForm.id && !payload.password) delete payload.password;
    userForm.id ? await api.updateUser(userForm.id, payload) : await api.createUser(payload);
    toast.success(t('settings.saved'));
    userForm.open = false;
    await loadUsers();
  } catch (e) { if (e.response?.status === 422) userForm.errors = e.response.data?.errors || {}; }
  finally { userForm.saving = false; }
}
async function removeUser(id) {
  try { await api.removeUser(id); await loadUsers(); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

// --- roles ---
async function loadRoles() {
  try {
    const [r, p] = await Promise.all([api.roles(), api.permissions()]);
    roles.value = r.data.data || [];
    Object.keys(permissions).forEach((k) => delete permissions[k]);
    Object.assign(permissions, p.data.data || {});
  } catch { /* noop */ }
}
async function openRoleDetail(r) {
  editingRole.value = false;
  try { const { data } = await api.role(r.id); selectedRole.value = { ...r, ...data.data }; roleForm.name = data.data.name; roleForm.permissions = [...data.data.permissions]; }
  catch { /* noop */ }
}
function openRole() {
  selectedRole.value = { id: null, name: '', is_protected: false, permissions: [] };
  roleForm.name = ''; roleForm.permissions = []; editingRole.value = true;
}
function cancelRoleEdit() {
  editingRole.value = false;
  if (selectedRole.value?.id) roleForm.permissions = [...selectedRole.value.permissions];
  else selectedRole.value = null;
}
async function saveRole() {
  savingRole.value = true;
  try {
    const payload = { name: roleForm.name, permissions: roleForm.permissions };
    selectedRole.value.id ? await api.updateRole(selectedRole.value.id, payload) : await api.createRole(payload);
    toast.success(t('settings.saved'));
    editingRole.value = false; selectedRole.value = null;
    await loadRoles();
  } catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message || t('settings.check_fields')); }
  finally { savingRole.value = false; }
}

function openClone(r) {
  cloneForm.sourceId = r.id; cloneForm.sourceName = r.name; cloneForm.name = ''; cloneForm.errors = {};
  cloneForm.open = true;
}
async function submitClone() {
  cloneForm.saving = true; cloneForm.errors = {};
  try {
    await api.cloneRole(cloneForm.sourceId, { name: cloneForm.name });
    toast.success(t('settings.saved'));
    cloneForm.open = false;
    await loadRoles();
  } catch (e) { if (e.response?.status === 422) cloneForm.errors = e.response.data?.errors || {}; }
  finally { cloneForm.saving = false; }
}

// --- org (branches/departments) ---
function openOrg(o) {
  orgForm.id = o?.id ?? null; orgForm.errors = {};
  orgForm.data = { name: o?.name ?? '', code: o?.code ?? '', city: o?.city ?? '', phone: o?.phone ?? '', branch_id: o?.branch_id ?? null };
  orgForm.open = true;
}
async function submitOrg() {
  orgForm.saving = true; orgForm.errors = {};
  const isBranch = tab.value === 'branches';
  try {
    const create = isBranch ? api.createBranch : api.createDepartment;
    const update = isBranch ? api.updateBranch : api.updateDepartment;
    orgForm.id ? await update(orgForm.id, orgForm.data) : await create(orgForm.data);
    toast.success(t('settings.saved'));
    orgForm.open = false;
    await Promise.all([loadTab(), loadMeta()]);
  } catch (e) { if (e.response?.status === 422) orgForm.errors = e.response.data?.errors || {}; }
  finally { orgForm.saving = false; }
}
async function removeOrg(id) {
  const remove = tab.value === 'branches' ? api.removeBranch : api.removeDepartment;
  try { await remove(id); await Promise.all([loadTab(), loadMeta()]); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

// --- api keys ---
async function loadApiKeys() {
  try { const { data } = await api.apiKeys({ per_page: 100 }); apiKeys.value = data.data || []; } catch { /* noop */ }
}
async function openApiKey() {
  if (!users.value.length) await loadUsers();
  apiKeyForm.errors = {};
  apiKeyForm.data = { name: '', user_id: auth.user?.id ?? users.value[0]?.id ?? null };
  apiKeyForm.open = true;
}
async function submitApiKey() {
  apiKeyForm.saving = true; apiKeyForm.errors = {};
  try {
    const { data } = await api.createApiKey(apiKeyForm.data);
    newKeyPlain.value = data.data.plain_key;
    apiKeyForm.open = false;
    await loadApiKeys();
  } catch (e) { if (e.response?.status === 422) apiKeyForm.errors = e.response.data?.errors || {}; }
  finally { apiKeyForm.saving = false; }
}
async function revokeApiKey(k) {
  try { await api.updateApiKey(k.id, { is_active: false }); await loadApiKeys(); }
  catch { /* noop */ }
}
async function removeApiKey(id) {
  try { await api.removeApiKey(id); await loadApiKeys(); }
  catch (e) { if (e.response?.status === 422) toast.error(e.response.data?.message); }
}

async function loadWebhooks() {
  try { const { data } = await api.webhooks(); webhooks.value = data.data || []; } catch { /* noop */ }
}
function openWebhook() {
  webhookForm.errors = {};
  webhookForm.data = { name: '', type: 'web_to_lead' };
  webhookForm.open = true;
}
async function submitWebhook() {
  webhookForm.saving = true; webhookForm.errors = {};
  try {
    const { data } = await api.createWebhook(webhookForm.data);
    newWebhook.value = { name: data.data.endpoint.name, url: data.data.endpoint.url, secret: data.data.plain_secret };
    webhookForm.open = false;
    await loadWebhooks();
  } catch (e) { if (e.response?.status === 422) webhookForm.errors = e.response.data?.errors || {}; }
  finally { webhookForm.saving = false; }
}
async function toggleWebhook(w) {
  try { await api.updateWebhook(w.id, { is_active: !w.is_active }); await loadWebhooks(); } catch { /* noop */ }
}
async function removeWebhook(id) {
  if (!window.confirm(t('settings.wh.confirm_delete'))) return;
  try { await api.removeWebhook(id); await loadWebhooks(); } catch { /* noop */ }
}
async function viewWebhookEvents(w) {
  try { const { data } = await api.webhook(w.id); webhookEvents.endpoint = data.data; webhookEvents.events = data.data.events || []; webhookEvents.open = true; } catch { /* noop */ }
}
function copyText(txt, msg) {
  try { navigator.clipboard.writeText(txt); toast.success(msg); } catch { /* noop */ }
}

async function loadRoutingRules() {
  try { const { data } = await api.routingRules(); routingRules.value = data.data || []; } catch { /* noop */ }
}
function openRouting(rule) {
  routingForm.errors = {};
  routingForm.id = rule?.id ?? null;
  routingForm.data = rule
    ? { name: rule.name, priority: rule.priority, strategy: rule.strategy, assign_to_user_id: rule.assign_to_user_id,
        pool_user_ids: [...(rule.pool_user_ids || [])], conditions: (rule.conditions || []).map((c) => ({ ...c })), is_active: rule.is_active }
    : { name: '', priority: routingRules.value.length + 1, strategy: 'least_busy', assign_to_user_id: null, pool_user_ids: [], conditions: [], is_active: true };
  routingForm.open = true;
}
const fullRoutingPayload = (r, priority = r.priority) => ({
  name: r.name, strategy: r.strategy, priority,
  assign_to_user_id: r.assign_to_user_id, pool_user_ids: r.pool_user_ids, conditions: r.conditions, is_active: r.is_active,
});
function addRoutingCond() { routingForm.data.conditions.push({ field: 'priority', op: 'equals', value: '' }); }
function removeRoutingCond(i) { routingForm.data.conditions.splice(i, 1); }
function toggleRoutingPool(uid) {
  const pool = routingForm.data.pool_user_ids;
  const i = pool.indexOf(uid);
  if (i >= 0) pool.splice(i, 1); else pool.push(uid);
}
async function submitRouting() {
  routingForm.saving = true; routingForm.errors = {};
  const payload = { ...routingForm.data, conditions: (routingForm.data.conditions || []).filter((c) => c.field) };
  try {
    routingForm.id ? await api.updateRouting(routingForm.id, payload) : await api.createRouting(payload);
    routingForm.open = false;
    await loadRoutingRules();
  } catch (e) { if (e.response?.status === 422) routingForm.errors = e.response.data?.errors || {}; }
  finally { routingForm.saving = false; }
}
async function toggleRouting(r) {
  try { await api.updateRouting(r.id, { ...fullRoutingPayload(r), is_active: !r.is_active }); await loadRoutingRules(); } catch { /* noop */ }
}
async function removeRouting(id) {
  if (!window.confirm(t('settings.rt.confirm_delete'))) return;
  try { await api.removeRouting(id); await loadRoutingRules(); } catch { /* noop */ }
}
async function moveRouting(rule, dir) {
  const sorted = [...routingRules.value].sort((a, b) => a.priority - b.priority || a.id - b.id);
  const idx = sorted.findIndex((r) => r.id === rule.id);
  const swap = sorted[idx + dir];
  if (!swap) return;
  try {
    await api.updateRouting(rule.id, fullRoutingPayload(rule, swap.priority));
    await api.updateRouting(swap.id, fullRoutingPayload(swap, rule.priority));
    await loadRoutingRules();
  } catch { /* noop */ }
}
function routingTarget(r) {
  if (r.strategy === 'specific') return r.assignee?.name || users.value.find((u) => u.id === r.assign_to_user_id)?.name || '—';
  return `${r.pool_user_ids?.length || 0} ${t('settings.rt.agents')}`;
}

async function loadSmsProviders() {
  try { const { data } = await api.smsProviders(); smsProviders.value = data.data || []; } catch { /* noop */ }
}
function openSms(prov) {
  smsForm.errors = {};
  smsForm.id = prov?.id ?? null;
  smsForm.data = prov
    ? { name: prov.name, provider: prov.provider, sender_id: prov.sender_id, is_default: prov.is_default, is_active: prov.is_active,
        config: { channel: prov.config?.channel || 'sms', account_sid: prov.config?.account_sid || '', auth_token: '', url: prov.config?.url || '' } }
    : { name: '', provider: 'twilio', sender_id: '', is_default: false, is_active: true, config: { channel: 'sms', account_sid: '', auth_token: '', url: '' } };
  smsForm.open = true;
}
async function submitSms() {
  smsForm.saving = true; smsForm.errors = {};
  try {
    smsForm.id ? await api.updateSmsProvider(smsForm.id, smsForm.data) : await api.createSmsProvider(smsForm.data);
    smsForm.open = false;
    await loadSmsProviders();
  } catch (e) { if (e.response?.status === 422) smsForm.errors = e.response.data?.errors || {}; }
  finally { smsForm.saving = false; }
}
async function removeSms(id) {
  if (!window.confirm(t('settings.sms.confirm_delete'))) return;
  try { await api.removeSmsProvider(id); await loadSmsProviders(); } catch { /* noop */ }
}
async function testSms(prov) {
  const to = window.prompt(t('settings.sms.test_prompt'));
  if (!to) return;
  try {
    const { data } = await api.testSmsProvider(prov.id, { to });
    data.data?.ok ? toast.success(data.message) : toast.error(data.data?.error || data.message);
  } catch (e) { toast.error(e.response?.data?.message || 'Test failed'); }
}
async function copyKey() {
  try { await navigator.clipboard.writeText(newKeyPlain.value); toast.success(t('settings.ak.copied')); }
  catch { /* noop */ }
}

onMounted(async () => { await Promise.all([loadTab(), loadMeta()]); });
</script>
