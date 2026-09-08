<template>
  <aside
    class="app-sidebar flex-col border-r transition-all duration-200 shrink-0"
    :class="mobile ? 'fixed inset-y-0 left-0 z-50 flex w-72 md:hidden' : ['hidden md:flex', ui.sidebarCollapsed ? 'w-16' : 'w-60']"
  >
    <div class="h-13 flex items-center gap-2.5 px-3.5 border-b sidebar-border shrink-0">
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 text-white flex items-center justify-center shrink-0 shadow-sm">
        <Building2 :size="17" />
      </div>
      <div v-if="expanded" class="min-w-0 flex-1">
        <div class="sidebar-strong text-[13px] font-semibold truncate leading-tight">{{ auth.company?.name || $t('app.name') }}</div>
        <div class="sidebar-subtle text-2xs truncate">{{ $t('nav.business_platform') }}</div>
      </div>
      <button v-if="mobile" class="btn-ghost p-1 md:hidden" :aria-label="$t('nav.close_menu')" @click="ui.closeMobileSidebar()">
        <X :size="16" />
      </button>
    </div>

    <!-- The route is the source of truth, so direct links select the right workspace. -->
    <div ref="workspaceSwitcher" class="relative p-2 border-b sidebar-border">
      <button
        type="button"
        class="sidebar-tile w-full flex items-center gap-2.5 rounded-lg px-2.5 h-10 text-left border transition-colors"
        :class="!expanded && 'justify-center'"
        :title="!expanded ? $t(currentWorkspace.label) : ''"
        :aria-expanded="workspaceOpen"
        @click="workspaceOpen = !workspaceOpen"
      >
        <component :is="currentWorkspace.icon" :size="17" class="shrink-0 text-primary-600 dark:text-primary-400" />
        <span v-if="expanded" class="sidebar-strong text-[13px] font-semibold truncate flex-1">{{ $t(currentWorkspace.label) }}</span>
        <ChevronDown v-if="expanded" :size="15" class="sidebar-subtle transition-transform" :class="workspaceOpen && 'rotate-180'" />
      </button>

      <div
        v-if="workspaceOpen"
        class="absolute z-50 card p-1.5 shadow-pop"
        :class="expanded ? 'left-2 right-2 top-full mt-1' : 'left-full top-2 ml-2 w-52'"
      >
        <div class="px-2 py-1 section-label">{{ $t('nav.switch_workspace') }}</div>
        <button
          v-for="workspace in visibleWorkspaces"
          :key="workspace.id"
          type="button"
          class="w-full flex items-center gap-2 rounded px-2 py-2 text-left text-xs hover:bg-slate-100 dark:hover:bg-surface-dark-subtle"
          :class="workspace.id === currentWorkspace.id && 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'"
          @click="openWorkspace(workspace)"
        >
          <component :is="workspace.icon" :size="15" class="shrink-0" />
          <span class="truncate">{{ $t(workspace.label) }}</span>
          <Check v-if="workspace.id === currentWorkspace.id" :size="13" class="ml-auto shrink-0" />
        </button>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
      <div v-if="expanded" class="section-label sidebar-subtle px-3 pb-1.5 pt-1">
        {{ $t(currentWorkspace.label) }}
      </div>
      <router-link
        v-for="item in currentWorkspace.items"
        :key="item.name"
        :to="{ name: item.name }"
        v-slot="{ href, isActive, navigate }"
        custom
      >
        <a
          :href="href"
          class="nav-item"
          :class="[isActive && 'nav-item-active', !expanded && 'justify-center']"
          :title="!expanded ? $t(item.label) : ''"
          @click="navigateToItem($event, navigate)"
        >
          <component :is="item.icon" :size="16" class="shrink-0" />
          <span v-if="expanded" class="truncate">{{ $t(item.label) }}</span>
        </a>
      </router-link>
    </nav>

    <button
      v-if="!mobile"
      class="sidebar-foot h-10 flex items-center justify-center gap-2 border-t sidebar-border transition-colors"
      :title="ui.sidebarCollapsed ? $t('nav.expand') : $t('nav.collapse')"
      @click="ui.toggleSidebar()"
    >
      <ChevronsLeft v-if="!ui.sidebarCollapsed" :size="16" />
      <ChevronsRight v-else :size="16" />
    </button>
  </aside>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import {
  BarChart3, Bot, BriefcaseBusiness, Building2, Cable, Check, CheckSquare, ChevronDown, ChevronsLeft,
  ChevronsRight, Contact, Factory, FileText, FolderOpen, Headphones, LayoutDashboard,
  Mail, Megaphone, MessagesSquare, Package, PieChart, Plug, ReceiptText, Settings,
  ShieldCheck, ShoppingCart, Tag, TrendingUp, Upload, UserCheck, UserCog, Users, Workflow, X,
} from 'lucide-vue-next';

