<template>
  <div
    class="flex min-h-dvh items-center justify-center bg-surface-page p-4 dark:bg-slate-950"
  >
    <LoadingSpinner v-if="loading" />
    <div v-else-if="exam" class="w-full max-w-2xl">
      <div
        class="overflow-hidden rounded-3xl border border-surface-line bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900"
      >
        <div
          class="relative h-36 bg-desk-dark"
        >
          <div class="absolute inset-0 bg-black/10" />
          <div class="absolute inset-x-0 bottom-0 p-6 text-white">
            <h1 class="text-2xl font-black">{{ exam.title }}</h1>
            <p class="mt-1 text-sm text-white/80">
              {{ exam.category?.name || exam.subject || 'آزمون' }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 p-6 md:grid-cols-4">
          <div
            v-for="item in infoItems"
            :key="item.label"
            class="rounded-2xl bg-slate-50 p-3 text-center dark:bg-slate-800/60"
          >
            <component
              :is="item.icon"
              class="mx-auto h-5 w-5 text-brand"
            />
            <p class="mt-1 text-[11px] text-ink-muted dark:text-slate-400">
              {{ item.label }}
            </p>
            <p class="text-sm font-bold dark:text-white">{{ item.value }}</p>
          </div>
        </div>

        <div class="px-6 pb-4">
          <h3 class="mb-3 font-bold text-ink dark:text-white">قوانین آزمون</h3>
          <ul class="space-y-2">
            <li
              v-for="(rule, i) in rules"
              :key="i"
              class="flex items-start gap-2 text-sm text-ink-soft dark:text-slate-400"
            >
              <span
                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand"
              >
                {{ toFaDigits(i + 1) }}
              </span>
              {{ rule }}
            </li>
          </ul>
        </div>

        <div v-if="subjects.length" class="px-6 pb-4">
          <h3 class="mb-3 font-bold text-ink dark:text-white">درس‌ها</h3>
          <div class="space-y-2">
            <div
              v-for="subject in subjects"
              :key="subject.slug || subject.name"
              class="flex items-center gap-3"
            >
              <span class="flex-1 truncate text-sm dark:text-slate-200">{{
                subject.name || subject.label
              }}</span>
              <span class="text-xs text-ink-muted"
                >{{ toFaDigits(subject.question_count || subject.count || 0) }}
                سوال</span
              >
              <div
                class="h-2 w-24 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
              >
                <div
                  class="h-full rounded-full bg-brand"
                  :style="{
                    width: `${subjectPercent(subject)}%`,
                  }"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="p-6 pt-2">
          <p v-if="error" class="mb-3 text-center text-sm text-brand">
            {{ error }}
          </p>
          <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand py-4 font-bold text-white transition hover:bg-brand-dark disabled:opacity-50"
            :disabled="isStarting"
            @click="startExam"
          >
            <PlayIcon v-if="!isStarting" class="h-6 w-6" />
            <span>{{ isStarting ? 'در حال آماده‌سازی...' : 'شروع آزمون' }}</span>
          </button>
          <p class="mt-3 text-center text-xs text-ink-muted">
            با زدن دکمه شروع، تایمر آغاز می‌شود و قابل توقف نیست.
          </p>
          <RouterLink
            :to="`/exams/${exam.slug}`"
            class="mt-3 block text-center text-sm text-ink-muted hover:text-brand"
          >
            بازگشت به جزئیات
          </RouterLink>
        </div>
      </div>
    </div>
    <p v-else class="text-center text-brand">{{ error || 'آزمون یافت نشد.' }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  CheckCircleIcon,
  ClockIcon,
  PlayIcon,
  QuestionMarkCircleIcon,
  StarIcon,
} from '@heroicons/vue/24/outline'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import { useAuthStore } from '../../stores/auth'
import { useExamStore } from '../../stores/exam'
import { useExamSessionStore } from '../../stores/examSession'
import { toFaDigits } from '../../utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const examStore = useExamStore()
const session = useExamSessionStore()

const exam = ref<any>(null)
const loading = ref(true)
const isStarting = ref(false)
const error = ref('')

const rules = [
  'پس از شروع آزمون، تایمر به صورت خودکار شروع می‌شود و قابل توقف نیست.',
  'بین سوالات می‌توانید جابجا شوید و سوالات را علامت‌گذاری کنید.',
  'پاسخ‌ها به صورت خودکار ذخیره می‌شوند (حتی آفلاین).',
  'خروج از صفحه یا تغییر تب مرورگر ثبت می‌شود.',
  'پس از اتمام زمان، آزمون به صورت خودکار ارسال می‌شود.',
]

const subjects = computed(() => {
  const list =
    exam.value?.subjects ||
    exam.value?.exam_subjects ||
    exam.value?.subject_breakdown ||
    []
  return Array.isArray(list) ? list : []
})

const questionCount = computed(
  () =>
    exam.value?.questions_count ||
    exam.value?.question_count ||
    exam.value?.total_questions ||
    subjects.value.reduce(
      (s: number, x: any) => s + Number(x.question_count || x.count || 0),
      0
    ) ||
    '—'
)

const infoItems = computed(() => [
  {
    label: 'زمان',
    value: `${toFaDigits(exam.value?.duration_minutes || '—')} دقیقه`,
    icon: ClockIcon,
  },
  {
    label: 'تعداد سوالات',
    value: toFaDigits(questionCount.value),
    icon: QuestionMarkCircleIcon,
  },
  {
    label: 'نمره قبولی',
    value: toFaDigits(exam.value?.passing_score ?? '—'),
    icon: CheckCircleIcon,
  },
  {
    label: 'نمره منفی',
    value: exam.value?.has_negative_marking ? 'دارد' : 'ندارد',
    icon: StarIcon,
  },
])

function subjectPercent(subject: any) {
  const total = Number(questionCount.value) || 1
  const count = Number(subject.question_count || subject.count || 0)
  return Math.min(100, Math.round((count / total) * 100))
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/exams/${route.params.slug}`)
    exam.value = data.data
  } catch {
    error.value = 'آزمون یافت نشد.'
  } finally {
    loading.value = false
  }
})

async function startExam() {
  if (!exam.value) return
  const loginPath = {
    path: '/login',
    query: { redirect: `/exams/${exam.value.slug}/start` },
  }
  if (!auth.isAuthenticated) {
    router.push(loginPath)
    return
  }
  isStarting.value = true
  error.value = ''
  try {
    const subject = (route.query.subject as string) || null
    const { data } = await api.post(`/exams/${exam.value.id}/start`, {
      subject,
    })
    const payload = data.data
    session.resetSessionUx()
    examStore.current = {
      examId: exam.value.id,
      attemptId: payload.attempt_id || payload.attempt?.id,
      questions: payload.questions || [],
      duration: exam.value.duration_minutes,
      hasNegativeMarking: Boolean(exam.value.has_negative_marking),
      title: exam.value.title,
    }
    examStore.answers = {}
    examStore.dirty = false
    examStore.pageIndex = 0
    examStore.endsAt = payload.end_time
      ? payload.end_time * 1000
      : Date.now() + exam.value.duration_minutes * 60 * 1000
    examStore.saveCache()
    router.push(`/exams/${exam.value.id}/take`)
  } catch (e: any) {
    if (e.response?.status === 401) {
      router.push(loginPath)
      return
    }
    error.value = e.response?.data?.message || 'شروع آزمون ممکن نشد.'
  } finally {
    isStarting.value = false
  }
}
</script>
