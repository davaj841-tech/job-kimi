/**
 * Multi-language foundation (Phase 8 Step 10).
 * Currently Persian-only; swap messages and wire vue-i18n later.
 */
const messages = {
  fa: {
    app_name: 'جاب‌آزمون',
    search_placeholder: 'جستجو...',
    support: 'پشتیبانی',
    leaderboard: 'رتبه‌بندی',
  },
  en: {
    app_name: 'JobAzmoon',
    search_placeholder: 'Search...',
    support: 'Support',
    leaderboard: 'Leaderboard',
  },
};

let locale = localStorage.getItem('locale') || 'fa';

export function t(key) {
  return messages[locale]?.[key] || messages.fa[key] || key;
}

export function setLocale(next) {
  locale = next === 'en' ? 'en' : 'fa';
  localStorage.setItem('locale', locale);
}

export function getLocale() {
  return locale;
}

export default { t, setLocale, getLocale, messages };
