import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/api'
import { useExamStore } from '@/stores/exam'
import type { ExamCurrent, ExamQuestion } from '@/types/exam'

export interface StartExamPayload {
  examId: number
  attemptId: number
  title?: string
  duration?: number
  ends_at?: string | number | null
  remaining_seconds?: number
  questions?: ExamQuestion[]
  answers?: Record<string | number, string>
  perPage?: number
}

/**
 * Exam lifecycle store: start → answer → submit.
 * Persisted cache still lives in `useExamStore` (`stores/exam.ts`).
 */
export const useExamLifecycleStore = defineStore('examStore', () => {
  const examCache = useExamStore()
  const starting = ref(false)
  const submitting = ref(false)
  const lastError = ref<string | null>(null)

  const current = computed(() => examCache.current)
  const answers = computed(() => examCache.answers)
  const isActive = computed(() => Boolean(examCache.current?.attemptId))

  function applyStartPayload(payload: StartExamPayload): void {
    const endsAt =
      payload.ends_at != null
        ? Number(payload.ends_at)
        : payload.remaining_seconds != null
          ? Date.now() + Number(payload.remaining_seconds) * 1000
          : null

    examCache.current = {
      examId: payload.examId,
      attemptId: payload.attemptId,
      title: payload.title || examCache.current?.title || '',
      duration: payload.duration,
      questions: payload.questions || [],
      perPage: payload.perPage,
    } satisfies ExamCurrent

    if (payload.answers && Object.keys(payload.answers).length) {
      examCache.answers = { ...payload.answers }
    } else {
      examCache.answers = {}
    }

    examCache.endsAt = endsAt
    examCache.dirty = false
    examCache.saveCache()
  }

  async function startExam(
    examId: number,
    opts: { resume?: boolean; restart?: boolean } = {}
  ): Promise<StartExamPayload> {
    starting.value = true
    lastError.value = null
    try {
      const body: Record<string, unknown> = {}
      if (opts.resume) body.resume = true
      if (opts.restart) body.restart = true

      const { data } = await api.post(`/exams/${examId}/start`, body)
      const payload = (data.data || data) as StartExamPayload & {
        exam_id?: number
        attempt_id?: number
      }

      const normalized: StartExamPayload = {
        examId: Number(payload.examId ?? payload.exam_id ?? examId),
        attemptId: Number(payload.attemptId ?? payload.attempt_id),
        title: payload.title,
        duration: payload.duration,
        ends_at: payload.ends_at,
        remaining_seconds: payload.remaining_seconds,
        questions: payload.questions || [],
        answers: payload.answers || {},
        perPage: payload.perPage,
      }

      applyStartPayload(normalized)
      return normalized
    } catch (e: unknown) {
      const err = e as { response?: { data?: { message?: string } }; message?: string }
      lastError.value =
        err.response?.data?.message || err.message || 'شروع آزمون ممکن نشد.'
      throw e
    } finally {
      starting.value = false
    }
  }

  function saveAnswer(questionId: string | number, value: string): void {
    examCache.setAnswer(questionId, value)
  }

  async function submitExam(): Promise<unknown> {
    const cur = examCache.current
    if (!cur?.examId || !cur?.attemptId) {
      lastError.value = 'آزمون فعالی برای ثبت وجود ندارد.'
      throw new Error(lastError.value)
    }

    submitting.value = true
    lastError.value = null
    try {
      const { data } = await api.post(
        `/exams/${cur.examId}/submit/${cur.attemptId}`,
        { answers: examCache.answers }
      )
      examCache.clearCache()
      return data
    } catch (e: unknown) {
      examCache.saveCache()
      const err = e as { response?: { data?: { message?: string } }; message?: string }
      lastError.value =
        err.response?.data?.message || err.message || 'ثبت آزمون ناموفق بود.'
      throw e
    } finally {
      submitting.value = false
    }
  }

  return {
    starting,
    submitting,
    lastError,
    current,
    answers,
    isActive,
    startExam,
    saveAnswer,
    submitExam,
    applyStartPayload,
  }
})

/** Alias matching requested store name in tests/docs */
export { useExamLifecycleStore as useExamStoreApi }
