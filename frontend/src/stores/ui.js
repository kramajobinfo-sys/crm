import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
  state: () => ({
    theme: 'light', // 'light' | 'dark'
    sidebarCollapsed: false,
    sidebarMobileOpen: false,
    locale: 'en',
  }),

  actions: {
    toggleTheme() {
      this.theme = this.theme === 'light' ? 'dark' : 'light';
      this.applyTheme();
    },

    setTheme(theme) {
      this.theme = theme;
      this.applyTheme();
    },

    applyTheme() {
      const root = document.documentElement;
      if (this.theme === 'dark') {
        root.classList.add('dark');
      } else {
        root.classList.remove('dark');
      }
    },

    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed;
    },

    toggleMobileSidebar() {
      this.sidebarMobileOpen = !this.sidebarMobileOpen;
    },

    closeMobileSidebar() {
      this.sidebarMobileOpen = false;
    },

    setLocale(locale) {
      this.locale = locale;
      const dir = locale === 'ar' ? 'rtl' : 'ltr';
      document.documentElement.setAttribute('dir', dir);
      document.documentElement.setAttribute('lang', locale);
    },
  },

  persist: {
    paths: ['theme', 'sidebarCollapsed', 'locale'],
  },
});
