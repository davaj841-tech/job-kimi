<template>
  <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
    <div>
      <p class="text-sm font-bold text-slate-800">{{ pageTitle }}</p>
      <p class="text-xs text-slate-500">مدیریت جاب‌آزمون</p>
    </div>
    <div class="flex items-center gap-4">
      <div class="hidden text-left sm:block" dir="rtl">
        <p class="text-xs font-bold text-desk-dark">{{ clockText }}</p>
        <p class="text-[11px] text-slate-400">تاریخ و ساعت شمسی</p>
      </div>
      <div class="text-left text-xs" dir="ltr">
        <p class="font-semibold text-slate-700">{{ auth.user?.name || 'ادمین' }}</p>
        <p class="text-slate-400">{{ auth.user?.mobile }}</p>
      </div>
      <button
        type="button"
        class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200"
        @click="onLogout"
      >
        خروج
      </button>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdminAuthStore } from '../../stores/auth';
import { formatJalaliDateTime } from '../../../utils/jalali';

const auth = useAdminAuthStore();
const router = useRouter();
const route = useRoute();
const pageTitle = computed(() => route.meta.title || 'پنل مدیریت');
const now = ref(new Date());
let timer;

const clockText = computed(() => formatJalaliDateTime(now.value));

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date();
  }, 1000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});

async function onLogout() {
  await auth.logout();
  router.replace('/admin/login');
}
</script>
