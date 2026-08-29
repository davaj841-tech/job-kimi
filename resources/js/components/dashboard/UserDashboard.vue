<template>
  <div class="space-y-6" dir="rtl">
    <!-- اسکلتون لودینگ -->
    <template v-if="store.loading && !store.data">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-24 rounded-xl" />
      </div>
      <Skeleton class="h-28 rounded-xl" />
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Skeleton class="h-64 rounded-xl" />
        <Skeleton class="h-64 rounded-xl" />
      </div>
    </template>

    <!-- خطا -->
    <div
      v-else-if="store.error && !dash"
      class="rounded-xl border border-red-200 bg-red-50 p-6 text-center dark:border-red-900 dark:bg-red-950/40"
    >
      <p class="mb-3 text-red-700 dark:text-red-300">{{ store.error }}</p>
      <button
        type="button"
        class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white"
        @click="reload"
      >
        تلاش مجدد
      </button>
    </div>

    <template v-else-if="dash">
      <!-- هدر -->
      <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <div>
          <h2 class="text-2xl font-bold text-ink dark:text-white">
            سلام، {{ dash.userName }}
          </h2>
          <p class="mt-1 text-sm text-ink-muted dark:text-slate-400">
            امروز {{ todayLabel }} — آماده‌ای برای آزمون؟
          </p>
        </div>
        <RouterLink
          to="/exams"
          class="inline-flex items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-dark"
        >
          شروع آزمون جدید
        </RouterLink>
      </div>

      <!-- بنر streak -->
      <StreakBanner :streak="dash.streak" />

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 gap-3 md:grid-cols-2 lg:grid-cols-4">
        <KpiCard
          label="آزمون‌های شرکت‌کرده"
          :value="dash.kpis.total_exams"
          :icon="AcademicCapIcon"
          icon-bg="bg-sky-100 dark:bg-sky-900/40"
          icon-color="text-sky-600 dark:text-sky-300"
          :trend="examChangeTrend"
          trend-color="text-emerald-600"
          :delay="0"
        />
        <KpiCard
          label="میانگین نمره"
          :value="Math.round(dash.kpis.avg_score)"
          suffix="٪"
          :icon="ChartBarIcon"
          icon-bg="bg-emerald-100 dark:bg-emerald-900/40"
          icon-color="text-emerald-600 dark:text-emerald-300"
          :trend="scoreChangeTrend"
          :trend-color="
            dash.kpis.avg_score_change >= 0 ? 'text-emerald-600' : 'text-brand'
          "
          :delay="100"
        />
        <KpiCard
          label="زمان مطالعه"
          :value="dash.kpis.study_hours"
          suffix=" ساعت"
          :icon="ClockIcon"
          icon-bg="bg-amber-100 dark:bg-amber-900/40"
          icon-color="text-amber-600 dark:text-amber-300"
          trend="این هفته"
          trend-color="text-ink-muted dark:text-slate-400"
          :delay="200"
        />
        <KpiCard
          label="رتبه لیدربورد"
          :value="dash.kpis.rank"
          prefix="#"
          :icon="TrophyIcon"
          icon-bg="bg-purple-100 dark:bg-purple-900/40"
          icon-color="text-purple-600 dark:text-purple-300"
          :trend="rankChangeTrend"
          trend-color="text-emerald-600"
          :delay="300"
        />
      </div>

      <div
        v-if="!dash.hasExams"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
      >
        هنوز آزمونی نداده‌اید — نمودارها پس از اولین آزمون با داده واقعی پر
        می‌شوند.
        <RouterLink
          to="/exams"
          class="mr-2 font-bold text-brand hover:underline"
        >
          شروع اولین آزمون
        </RouterLink>
      </div>

      <!-- نمودارها -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <ScoreTrendChart
          :data="displayScoreHistory"
          :growth-label="dash.score_growth"
        />
        <SkillRadarChart
          :labels="dash.skill_labels"
          :user-scores="displaySkillScores"
          :avg-scores="dash.avg_skill_scores"
        />
      </div>

      <!-- نقاط قوت/ضعف + توزیع زمان -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <SkillBreakdown
          :strengths="displayStrengths"
          :weaknesses="displayWeaknesses"
          :suggestion="dash.suggestion"
        />
        <TimeDistribution :data="dash.time_distribution" />
      </div>

      <!-- فعالیت + برنامه روزانه -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <RecentActivity :items="dash.recent_activity" />
        <DailyPlan :items="dash.daily_plan" />
      </div>

      <!-- اکشن‌های سریع -->
      <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <QuickAction
          v-for="action in quickActions"
          :key="action.to"
          :to="action.to"
          :icon="action.icon"
          :label="action.label"
          :description="action.description"
        />
      </div>
    </template>

    <div
      v-else
      class="rounded-xl border border-slate-200 p-6 text-center dark:border-slate-700"
    >
      <p class="mb-3 text-sm text-ink-muted">داده‌ای برای نمایش نیست.</p>
      <button
        type="button"
        class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white"
        @click="reload"
      >
        بارگذاری مجدد
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import type { ScoreHistoryItem, SkillItem } from '@/stores/dashboardStore'
import {
  AcademicCapIcon,
  ChartBarIcon,
  ClockIcon,
  DocumentTextIcon,
  ShoppingBagIcon,
  StarIcon,
  TrophyIcon,
  WalletIcon,
} from '@heroicons/vue/24/outline'
import { storeToRefs } from 'pinia'
import { useDashboardStore } from '@/stores/dashboardStore'
import Skeleton from '../ui/Skeleton.vue'
import QuickAction from '../ui/QuickAction.vue'
import KpiCard from './KpiCard.vue'
import ScoreTrendChart from './ScoreTrendChart.vue'
import SkillRadarChart from './SkillRadarChart.vue'
import TimeDistribution from './TimeDistribution.vue'
import SkillBreakdown from './SkillBreakdown.vue'
import RecentActivity from './RecentActivity.vue'
import DailyPlan from './DailyPlan.vue'
import StreakBanner from './StreakBanner.vue'
import { toFaDigits } from '@/utils/format'

