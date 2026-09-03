<template>
  <header class="h-13 py-3 flex items-center gap-3 px-4 border-b border-slate-200 dark:border-slate-700
                 bg-white dark:bg-surface-dark-muted shrink-0">
    <button class="md:hidden btn-ghost p-1" :aria-label="$t('nav.open_menu')" @click="ui.toggleMobileSidebar()">
      <Menu :size="18" />
    </button>

    <!-- Global search -->
    <div class="flex-1 max-w-md relative">
      <div class="flex items-center gap-2 bg-slate-100 dark:bg-surface-dark-subtle rounded-md px-3 py-1.5">
        <Search :size="14" class="text-ink-subtle dark:text-ink-dark-subtle" />
        <input
          v-model="q"
          @input="onSearch"
          @focus="showResults = true"
          @blur="setTimeout(() => showResults = false, 200)"
          :placeholder="$t('app.search_placeholder')"
          class="bg-transparent border-0 outline-none focus:ring-0 text-xs w-full p-0"
        />
      </div>
      <div v-if="showResults && Object.keys(results).length" class="absolute z-30 top-full mt-1 left-0 right-0 card p-2 max-h-80 overflow-auto">
        <template v-for="(items, type) in results" :key="type">
          <div class="text-[10px] uppercase tracking-wider text-ink-subtle dark:text-ink-dark-subtle px-2 py-1">{{ type }}</div>
          <a
            v-for="r in items" :key="`${type}-${r.id}`"
            class="block px-2 py-1.5 rounded hover:bg-slate-100 dark:hover:bg-surface-dark-subtle cursor-pointer"
            @mousedown.prevent="openResult(type)"
          >
            <div class="text-sm text-ink dark:text-ink-dark">{{ r.title }}</div>
            <div v-if="r.subtitle" class="text-xs text-ink-subtle">{{ r.subtitle }}</div>
          </a>
        </template>
      </div>
    </div>

    <div class="flex-1" />

    <!-- Language -->
    <button class="btn-ghost p-1.5" @click="toggleLocale" :title="ui.locale === 'en' ? 'العربية' : 'English'">
      <Languages :size="17" />
    </button>

    <!-- Theme -->
    <button class="btn-ghost p-1.5" @click="ui.toggleTheme()" :title="ui.theme === 'light' ? 'Dark mode' : 'Light mode'">
      <Moon v-if="ui.theme === 'light'" :size="17" />
      <Sun v-else :size="17" />
    </button>

    <!-- Notifications -->
    <div class="relative">
      <button class="btn-ghost p-1.5" @click="toggleNotifications">
        <Bell :size="17" />
        <span v-if="notifications.unreadCount" class="absolute top-0.5 right-0.5 w-2 h-2 rounded-full bg-rose-500" />
      </button>
      <div v-if="showNotifs" class="absolute right-0 top-full mt-2 w-80 card p-3 z-20">
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
      <button class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center
                     text-xs font-medium dark:bg-primary-900/40 dark:text-primary-300 overflow-hidden"
              @click="showUser = !showUser">
        <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-full h-full object-cover" alt="" />
        <span v-else>{{ auth.initials }}</span>
      </button>
      <div v-if="showUser" class="absolute right-0 top-full mt-2 w-56 card p-2 z-20 text-sm">
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
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUiStore } from '@/stores/ui';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import http from '@/services/http';
import { Menu, Search, Languages, Moon, Sun, Bell } from 'lucide-vue-next';

const ui = useUiStore();
const auth = useAuthStore();
const notifications = useNotificationsStore();
const router = useRouter();

const q = ref('');
const results = ref({});
const showResults = ref(false);
const showNotifs = ref(false);
const showUser = ref(false);

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

const toggleLocale = () => {
  ui.setLocale(ui.locale === 'en' ? 'ar' : 'en');
  // vue-i18n hot-swap
  const i18nEl = document.querySelector('html');
  i18nEl.setAttribute('lang', ui.locale);
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

onMounted(() => {
  notifications.fetch();
  notificationTimer = setInterval(() => notifications.fetch(), 60_000);
});

onUnmounted(() => {
  clearTimeout(searchTimer);
  clearInterval(notificationTimer);
});
</script>
