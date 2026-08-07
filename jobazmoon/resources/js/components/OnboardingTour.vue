<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 z-[100]">
      <div class="absolute inset-0 bg-black/60" @click="skip" />
      <div
        class="absolute z-10 max-w-sm rounded-2xl bg-white p-5 shadow-2xl"
        :style="panelStyle"
      >
        <p class="mb-1 text-xs font-bold text-brand">گام {{ step + 1 }} از {{ steps.length }}</p>
        <h3 class="mb-2 text-lg font-black text-slate-800">{{ current.title }}</h3>
        <p class="mb-5 text-sm leading-7 text-slate-600">{{ current.body }}</p>
        <div class="flex items-center justify-between gap-2">
          <button type="button" class="text-sm font-bold text-slate-500" @click="skip">رد کردن</button>
          <button type="button" class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white" @click="next">
            {{ step === steps.length - 1 ? 'شروع کنیم' : 'بعدی' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/client';

const router = useRouter();
const visible = ref(false);
const step = ref(0);
const panelStyle = ref({ top: '20%', left: '50%', transform: 'translateX(-50%)' });

const steps = [
  { title: 'به جاب‌آزمون خوش آمدید!', body: 'اینجا می‌توانید آزمون بدهید، آگهی ببینید، رزومه بسازید و اشتراک بگیرید.', path: '/dashboard' },
  { title: 'آزمون بده', body: 'از بخش آزمون‌ها شروع کنید و کارنامه بگیرید.', path: '/exams', highlight: true },
  { title: 'آگهی استخدام ببین', body: 'جدیدترین فرصت‌های شغلی و مهلت ثبت‌نام را دنبال کنید.', path: '/jobs' },
  { title: 'رزومه بساز', body: 'با رزومه‌ساز حرفه‌ای، فایل PDF آماده کنید.', path: '/resumes' },
  { title: 'اشتراک بخر', body: 'با اشتراک، به همه آزمون‌ها و امکانات ویژه دسترسی دارید.', path: '/subscription' },
];

const current = computed(() => steps[step.value]);

onMounted(async () => {
  if (localStorage.getItem('onboarding_seen') === '1') return;
  try {
    const { data } = await api.get('/settings/public').catch(() => ({ data: null }));
    const enabled = data?.data?.onboarding_enabled;
    if (enabled === 'false' || enabled === false) return;
  } catch {
    // if public settings missing, still show tour
  }
  visible.value = true;
});

function finish() {
  localStorage.setItem('onboarding_seen', '1');
  visible.value = false;
}

function skip() {
  finish();
}

function next() {
  if (step.value >= steps.length - 1) {
    finish();
    return;
  }
  step.value += 1;
  const path = steps[step.value].path;
  if (path && router.currentRoute.value.path !== path) {
    router.push(path).catch(() => {});
  }
}
</script>
