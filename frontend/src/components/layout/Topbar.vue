<template>
  <header class="app-topbar h-13 flex items-center gap-2 px-4 border-b backdrop-blur-sm shrink-0">
    <button class="md:hidden btn-ghost btn-icon btn-sm" :aria-label="$t('nav.open_menu')" @click="ui.toggleMobileSidebar()">
      <Menu :size="18" />
    </button>

    <!-- Global search -->
    <div class="flex-1 max-w-lg relative">
      <div class="topbar-search flex items-center gap-2 rounded-lg h-9 px-3 border border-transparent focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/25 transition-all">
        <Search :size="15" class="text-ink-subtle dark:text-ink-dark-subtle shrink-0" />
        <input
          ref="searchInput"
          v-model="q"
          @input="onSearch"
          @focus="showResults = true"
          @blur="setTimeout(() => showResults = false, 200)"
          :placeholder="$t('app.search_placeholder')"
          class="bg-transparent border-0 outline-none focus:ring-0 text-[13px] w-full p-0 text-ink dark:text-ink-dark"
        />
        <span class="hidden sm:flex items-center gap-0.5 shrink-0"><kbd class="kbd">Ctrl</kbd><kbd class="kbd">K</kbd></span>
      </div>
      <div v-if="showResults && Object.keys(results).length" class="absolute z-30 top-full mt-2 left-0 right-0 card shadow-pop p-1.5 max-h-96 overflow-auto">
        <template v-for="(items, type) in results" :key="type">
          <div class="section-label px-2 py-1.5">{{ type }}</div>
          <a
            v-for="r in items" :key="`${type}-${r.id}`"
            class="block px-2 py-1.5 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 cursor-pointer"
            @mousedown.prevent="openResult(type)"
          >
            <div class="text-[13px] font-medium text-ink dark:text-ink-dark">{{ r.title }}</div>
            <div v-if="r.subtitle" class="text-xs text-ink-subtle">{{ r.subtitle }}</div>
          </a>
        </template>
      </div>
    </div>

    <div class="flex-1" />

    <!-- Quick create -->
    <div v-if="quickCreateItems.length" ref="createMenu" class="relative">
      <button class="btn-primary btn-sm" @click="showCreate = !showCreate" :aria-expanded="showCreate">
        <Plus :size="15" /> <span class="hidden sm:inline">{{ $t('app.create') }}</span>
        <ChevronDown :size="14" class="hidden sm:inline -mr-1 opacity-80" />
      </button>
      <div v-if="showCreate" class="absolute right-0 top-full mt-2 w-52 card shadow-pop p-1.5 z-30">
        <div class="section-label px-2 py-1.5">{{ $t('app.create_new') }}</div>
        <button
          v-for="item in quickCreateItems" :key="item.name"
          class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-lg text-[13px] text-ink dark:text-ink-dark hover:bg-primary-50 dark:hover:bg-primary-900/20"
          @click="quickCreate(item)"
        >
          <component :is="item.icon" :size="15" class="text-ink-subtle" /> {{ $t(item.label) }}
        </button>
      </div>
    </div>

    <div class="topbar-divider w-px h-6 mx-1" />

    <!-- Language -->
    <div ref="themeMenu" class="relative">
      <button class="btn-ghost btn-icon btn-sm" @click="showTheme = !showTheme" :title="$t('theme.title')" :aria-expanded="showTheme">
        <Palette :size="17" />
      </button>
      <div v-if="showTheme" class="absolute right-0 top-full mt-2 w-56 card shadow-pop p-3 z-30 space-y-3">
        <div>
          <div class="section-label mb-1.5">{{ $t('theme.mode') }}</div>
          <div class="grid grid-cols-2 gap-1.5">
            <button class="btn-secondary btn-sm justify-center" :class="ui.theme === 'light' && '!border-primary-500 !text-primary-600'" @click="ui.setTheme('light')"><Sun :size="14" /> {{ $t('theme.light') }}</button>
            <button class="btn-secondary btn-sm justify-center" :class="ui.theme === 'dark' && '!border-primary-500 !text-primary-600'" @click="ui.setTheme('dark')"><Moon :size="14" /> {{ $t('theme.dark') }}</button>
          </div>
        </div>
        <div>
          <div class="section-label mb-1.5">{{ $t('theme.accent') }}</div>
          <div class="flex items-center gap-2.5">
            <button v-for="a in ACCENTS" :key="a.key" type="button"
                    class="w-7 h-7 rounded-full ring-2 ring-offset-2 ring-offset-white dark:ring-offset-surface-dark-muted flex items-center justify-center transition"
                    :class="ui.accent === a.key ? 'ring-current' : 'ring-transparent hover:ring-slate-300'"
                    :style="{ backgroundColor: a.color, color: a.color }" :title="a.label" @click="ui.setAccent(a.key)">
              <Check v-if="ui.accent === a.key" :size="14" class="text-white" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Notifications -->
    <div class="relative">
      <button class="btn-ghost btn-icon btn-sm relative" @click="toggleNotifications">
        <Bell :size="17" />
        <span v-if="notifications.unreadCount" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white dark:ring-surface-dark-muted" />
      </button>
      <div v-if="showNotifs" class="absolute right-0 top-full mt-2 w-80 card shadow-pop p-3 z-20">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">{{ $t('notifications.title') }}</span>
          <button v-if="notifications.unreadCount" @click="notifications.markAllRead()" class="text-xs text-primary-600">
            {{ $t('notifications.mark_all_read') }}
          </button>
        </div>
        <div v-if="!notifications.items.length" class="text-xs text-ink-subtle py-6 text-center">
          {{ $t('notifications.empty') }}
        </div>
        <div v-else class="max-h-72 overflow-auto -mx-1">
          <div
            v-for="n in notifications.items" :key="n.id"
            class="px-2 py-2 rounded hover:bg-slate-100 dark:hover:bg-surface-dark-subtle text-xs cursor-pointer"
            @click="openNotification(n)"
          >
            <div :class="!n.read && 'font-medium'">{{ n.data?.message || n.type }}</div>
            <div class="text-ink-subtle mt-0.5">{{ n.created }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- User menu -->
    <div class="relative">
      <button class="avatar avatar-md bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 ring-2 ring-transparent hover:ring-primary-200 dark:hover:ring-primary-800 transition"
              @click="showUser = !showUser">
        <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-full h-full object-cover" alt="" />
        <span v-else>{{ auth.initials }}</span>
      </button>
      <div v-if="showUser" class="absolute right-0 top-full mt-2 w-56 card shadow-pop p-2 z-20 text-sm">
        <div class="px-2 py-2 border-b border-slate-100 dark:border-slate-700">
          <div class="font-medium text-ink dark:text-ink-dark truncate">{{ auth.user?.name }}</div>
          <div class="text-xs text-ink-subtle truncate">{{ auth.user?.email }}</div>
          <div v-if="auth.roles.length" class="mt-1 flex flex-wrap gap-1">
            <span v-for="r in auth.roles" :key="r" class="badge-info">{{ r }}</span>
          </div>
        </div>
        <button class="w-full text-left px-2 py-1.5 rounded hover:bg-slate-100 dark:hover:bg-surface-dark-subtle"
                @click="goProfile()">{{ $t('profile.menu_profile') }}</button>
        <button class="w-full text-left px-2 py-1.5 rounded hover:bg-slate-100 dark:hover:bg-surface-dark-subtle"
                @click="goProfile('password')">{{ $t('profile.menu_password') }}</button>
        <button class="w-full text-left px-2 py-1.5 rounded text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20"
                @click="logout">
          {{ $t('profile.menu_signout') }}
        </button>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import http from '@/services/http';
import { Menu, Search, Moon, Sun, Bell, Plus, ChevronDown, Palette, Check, Users, UserCheck, Contact, PieChart } from 'lucide-vue-next';

const ui = useUiStore();
const auth = useAuthStore();
const notifications = useNotificationsStore();
const router = useRouter();

const q = ref('');
const results = ref({});
const showResults = ref(false);
const showNotifs = ref(false);
const showUser = ref(false);
const showCreate = ref(false);
const showTheme = ref(false);
const searchInput = ref(null);
const createMenu = ref(null);
const themeMenu = ref(null);
const ACCENTS = [
  { key: 'blue', color: '#1D6FE0', label: 'Blue' },
  { key: 'indigo', color: '#4F46E5', label: 'Indigo' },
  { key: 'emerald', color: '#059669', label: 'Emerald' },
  { key: 'violet', color: '#7C3AED', label: 'Violet' },
  { key: 'rose', color: '#E11D48', label: 'Rose' },
];

// Global quick-create — routes to the module list with ?create=1 (pages auto-open their form).
const quickCreateItems = computed(() => [
  { name: 'leads',    label: 'nav.leads',    icon: Users,     permission: 'leads.create' },
  { name: 'accounts', label: 'nav.accounts', icon: UserCheck, permission: 'customers.create' },
  { name: 'contacts', label: 'nav.contacts', icon: Contact,   permission: 'contacts.create' },
  { name: 'deals',    label: 'nav.deals',    icon: PieChart,  permission: 'deals.create' },
].filter((i) => auth.can(i.permission)));

const quickCreate = (item) => {
  showCreate.value = false;
  router.push({ name: item.name, query: { create: 1 } });
};

let searchTimer;
let notificationTimer;
const onSearch = () => {
  clearTimeout(searchTimer);
  if (!q.value || q.value.length < 2) { results.value = {}; return; }
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await http.get('/search', { params: { q: q.value } });
      results.value = data.data;
    } catch { results.value = {}; }
  }, 250);
};

