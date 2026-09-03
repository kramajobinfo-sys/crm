import { defineStore } from 'pinia';
import portalService from '@/services/portal';

// A separate store (own id, own persisted keys) from stores/auth.js on purpose: a portal
// Contact has no roles/permissions/company concepts, and sharing the staff store's localStorage
// keys would let a portal login silently clobber (or be clobbered by) a staff session.
export const usePortalAuthStore = defineStore('portalAuth', {
  state: () => ({
    token: null,
    contact: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.contact,
  },

  actions: {
    async login(email, password) {
      const { data } = await portalService.login({ email, password });
      this.token = data.data.access_token;
      this.contact = data.data.contact;
      return data.data;
    },

    async loadMe() {
      const { data } = await portalService.me();
      this.contact = data.data;
    },

    async logout() {
      try { await portalService.logout(); } catch { /* ignore */ }
      this.clear();
    },

    clear() {
      this.token = null;
      this.contact = null;
    },
  },

  persist: {
    key: 'portalAuth',
    paths: ['token', 'contact'],
  },
});
