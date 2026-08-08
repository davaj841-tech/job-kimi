<template>
  <div class="px-4 py-4">
    <!-- Profile header card -->
    <div class="card-soft mb-4 overflow-hidden">
      <div class="bg-gradient-to-l from-desk-dark to-desk-blue px-4 py-5 text-white">
        <div class="flex items-center gap-3">
          <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white/15 text-2xl font-black">
            {{ initials }}
          </div>
          <div class="min-w-0">
            <p class="truncate text-lg font-bold">{{ auth.user?.name || 'کاربر جاب‌آزمون' }}</p>
            <p class="mt-0.5 text-xs text-white/70" dir="ltr">
              {{ auth.user?.username ? '@' + auth.user.username : auth.user?.mobile }}
            </p>
            <p v-if="auth.user?.province" class="mt-1 text-xs text-white/80">📍 {{ auth.user.province }}</p>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-3 divide-x divide-surface-line">
        <div class="p-3 text-center">
          <p class="text-[11px] text-ink-muted">💰 موجودی</p>
          <p class="mt-1 text-xs font-black text-brand">{{ formatPrice(auth.user?.wallet_balance) }}</p>
        </div>
        <div class="p-3 text-center">
          <p class="text-[11px] text-ink-muted">⭐ اشتراک</p>
          <p class="mt-1 text-xs font-black">{{ auth.user?.subscription_plan?.name || 'رایگان' }}</p>
        </div>
        <div class="p-3 text-center">
          <p class="text-[11px] text-ink-muted">✅ وضعیت</p>
          <p class="mt-1 text-xs font-black" :class="auth.user?.is_verified ? 'text-emerald-600' : 'text-amber-600'">
            {{ auth.user?.is_verified ? 'تایید شده' : 'در انتظار' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Editable details -->
    <form class="card-soft mb-4 space-y-3 p-4 text-sm" @submit.prevent="saveProfile">
      <div>
        <label class="mb-1 block text-xs text-ink-muted">👤 نام</label>
        <input v-model="form.name" class="input-field" placeholder="نام و نام خانوادگی" />
      </div>
      <div class="flex items-center justify-between">
        <span class="text-ink-muted">📱 موبایل</span>
        <span class="font-medium" dir="ltr">{{ auth.user?.mobile || '—' }}</span>
      </div>
      <div>
        <label class="mb-1 block text-xs text-ink-muted">📧 ایمیل</label>
        <input v-model="form.email" type="email" class="input-field text-left" dir="ltr" placeholder="you@example.com" />
      </div>
      <div>
        <label class="mb-1 block text-xs text-ink-muted">📍 استان *</label>
        <select v-model="form.province" class="input-field" required>
          <option value="">انتخاب استان</option>
          <option v-for="p in IRAN_PROVINCES" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>
      <p v-if="profileMsg" class="text-xs text-emerald-600">{{ profileMsg }}</p>
      <p v-if="profileError" class="text-xs text-brand">{{ profileError }}</p>
      <button type="submit" class="btn-primary w-full" :disabled="saving || !form.province">
        {{ saving ? '...' : 'ذخیره تغییرات' }}
      </button>
    </form>

    <div class="space-y-2">
      <RouterLink
        v-for="item in links"
        :key="item.to"
        :to="item.to"
        class="card-soft flex items-center justify-between p-3.5 text-sm"
      >
        <span class="flex items-center gap-2">
          <span>{{ item.emoji }}</span>
          <span>{{ item.label }}</span>
        </span>
        <span class="text-ink-muted">‹</span>
      </RouterLink>
      <button class="card-soft w-full p-3.5 text-right text-sm text-brand" @click="onLogout">
        🚪 خروج از حساب
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { formatPrice } from '../../utils/format';
import { IRAN_PROVINCES } from '../../utils/provinces';

const auth = useAuthStore();
const router = useRouter();
const saving = ref(false);
const profileMsg = ref('');
const profileError = ref('');
const form = reactive({
  name: '',
  email: '',
  province: '',
});

const links = [
  { to: '/dashboard', label: 'داشبورد', emoji: '📊' },
  { to: '/leaderboard', label: 'رتبه‌بندی', emoji: '🏆' },
  { to: '/support', label: 'پشتیبانی', emoji: '🎫' },
  { to: '/profile/notifications', label: 'تنظیمات اعلان‌ها', emoji: '🔔' },
  { to: '/wallet', label: 'کیف پول و تراکنش‌ها', emoji: '💰' },
  { to: '/subscription', label: 'اشتراک', emoji: '⭐' },
  { to: '/resumes', label: 'رزومه‌ساز', emoji: '📄' },
  { to: '/my-purchases', label: 'خریدهای PDF', emoji: '📁' },
  { to: '/jobs/submit', label: 'ثبت آگهی شغلی', emoji: '💼' },
  { to: '/exams', label: 'آزمون‌ها', emoji: '📝' },
];

const initials = computed(() => {
  const n = auth.user?.name || 'ک';
  return n.trim().charAt(0);
});

function syncForm() {
  form.name = auth.user?.name || '';
  form.email = auth.user?.email || '';
  form.province = auth.user?.province || '';
}

async function saveProfile() {
  saving.value = true;
  profileMsg.value = '';
  profileError.value = '';
  try {
    await auth.updateProfile({
      name: form.name || null,
      email: form.email || null,
      province: form.province,
    });
    profileMsg.value = 'پروفایل ذخیره شد.';
    syncForm();
  } catch (e) {
    profileError.value = e.response?.data?.message || 'ذخیره پروفایل ناموفق بود.';
  } finally {
    saving.value = false;
  }
}

async function onLogout() {
  await auth.logout();
  router.push('/login');
}

onMounted(async () => {
  try {
    await auth.fetchMe();
  } catch (_) {
    // ignore
  }
  syncForm();
});
</script>
