import { defineStore } from 'pinia'
import { ref } from 'vue'

const KEY = 'offline_exam_attempt'

export interface ExamAttemptCache {
  current: Record<string, unknown> | null
  answers: Record<string | number, string>
  endsAt: number | string | null
  dirty: boolean
  lastSyncedAt: string | null
  pageIndex: number
}

export const useExamStore = defineStore('exam', () => {
  const current = ref<Record<string, unknown> | null>(null)
  const answers = ref<Record<string | number, string>>({})
  const endsAt = ref<number | string | null>(null)
  const dirty = ref(false)
  const lastSyncedAt = ref<string | null>(null)
  const offline = ref(
    typeof navigator !== 'undefined' ? !navigator.onLine : false
  )
  const pageIndex = ref(0)

  function loadCache(): ExamAttemptCache | null {
    const raw = localStorage.getItem(KEY)
    if (!raw) return null
    try {
      const parsed = JSON.parse(raw) as ExamAttemptCache
      current.value = parsed.current
      answers.value = parsed.answers || {}
      endsAt.value = parsed.endsAt
      dirty.value = Boolean(parsed.dirty)
      lastSyncedAt.value = parsed.lastSyncedAt || null
      pageIndex.value = parsed.pageIndex || 0
      return parsed
    } catch {
      return null
    }
  }

  function saveCache(): void {
    localStorage.setItem(
      KEY,
      JSON.stringify({
        current: current.value,
        answers: answers.value,
        endsAt: endsAt.value,
        dirty: dirty.value,
        lastSyncedAt: lastSyncedAt.value,
        pageIndex: pageIndex.value,
      })
    )
  }

  function clearCache(): void {
    current.value = null
    answers.value = {}
    endsAt.value = null
    dirty.value = false
    lastSyncedAt.value = null
    pageIndex.value = 0
    localStorage.removeItem(KEY)
  }

  function setPage(index: number): void {
    pageIndex.value = index
    saveCache()
  }

  function setAnswer(questionId: string | number, value: string): void {
    answers.value = { ...answers.value, [questionId]: value }
    dirty.value = true
    saveCache()
  }

  function markSynced(): void {
    dirty.value = false
    lastSyncedAt.value = new Date().toISOString()
    saveCache()
  }

  function setOffline(value: boolean): void {
    offline.value = value
  }

  return {
    current,
    answers,
    endsAt,
    dirty,
    lastSyncedAt,
    offline,
    pageIndex,
    loadCache,
    saveCache,
    clearCache,
    setAnswer,
    markSynced,
    setOffline,
    setPage,
  }
})
