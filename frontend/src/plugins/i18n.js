import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import ar from '@/locales/ar.json';

const savedLocale = (() => {
  try {
    const raw = localStorage.getItem('ui');
    if (raw) return JSON.parse(raw)?.locale || 'en';
  } catch { /* ignore */ }
  return 'en';
})();

export default createI18n({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'en',
  messages: { en, ar },
});
