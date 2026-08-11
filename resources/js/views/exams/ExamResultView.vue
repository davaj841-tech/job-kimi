<template>
  <div class="min-h-dvh bg-surface-page px-4 py-8 dark:bg-slate-950">
    <LoadingSpinner v-if="loading" />
    <div v-else-if="result" class="mx-auto max-w-4xl space-y-6">
      <div
        class="overflow-hidden rounded-3xl border border-surface-line bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900"
      >
        <div
          class="relative flex h-48 items-center justify-center bg-gradient-to-br from-desk-dark via-desk-blue to-brand"
        >
          <div class="relative z-10 text-center text-white">
            <p class="mb-2 text-sm text-white/80">نمره شما</p>
            <div class="text-6xl font-black sm:text-7xl">
              {{ toFaDigits(Math.round(result.percentage || 0)) }}٪
            </div>
            <p class="mt-2 text-sm text-white/80">
              نمره {{ toFaDigits(result.score) }}
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

        <div class="grid grid-cols-2 gap-3 p-5 md:grid-cols-4">
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
        v-if="subjects.length"
        class="rounded-2xl border border-surface-line bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
      >
        <h2 class="mb-4 text-lg font-bold dark:text-white">تحلیل درس‌ها</h2>
        <div class="space-y-4">
          <div
            v-for="row in subjects"
            :key="row.subject"
            class="flex items-center gap-3"
          >
            <span
              class="w-24 truncate text-sm font-medium dark:text-slate-200"
              >{{ row.subject_label }}</span
            >
            <div
              class="relative h-8 flex-1 overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-800"
            >
              <div
                class="h-full rounded-lg transition-all duration-1000"
                :class="
                  (row.percentage ?? 0) >= 70
                    ? 'bg-emerald-500'
                    : (row.percentage ?? 0) >= 50
                      ? 'bg-amber-500'
                      : 'bg-brand'
                "
                :style="{ width: `${row.percentage ?? 0}%` }"
              />
              <span
                class="absolute inset-0 flex items-center px-2 text-xs font-medium"
                :class="
                  (row.percentage ?? 0) > 45
                    ? 'text-white'
                    : 'text-ink dark:text-slate-200'
                "
              >
                {{ toFaDigits(row.correct) }}/{{ toFaDigits(row.total) }} ({{
                  toFaDigits(Math.round(row.percentage ?? 0))
                }}٪)
              </span>
            </div>
          </div>
        </div>
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
          <button
            type="button"
            class="text-sm font-bold text-brand"
            @click="showSheet = !showSheet"
          >
            {{ showSheet ? 'بستن' : 'نمایش پاسخبرگ' }}
          </button>
        </div>
        <div v-if="showSheet" class="space-y-3">
          <div
            v-for="item in sheet"
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
                  {{ String(item.correct_answer || '').toUpperCase() }}
                </p>
                <p
                  v-if="!item.is_correct"
                  class="text-sm text-brand dark:text-red-300"
                >
                  پاسخ شما:
                  {{
                    item.user_answer
                      ? String(item.user_answer).toUpperCase()
                      : 'نزده'
                  }}
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

      <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
        <button
          type="button"
          class="rounded-2xl bg-brand px-8 py-3 font-bold text-white hover:bg-brand-dark disabled:opacity-50"
          :disabled="downloading"
          @click="downloadReportCard"
        >
          {{ downloading ? '...' : 'دانلود کارنامه PDF' }}
        </button>
        <button
          type="button"
          class="flex items-center justify-center gap-2 rounded-2xl border border-surface-line bg-white px-8 py-3 font-medium dark:border-slate-700 dark:bg-slate-900"
          @click="shareResult"
        >
          <ShareIcon class="h-5 w-5" />
          اشتراک‌گذاری
        </button>
        <RouterLink
          to="/exams"
          class="rounded-2xl border border-surface-line px-8 py-3 text-center font-medium dark:border-slate-700"
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
import { useRoute } from 'vue-router'
import {
  CheckIcon,
  CheckCircleIcon,
  MinusCircleIcon,
  ShareIcon,
  TrophyIcon,
  XCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import confetti from 'canvas-confetti'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import StarRating from '../../components/StarRating.vue'
import { toFaDigits } from '../../utils/format'
import { renderKatexHtml } from '../../utils/renderKatexHtml'

const route = useRoute()
const loading = ref(true)
const result = ref<any>(null)
const analysis = ref<any>(null)
const sheet = ref<any[]>([])
const showSheet = ref(true)
const downloading = ref(false)
const error = ref('')
const rating = ref(0)
const ratingSaving = ref(false)
const ratingMsg = ref('')

const passed = computed(() => Boolean(analysis.value?.passed))
const subjects = computed(() => analysis.value?.by_subject || [])
const totalCount = computed(
  () => (result.value?.total_correct || 0) + (result.value?.total_wrong || 0)
)
const blankCount = computed(() => {
  const total = analysis.value?.total_questions ?? totalCount.value
  return Math.max(
    0,
    total -
      (result.value?.total_correct || 0) -
      (result.value?.total_wrong || 0)
  )
})

const stats = computed(() => [
  {
    label: 'درست',
    value: toFaDigits(result.value?.total_correct),
    icon: CheckCircleIcon,
    color: 'text-emerald-600',
    bg: 'bg-emerald-50 dark:bg-emerald-900/20',
  },
  {
    label: 'غلط',
    value: toFaDigits(result.value?.total_wrong),
    icon: XCircleIcon,
    color: 'text-brand',
    bg: 'bg-brand-soft dark:bg-brand/10',
  },
  {
    label: 'نزده',
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
  const text = `نتیجه آزمون جاب‌آزمون: ${Math.round(result.value?.percentage || 0)}٪`
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
