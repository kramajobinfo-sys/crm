import { defineStore } from 'pinia';

// ---- Chrome color math -----------------------------------------------------
// Derive a full, legible surface palette from a single user-chosen background,
// so any color the user picks stays readable (auto light/dark foreground).
const CHROME_TOPBAR_VARS = ['--c-topbar-bg', '--c-topbar-fg', '--c-topbar-border', '--c-topbar-muted', '--c-topbar-field-bg'];
const CHROME_SIDEBAR_VARS = [
  '--c-sidebar-bg', '--c-sidebar-fg', '--c-sidebar-fg-strong', '--c-sidebar-subtle',
  '--c-sidebar-border', '--c-sidebar-hover-bg', '--c-sidebar-active-bg',
  '--c-sidebar-active-fg', '--c-sidebar-active-bar', '--c-sidebar-brand-fg',
];

function hexToRgb(hex) {
  const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(String(hex || '').trim());
  return m ? { r: parseInt(m[1], 16), g: parseInt(m[2], 16), b: parseInt(m[3], 16) } : null;
}

// Perceived luminance (sRGB) → true when the background is light.
function isLight({ r, g, b }) {
  const lin = (v) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; };
  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b) > 0.45;
}

// Mix a color toward white or black by `amt` (0..1) — for hover/border tints.
function shade({ r, g, b }, amt) {
  const t = amt < 0 ? 0 : 255; const p = Math.abs(amt);
  return `rgb(${Math.round(r + (t - r) * p)} ${Math.round(g + (t - g) * p)} ${Math.round(b + (t - b) * p)})`;
}
const rgba = ({ r, g, b }, a) => `rgb(${r} ${g} ${b} / ${a})`;

function clearVars(root, vars) { vars.forEach((v) => root.style.removeProperty(v)); }

function applyTopbar(root, hex) {
  const rgb = hexToRgb(hex);
  if (!rgb) { clearVars(root, CHROME_TOPBAR_VARS); return; }
  const light = isLight(rgb);
  const fg = light ? '#101828' : '#F4F6FA';
  root.style.setProperty('--c-topbar-bg', hex);
  root.style.setProperty('--c-topbar-fg', fg);
  root.style.setProperty('--c-topbar-muted', light ? 'rgb(16 24 40 / 0.65)' : 'rgb(244 246 250 / 0.72)');
  root.style.setProperty('--c-topbar-border', light ? shade(rgb, -0.10) : shade(rgb, 0.14));
  root.style.setProperty('--c-topbar-field-bg', light ? shade(rgb, -0.05) : shade(rgb, 0.12));
}

function applySidebar(root, hex) {
  const rgb = hexToRgb(hex);
  if (!rgb) { clearVars(root, CHROME_SIDEBAR_VARS); return; }
  const light = isLight(rgb);
  const strong = light ? { r: 16, g: 24, b: 40 } : { r: 244, g: 246, b: 250 };
  root.style.setProperty('--c-sidebar-bg', hex);
  root.style.setProperty('--c-sidebar-fg', rgba(strong, 0.72));
  root.style.setProperty('--c-sidebar-fg-strong', rgba(strong, 1));
  root.style.setProperty('--c-sidebar-subtle', rgba(strong, 0.5));
  root.style.setProperty('--c-sidebar-brand-fg', rgba(strong, 1));
  root.style.setProperty('--c-sidebar-border', rgba(strong, 0.14));
  root.style.setProperty('--c-sidebar-hover-bg', rgba(strong, 0.08));
  root.style.setProperty('--c-sidebar-active-bg', rgba(strong, 0.14));
  root.style.setProperty('--c-sidebar-active-fg', rgba(strong, 1));
  // Keep the selected-item accent bar tied to the app accent for continuity.
  root.style.setProperty('--c-sidebar-active-bar', 'rgb(var(--p-500))');
}

export const useUiStore = defineStore('ui', {
  state: () => ({
    theme: 'light', // 'light' | 'dark'   (personal)
    accent: 'blue', // 'blue' | 'indigo' | 'emerald' | 'violet' | 'rose'   (personal)
    // Per-surface personal background overrides (hex string, or null = use theme default).
    chrome: { topbar: null, sidebar: null },
    // Set true once the user personalizes anything — lets a company default apply only until then.
    userCustomized: false,
    // Company-wide appearance from the server: { theme, accent, chrome:{sidebar,topbar}, enforced }.
    // Not persisted — reloaded from /auth/me each session.
    company: null,
    // True while a company appearance is enforced (personal controls are then inert).
    managed: false,
    sidebarCollapsed: false,
    sidebarMobileOpen: false,
    locale: 'en',
  }),

  getters: {
    // The appearance actually shown, after company vs personal precedence.
    effective(state) {
      const def = { theme: 'light', accent: 'blue', chrome: { topbar: null, sidebar: null } };
      const c = state.company;
      if (c && c.enforced) {
        return { theme: c.theme || def.theme, accent: c.accent || def.accent, chrome: c.chrome || def.chrome, managed: true };
      }
      if (c && !state.userCustomized) {
        return { theme: c.theme || def.theme, accent: c.accent || def.accent, chrome: c.chrome || def.chrome, managed: false };
      }
      return { theme: state.theme, accent: state.accent, chrome: state.chrome || def.chrome, managed: false };
    },
  },

  actions: {
    // ---- Apply the effective appearance to the DOM -------------------------
    applyAll() {
      const e = this.effective;
      this.managed = e.managed;
      const root = document.documentElement;
      root.classList.toggle('dark', e.theme === 'dark');
      root.setAttribute('data-accent', e.accent || 'blue');
      applyTopbar(root, e.chrome?.topbar);
      applySidebar(root, e.chrome?.sidebar);
    },
    // Back-compat aliases — everything routes through applyAll now.
    applyTheme() { this.applyAll(); },
    applyAccent() { this.applyAll(); },
    applyChrome() { this.applyAll(); },

    // Company appearance arrives with the authenticated user (auth store -> /auth/me).
    setCompanyAppearance(appearance) {
      this.company = appearance || null;
      this.applyAll();
    },

    toggleTheme() { this.setTheme(this.theme === 'light' ? 'dark' : 'light'); },

    setTheme(theme) {
      this.theme = theme;
      this.userCustomized = true;
      this.applyAll();
    },

    setAccent(accent) {
      this.accent = accent;
      this.userCustomized = true;
      this.applyAll();
    },

    // ---- Customizable chrome (topbar / sidebar backgrounds) ----------------
    setChrome(surface, hex) {
      this.chrome = { ...this.chrome, [surface]: hex || null };
      this.userCustomized = true;
      this.applyAll();
    },

    resetChrome(surface) {
      if (surface) this.chrome = { ...this.chrome, [surface]: null };
      else this.chrome = { topbar: null, sidebar: null };
      this.userCustomized = true;
      this.applyAll();
    },

    // Discard personal customization and fall back to the company default (or app default).
    resetToCompany() {
      this.userCustomized = false;
      this.theme = 'light';
      this.accent = 'blue';
      this.chrome = { topbar: null, sidebar: null };
      this.applyAll();
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
    paths: ['theme', 'accent', 'chrome', 'userCustomized', 'sidebarCollapsed', 'locale'],
  },
});
