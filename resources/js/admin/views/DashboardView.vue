<template>
      <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">داشبورد</h1>
          <p class="text-sm text-gray-500">{{ todayJalali }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
            :disabled="loading"
            @click="loadStats"
          >
            بروزرسانی
          </button>
          <span class="text-xs text-slate-400">خودکار هر ۳۰ث</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <RouterLink
          v-for="action in quickActions"
          :key="action.to"
          :to="action.to"
          class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-orange-300 hover:shadow-md"
        >
          <p class="text-lg">{{ action.icon }}</p>
          <p class="mt-1 text-sm font-bold text-slate-800">{{ action.label }}</p>
        </RouterLink>
      </div>

      <div
        v-if="loading"
        class="rounded-2xl bg-white p-10 text-center text-sm text-slate-500"
      >
        در حال بارگذاری آمار...
      </div>

      <template v-else>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <StatCard
            v-for="stat in topStats"
            :key="stat.id"
            :title="stat.title"
            :value="stat.value"
            :icon="stat.icon"
            :color="stat.color"
            :trend="stat.trend"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          <StatCard
            v-for="stat in bottomStats"
            :key="stat.id"
            :title="stat.title"
            :value="stat.value"
            :icon="stat.icon"
            :color="stat.color"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <StatCard
            title="بازدید امروز"
            :value="faNum(counts.visits_today)"
            icon="👁"
            color="#0284c7"
          />
          <StatCard
            title="بازدید این ماه"
            :value="faNum(counts.visits_month)"
            icon="📊"
            color="#4338ca"
          />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <ChartCard title="درآمد ۳۰ روز اخیر" class="lg:col-span-2">
            <LineChart :data="charts.revenue" value-key="amount" />
          </ChartCard>
          <ChartCard title="کاربران جدید">
            <BarChart :data="charts.users" />
          </ChartCard>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <ChartCard title="بازدید ۳۰ روز اخیر" class="lg:col-span-2">
            <LineChart :data="visitSeries" color="#0284c7" value-key="visits" />
          </ChartCard>
          <ChartCard title="دستگاه‌ها">
            <DoughnutChart :data="deviceDistribution" />
          </ChartCard>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <ChartCard title="آزمون‌های برگزار شده" class="lg:col-span-2">
            <LineChart :data="charts.exams" color="#10b981" value-key="count" />
          </ChartCard>
          <ChartCard title="توزیع اشتراک‌ها">
            <DoughnutChart :data="subscriptionDistribution" />
          </ChartCard>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm">
          <h3 class="mb-3 font-bold text-slate-800">۱۰ صفحه پربازدید</h3>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-slate-500">
                <th class="py-2 text-right font-medium">صفحه</th>
                <th class="py-2 text-left font-medium">بازدید</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="p in topPages"
                :key="p.page"
                class="border-b border-slate-50"
              >
                <td class="py-2 font-medium" dir="ltr">{{ p.page }}</td>
                <td class="py-2 text-left">{{ faNum(p.count) }}</td>
              </tr>
              <tr v-if="!topPages.length">
                <td colspan="2" class="py-4 text-center text-slate-400">
                  هنوز بازدیدی ثبت نشده
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <RecentTable
            title="آخرین ثبت‌نام‌ها"
            :columns="['نام', 'موبایل', 'تاریخ']"
            :rows="userRows"
            link="/admin/users"
          />
          <RecentTable
            title="آخرین آزمون‌ها"
            :columns="['آزمون', 'کاربر', 'نمره', 'تاریخ']"
            :rows="examRows"
            link="/admin/exams"
          />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <RecentTable
            title="آخرین خریدها"
            :columns="['محصول', 'کاربر', 'مبلغ', 'تاریخ']"
            :rows="purchaseRows"
            link="/admin/transactions"
          />
          <RecentTable
            title="آخرین آگهی‌ها"
            :columns="['عنوان', 'شرکت', 'وضعیت', 'تاریخ']"
            :rows="jobRows"
            link="/admin/job-posts"
          />
        </div>
      </template>

      <p v-if="error" class="text-center text-sm text-red-500">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import adminApi from '../api/client'
import BarChart from '../components/ui/BarChart.vue'
import ChartCard from '../components/ui/ChartCard.vue'
import DoughnutChart from '../components/ui/DoughnutChart.vue'
import LineChart from '../components/ui/LineChart.vue'
import RecentTable from '../components/ui/RecentTable.vue'
import StatCard from '../components/ui/StatCard.vue'

const loading = ref(true)
const error = ref('')
const counts = ref({})
const charts = ref({
  revenue: [],
  users: [],
  exams: [],
  visits: [],
  devices: [],
})
const recent = ref({
  users: [],
  exams: [],
  purchases: [],
  job_posts: [],
  blog_posts: [],
})
const subscriptionDistribution = ref([])
const topPages = ref([])
const deviceDistribution = ref([])
let pollTimer

const quickActions = [
  { to: '/admin/exams', label: 'آزمون‌ها', icon: '📋' },
  { to: '/admin/users', label: 'کاربران', icon: '👤' },
  { to: '/admin/transactions', label: 'تراکنش‌ها', icon: '💳' },
  { to: '/admin/tickets', label: 'تیکت‌ها', icon: '✉' },
  { to: '/admin/aggregated-jobs', label: 'تجمیع', icon: '☑' },
  { to: '/admin/job-posts', label: 'آگهی‌ها', icon: '💼' },
]

const visitSeries = computed(() =>
  (charts.value.visits || []).map((r) => ({
    date: r.date,
    visits: r.visits ?? r.page_views ?? 0,
  }))
)

const todayJalali = computed(() =>
  new Date().toLocaleDateString('fa-IR', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
)

function faNum(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}

function faMoney(n) {
  return `${faNum(n)} ریال`
}

function faDate(v) {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleDateString('fa-IR')
  } catch {
    return String(v)
  }
}

const topStats = computed(() => [
  {
    id: 'users',
    title: 'کاربران',
    value: faNum(counts.value.users),
    icon: '👤',
    color: '#0f2744',
    trend: '',
  },
  {
    id: 'exams',
    title: 'آزمون‌ها',
    value: faNum(counts.value.exams),
    icon: '📋',
    color: '#f97316',
    trend: '',
  },
  {
    id: 'questions',
    title: 'سوالات',
    value: faNum(counts.value.questions),
    icon: '❓',
    color: '#3b82f6',
    trend: '',
  },
  {
    id: 'subs',
    title: 'اشتراک فعال',
    value: faNum(counts.value.active_subscriptions),
    icon: '⭐',
    color: '#22c55e',
    trend: '',
  },
])

const bottomStats = computed(() => [
  {
    id: 'pdf',
    title: 'فروش PDF',
    value: faNum(counts.value.pdf_sales),
    icon: '📄',
    color: '#7c3aed',
  },
  {
    id: 'agg-pending',
    title: 'تجمیع در انتظار',
    value: faNum(counts.value.aggregated_jobs_pending),
    icon: '☑',
    color: '#d97706',
  },
  {
    id: 'agg-sources',
    title: 'منابع whitelist',
    value: faNum(counts.value.whitelisted_job_sources),
    icon: '◎',
    color: '#2563eb',
  },
  {
    id: 'crawl-fail',
    title: 'خطای خزش ۷روز',
    value: faNum(counts.value.recent_crawl_failures),
    icon: '⚠',
    color: '#dc2626',
  },
  {
    id: 'today',
    title: 'درآمد امروز',
    value: faMoney(counts.value.today_revenue),
    icon: '💰',
    color: '#ea580c',
  },
  {
    id: 'month',
    title: 'درآمد ماه',
    value: faMoney(counts.value.month_revenue),
    icon: '📈',
    color: '#0f766e',
  },
  {
    id: 'tx',
    title: 'تراکنش امروز',
    value: faNum(counts.value.transactions_today),
    icon: '🔁',
    color: '#be123c',
  },
])

const userRows = computed(() =>
  (recent.value.users || []).map((u) => [
    u.name,
    u.mobile,
    faDate(u.created_at),
  ])
)

const examRows = computed(() =>
  (recent.value.exams || []).map((e) => [
    e.title,
    e.user_name,
    faNum(e.score),
    faDate(e.created_at),
  ])
)

const purchaseRows = computed(() =>
  (recent.value.purchases || []).map((p) => [
    p.product_name,
    p.user_name,
    faMoney(p.amount),
    faDate(p.created_at),
  ])
)

const jobRows = computed(() =>
  (recent.value.job_posts || []).map((j) => [
    j.title,
    j.company_name,
    statusFa(j.status),
    faDate(j.created_at),
  ])
)

function statusFa(status) {
  return (
    {
      pending: 'در انتظار',
      approved: 'تایید شده',
      rejected: 'رد شده',
      draft: 'پیش‌نویس',
      published: 'منتشر شده',
    }[status] ||
    status ||
    '—'
  )
}

onMounted(() => {
  loadStats()
  pollTimer = setInterval(() => loadStats(true), 30000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})

async function loadStats(silent = false) {
  if (!silent) loading.value = true
  error.value = ''
  try {
    const { data } = await adminApi.get('/admin/dashboard-stats')
    const payload = data.data || {}
    counts.value = payload.counts || {}
    charts.value = payload.charts || {
      revenue: [],
      users: [],
      exams: [],
      visits: [],
      devices: [],
    }
    recent.value = payload.recent || {}
    subscriptionDistribution.value = payload.subscription_distribution || []
    topPages.value = payload.top_pages || []
    deviceDistribution.value = payload.charts?.devices || []
  } catch (e) {
    error.value = e.response?.data?.message || 'بارگذاری داشبورد ناموفق بود.'
  } finally {
    loading.value = false
  }
}
</script>
