<template>
  <div class="px-4 py-4">
    <div class="card-soft mb-4 flex items-center gap-3 p-4">
      <div class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-soft text-xl font-black text-brand">
        {{ initials }}
      </div>
      <div>
        <p class="font-bold">{{ auth.user?.name || 'کاربر جاب‌آزمون' }}</p>
        <p class="text-sm text-ink-muted" dir="ltr">{{ auth.user?.mobile }}</p>
      </div>
    </div>

    <div class="space-y-2">
      <RouterLink v-for="item in links" :key="item.to" :to="item.to" class="card-soft flex items-center justify-between p-3.5 text-sm">
        <span>{{ item.label }}</span>
        <span class="text-ink-muted">‹</span>
      </RouterLink>
      <button class="card-soft w-full p-3.5 text-right text-sm text-brand" @click="onLogout">خروج از حساب</button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const links = [
  { to: '/dashboard', label: 'داشبورد' },
  { to: '/leaderboard', label: 'رتبه‌بندی' },
  { to: '/support', label: 'پشتیبانی' },
  { to: '/profile/notifications', label: 'تنظیمات اعلان‌ها' },
  { to: '/wallet', label: 'کیف پول و تراکنش‌ها' },
  { to: '/subscription', label: 'اشتراک' },
  { to: '/resumes', label: 'رزومه‌ساز' },
  { to: '/my-purchases', label: 'خریدهای PDF' },
  { to: '/jobs/submit', label: 'ثبت آگهی شغلی' },
  { to: '/exams', label: 'آزمون‌ها' },
];

const initials = computed(() => {
  const n = auth.user?.name || 'ک';
  return n.trim().charAt(0);
});

onMounted(() => {
  auth.fetchMe().catch(() => {});
});

async function onLogout() {
  await auth.logout();
  router.replace('/login');
}
</script>
