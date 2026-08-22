import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useExamLifecycleStore } from '@/stores/examStore'
import { useExamStore } from '@/stores/exam'

const post = vi.fn()

vi.mock('@/api', () => ({
  default: {
    post: (...args: unknown[]) => post(...args),
    get: vi.fn(),
    put: vi.fn(),
  },
}))

describe('examStore (lifecycle)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    post.mockReset()
  })

  it('شروع آزمون: پاسخ API را در کش اعمال می‌کند', async () => {
    post.mockResolvedValueOnce({
      data: {
        data: {
          examId: 11,
          attemptId: 99,
          title: 'آزمون نمونه',
          duration: 60,
          remaining_seconds: 3600,
          questions: [{ id: 1, text: 'Q1' }],
          answers: {},
        },
      },
    })

    const store = useExamLifecycleStore()
    const result = await store.startExam(11)

    expect(post).toHaveBeenCalledWith('/exams/11/start', {})
    expect(result.attemptId).toBe(99)
    expect(store.current?.examId).toBe(11)
    expect(store.current?.attemptId).toBe(99)
    expect(store.isActive).toBe(true)
    expect(useExamStore().endsAt).toBeTruthy()
  })

  it('ذخیره پاسخ: answer را dirty و کش می‌کند', async () => {
    const lifecycle = useExamLifecycleStore()
    lifecycle.applyStartPayload({
      examId: 5,
      attemptId: 50,
      title: 'T',
      questions: [],
    })

    lifecycle.saveAnswer(3, 'b')

    expect(lifecycle.answers[3]).toBe('b')
    expect(useExamStore().dirty).toBe(true)
    expect(localStorage.getItem('offline_exam_attempt_5_50')).toBeTruthy()
  })

  it('submit: پاسخ‌ها را می‌فرستد و کش را پاک می‌کند', async () => {
    post.mockResolvedValueOnce({
      data: { success: true, message: 'ثبت شد' },
    })

    const lifecycle = useExamLifecycleStore()
    lifecycle.applyStartPayload({
      examId: 8,
      attemptId: 80,
      questions: [],
    })
    lifecycle.saveAnswer(1, 'a')

    const data = await lifecycle.submitExam()

    expect(post).toHaveBeenCalledWith('/exams/8/submit/80', {
      answers: { 1: 'a' },
    })
    expect(data).toEqual({ success: true, message: 'ثبت شد' })
    expect(lifecycle.current).toBeNull()
    expect(lifecycle.isActive).toBe(false)
    expect(localStorage.getItem('offline_exam_attempt_8_80')).toBeNull()
  })

  it('submit بدون آزمون فعال خطا می‌دهد', async () => {
    const lifecycle = useExamLifecycleStore()
    await expect(lifecycle.submitExam()).rejects.toThrow(/آزمون فعالی/)
    expect(post).not.toHaveBeenCalled()
  })
})
