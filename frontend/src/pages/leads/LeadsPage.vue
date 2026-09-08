<template>
  <div class="page">

    <div class="page-header">
      <div class="min-w-0">
        <nav class="breadcrumb"><span>{{ $t('nav.ws_crm') }}</span><span class="opacity-50">›</span><span class="text-ink-muted dark:text-ink-dark-muted font-medium">{{ $t('leads.title') }}</span></nav>
        <h1 class="page-title">{{ $t('leads.title') }}</h1>
        <p class="page-sub">{{ $t('leads.subtitle') }}</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn-secondary btn-sm" :disabled="loading" @click="load">
          <RefreshCw :size="14" :class="loading && 'animate-spin'" /> {{ $t('leads.refresh') }}
        </button>
        <button v-if="can('leads.create')" class="btn-primary btn-sm" @click="openCreate">
          <Plus :size="14" /> {{ $t('leads.new') }}
        </button>
      </div>
    </div>

    <!-- Stat tiles -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
      <button
        v-for="s in statTiles" :key="s.key"
        class="stat stat-clickable"
        :class="isActiveTile(s) && 'stat-active'"
        @click="applyTile(s)"
      >
        <span class="stat-label">{{ $t(s.label) }}</span>
        <span class="stat-value text-xl">
          {{ s.key === 'pipeline_value' ? compact(stats[s.key]) : (stats[s.key] ?? 0) }}
        </span>
      </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="toolbar">
        <div class="relative">
          <Search :size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-subtle pointer-events-none" />
          <input v-model="filters.q" class="input input-sm w-64 pl-8" :placeholder="$t('leads.search')" @keyup.enter="load" />
        </div>
        <select v-model="filters.status_id" class="input input-sm w-auto" @change="load">
          <option value="">{{ $t('leads.all_statuses') }}</option>
          <option v-for="s in meta.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="filters.source_id" class="input input-sm w-auto" @change="load">
          <option value="">{{ $t('leads.all_sources') }}</option>
          <option v-for="s in meta.sources" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="filters.rating" class="input input-sm w-auto" @change="load">
          <option value="">{{ $t('leads.all_ratings') }}</option>
          <option v-for="r in meta.ratings" :key="r" :value="r">{{ $t(`leads.rating.${r}`) }}</option>
        </select>
        <select v-model="filters.priority" class="input input-sm w-auto" @change="load">
          <option value="">All priorities</option>
          <option v-for="p in (meta.priorities || [])" :key="p" :value="p" class="capitalize">{{ p }}</option>
        </select>
        <select v-model="filters.follow_up" class="input input-sm w-auto" @change="load">
          <option value="">All follow-ups</option>
          <option value="overdue">Follow-up overdue</option>
          <option value="today">Follow-up today</option>
        </select>
        <label class="chip cursor-pointer" :class="filters.owner_id === 'me' && '!bg-primary-50 !text-primary-700 dark:!bg-primary-900/25 dark:!text-primary-300'">
          <input type="checkbox" class="rounded border-line-strong w-3.5 h-3.5" :checked="filters.owner_id === 'me'"
                 @change="filters.owner_id = $event.target.checked ? 'me' : ''; load()" />
          {{ $t('leads.mine_only') }}
        </label>
        <button class="btn-ghost btn-sm ml-auto" @click="resetFilters">{{ $t('leads.reset') }}</button>
      </div>
    </div>

    <div class="flex gap-3">
      <!-- List -->
      <div class="panel flex-1 min-w-0">
        <div v-if="loading" class="text-sm text-ink-subtle py-16 text-center">{{ $t('app.loading') }}</div>
        <div v-else-if="!rows.length" class="empty">
          <div class="empty-icon"><Search :size="18" /></div>
          <div class="text-sm text-ink-muted dark:text-ink-dark-muted">{{ $t('leads.empty') }}</div>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('leads.col.score') }}</th>
                <th>{{ $t('leads.col.name') }}</th>
                <th class="hidden md:table-cell">{{ $t('leads.col.status') }}</th>
                <th class="hidden lg:table-cell">{{ $t('leads.col.source') }}</th>
                <th class="th-num hidden lg:table-cell">{{ $t('leads.col.value') }}</th>
                <th class="hidden lg:table-cell">{{ $t('leads.col.owner') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in rows" :key="r.id"
                class="cursor-pointer"
                :class="selected?.id === r.id && 'is-selected'"
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
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-3 py-2.5 border-t border-line dark:border-line-dark text-xs">
          <span class="text-ink-subtle dark:text-ink-dark-subtle">{{ $t('leads.showing', { from: pagination.from, to: pagination.to, total: pagination.total }) }}</span>
          <div class="flex gap-1.5">
            <button class="btn-secondary btn-icon btn-sm" :disabled="page <= 1" @click="page--; load()">‹</button>
            <button class="btn-secondary btn-icon btn-sm" :disabled="page >= pagination.last_page" @click="page++; load()">›</button>
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
          <button v-if="can('leads.update')" class="btn-secondary btn-xs" @click="openEdit(selected)">
            {{ $t('leads.edit') }}
          </button>
          <button v-if="can('activities.create')" class="btn-secondary btn-xs" @click="addFollowUp(selected)">{{ $t('activities.quick_follow_up') }}</button>
          <button class="p-1 text-ink-subtle hover:text-ink" @click="selected = null"><X :size="14" /></button>
        </div>

        <!-- Converted banner, else the convert action -->
        <div v-if="selected.is_converted"
             class="px-3 py-2 text-[11px] bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300 shrink-0">
          {{ $t('leads.converted_to', { no: selected.customer?.customer_no || '—' }) }}
        </div>
        <div v-else-if="can('leads.convert')" class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 shrink-0 flex gap-2">
          <button class="btn-primary btn-xs" :disabled="converting" @click="openConvert">
            <UserPlus :size="11" /> {{ converting ? $t('leads.converting') : $t('leads.convert') }}
          </button>
          <button v-if="can('leads.assign')" class="btn-secondary btn-xs" @click="doAutoAssign">
            {{ $t('leads.auto_assign') }}
          </button>
        </div>

        <!-- Relationship tabs -->
        <div class="px-3 pt-1.5 flex gap-1 border-b border-slate-200 dark:border-slate-700 shrink-0 overflow-x-auto">
          <button v-for="tb in detailTabs" :key="tb.key" @click="detailTab = tb.key"
                  class="px-2 py-1.5 -mb-px border-b-2 whitespace-nowrap text-[11px]"
                  :class="detailTab === tb.key ? 'border-primary-500 text-primary-600 font-medium' : 'border-transparent text-ink-subtle hover:text-ink'">
            {{ tb.label }}<span v-if="tb.count" class="ml-1 opacity-70">{{ tb.count }}</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-3 text-xs">
          <!-- ===== OVERVIEW ===== -->
          <div v-if="detailTab === 'overview'" class="space-y-3">
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
              <dt class="text-ink-subtle">Source campaign</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.campaign?.name || '—' }}</dd>
              <dt class="text-ink-subtle">{{ $t('leads.col.owner') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.owner?.name || $t('leads.unassigned') }}</dd>
              <dt class="text-ink-subtle">{{ $t('leads.col.value') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark tabular-nums">{{ money(selected.estimated_value, selected.currency) }}</dd>
              <dt class="text-ink-subtle">{{ $t('leads.last_contact') }}</dt>
              <dd class="col-span-2 text-ink dark:text-ink-dark">{{ selected.last_contacted_human || $t('leads.never') }}</dd>
            </dl>

            <!-- Products of interest -->
            <div v-if="selected.products?.length">
              <div class="text-[10px] tracking-wider text-ink-subtle mb-1">Products of interest</div>
              <div v-for="p in selected.products" :key="p.product_id" class="flex items-center gap-1.5 py-0.5">
                <span class="text-ink dark:text-ink-dark truncate flex-1">{{ p.name }}</span>
                <span v-if="p.quantity" class="text-ink-subtle shrink-0">×{{ p.quantity }}</span>
              </div>
            </div>

            <!-- Custom fields (admin-defined) -->
            <div v-if="detailCustomFields.length">
              <div class="text-[10px] tracking-wider text-ink-subtle mb-1">Custom fields</div>
              <dl class="grid grid-cols-3 gap-x-2 gap-y-0.5">
                <template v-for="cf in detailCustomFields" :key="cf.key">
                  <dt class="text-ink-subtle">{{ cf.label }}</dt>
                  <dd class="col-span-2 text-ink dark:text-ink-dark break-words">
                    <span v-if="cf.type === 'checkbox'">{{ cf.value ? 'Yes' : 'No' }}</span>
                    <a v-else-if="cf.type === 'url'" :href="cf.value" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ cf.value }}</a>
                    <span v-else>{{ cf.value }}</span>
                  </dd>
                </template>
              </dl>
            </div>

            <!-- Communication consent -->
            <div v-if="leadConsents">
              <div class="text-[10px] tracking-wider text-ink-subtle mb-1">Communication consent</div>
              <div v-for="c in leadConsents.current" :key="c.channel" class="flex items-center gap-2 py-0.5">
                <span class="text-ink dark:text-ink-dark flex-1">{{ channelLabel(c.channel) }}</span>
                <span class="badge" :class="c.can_receive ? 'badge-success' : (c.status === 'withdrawn' ? 'badge-danger' : 'badge-neutral')">
                  {{ c.can_receive ? 'Reachable' : (c.status === 'withdrawn' ? 'Opted out' : 'Opt-in required') }}
                </span>
                <button v-if="can('leads.update')" class="btn-ghost btn-xs shrink-0" :disabled="consentBusy" @click="toggleConsent(c)">
                  {{ c.can_receive ? 'Opt out' : 'Opt in' }}
                </button>
              </div>
            </div>

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
          </div>

          <!-- ===== OPPORTUNITIES ===== -->
          <div v-else-if="detailTab === 'opportunities'">
            <div v-if="!selected.deals?.length" class="text-ink-subtle py-4 text-center">No opportunities yet. Convert this lead or add a deal to see it here.</div>
            <div v-for="d in selected.deals" :key="d.id" class="flex items-center gap-1.5 py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <span class="text-ink dark:text-ink-dark truncate flex-1">{{ d.title }}</span>
              <span v-if="d.stage" class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-ink-muted shrink-0">{{ d.stage.name }}</span>
              <span class="tabular-nums text-ink-muted shrink-0">{{ money(d.amount, d.currency) }}</span>
            </div>
          </div>

          <!-- ===== QUOTES (via this lead's opportunities) ===== -->
          <div v-else-if="detailTab === 'quotes'">
            <div v-if="quotesLoading" class="text-ink-subtle py-4 text-center">Loading…</div>
            <div v-else-if="!leadQuotes.length" class="text-ink-subtle py-4 text-center">No quotes on this lead's opportunities.</div>
            <div v-for="q in leadQuotes" :key="q.id" class="flex items-center gap-1.5 py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <span class="font-mono text-[11px] text-ink dark:text-ink-dark shrink-0">{{ q.quote_no }}</span>
              <span class="text-ink-muted truncate flex-1">{{ q.deal?.title || '—' }}</span>
              <span class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-ink-muted capitalize shrink-0">{{ q.status }}</span>
              <span class="tabular-nums text-ink-muted shrink-0">{{ money(q.grand_total, q.currency) }}</span>
            </div>
          </div>

          <!-- ===== CAMPAIGNS (marketing-list memberships) ===== -->
          <div v-else-if="detailTab === 'campaigns'">
            <div v-if="can('leads.update')" class="flex gap-1.5 mb-2">
              <select v-model.number="newMembership.campaign_id" class="input text-xs flex-1">
                <option :value="null">Add to campaign…</option>
                <option v-for="c in availableCampaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <select v-model="newMembership.status" class="input text-xs w-auto capitalize">
                <option v-for="s in (meta.campaign_member_statuses || ['member','contacted','responded'])" :key="s" :value="s">{{ s }}</option>
              </select>
              <button class="btn-primary text-[11px] px-2" :disabled="!newMembership.campaign_id || campaignBusy" @click="addMembership">
                <Plus :size="11" />
              </button>
            </div>
            <div v-if="campaignsLoading" class="text-ink-subtle py-4 text-center">Loading…</div>
            <div v-else-if="!leadCampaigns.length" class="text-ink-subtle py-4 text-center">Not a member of any campaign yet.</div>
            <div v-for="m in leadCampaigns" :key="m.campaign_id" class="flex items-center gap-1.5 py-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
              <span class="text-ink dark:text-ink-dark truncate flex-1">{{ m.name }}</span>
              <span v-if="m.type" class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-ink-muted uppercase shrink-0">{{ m.type }}</span>
              <select v-if="can('leads.update')" v-model="m.status" class="input input-xs w-auto capitalize shrink-0" :disabled="campaignBusy" @change="updateMembershipStatus(m)">
                <option v-for="s in (meta.campaign_member_statuses || ['member','contacted','responded'])" :key="s" :value="s">{{ s }}</option>
              </select>
              <span v-else class="text-ink-subtle capitalize shrink-0">{{ m.status }}</span>
              <button v-if="can('leads.update')" class="p-1 text-ink-subtle hover:text-red-500 shrink-0" :disabled="campaignBusy" @click="removeMembership(m)"><X :size="12" /></button>
            </div>
          </div>

          <!-- ===== ACTIVITIES (tasks / calls / emails) ===== -->
          <div v-else-if="detailTab === 'activities'">
            <div v-if="activitiesLoading" class="text-ink-subtle py-4 text-center">Loading…</div>
            <div v-else-if="!activityItems.length" class="text-ink-subtle py-4 text-center">No activities logged.</div>
            <div v-for="a in activityItems" :key="a.kind + a.id" class="flex items-center gap-1.5 py-0.5">
              <span class="text-[9px] px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-ink-muted capitalize shrink-0">{{ a.kind }}</span>
              <span class="text-ink dark:text-ink-dark truncate flex-1">{{ a.title }}</span>
              <span class="text-ink-subtle shrink-0 capitalize">{{ a.status }}</span>
              <span class="text-ink-subtle ml-1 shrink-0">{{ a.when_human }}</span>
            </div>
          </div>

          <!-- ===== EVENTS (meetings) ===== -->
          <div v-else-if="detailTab === 'events'">
            <div v-if="activitiesLoading" class="text-ink-subtle py-4 text-center">Loading…</div>
            <div v-else-if="!eventItems.length" class="text-ink-subtle py-4 text-center">No meetings or events scheduled.</div>
            <div v-for="a in eventItems" :key="a.kind + a.id" class="flex items-center gap-1.5 py-0.5">
              <span class="text-[9px] px-1 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 shrink-0">event</span>
              <span class="text-ink dark:text-ink-dark truncate flex-1">{{ a.title }}</span>
              <span v-if="a.location" class="text-ink-subtle shrink-0 truncate max-w-[6rem]">{{ a.location }}</span>
              <span class="text-ink-subtle ml-1 shrink-0">{{ a.when_human }}</span>
            </div>
          </div>

          <!-- ===== TIMELINE ===== -->
          <div v-else-if="detailTab === 'timeline'">
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
          <button class="btn-secondary btn-sm" @click="conversion.open = false">{{ $t('leads.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="converting || conversion.loading_options" @click="submitConversion">
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
            <label class="label">Campaign</label>
            <select v-model="form.data.campaign_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="c in (meta.campaigns || [])" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Account (existing)</label>
            <select v-model="form.data.account_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="a in customerOptions" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Territory</label>
            <input v-model="form.data.territory" class="input text-sm" placeholder="e.g. North" />
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
          <div>
            <label class="label">Priority</label>
            <select v-model="form.data.priority" class="input text-sm">
              <option v-for="p in (meta.priorities || [])" :key="p" :value="p" class="capitalize">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="label">Follow-up date</label>
            <input v-model="form.data.follow_up_at" type="date" class="input text-sm" />
          </div>
          <div class="sm:col-span-2">
            <label class="label">Next action</label>
            <input v-model="form.data.next_action" class="input text-sm" placeholder="e.g. Call back, send proposal" />
          </div>
          <div v-if="isLostStatus" class="sm:col-span-2">
            <label class="label">Loss reason</label>
            <select v-model="form.data.lost_reason_id" class="input text-sm">
              <option :value="null">—</option>
              <option v-for="lr in (meta.lost_reasons || [])" :key="lr.id" :value="lr.id">{{ lr.name }}</option>
            </select>
          </div>
        </div>

        <!-- Products of interest -->
        <div class="mt-3">
          <div class="flex items-center mb-1">
            <span class="text-[10px] tracking-wider text-ink-subtle">Products of interest</span>
            <button type="button" class="text-[11px] text-primary-600 hover:underline ml-auto" @click="addLeadProduct">+ Add product</button>
          </div>
          <div v-for="(row, i) in (form.data.products || [])" :key="i" class="flex items-center gap-1.5 mb-1">
            <select v-model.number="row.product_id" class="input input-sm flex-1">
              <option :value="null">Select product…</option>
              <option v-for="p in productOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-model.number="row.quantity" type="number" min="0" placeholder="Qty" class="input input-sm w-20" />
            <button type="button" class="btn-ghost btn-xs" @click="form.data.products.splice(i, 1)"><X :size="12" /></button>
          </div>
          <p v-if="!(form.data.products || []).length" class="text-[11px] text-ink-subtle">None yet — what does this lead want to buy?</p>
        </div>

        <!-- Custom fields (admin-defined) -->
        <div v-if="(meta.custom_fields || []).length" class="mt-3 pt-3 border-t border-line dark:border-line-dark grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div v-for="cf in meta.custom_fields" :key="cf.key" :class="cf.type === 'textarea' ? 'sm:col-span-2' : ''">
            <label class="label">{{ cf.label }}<span v-if="cf.required" class="text-rose-500"> *</span></label>
            <textarea v-if="cf.type === 'textarea'" v-model="form.data.custom_fields[cf.key]" rows="2" class="input text-sm"></textarea>
            <select v-else-if="cf.type === 'select'" v-model="form.data.custom_fields[cf.key]" class="input text-sm">
              <option value="">—</option>
              <option v-for="o in (cf.options || [])" :key="o" :value="o">{{ o }}</option>
            </select>
            <label v-else-if="cf.type === 'checkbox'" class="flex items-center gap-2 text-sm h-8">
              <input type="checkbox" v-model="form.data.custom_fields[cf.key]" /> {{ cf.help || 'Yes' }}
            </label>
            <input v-else v-model="form.data.custom_fields[cf.key]"
                   :type="cf.type === 'number' ? 'number' : cf.type === 'date' ? 'date' : cf.type === 'email' ? 'email' : cf.type === 'url' ? 'url' : 'text'"
                   class="input text-sm" />
            <p v-if="cf.help && cf.type !== 'checkbox'" class="text-[11px] text-ink-subtle mt-0.5">{{ cf.help }}</p>
          </div>
        </div>

        <p class="text-[11px] text-ink-subtle mt-2">{{ $t('leads.assign_hint') }}</p>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn-secondary btn-sm" @click="form.open = false">{{ $t('leads.cancel') }}</button>
          <button class="btn-primary btn-sm" :disabled="form.saving" @click="submitForm">
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
import http from '@/services/http';
import customerApi from '@/services/customers';
import dealApi from '@/services/deals';
import duplicateApi from '@/services/duplicates';
import DuplicateWarningModal from '@/components/crm/DuplicateWarningModal.vue';
import RecordMergeModal from '@/components/crm/RecordMergeModal.vue';
import { useDuplicateGuard } from '@/composables/useDuplicateGuard';
import { useRecordMerge } from '@/composables/useRecordMerge';
import { RefreshCw, Plus, X, Send, Paperclip, UserPlus, CheckCircle2, Search } from 'lucide-vue-next';

const toast = useToast();
const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const can = (p) => auth.can(p);

function addFollowUp(lead) {
  router.push({ name: 'activities', query: { new: 'task', related_type: 'lead', related_id: lead.id } });
}

const statTiles = [
  { key: 'open',       label: 'leads.stat.open',       set: { converted: 'open', rating: '', owner_id: '' } },
  { key: 'hot',        label: 'leads.stat.hot',        set: { converted: 'open', rating: 'hot', owner_id: '' } },
  { key: 'warm',       label: 'leads.stat.warm',       set: { converted: 'open', rating: 'warm', owner_id: '' } },
  { key: 'unassigned', label: 'leads.stat.unassigned', set: { converted: 'open', rating: '', owner_id: 'unassigned' } },
  { key: 'converted',  label: 'leads.stat.converted',  set: { converted: 'converted', rating: '', owner_id: '' } },
  { key: 'pipeline_value', label: 'leads.stat.pipeline', set: null },
];

const rows       = ref([]);
const stats      = reactive({});
const meta       = reactive({ sources: [], statuses: [], ratings: [], priorities: [], lost_reasons: [], campaigns: [], custom_fields: [], campaign_member_statuses: [] });
const productOptions = ref([]);
const customerOptions = ref([]);
function addLeadProduct() { (form.data.products ||= []).push({ product_id: null, quantity: null }); }
const pagination = reactive({ last_page: 1, from: 0, to: 0, total: 0 });
const selected   = ref(null);
const loading    = ref(false);
const converting = ref(false);
const page       = ref(1);
const noteDraft  = ref('');
const noteType   = ref('note');
const savingNote = ref(false);
const fileInput  = ref(null);
const filters    = reactive({ q: '', converted: 'open', status_id: '', source_id: '', rating: '', priority: '', follow_up: '', owner_id: '' });
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

const isActiveTile = (s) => s.set && filters.converted === s.set.converted
  && (filters.rating || '') === (s.set.rating || '')
  && (filters.owner_id || '') === (s.set.owner_id || '');

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
  // Products for the "products of interest" picker — optional (needs products.view).
  try { const { data } = await http.get('/products', { params: { per_page: 200 } }); productOptions.value = data.data || []; }
  catch { productOptions.value = []; }
  // Customers for the "existing account" picker — optional (needs customers.view).
  try { const { data } = await http.get('/customers', { params: { per_page: 200 } }); customerOptions.value = data.data || []; }
  catch { customerOptions.value = []; }
}

const leadActivities = ref([]);
const activitiesLoading = ref(false);
async function loadLeadActivities(id) {
  activitiesLoading.value = true; leadActivities.value = [];
  try { const { data } = await http.get(`/leads/${id}/activities`); leadActivities.value = data.data || []; }
  catch { leadActivities.value = []; }
  finally { activitiesLoading.value = false; }
}
// Split the merged activity feed: meetings surface under Events, everything else under Activities.
const activityItems = computed(() => leadActivities.value.filter((a) => a.kind !== 'meeting'));
const eventItems = computed(() => leadActivities.value.filter((a) => a.kind === 'meeting'));

// Quotes raised on this lead's opportunities (Lead → deals → quotations).
const leadQuotes = ref([]);
const quotesLoading = ref(false);
async function loadLeadQuotes(id) {
  quotesLoading.value = true; leadQuotes.value = [];
  try { const { data } = await http.get(`/leads/${id}/quotes`); leadQuotes.value = data.data || []; }
  catch { leadQuotes.value = []; }
  finally { quotesLoading.value = false; }
}

// Campaign memberships (a lead can belong to many marketing campaigns).
const leadCampaigns = ref([]);
const campaignsLoading = ref(false);
const campaignBusy = ref(false);
const newMembership = reactive({ campaign_id: null, status: 'member' });
async function loadLeadCampaigns(id) {
  campaignsLoading.value = true; leadCampaigns.value = [];
  try { const { data } = await http.get(`/leads/${id}/campaigns`); leadCampaigns.value = data.data || []; }
  catch { leadCampaigns.value = []; }
  finally { campaignsLoading.value = false; }
}
async function addMembership() {
  if (!newMembership.campaign_id || !selected.value) return;
  campaignBusy.value = true;
  try {
    const { data } = await http.post(`/leads/${selected.value.id}/campaigns`, { ...newMembership });
    leadCampaigns.value = data.data || [];
    newMembership.campaign_id = null; newMembership.status = 'member';
  } catch { /* interceptor surfaces the error */ }
  finally { campaignBusy.value = false; }
}
async function updateMembershipStatus(m) {
  campaignBusy.value = true;
  try {
    const { data } = await http.post(`/leads/${selected.value.id}/campaigns`, { campaign_id: m.campaign_id, status: m.status });
    leadCampaigns.value = data.data || [];
  } catch { /* noop */ }
  finally { campaignBusy.value = false; }
}
async function removeMembership(m) {
  campaignBusy.value = true;
  try {
    const { data } = await http.delete(`/leads/${selected.value.id}/campaigns/${m.campaign_id}`);
    leadCampaigns.value = data.data || [];
  } catch { /* noop */ }
  finally { campaignBusy.value = false; }
}
// Campaigns not already joined, for the add picker.
const availableCampaigns = computed(() => {
  const joined = new Set(leadCampaigns.value.map((m) => m.campaign_id));
  return (meta.campaigns || []).filter((c) => !joined.has(c.id));
});

// Relationship tabs on the lead detail drawer.
const detailTab = ref('overview');
const detailTabs = computed(() => [
  { key: 'overview', label: 'Overview' },
  { key: 'opportunities', label: 'Opportunities', count: selected.value?.deals?.length || 0 },
  { key: 'quotes', label: 'Quotes', count: leadQuotes.value.length },
  { key: 'campaigns', label: 'Campaigns', count: leadCampaigns.value.length },
  { key: 'activities', label: 'Activities', count: activityItems.value.length },
  { key: 'events', label: 'Events', count: eventItems.value.length },
  { key: 'timeline', label: 'Timeline' },
]);

const leadConsents = ref(null);
const consentBusy = ref(false);
async function loadLeadConsents(id) {
  try { const { data } = await http.get(`/leads/${id}/consents`); leadConsents.value = data.data; }
  catch { leadConsents.value = null; }
}
async function toggleConsent(c) {
  if (!can('leads.update')) return;
  consentBusy.value = true;
  try {
    const status = c.can_receive ? 'withdrawn' : 'granted';
    const { data } = await http.post(`/leads/${selected.value.id}/consents`, { channel: c.channel, status });
    leadConsents.value = data.data;
  } catch { /* interceptor surfaces the error */ }
  finally { consentBusy.value = false; }
}
const channelLabel = (ch) => ({ email: 'Email', sms: 'SMS', phone: 'Phone', whatsapp: 'WhatsApp', marketing: 'Marketing' }[ch] || ch);

async function openDetail(id) {
  try {
    const { data } = await api.show(id);
    selected.value = data.data;
    detailTab.value = 'overview';
    loadLeadActivities(id);
    loadLeadConsents(id);
    loadLeadQuotes(id);
    loadLeadCampaigns(id);
  } catch { /* interceptor surfaces the error */ }
}

function applyTile(s) {
  if (!s.set) return;               // pipeline value is a figure, not a filter
  Object.assign(filters, s.set);
  page.value = 1;
  load();
}
function resetFilters() {
  Object.assign(filters, { q: '', converted: 'open', status_id: '', source_id: '', rating: '', priority: '', follow_up: '', owner_id: '' });
  page.value = 1; load();
}

// Seed a custom_fields object from the definitions, overlaying any existing stored values.
function seedCustomFields(existing = {}) {
  const out = {};
  for (const cf of (meta.custom_fields || [])) {
    const v = existing?.[cf.key];
    out[cf.key] = v !== undefined && v !== null ? v : (cf.type === 'checkbox' ? false : '');
  }
  return out;
}

function openCreate() {
  form.id = null; form.errors = {};
  form.data = { name: '', company_name: '', title: '', email: '', phone: '',
                source_id: null, campaign_id: null, account_id: null, territory: '', status_id: null, estimated_value: 0,
                priority: 'medium', follow_up_at: '', next_action: '', lost_reason_id: null, products: [],
                custom_fields: seedCustomFields() };
  form.open = true;
}
function openEdit(l) {
  form.id = l.id; form.errors = {};
  form.data = {
    name: l.name, company_name: l.company_name ?? '', title: l.title ?? '',
    email: l.email ?? '', phone: l.phone ?? '',
    source_id: l.source?.id ?? null, campaign_id: l.campaign_id ?? null, account_id: l.account_id ?? null,
    territory: l.territory ?? '', status_id: l.status?.id ?? null,
    estimated_value: l.estimated_value ?? 0,
    priority: l.priority ?? 'medium', follow_up_at: l.follow_up_at ? l.follow_up_at.slice(0, 10) : '',
    next_action: l.next_action ?? '', lost_reason_id: l.lost_reason_id ?? null,
    products: (l.products || []).map((p) => ({ product_id: p.product_id, quantity: p.quantity })),
    custom_fields: seedCustomFields(l.custom_fields),
  };
  form.open = true;
}

// Populated custom fields for the detail drawer: definition label/type + the stored value.
const detailCustomFields = computed(() => {
  const stored = selected.value?.custom_fields || {};
  return (meta.custom_fields || [])
    .map((cf) => ({ ...cf, value: stored[cf.key] }))
    .filter((cf) => cf.value !== undefined && cf.value !== null && cf.value !== '');
});

// The loss-reason picker only makes sense when the chosen status is a "lost" one.
const isLostStatus = computed(() => {
  const s = meta.statuses.find((x) => x.id === form.data.status_id);
  return !!s?.is_lost;
});

async function submitForm(force = false) {
  form.saving = true; form.errors = {};
  try {
    const payload = { ...form.data };
    ['company_name','title','email','phone','follow_up_at','next_action'].forEach((k) => { if (!payload[k]) delete payload[k]; });
    if (!isLostStatus.value) payload.lost_reason_id = null; // clear a stale reason when not lost
    payload.products = (payload.products || []).filter((p) => p.product_id); // drop empty rows
    if (payload.custom_fields) { // drop blank optionals so the server doesn't coerce '' → 0 etc.
      payload.custom_fields = Object.fromEntries(
        Object.entries(payload.custom_fields).filter(([, v]) => v !== '' && v !== null && v !== undefined));
    }
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

onMounted(async () => { await Promise.all([load(), loadAux()]); if (router.currentRoute.value.query.create) openCreate(); });
</script>
