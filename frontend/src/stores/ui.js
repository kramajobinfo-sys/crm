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
    theme: 'light', // 'light' | 'dark'
    accent: 'blue', // 'blue' | 'indigo' | 'emerald' | 'violet' | 'rose'
    // Per-surface background overrides (hex string, or null = use theme default).
    chrome: { topbar: null, sidebar: null },
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

    setAccent(accent) {
      this.accent = accent;
      this.applyAccent();
    },

    applyAccent() {
      document.documentElement.setAttribute('data-accent', this.accent || 'blue');
    },

    // ---- Customizable chrome (topbar / sidebar backgrounds) ----------------
    setChrome(surface, hex) {
      this.chrome = { ...this.chrome, [surface]: hex || null };
      this.applyChrome();
    },

    resetChrome(surface) {
      if (surface) this.chrome = { ...this.chrome, [surface]: null };
      else this.chrome = { topbar: null, sidebar: null };
      this.applyChrome();
    },

    applyChrome() {
      const root = document.documentElement;
      const c = this.chrome || {};
      applyTopbar(root, c.topbar);
      applySidebar(root, c.sidebar);
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
    paths: ['theme', 'accent', 'chrome', 'sidebarCollapsed', 'locale'],
  },
});
