<template>
  <div class="space-y-6">
    <template v-if="loading">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Skeleton v-for="i in 4" :key="i" class="h-24 rounded-2xl" />
      </div>
      <Skeleton class="h-72 rounded-2xl" />
    </template>

    <template v-else>
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-bold text-ink dark:text-white">
            سلام، {{ user.name || 'کاربر' }}
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

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="آزمون‌های شرکت‌کرده"
          :value="toFaDigits(stats.total_exams_taken)"
          :icon="AcademicCapIcon"
          icon-bg="bg-sky-100 dark:bg-sky-900/40"
          icon-color="text-sky-600 dark:text-sky-300"
          :trend="`+${toFaDigits(stats.exams_this_week)} این هفته`"
          trend-color="text-emerald-600"
        />
        <StatCard
          label="میانگین نمره"
          :value="`${toFaDigits(stats.average_score)}٪`"
          :icon="ChartBarIcon"
          icon-bg="bg-emerald-100 dark:bg-emerald-900/40"
          icon-color="text-emerald-600 dark:text-emerald-300"
          :trend="stats.score_trend"
          :trend-color="
            String(stats.score_trend || '').includes('+')
              ? 'text-emerald-600'
              : 'text-brand'
          "
        />
        <StatCard
          label="کیف پول"
          :value="formatPrice(wallet.balance)"
          :icon="WalletIcon"
          icon-bg="bg-amber-100 dark:bg-amber-900/40"
          icon-color="text-amber-600 dark:text-amber-300"
        />
        <StatCard
          label="اشتراک"
          :value="
            (user.subscription_days_left ?? 0) > 0
              ? `${toFaDigits(user.subscription_days_left)} روز`
              : 'منقضی / رایگان'
          "
          :icon="StarIcon"
          icon-bg="bg-brand-soft dark:bg-brand/20"
          icon-color="text-brand"
          :trend="user.subscription_name || 'بدون اشتراک'"
          trend-color="text-ink-muted dark:text-slate-400"
        />
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card class="p-5 lg:col-span-2">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-ink dark:text-white">
              آزمون‌های اخیر
            </h3>
            <RouterLink
              to="/user/exams"
              class="text-sm font-medium text-brand hover:underline"
            >
              مشاهده همه
            </RouterLink>
          </div>
          <EmptyState
            v-if="!recent.length"
            title="هنوز آزمونی نداده‌اید"
            description="از بخش آزمون‌ها شروع کنید."
          >
            <RouterLink to="/exams" class="btn-primary mt-3 !w-auto px-6"
              >مشاهده آزمون‌ها</RouterLink
            >
          </EmptyState>
          <div v-else class="space-y-2">
            <button
              v-for="exam in recent"
              :key="exam.id"
              type="button"
              class="flex w-full items-center gap-3 rounded-xl p-3 text-right transition hover:bg-slate-50 dark:hover:bg-slate-700/40"
              @click="
                $router.push(`/exams/${exam.exam_id}/result/${exam.id}`)
              "
            >
              <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-desk-dark to-brand text-sm font-bold text-white"
              >
                {{ toFaDigits(Math.round(exam.percentage || 0)) }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-ink dark:text-white">
                  {{ exam.exam_title }}
                </p>
                <p class="text-xs text-ink-muted dark:text-slate-400">
                  {{ formatDate(exam.finished_at || exam.created_at) }}
                </p>
              </div>
              <Badge :variant="scoreVariant(exam.percentage)">
                {{ scoreLabel(exam.percentage) }}
              </Badge>
              <ChevronLeftIcon class="h-5 w-5 text-slate-400" />
            </button>
          </div>
        </Card>

        <Card class="p-5">
          <h3 class="mb-4 text-lg font-bold text-ink dark:text-white">
            تحلیل عملکرد
          </h3>
          <RadarChart :data="skillRadar" />
          <div class="mt-4 space-y-3">
            <div v-for="skill in skills" :key="skill.name">
              <div class="mb-1 flex justify-between text-sm">
                <span class="text-ink dark:text-slate-200">{{ skill.name }}</span>
                <span class="font-medium">{{ toFaDigits(skill.percent) }}٪</span>
              </div>
              <ProgressBar :percent="skill.percent" :color="skill.color" />
            </div>
          </div>
        </Card>
      </div>

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
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  AcademicCapIcon,
  ChartBarIcon,
  ChevronLeftIcon,
  DocumentTextIcon,
  ShoppingBagIcon,
  StarIcon,
  WalletIcon,
} from '@heroicons/vue/24/outline'
import api from '../api/client'
import EmptyState from '../components/EmptyState.vue'
import Badge from '../components/ui/Badge.vue'
import Card from '../components/ui/Card.vue'
import ProgressBar from '../components/ui/ProgressBar.vue'
import QuickAction from '../components/ui/QuickAction.vue'
import Skeleton from '../components/ui/Skeleton.vue'
import StatCard from '../components/ui/StatCard.vue'
import RadarChart from '../components/user/RadarChart.vue'
import {
  formatDate,
  formatPrice,
  toFaDigits,
  unwrapItem,
} from '../utils/format'

const loading = ref(true)
const recent = ref<any[]>([])
const progressChart = ref<any[]>([])

const user = reactive({
  name: '',
  subscription_name: '',
  subscription_days_left: 0 as number | null,
})
const stats = reactive({
  total_exams_taken: 0,
  average_score: 0,
  exams_this_week: 0,
  score_trend: '',
})
const wallet = reactive({ balance: 0 })

const todayLabel = computed(() =>
  new Intl.DateTimeFormat('fa-IR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(new Date())
)

const skillRadar = computed(() =>
  progressChart.value.map((row) => ({
    label: row.subject_label,
    value: row.average_score,
  }))
)

const skills = computed(() => {
  const colors = ['brand', 'green', 'amber', 'blue', 'orange']
  return progressChart.value.slice(0, 5).map((row, i) => ({
    name: row.subject_label,
    percent: Math.round(row.average_score || 0),
    color: colors[i % colors.length],
  }))
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
    to: '/my-purchases',
    label: 'خریدها',
    description: 'PDFهای من',
    icon: ShoppingBagIcon,
  },
]

function scoreVariant(pct: number) {
  if (pct >= 70) return 'success'
  if (pct >= 50) return 'warning'
  return 'danger'
}

function scoreLabel(pct: number) {
  if (pct >= 70) return 'عالی'
  if (pct >= 50) return 'قابل قبول'
  return 'نیاز به تمرین'
}

onMounted(async () => {
  try {
    const [dash, walletRes] = await Promise.all([
      api.get('/dashboard').catch(() => null),
      api.get('/wallet').catch(() => null),
    ])
    const dashData = (unwrapItem(dash?.data) || {}) as Record<string, any>
    Object.assign(user, dashData.user || {})
    Object.assign(stats, {
      total_exams_taken: 0,
      average_score: 0,
      exams_this_week: 0,
      score_trend: '',
      ...(dashData.stats || {}),
    })
    progressChart.value = dashData.progress_chart || []
    recent.value = (dashData.recent_attempts || []).slice(0, 5)
    wallet.balance =
      (unwrapItem(walletRes?.data) as any)?.balance ??
      dashData.user?.wallet_balance ??
      0
  } finally {
    loading.value = false
  }
})
</script>
