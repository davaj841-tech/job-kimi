import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/api'
import { unwrapItem } from '@/utils/format'

const CACHE_TTL_MS = 5 * 60 * 1000

export interface DashboardKpis {
  total_exams: number
  total_exams_change: number
  avg_score: number
  avg_score_change: number
  study_hours: number
  rank: number
  rank_change: number
}

export interface ScoreHistoryItem {
  exam: string
  score: number
  date: string | null
}

export interface TimeDistributionItem {
  label: string
  value: number
  color: string
}

export interface SkillItem {
  name: string
  score: number
}

export interface ActivityItem {
  icon: string
  title: string
  meta: string
  color: 'blue' | 'emerald' | 'purple' | 'orange'
}

export interface DailyPlanItem {
  num: number
  title: string
  meta: string
  color: string
  action: string
  link?: string
}

export interface StreakData {
  current: number
  target_badge: string
  days_to_badge: number
  target_rank: number
  week_days: Array<{ label: string; active: boolean }>
}

export interface DashboardData {
  userName: string
  kpis: DashboardKpis
  score_history: ScoreHistoryItem[]
  score_growth: string
  skill_labels: string[]
  skill_scores: number[]
  avg_skill_scores: number[]
  time_distribution: TimeDistributionItem[]
  strengths: SkillItem[]
  weaknesses: SkillItem[]
  suggestion: string
  recent_activity: ActivityItem[]
  daily_plan: DailyPlanItem[]
  streak: StreakData
  hasExams: boolean
}

/** دادهٔ نمونه برای تست اولیه */
function mockDashboard(): DashboardData {
  return {
    userName: 'کاربر',
    kpis: {
      total_exams: 12,
      total_exams_change: 3,
      avg_score: 72,
      avg_score_change: 5.2,
      study_hours: 8.5,
      rank: 24,
      rank_change: 2,
    },
    score_history: [
      { exam: 'آزمون ۱', score: 55, date: null },
      { exam: 'آزمون ۲', score: 62, date: null },
      { exam: 'آزمون ۳', score: 68, date: null },
      { exam: 'آزمون ۴', score: 71, date: null },
      { exam: 'آزمون ۵', score: 75, date: null },
    ],
    score_growth: '+۲۰٪ رشد از اولین آزمون',
    skill_labels: ['ریاضی', 'زبان', 'کامپیوتر', 'استدلال', 'اطلاعات', 'فنی'],
    skill_scores: [78, 65, 82, 70, 58, 74],
    avg_skill_scores: [62, 58, 55, 60, 57, 54],
    time_distribution: [
      { label: 'آزمون آنلاین', value: 4.2, color: '#3b82f6' },
      { label: 'فایل آموزشی', value: 2.1, color: '#10b981' },
      { label: 'رزومه‌ساز', value: 1.3, color: '#ef394e' },
      { label: 'مقاله/بلاگ', value: 0.9, color: '#a855f7' },
    ],
    strengths: [
      { name: 'کامپیوتر', score: 82 },
      { name: 'ریاضی', score: 78 },
      { name: 'فنی', score: 74 },
    ],
    weaknesses: [
      { name: 'اطلاعات', score: 58 },
      { name: 'زبان', score: 65 },
      { name: 'استدلال', score: 70 },
    ],
    suggestion: 'با ۲ ساعت تمرین در اطلاعات، نمره کلی +۲٪ می‌شود',
    recent_activity: [
      { icon: '📝', title: 'آزمون استخدام بانک', meta: '۷۵٪ · ۲ ساعت پیش', color: 'emerald' },
      { icon: '📚', title: 'مطالعه جزوه', meta: 'دیروز', color: 'blue' },
      { icon: '🏆', title: 'صعود ۲ پله در لیدربورد', meta: '۳ روز پیش', color: 'purple' },
    ],
    daily_plan: [
      { num: 1, title: 'آزمون شبیه‌سازی', meta: 'یک آزمون کامل', color: 'blue', action: 'شروع', link: '/exams' },
      { num: 2, title: 'تمرین اطلاعات', meta: 'تمرکز روی بخش ضعیف', color: 'emerald', action: 'تمرین', link: '/exams' },
      { num: 3, title: 'مطالعه جزوه', meta: 'مرور مفاهیم', color: 'amber', action: 'مشاهده', link: '/my-purchases' },
    ],
    streak: {
      current: 4,
      target_badge: 'پرتکرار',
      days_to_badge: 3,
      target_rank: 21,
      week_days: [
        { label: 'ش', active: true },
        { label: 'ی', active: true },
        { label: 'د', active: false },
        { label: 'س', active: true },
        { label: 'چ', active: true },
        { label: 'پ', active: false },
        { label: 'ج', active: false },
      ],
    },
    hasExams: true,
  }
}

