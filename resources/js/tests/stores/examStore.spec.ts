import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useExamStore } from '../../stores/exam'

describe('Exam Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('sets and clears answers with cache', () => {
    const store = useExamStore()
    store.current = {
      examId: 1,
      attemptId: 10,
      questions: [],
      title: 'Test Exam',
    }
    store.setAnswer(1, 'a')

    expect(store.answers[1]).toBe('a')
    expect(store.dirty).toBe(true)
    expect(localStorage.getItem('offline_exam_attempt_1_10')).toBeTruthy()

    store.clearCache()
    expect(store.current).toBeNull()
    expect(store.answers).toEqual({})
    expect(store.dirty).toBe(false)
    expect(localStorage.getItem('offline_exam_attempt_1_10')).toBeNull()
  })

  it('loads cache from localStorage', () => {
    localStorage.setItem(
      'offline_exam_attempt_9_99',
      JSON.stringify({
        current: { examId: 9, attemptId: 99, questions: [] },
        answers: { 2: 'b' },
        endsAt: null,
        dirty: true,
        lastSyncedAt: null,
        pageIndex: 3,
        flagged: [2, 5],
      })
    )

    const store = useExamStore()
    const parsed = store.loadCache(9)

    expect(parsed?.current).toEqual({ examId: 9, attemptId: 99, questions: [] })
    expect(store.answers[2]).toBe('b')
    expect(store.pageIndex).toBe(3)
    expect(store.dirty).toBe(true)
    expect(store.flagged).toEqual([2, 5])
  })

  it('persists flagged questions per attempt', () => {
    const store = useExamStore()
    store.current = { examId: 3, attemptId: 7, questions: [] }
    store.toggleFlag(12)
    store.toggleFlag(15)

    expect(store.isFlagged(12)).toBe(true)
    expect(store.flagged).toEqual([12, 15])

    const raw = localStorage.getItem('offline_exam_attempt_3_7')
    expect(raw).toBeTruthy()
    expect(JSON.parse(raw!).flagged).toEqual([12, 15])
  })

  it('marks synced and updates page', () => {
    const store = useExamStore()
    store.current = { examId: 1, attemptId: 1, questions: [] }
    store.setAnswer(5, 'c')
    store.markSynced()
    expect(store.dirty).toBe(false)
    expect(store.lastSyncedAt).toBeTruthy()

    store.setPage(2)
    expect(store.pageIndex).toBe(2)
  })
})