const props = defineProps({ mobile: { type: Boolean, default: false } });
const ui = useUiStore();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const workspaceOpen = ref(false);
const workspaceSwitcher = ref(null);
const expanded = computed(() => props.mobile || !ui.sidebarCollapsed);

const workspaces = [
  {
    id: 'home', label: 'nav.ws_home', icon: LayoutDashboard,
    items: [
      { name: 'dashboard', label: 'nav.dashboard', icon: LayoutDashboard, permission: 'dashboard.view', module: 'dashboard' },
      { name: 'my-work', label: 'nav.my_work', icon: CheckSquare, permission: 'dashboard.view', module: 'dashboard' },
      { name: 'activities', label: 'nav.activities', icon: CheckSquare, permission: 'activities.view', module: 'activities' },
      { name: 'documents', label: 'nav.documents', icon: FolderOpen, permission: 'documents.view', module: 'documents' },
      { name: 'ai', label: 'nav.ai', icon: Bot, permission: 'ai.use', module: 'ai' },
    ],
  },
  {
    id: 'crm', label: 'nav.ws_crm', icon: Contact,
    items: [
      { name: 'leads', label: 'nav.leads', icon: Users, permission: 'leads.view', module: 'leads' },
      { name: 'accounts', label: 'nav.accounts', icon: UserCheck, permission: 'customers.view', module: 'customers' },
      { name: 'contacts', label: 'nav.contacts', icon: Contact, permission: 'contacts.view', module: 'contacts' },
      { name: 'duplicates', label: 'nav.duplicates', icon: Users, permission: 'customers.view', module: 'customers' },
      { name: 'import', label: 'nav.import', icon: Upload, permission: 'leads.create', module: 'leads' },
      { name: 'deals', label: 'nav.deals', icon: PieChart, permission: 'deals.view', module: 'deals' },
      { name: 'email', label: 'nav.email', icon: Mail, permission: 'email.view', module: 'email' },
      { name: 'marketing', label: 'nav.marketing', icon: Megaphone, permission: 'campaigns.view', module: 'campaigns' },
    ],
  },
  {
    id: 'projects', label: 'nav.ws_projects', icon: BriefcaseBusiness,
    items: [
      { name: 'projects', label: 'nav.projects', icon: BriefcaseBusiness, permission: 'projects.view', module: 'projects' },
    ],
  },
  {
    id: 'inbox', label: 'nav.ws_inbox', icon: MessagesSquare,
    items: [
      { name: 'inbox', label: 'nav.inbox', icon: MessagesSquare, permission: 'chat.view', module: 'chat' },
      { name: 'inbox-settings', label: 'nav.inbox_settings', icon: Cable, permission: 'chat.manage_channels', module: 'chat' },
    ],
  },
  {
    id: 'service', label: 'nav.ws_service', icon: Headphones,
    items: [
      { name: 'helpdesk', label: 'nav.helpdesk', icon: Headphones, permission: 'tickets.view', module: 'tickets' },
      { name: 'knowledge', label: 'nav.knowledge', icon: FileText, permission: 'kb.view', module: 'kb' },
    ],
  },
  {
    id: 'operations', label: 'nav.ws_operations', icon: Package,
    items: [
      { name: 'sales', label: 'nav.sales', icon: FileText, permission: 'quotations.view', module: 'quotations' },
      { name: 'price-books', label: 'nav.price_books', icon: Tag, permission: 'price_books.view', module: 'price_books' },
      { name: 'credits', label: 'nav.credits', icon: ReceiptText, permission: 'credits.view', module: 'credits' },
      { name: 'purchase', label: 'nav.purchase', icon: ShoppingCart, permission: 'purchase_orders.view', module: 'purchase_orders' },
      { name: 'inventory', label: 'nav.inventory', icon: Package, permission: 'inventory.view', module: 'inventory' },
      { name: 'manufacturing', label: 'nav.manufacturing', icon: Factory, permission: 'manufacturing.view', module: 'manufacturing' },
    ],
  },
  {
    id: 'people', label: 'nav.ws_people', icon: UserCog,
    items: [{ name: 'hr', label: 'nav.hr', icon: UserCog, permission: 'employees.view', module: 'employees' }],
  },
  {
    id: 'analytics', label: 'nav.ws_analytics', icon: BarChart3,
    items: [
      { name: 'reports', label: 'nav.reports', icon: BarChart3, permission: 'reports.view', module: 'reports' },
      { name: 'forecasts', label: 'nav.forecasts', icon: TrendingUp, permission: 'forecasts.view', module: 'forecasts' },
    ],
  },
  {
    id: 'integrations', label: 'nav.ws_integrations', icon: Plug,
    items: [{ name: 'dynamics', label: 'nav.dynamics', icon: Plug, permission: 'integrations.view', module: 'integrations' }],
  },
  {
    id: 'admin', label: 'nav.ws_admin', icon: Settings,
    items: [
      { name: 'workflows', label: 'nav.workflows', icon: Workflow, permission: 'workflows.view', module: 'workflows' },
      { name: 'settings', label: 'nav.settings', icon: Settings, permission: 'settings.view', module: 'settings' },
    ],
  },
  {
    id: 'platform', label: 'nav.ws_platform', icon: ShieldCheck, platformOnly: true,
    items: [{ name: 'platform', label: 'nav.platform', icon: ShieldCheck }],
  },
];