function mapApiPayload(raw: Record<string, unknown>): DashboardData {
  const kpisRaw = (raw.kpis || {}) as Record<string, number>
  const stats = (raw.stats || {}) as Record<string, number>
  const user = (raw.user || {}) as Record<string, unknown>

  const kpis: DashboardKpis = {
    total_exams: Number(kpisRaw.total_exams ?? stats.total_exams_taken ?? 0),
    total_exams_change: Number(kpisRaw.total_exams_change ?? 0),
    avg_score: Number(kpisRaw.avg_score ?? stats.average_score ?? 0),
    avg_score_change: Number(kpisRaw.avg_score_change ?? 0),
    study_hours: Number(kpisRaw.study_hours ?? 0),
    rank: Number(kpisRaw.rank ?? 0),
    rank_change: Number(kpisRaw.rank_change ?? 0),
  }

  const scoreHistory = (raw.score_history as ScoreHistoryItem[])?.length
    ? (raw.score_history as ScoreHistoryItem[])
    : ((raw.exam_chart as Array<{ label: string; percentage: number; date?: string }>) || []).slice(-10).map((row) => ({
        exam: row.label,
        score: row.percentage,
        date: row.date ?? null,
      }))

  const skillLabels = (raw.skill_labels as string[])?.length
    ? (raw.skill_labels as string[])
    : ['ریاضی', 'زبان', 'کامپیوتر', 'استدلال', 'اطلاعات', 'فنی']

  const skillScores = (raw.skill_scores as number[])?.length
    ? (raw.skill_scores as number[])
    : ((raw.progress_chart as Array<{ average_score: number }>) || [])
        .slice(0, 6)
        .map((r) => r.average_score)
        .concat(Array(6).fill(0))
        .slice(0, 6)

  const avgSkillScores = (raw.avg_skill_scores as number[])?.length
    ? (raw.avg_skill_scores as number[])
    : skillScores.map((s) => Math.max(0, Math.round(s * 0.85)))

  const strengths = ((raw.strengths as SkillItem[]) || []).slice(0, 3)
  const weaknesses = ((raw.weaknesses as SkillItem[]) || []).slice(0, 3)

  const totalExams = kpis.total_exams

  return {
    userName: String(user.name || 'کاربر'),
    kpis,
    score_history: scoreHistory,
    score_growth: String(raw.score_growth || ''),
    skill_labels: skillLabels,
    skill_scores: skillScores,
    avg_skill_scores: avgSkillScores,
    time_distribution: (raw.time_distribution as TimeDistributionItem[]) || mockDashboard().time_distribution,
    strengths: strengths.length ? strengths : mockDashboard().strengths,
    weaknesses: weaknesses.length ? weaknesses : mockDashboard().weaknesses,
    suggestion: String(raw.suggestion || mockDashboard().suggestion),
    recent_activity: (raw.recent_activity as ActivityItem[])?.length
      ? (raw.recent_activity as ActivityItem[])
      : mockDashboard().recent_activity,
    daily_plan: (raw.daily_plan as DailyPlanItem[])?.length
      ? (raw.daily_plan as DailyPlanItem[])
      : mockDashboard().daily_plan,
    streak: (raw.streak as StreakData) || mockDashboard().streak,
    hasExams: totalExams > 0,
  }
}

export const useDashboardStore = defineStore('dashboard', () => {
  const data = ref<DashboardData | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const fetchedAt = ref<number | null>(null)

  const isStale = computed(() => {
    if (!fetchedAt.value) return true
    return Date.now() - fetchedAt.value > CACHE_TTL_MS
  })

  async function fetchDashboard(force = false): Promise<void> {
    if (!force && data.value && !isStale.value) return

    loading.value = true
    error.value = null

    try {
      const res = await api.get('/dashboard')
      const raw = (unwrapItem(res.data) || {}) as Record<string, unknown>
      data.value = mapApiPayload(raw)
      fetchedAt.value = Date.now()
    } catch (e: unknown) {
      error.value = 'بارگذاری داشبورد ناموفق بود.'
      if (!data.value) {
        data.value = mockDashboard()
      }
      throw e
    } finally {
      loading.value = false
    }
  }

  function invalidate(): void {
    fetchedAt.value = null
  }

  return {
    data,
    loading,
    error,
    isStale,
    fetchDashboard,
    invalidate,
  }
})
