<template>
  <div class="px-4 py-4 lg:desk-container lg:py-8">
    <SkeletonCard v-if="loading" :count="4" />
    <template v-else>
      <!-- Greeting -->
      <div class="mb-4 overflow-hidden rounded-2xl bg-gradient-to-l from-desk-dark to-desk-blue p-4 text-white">
        <p class="text-sm text-white/80">👋 سلام،</p>
        <h1 class="mt-0.5 text-xl font-black">{{ user.name || 'کاربر جاب‌آزمون' }}</h1>
        <p v-if="user.province" class="mt-1 text-xs text-white/70">📍 {{ user.province }}</p>
        <p v-if="user.subscription_name" class="mt-2 inline-block rounded-lg bg-white/15 px-2 py-1 text-[11px] font-bold">
          ⭐ اشتراک {{ user.subscription_name }}
          <span v-if="user.subscription_days_left !== null">— {{ toFaDigits(user.subscription_days_left) }} روز مانده</span>
        </p>
      </div>

      <!-- Stats cards -->
      <div class="mb-5 grid grid-cols-2 gap-2 lg:grid-cols-4">
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">📝 آزمون‌های داده‌شده</p>
          <p class="mt-1 text-xl font-black">{{ toFaDigits(stats.total_exams_taken) }}</p>
        </div>
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">✅ قبول‌شده</p>
          <p class="mt-1 text-xl font-black text-emerald-600">{{ toFaDigits(stats.total_exams_passed) }}</p>
        </div>
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">📊 میانگین نمره</p>
          <p class="mt-1 text-xl font-black text-brand">{{ toFaDigits(stats.average_score) }}</p>
        </div>
        <div class="card-soft p-3">
          <p class="text-[11px] text-ink-muted">💰 موجودی کیف پول</p>
          <p class="mt-1 text-sm font-black text-brand">{{ formatPrice(wallet.balance) }}</p>
        </div>
      </div>

      <div class="lg:grid lg:grid-cols-2 lg:gap-5">
        <!-- Progress by subject -->
        <div class="card-soft mb-5 p-4">
          <h2 class="mb-3 text-sm font-bold">📚 پیشرفت بر اساس درس (۳۰ روز اخیر)</h2>
          <EmptyState v-if="!progressChart.length" title="داده‌ای موجود نیست" icon="📚" />
          <div v-else class="space-y-3">
            <div v-for="row in progressChart" :key="row.subject" class="text-xs">
              <div class="mb-1 flex items-center justify-between">
                <span class="font-medium">{{ row.subject_label }}</span>
                <span class="font-bold text-brand">{{ toFaDigits(Math.round(row.average_score)) }}٪</span>
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-surface-page">
                <div
                  class="h-full rounded-full bg-gradient-to-l from-brand to-brand-dark transition-all"
                  :style="{ width: `${Math.min(100, row.average_score)}%` }"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Exam chart -->
        <div class="card-soft mb-5 p-4">
          <h2 class="mb-3 text-sm font-bold">📈 روند آزمون‌های اخیر</h2>
          <EmptyState v-if="!examChart.length" title="هنوز آزمونی نداده‌اید" icon="📈" />
          <div v-else class="flex items-end gap-2 overflow-x-auto pb-1" style="min-height: 120px">
            <div
              v-for="(row, idx) in examChart"
              :key="idx"
              class="flex w-10 shrink-0 flex-col items-center gap-1"
            >
              <span class="text-[10px] font-bold text-ink-soft">{{ toFaDigits(Math.round(row.percentage)) }}٪</span>
              <div class="flex h-20 w-full items-end overflow-hidden rounded-lg bg-surface-page">
                <div
                  class="w-full rounded-lg bg-gradient-to-t from-desk-blue to-desk-orange transition-all"
                  :style="{ height: `${Math.max(4, row.percentage)}%` }"
                />
              </div>
              <span class="line-clamp-2 text-center text-[9px] text-ink-muted">{{ row.label }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent attempts -->
      <h2 class="mb-2 text-sm font-bold">🕒 آخرین نتایج</h2>
      <EmptyState v-if="!recent.length" title="هنوز آزمونی نداده‌اید" icon="📝" description="از بخش آزمون‌ها شروع کنید.">
        <RouterLink to="/exams" class="btn-primary mt-2 !w-auto px-6">مشاهده آزمون‌ها</RouterLink>
      </EmptyState>
      <div v-else class="mb-6 space-y-2">
        <RouterLink
          v-for="item in recent"
          :key="item.id"
          :to="`/exams/${item.exam_id}/result/${item.id}`"
          class="card-soft flex items-center justify-between p-3 text-sm"
        >
          <div class="min-w-0">
            <p class="truncate font-bold">{{ item.exam_title || 'آزمون' }}</p>
            <p class="mt-1 text-xs text-ink-muted">
              نمره {{ toFaDigits(item.score ?? '—') }} · {{ toFaDigits(Math.round(item.percentage || 0)) }}٪ ·
              {{ formatDate(item.finished_at || item.created_at) }}
            </p>
          </div>
          <span class="text-ink-muted">‹</span>
        </RouterLink>
      </div>

      <!-- Quick links -->
      <h2 class="mb-2 text-sm font-bold">دسترسی سریع</h2>
      <div class="grid grid-cols-4 gap-2 lg:grid-cols-7">
        <RouterLink
          v-for="link in quickLinks"
          :key="link.to"
          :to="link.to"
          class="card-soft flex flex-col items-center gap-1.5 p-3 text-center transition active:scale-95"
        >
          <span class="text-2xl">{{ link.emoji }}</span>
          <span class="text-[11px] font-bold">{{ link.label }}</span>
          <span v-if="link.badge" class="rounded-full bg-brand-soft px-1.5 text-[10px] font-bold text-brand">
            {{ toFaDigits(link.badge) }}
          </span>
        </RouterLink>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../api/client';
import EmptyState from '../components/EmptyState.vue';
import SkeletonCard from '../components/ui/SkeletonCard.vue';
import { formatDate, formatPrice, toFaDigits, unwrapItem } from '../utils/format';

const loading = ref(true);
const recent = ref([]);
const progressChart = ref([]);
const examChart = ref([]);
const purchasesCount = ref(0);
const ticketsCount = ref(0);

const user = reactive({
  name: '',
  province: '',
  subscription_name: '',
  subscription_days_left: null,
});
const stats = reactive({
  total_exams_taken: 0,
  total_exams_passed: 0,
  average_score: 0,
});
const wallet = reactive({ balance: 0 });

const quickLinks = computed(() => [
  { to: '/exams', label: 'آزمون‌ها', emoji: '📝' },
  { to: '/resumes', label: 'رزومه', emoji: '📄' },
  { to: '/my-purchases', label: 'خریدها', emoji: '📁', badge: purchasesCount.value || null },
  { to: '/support', label: 'تیکت‌ها', emoji: '🎫', badge: ticketsCount.value || null },
  { to: '/wallet', label: 'کیف پول', emoji: '💰' },
  { to: '/subscription', label: 'اشتراک', emoji: '⭐' },
  { to: '/profile', label: 'پروفایل', emoji: '👤' },
]);

onMounted(async () => {
  try {
    const [dash, walletRes, purchasesRes, ticketsRes] = await Promise.all([
      api.get('/dashboard').catch(() => null),
      api.get('/wallet').catch(() => null),
      api.get('/my-purchases').catch(() => null),
      api.get('/tickets').catch(() => null),
    ]);

    const dashData = unwrapItem(dash?.data) || {};
    Object.assign(user, dashData.user || {});
    Object.assign(stats, dashData.stats || {});
    progressChart.value = dashData.progress_chart || [];
    examChart.value = dashData.exam_chart || [];
    recent.value = dashData.recent_attempts || [];

    wallet.balance = unwrapItem(walletRes?.data)?.balance || 0;

    const purchases = unwrapItem(purchasesRes?.data);
    purchasesCount.value = Array.isArray(purchases) ? purchases.length : 0;

    const ticketsData = unwrapItem(ticketsRes?.data);
    ticketsCount.value = ticketsData?.meta?.total ?? (Array.isArray(ticketsData?.data) ? ticketsData.data.length : 0);
  } finally {
    loading.value = false;
  }
});
</script>
