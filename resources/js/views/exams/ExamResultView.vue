<template>
  <div class="min-h-dvh bg-surface-page px-4 py-8 dark:bg-slate-950">
    <LoadingSpinner v-if="loading" />
    <div v-else-if="result && reviewMode" class="mx-auto max-w-4xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-lg font-bold dark:text-white">
          {{ reviewTitle }}
        </h1>
        <button
          type="button"
          class="rounded-lg border border-surface-line bg-white px-3 py-1.5 text-xs font-bold dark:border-slate-700 dark:bg-slate-900"
          @click="reviewMode = null"
        >
          بازگشت به صفحه اصلی
        </button>
      </div>
      <p
        v-if="!filteredSheet.length"
        class="py-8 text-center text-sm text-ink-muted"
      >
        موردی یافت نشد.
      </p>
      <div
        v-for="item in filteredSheet"
        :key="item.id"
        class="rounded-xl border-2 p-3"
        :class="
          item.is_blank
            ? 'border-slate-200 dark:border-slate-700'
            : item.is_correct
              ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
              : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
        "
      >
        <p class="mb-1 text-xs text-ink-muted">
          سوال {{ toFaDigits(item.number) }}
        </p>
        <div
          class="mb-2 text-sm leading-7 dark:text-slate-200"
          v-html="renderKatexHtml(item.question_text)"
        />
        <p class="text-sm text-emerald-600">
          پاسخ صحیح:
          <b>{{ item.correct_answer_label || optionFaLetter(item.correct_answer) }})</b>
          <span v-html="renderKatexHtml(item.correct_answer_text || optionText(item, item.correct_answer))" />
        </p>
        <p v-if="!item.is_correct" class="text-sm text-brand">
          پاسخ شما:
          <template v-if="item.is_blank || !item.user_answer">نزده</template>
          <template v-else>
            <b>{{ item.user_answer_label || optionFaLetter(item.user_answer) }})</b>
            <span v-html="renderKatexHtml(item.user_answer_text || optionText(item, item.user_answer))" />
          </template>
        </p>
      </div>
    </div>
    <div v-else-if="result" class="mx-auto max-w-4xl space-y-6">
      <div
        v-if="isRetryResult"
        class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900/40 dark:bg-amber-950/30"
      >
        <p class="font-bold text-amber-900 dark:text-amber-200">
          {{ retryMode === 'blank' ? 'نتیجه آزمون سوالات بدون پاسخ' : 'نتیجه مرور سوالات غلط' }}
        </p>
        <p class="mt-1 text-xs text-amber-800 dark:text-amber-300/90">
          {{
            retryMode === 'blank'
              ? 'این کارنامه جدا از آزمون اصلی است و فقط سوالات بدون پاسخ را پوشش می‌دهد.'
              : 'این کارنامه جدا از آزمون اصلی است و فقط سوالات غلط را پوشش می‌دهد.'
          }}
        </p>
        <RouterLink
          v-if="parentAttemptId"
          :to="`/exams/${route.params.id}/result/${parentAttemptId}`"
          class="mt-2 inline-block text-xs font-bold text-brand"
        >
          مشاهده کارنامه اصلی
        </RouterLink>
      </div>
      <div
        class="overflow-hidden rounded-3xl border border-surface-line bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900"
      >
        <div
          class="relative flex h-48 items-center justify-center bg-gradient-to-br from-desk-dark via-desk-blue to-brand"
        >
          <div class="relative z-10 text-center text-white">
            <p class="mb-2 text-sm text-white/80">نمره شما</p>
            <div class="text-6xl font-black sm:text-7xl">
              {{ toFaDigits(Math.round(displayPercentage)) }}٪
            </div>
            <p class="mt-2 text-sm text-white/80">
              {{ toFaDigits(correctCount) }} از
              {{ toFaDigits(totalQuestions) }} سوال
              <span v-if="analysis?.rank">
                · رتبه {{ toFaDigits(analysis.rank) }}</span
              >
            </p>
            <p
              class="mt-3 inline-block rounded-full px-3 py-1 text-xs font-bold"
              :class="
                passed
                  ? 'bg-emerald-500/90 text-white'
                  : 'bg-white/20 text-white'
              "
            >
              {{ passed ? 'قبول شدید' : 'قبول نشدید' }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 md:grid-cols-5">
          <div
            v-for="stat in stats"
            :key="stat.label"
            class="rounded-2xl p-3 text-center"
            :class="stat.bg"
          >
            <component :is="stat.icon" class="mx-auto h-6 w-6" :class="stat.color" />
            <p class="mt-1 text-[11px] text-ink-muted dark:text-slate-400">
              {{ stat.label }}
            </p>
            <p class="text-lg font-black dark:text-white">{{ stat.value }}</p>
          </div>
        </div>
      </div>

      <div
        class="rounded-2xl border border-surface-line bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
      >
        <h2 class="mb-4 text-lg font-bold dark:text-white">📊 نمودار عملکرد</h2>
        <ExamResultCharts
          :percentage="displayPercentage"
          :correct="correctCount"
          :wrong="wrongCount"
          :blank="blankCount"
          :subjects="chartSubjects"
        />
      </div>

      <div
        class="rounded-2xl border border-amber-100 bg-amber-50 p-5 text-center dark:border-amber-900/40 dark:bg-amber-950/30"
      >
        <p class="mb-2 text-sm font-bold text-amber-900 dark:text-amber-200">
          این آزمون را چقدر ارزیابی می‌کنید؟
        </p>
        <StarRating v-model="rating" />
        <button
          type="button"
          class="mt-3 text-xs font-bold text-brand"
          :disabled="!rating || ratingSaving"
          @click="submitRating"
        >
          {{ ratingSaving ? '...' : 'ثبت امتیاز' }}
        </button>
        <p v-if="ratingMsg" class="mt-2 text-xs text-emerald-700">
          {{ ratingMsg }}
        </p>
      </div>

      <div
        class="rounded-2xl border border-surface-line bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-lg font-bold dark:text-white">مرور پاسخ‌ها</h2>
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white"
              @click="openReview('correct')"
            >
              گزینه درست
            </button>
            <button
              v-if="blankCount > 0"
              type="button"
              class="rounded-lg bg-slate-700 px-2.5 py-1 text-[11px] font-bold text-white"
              @click="openReview('blank')"
            >
              بدون پاسخ
            </button>
            <button
              v-if="wrongCount > 0"
              type="button"
              class="rounded-lg bg-brand px-2.5 py-1 text-[11px] font-bold text-white"
              @click="openReview('wrong')"
            >
              مرور غلط
            </button>
            <button
              type="button"
              class="text-xs font-bold text-brand"
              @click="showSheet = !showSheet"
            >
              {{ showSheet ? 'بستن' : 'نمایش پاسخبرگ' }}
            </button>
          </div>
        </div>
        <div v-if="showSheet" class="mb-3 flex flex-wrap gap-2">
          <button
            v-for="f in sheetFilters"
            :key="f.id"
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-bold"
            :class="
              sheetFilter === f.id
                ? 'bg-desk-dark text-white'
                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
            "
            @click="sheetFilter = f.id"
          >
            {{ f.label }}
            <span class="opacity-70">({{ toFaDigits(f.count) }})</span>
          </button>
        </div>
        <div v-if="showSheet" class="space-y-3">
          <p
            v-if="!filteredSheet.length"
            class="py-6 text-center text-sm text-ink-muted"
          >
            موردی در این فیلتر نیست.
          </p>
          <div
            v-for="item in filteredSheet"
            :key="item.id"
            class="rounded-xl border-2 p-4"
            :class="
              item.is_blank
                ? 'border-slate-200 dark:border-slate-700'
                : item.is_correct
                  ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
                  : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
            "
          >
            <div class="flex items-start gap-3">
              <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white"
                :class="
                  item.is_blank
                    ? 'bg-slate-400'
                    : item.is_correct
                      ? 'bg-emerald-500'
                      : 'bg-brand'
                "
              >
                <CheckIcon v-if="item.is_correct" class="h-5 w-5" />
                <XMarkIcon v-else-if="!item.is_blank" class="h-5 w-5" />
                <span v-else class="text-xs">—</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="mb-2 text-xs text-ink-muted">
                  سوال {{ toFaDigits(item.number) }}
                </p>
                <div
                  class="mb-2 text-sm leading-7 dark:text-slate-200"
                  v-html="renderKatexHtml(item.question_text)"
                />
                <p class="text-sm text-emerald-600 dark:text-emerald-400">
                  پاسخ صحیح:
                  <b>{{ item.correct_answer_label || optionFaLetter(item.correct_answer) }})</b>
                  <span
                    v-html="renderKatexHtml(item.correct_answer_text || optionText(item, item.correct_answer))"
                  />
                </p>
                <p
                  v-if="!item.is_correct"
                  class="text-sm text-brand dark:text-red-300"
                >
                  پاسخ شما:
                  <template v-if="item.is_blank || !item.user_answer">نزده</template>
                  <template v-else>
                    <b>{{ item.user_answer_label || optionFaLetter(item.user_answer) }})</b>
                    <span
                      v-html="renderKatexHtml(item.user_answer_text || optionText(item, item.user_answer))"
                    />
                  </template>
                </p>
                <p
                  v-if="item.explanation"
                  class="mt-2 text-xs leading-5 text-ink-soft dark:text-slate-400"
                >
                  {{ item.explanation }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap justify-center gap-2">
        <button
          v-if="blankCount > 0 && !isRetryResult"
          type="button"
          class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
          :disabled="retrying"
          @click="retry('blank')"
        >
          {{ retrying ? '...' : 'پاسخ به سوالات بدون پاسخ' }}
        </button>
        <button
          v-if="wrongCount > 0 && !isRetryResult"
          type="button"
          class="rounded-lg bg-brand px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
          :disabled="retrying"
          @click="retry('wrong')"
        >
          {{ retrying ? '...' : 'آزمون مجدد سوالات غلط' }}
        </button>
        <button
          type="button"
          class="rounded-lg bg-desk-dark px-3 py-2 text-xs font-bold text-white disabled:opacity-50"
          :disabled="downloading"
          @click="downloadReportCard"
        >
          {{ downloading ? '...' : 'دانلود کارنامه' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-surface-line bg-white px-3 py-2 text-xs font-medium dark:border-slate-700 dark:bg-slate-900"
          @click="shareResult"
        >
          <ShareIcon class="h-4 w-4" />
          اشتراک
        </button>
        <RouterLink
          to="/"
          class="rounded-lg border border-surface-line px-3 py-2 text-xs font-medium dark:border-slate-700"
        >
          بازگشت به صفحه اصلی
        </RouterLink>
        <RouterLink
          to="/exams"
          class="rounded-lg border border-surface-line px-3 py-2 text-xs font-medium dark:border-slate-700"
        >
          آزمون جدید
        </RouterLink>
      </div>

      <p v-if="error" class="text-center text-sm text-brand">{{ error }}</p>
    </div>
    <p v-else class="text-center text-brand">{{ error || 'نتیجه یافت نشد.' }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  CheckIcon,
  CheckCircleIcon,
  MinusCircleIcon,
  QuestionMarkCircleIcon,
  ShareIcon,
  TrophyIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import confetti from 'canvas-confetti'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import StarRating from '../../components/StarRating.vue'
import ExamResultCharts from '../../components/exam/ExamResultCharts.vue'
import { toFaDigits } from '../../utils/format'
import { renderKatexHtml } from '../../utils/renderKatexHtml'
import { optionFaLetter, optionText } from '../../utils/examAnswers'
import { useExamStore } from '../../stores/exam'
import { useExamSessionStore } from '../../stores/examSession'

const route = useRoute()
const router = useRouter()
const examStore = useExamStore()
const session = useExamSessionStore()
const loading = ref(true)
const result = ref<any>(null)
const analysis = ref<any>(null)
const sheet = ref<any[]>([])
const showSheet = ref(true)
const sheetFilter = ref('all')
const reviewMode = ref<string | null>(null)
const downloading = ref(false)
const retrying = ref(false)
const error = ref('')
const rating = ref(0)
const ratingSaving = ref(false)
const ratingMsg = ref('')

const passed = computed(() => Boolean(analysis.value?.passed))
const isRetryResult = computed(
  () =>
    Boolean(result.value?.is_retry_wrong || analysis.value?.is_retry_wrong)
)
const parentAttemptId = computed(
  () => result.value?.parent_attempt_id || analysis.value?.parent_attempt_id || null
)
const retryMode = computed(
  () => result.value?.retry_mode || analysis.value?.retry_mode || 'wrong'
)
const subjects = computed(() => analysis.value?.by_subject || [])
const chartSubjects = computed(() =>
  subjects.value.map((row: any) => ({
    ...row,
    emoji: subjectEmoji(row.subject),
  }))
)
const totalQuestions = computed(() => {
  if (sheet.value.length) return sheet.value.length
  return Number(
    analysis.value?.total_questions ||
      result.value?.exam?.total_questions ||
      0
  )
})
const correctCount = computed(() => {
  if (sheet.value.length)
    return sheet.value.filter((i) => i.is_correct && !i.is_blank).length
  return Number(result.value?.total_correct || analysis.value?.total_correct || 0)
})
const wrongCount = computed(() => {
  if (sheet.value.length)
    return sheet.value.filter((i) => !i.is_blank && !i.is_correct).length
  return Number(result.value?.total_wrong || analysis.value?.total_wrong || 0)
})
const blankCount = computed(() => {
  if (sheet.value.length) return sheet.value.filter((i) => i.is_blank).length
  return Math.max(0, totalQuestions.value - correctCount.value - wrongCount.value)
})
const displayPercentage = computed(() => {
  const total = totalQuestions.value
  if (!total) return Number(result.value?.percentage || analysis.value?.percentage || 0)
  return Math.round((correctCount.value / total) * 10000) / 100
})
const filteredSheet = computed(() => {
  const mode = reviewMode.value || sheetFilter.value
  if (mode === 'blank') return sheet.value.filter((i) => i.is_blank)
  if (mode === 'wrong')
    return sheet.value.filter((i) => !i.is_blank && !i.is_correct)
  if (mode === 'correct')
    return sheet.value.filter((i) => i.is_correct && !i.is_blank)
  return sheet.value
})
const reviewTitle = computed(() => {
  if (reviewMode.value === 'blank') return 'سوالات بدون پاسخ'
  if (reviewMode.value === 'wrong') return 'سوالات غلط'
  if (reviewMode.value === 'correct') return 'گزینه‌های درست'
  return 'مرور پاسخ‌ها'
})
const sheetFilters = computed(() => [
  { id: 'all', label: 'همه', count: sheet.value.length },
  { id: 'correct', label: 'درست', count: correctCount.value },
  { id: 'blank', label: 'بدون پاسخ', count: blankCount.value },
  { id: 'wrong', label: 'غلط', count: wrongCount.value },
])

const stats = computed(() => [
  {
    label: 'کل سوال',
    value: toFaDigits(totalQuestions.value),
    icon: QuestionMarkCircleIcon,
    bg: 'bg-slate-50 dark:bg-slate-800',
  },
  {
    label: 'درست',
    value: toFaDigits(correctCount.value),
    icon: CheckCircleIcon,
    color: 'text-emerald-600',
    bg: 'bg-emerald-50 dark:bg-emerald-900/20',
  },
  {
    label: 'غلط',
    value: toFaDigits(wrongCount.value),
    icon: XCircleIcon,
    color: 'text-brand',
    bg: 'bg-brand-soft dark:bg-brand/10',
  },
  {
    label: 'بدون پاسخ',
    value: toFaDigits(blankCount.value),
    icon: MinusCircleIcon,
    color: 'text-slate-500',
    bg: 'bg-slate-50 dark:bg-slate-800',
  },
  {
    label: 'رتبه',
    value: analysis.value?.rank
      ? `#${toFaDigits(analysis.value.rank)}`
      : '—',
    icon: TrophyIcon,
    color: 'text-amber-600',
    bg: 'bg-amber-50 dark:bg-amber-900/20',
  },
])

function subjectEmoji(slug: string) {
  const map: Record<string, string> = {
    islamic: '📖',
    general: '📚',
    intelligence: '🧠',
    specialized: '🎯',
    math: '🔢',
    language: '🔤',
  }
  return map[slug] || '📘'
}

onMounted(async () => {
  try {
    const examId = route.params.id
    const attemptId = route.params.attemptId
    const [{ data: resultRes }, { data: sheetRes }] = await Promise.all([
      api.get(`/exams/${examId}/result/${attemptId}`),
      api.get(`/exams/${examId}/answer-sheet/${attemptId}`),
    ])
    result.value = resultRes.data?.attempt || resultRes.data
    analysis.value = sheetRes.data?.analysis || null
    sheet.value = sheetRes.data?.sheet || []
    const qf = String(route.query.filter || '')
    if (qf === 'blank' || qf === 'wrong') sheetFilter.value = qf

    if (passed.value) {
      confetti({
        particleCount: 150,
        spread: 70,
        origin: { y: 0.6 },
        colors: ['#ef394e', '#10b981', '#f59e0b', '#0f2744'],
      })
    }
  } catch {
    error.value = 'بارگذاری نتیجه ممکن نشد.'
  } finally {
    loading.value = false
  }
})

function openReview(mode: 'blank' | 'wrong' | 'correct') {
  reviewMode.value = mode
  showSheet.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function retry(mode: 'blank' | 'wrong') {
  retrying.value = true
  error.value = ''
  try {
    const examId = route.params.id
    const attemptId = route.params.attemptId
    const { data } = await api.post(`/exams/${examId}/retry-wrong/${attemptId}`, {
      mode,
    })
    const payload = data.data || {}
    session.resetSessionUx()
    const perPage = Math.max(1, Math.min(20, Number(payload.per_page) || 5))
    session.setQuestionsPerPage(perPage)
    examStore.current = {
      examId: Number(examId),
      attemptId: payload.attempt_id || payload.attempt?.id,
      questions: payload.questions || [],
      duration: payload.duration_minutes || 20,
      title:
        (mode === 'blank' ? 'آزمون سوالات بدون پاسخ · ' : 'مرور سوالات غلط · ') +
        (result.value?.exam?.title || result.value?.exam_title || 'آزمون'),
      perPage,
      isRetryWrong: true,
    }
    examStore.answers = { ...(payload.answers || {}) }
    examStore.dirty = false
    examStore.pageIndex = 0
    examStore.endsAt = payload.end_time
      ? payload.end_time * 1000
      : Date.now() + (payload.duration_minutes || 20) * 60 * 1000
    examStore.saveCache()
    router.push(`/exams/${examId}/take`)
  } catch (e) {
    error.value = e.response?.data?.message || 'شروع مرور ممکن نشد.'
  } finally {
    retrying.value = false
  }
}

async function downloadReportCard() {
  downloading.value = true
  error.value = ''
  try {
    const examId = route.params.id
    const attemptId = route.params.attemptId
    const response = await api.get(
      `/exams/${examId}/report-card/${attemptId}`,
      { responseType: 'blob' }
    )
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `report-card-${examId}-${attemptId}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = 'دانلود کارنامه ممکن نشد.'
  } finally {
    downloading.value = false
  }
}

async function submitRating() {
  if (!rating.value) return
  ratingSaving.value = true
  ratingMsg.value = ''
  try {
    await api.post(`/exams/${route.params.id}/rate`, { rating: rating.value })
    ratingMsg.value = 'امتیاز ثبت شد. متشکریم!'
  } catch (e: any) {
    ratingMsg.value = e.response?.data?.message || 'ثبت امتیاز ممکن نشد.'
  } finally {
    ratingSaving.value = false
  }
}

async function shareResult() {
  const text = `نتیجه آزمون جاب‌آزمون: ${Math.round(displayPercentage.value || 0)}٪ (${correctCount.value} درست از ${totalQuestions.value} سوال)`
  const url = window.location.href
  try {
    if (navigator.share) {
      await navigator.share({ title: 'نتیجه آزمون', text, url })
    } else {
      await navigator.clipboard.writeText(`${text}\n${url}`)
      error.value = ''
      ratingMsg.value = 'لینک نتیجه کپی شد.'
    }
  } catch {
    /* user cancelled */
  }
}
</script>