const toggleNotifications = async () => {
  showNotifs.value = !showNotifs.value;
  if (showNotifs.value) await notifications.fetch();
};

const openNotification = async (notification) => {
  if (!notification.read) await notifications.markRead(notification.id);
  showNotifs.value = false;
  if (notification.data?.url) await router.push(notification.data.url);
};

const logout = async () => {
  await auth.logout();
  router.push({ name: 'login' });
};

const goProfile = (hash) => {
  showUser.value = false;
  router.push({ name: 'profile', hash: hash ? `#${hash}` : undefined });
};

const openResult = (type) => {
  const routeNames = { leads: 'leads', customers: 'accounts', contacts: 'contacts', deals: 'deals', tickets: 'helpdesk' };
  const name = routeNames[type];
  if (!name) return;
  showResults.value = false;
  router.push({ name });
};

const onKeydown = (e) => {
  if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
    e.preventDefault();
    searchInput.value?.focus();
    showResults.value = true;
  }
};
const onDocClick = (e) => {
  if (createMenu.value && !createMenu.value.contains(e.target)) showCreate.value = false;
  if (themeMenu.value && !themeMenu.value.contains(e.target)) showTheme.value = false;
};

onMounted(() => {
  notifications.fetch();
  notificationTimer = setInterval(() => notifications.fetch(), 60_000);
  window.addEventListener('keydown', onKeydown);
  document.addEventListener('pointerdown', onDocClick);
});

onUnmounted(() => {
  clearTimeout(searchTimer);
  clearInterval(notificationTimer);
  window.removeEventListener('keydown', onKeydown);
  document.removeEventListener('pointerdown', onDocClick);
});
</script>