const visibleWorkspaces = computed(() => workspaces
  .filter((workspace) => !workspace.platformOnly || auth.isPlatformAdmin)
  .map((workspace) => ({
    ...workspace,
    items: workspace.items.filter((item) => (!item.permission || auth.can(item.permission)) && auth.hasModule(item.module)),
  }))
  .filter((workspace) => workspace.items.length));

const currentWorkspace = computed(() => {
  const routeName = String(route.name || '');
  return visibleWorkspaces.value.find((workspace) => workspace.items.some((item) => item.name === routeName))
    || visibleWorkspaces.value[0]
    || workspaces[0];
});

async function openWorkspace(workspace) {
  workspaceOpen.value = false;
  const destination = workspace.items[0];
  if (destination && route.name !== destination.name) await router.push({ name: destination.name });
  if (props.mobile) ui.closeMobileSidebar();
}

function navigateToItem(event, navigate) {
  navigate(event);
  workspaceOpen.value = false;
  if (props.mobile) ui.closeMobileSidebar();
}

function closeWorkspaceOnOutsideClick(event) {
  if (workspaceSwitcher.value && !workspaceSwitcher.value.contains(event.target)) workspaceOpen.value = false;
}

watch(() => route.name, () => { workspaceOpen.value = false; });
onMounted(() => document.addEventListener('pointerdown', closeWorkspaceOnOutsideClick));
onUnmounted(() => document.removeEventListener('pointerdown', closeWorkspaceOnOutsideClick));
</script>
