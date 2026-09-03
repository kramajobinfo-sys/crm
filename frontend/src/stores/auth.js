import { defineStore } from 'pinia';
import authService from '@/services/auth';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null,
    user: null,
    requires2fa: false,
    twoFactorVerified: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    isPlatformAdmin: (state) => !!state.user?.is_platform_admin,
    roles:           (state) => state.user?.roles ?? [],
    permissions:     (state) => state.user?.permissions ?? [],
    company:         (state) => state.user?.company ?? null,
    branch:          (state) => state.user?.branch ?? null,
    planModules:     (state) => state.user?.company?.plan?.modules ?? null,
    firstName:       (state) => (state.user?.name || '').split(' ')[0],
    initials: (state) => {
      const name = state.user?.name || '';
      return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase();
    },
  },

  actions: {
    can(permission) {
      if (this.isPlatformAdmin) return true;
      if (Array.isArray(permission)) {
        return permission.some((p) => this.permissions.includes(p));
      }
      return this.permissions.includes(permission);
    },

    hasRole(role) {
      return this.roles.includes(role);
    },

    hasModule(module) {
      if (this.isPlatformAdmin || !module) return true;
      // Older persisted sessions do not contain plan metadata. Keep them usable until
      // AppLayout refreshes /auth/me, then enforce the current tenant entitlement.
      if (!Array.isArray(this.planModules)) return true;
      return this.planModules.includes(module);
    },

    async login(email, password, remember = false) {
      const { data } = await authService.login({ email, password, remember });
      const payload = data.data;
      this.token = payload.access_token;
      this.user = payload.user;
      this.requires2fa = payload.requires_2fa;
      this.twoFactorVerified = !payload.requires_2fa;
      return payload;
    },

    async register(fields) {
      const { data } = await authService.register(fields);
      const payload = data.data;
      this.token = payload.access_token;
      this.user = payload.user;
      this.requires2fa = payload.requires_2fa;
      this.twoFactorVerified = true;
      return payload;
    },

    setUser(user) { this.user = user; },

    async refresh() {
      const { data } = await authService.refresh();
      this.token = data.data.access_token;
      return this.token;
    },

    async loadMe() {
      const { data } = await authService.me();
      this.user = data.data;
    },

    async logout() {
      try { await authService.logout(); } catch { /* ignore */ }
      this.clear();
    },

    async verifyTwoFactor(code) {
      // The server hands back a NEW token carrying the verified second-factor claim; the
      // pending one we logged in with stays pending forever and is rejected by every gated
      // route, so it has to be replaced rather than kept alongside a local boolean.
      const { data } = await authService.twoFactor.verify(code);
      const payload = data.data;
      if (payload?.access_token) this.token = payload.access_token;
      this.twoFactorVerified = true;
    },

    /**
     * Turning 2FA ON. The server enables it and hands back a token already carrying the
     * verified claim — without swapping it in, the token we hold predates
     * two_factor_enabled becoming true and every gated route would start 403ing
     * immediately after a successful setup.
     */
    async confirmTwoFactor(code) {
      const { data } = await authService.twoFactor.confirm(code);
      const payload = data.data;
      if (payload?.access_token) this.token = payload.access_token;
      this.requires2fa = true;
      this.twoFactorVerified = true;
      await this.loadMe();
    },

    async disableTwoFactor(password) {
      await authService.twoFactor.disable(password);
      this.requires2fa = false;
      this.twoFactorVerified = false;
      await this.loadMe();
    },

    clear() {
      this.token = null;
      this.user = null;
      this.requires2fa = false;
      this.twoFactorVerified = false;
    },
  },

  persist: {
    paths: ['token', 'user', 'requires2fa', 'twoFactorVerified'],
  },
});