const store = useDashboardStore()
const { data: dash } = storeToRefs(store)

const todayLabel = computed(() =>
  new Intl.DateTimeFormat('fa-IR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(new Date())
)

const examChangeTrend = computed(() => {
  if (!dash.value) return ''
  const c = dash.value.kpis.total_exams_change
  if (c === 0) return 'بدون تغییر نسبت به ماه قبل'
  const sign = c > 0 ? '+' : ''
  return `${sign}${toFaDigits(c)} نسبت به ماه قبل`
})

const scoreChangeTrend = computed(() => {
  if (!dash.value) return ''
  const c = dash.value.kpis.avg_score_change
  if (c === 0) return 'بدون تغییر'
  const sign = c > 0 ? '+' : ''
  return `${sign}${toFaDigits(c)}٪ نسبت به قبل`
})

const rankChangeTrend = computed(() => {
  if (!dash.value) return ''
  const c = dash.value.kpis.rank_change
  if (c === 0) return 'بدون تغییر پله'
  return `${toFaDigits(c)} پله صعود`
})

const previewScoreHistory: ScoreHistoryItem[] = [
  { exam: 'نمونه ۱', score: 55, date: null },
  { exam: 'نمونه ۲', score: 62, date: null },
  { exam: 'نمونه ۳', score: 68, date: null },
  { exam: 'نمونه ۴', score: 71, date: null },
  { exam: 'نمونه ۵', score: 75, date: null },
]

const previewStrengths: SkillItem[] = [
  { name: 'کامپیوتر', score: 82 },
  { name: 'ریاضی', score: 78 },
  { name: 'فنی', score: 74 },
]

const previewWeaknesses: SkillItem[] = [
  { name: 'اطلاعات', score: 58 },
  { name: 'زبان', score: 65 },
  { name: 'استدلال', score: 70 },
]

const displayScoreHistory = computed(() => {
  if (!dash.value) return []
  return dash.value.hasExams && dash.value.score_history.length
    ? dash.value.score_history
    : previewScoreHistory
})

const displaySkillScores = computed(() => {
  if (!dash.value) return []
  const hasData = dash.value.skill_scores.some((s) => s > 0)
  return dash.value.hasExams && hasData
    ? dash.value.skill_scores
    : [78, 65, 82, 70, 58, 74]
})

const displayStrengths = computed(() => {
  if (!dash.value) return []
  return dash.value.hasExams && dash.value.strengths.length
    ? dash.value.strengths
    : previewStrengths
})

const displayWeaknesses = computed(() => {
  if (!dash.value) return []
  return dash.value.hasExams && dash.value.weaknesses.length
    ? dash.value.weaknesses
    : previewWeaknesses
})

const quickActions = [
  {
    to: '/exams',
    label: 'آزمون‌ها',
    description: 'شروع تمرین',
    icon: AcademicCapIcon,
  },
  {
    to: '/wallet',
    label: 'کیف پول',
    description: 'شارژ موجودی',
    icon: WalletIcon,
  },
  {
    to: '/subscription',
    label: 'اشتراک',
    description: 'ارتقا پلن',
    icon: StarIcon,
  },
  {
    to: '/resumes',
    label: 'رزومه',
    description: 'ساخت و ویرایش',
    icon: DocumentTextIcon,
  },
  {
    to: '/leaderboard',
    label: 'لیدربورد',
    description: 'رتبه‌بندی',
    icon: TrophyIcon,
  },
  {
    to: '/my-purchases',
    label: 'خریدها',
    description: 'PDFهای من',
    icon: ShoppingBagIcon,
  },
]

async function reload(): Promise<void> {
  await store.fetchDashboard(true)
}

onMounted(() => {
  store.fetchDashboard().catch(() => {})
})
</script>
