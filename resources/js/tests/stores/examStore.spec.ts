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
    store.current = { id: 1, title: 'Test Exam' }
    store.setAnswer(1, 'a')

    expect(store.answers[1]).toBe('a')
    expect(store.dirty).toBe(true)
    expect(localStorage.getItem('offline_exam_attempt')).toBeTruthy()

    store.clearCache()
    expect(store.current).toBeNull()
    expect(store.answers).toEqual({})
    expect(store.dirty).toBe(false)
    expect(localStorage.getItem('offline_exam_attempt')).toBeNull()
  })

  it('loads cache from localStorage', () => {
    localStorage.setItem(
      'offline_exam_attempt',
      JSON.stringify({
        current: { id: 9 },
        answers: { 2: 'b' },
        endsAt: null,
        dirty: true,
        lastSyncedAt: null,
        pageIndex: 3,
      })
    )

    const store = useExamStore()
    const parsed = store.loadCache()

    expect(parsed?.current).toEqual({ id: 9 })
    expect(store.answers[2]).toBe('b')
    expect(store.pageIndex).toBe(3)
    expect(store.dirty).toBe(true)
  })

  it('marks synced and updates page', () => {
    const store = useExamStore()
    store.setAnswer(5, 'c')
    store.markSynced()
    expect(store.dirty).toBe(false)
    expect(store.lastSyncedAt).toBeTruthy()

    store.setPage(2)
    expect(store.pageIndex).toBe(2)
  })
})
