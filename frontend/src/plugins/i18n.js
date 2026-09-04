import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';

// English-only for now. Messages are PRE-COMPILED at build by
// @intlify/unplugin-vue-i18n (see vite.config.js) so vue-i18n needs no runtime
// eval -> the CSP stays strict (script-src 'self'). Add a locale later by
// importing its JSON and adding it to `messages`.
export default createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en },
});
