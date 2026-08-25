import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { ExamAttemptCache, ExamCurrent } from '@/types/exam'

const LEGACY_KEY = 'offline_exam_attempt'
const KEY_PREFIX = 'offline_exam_attempt_'

function canUseStorage(): boolean {
  try {
    return typeof localStorage !== 'undefined'
  } catch {
    return false
  }
}

function storageKey(
  examId?: number | string,
  attemptId?: number | string
): string | null {
  if (examId == null || attemptId == null) return null
  return `${KEY_PREFIX}${examId}_${attemptId}`
}

function resolveStorageKey(examIdHint?: number | string): string | null {
  const currentMeta =
    currentRef()?.examId != null && currentRef()?.attemptId != null
      ? storageKey(currentRef()!.examId, currentRef()!.attemptId)
      : null
  if (currentMeta) return currentMeta

  if (!canUseStorage()) return null

  try {
    const legacy = localStorage.getItem(LEGACY_KEY)
    if (legacy) return LEGACY_KEY

    if (examIdHint != null) {
      const prefix = `${KEY_PREFIX}${examIdHint}_`
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i)
        if (key?.startsWith(prefix)) return key
      }
    }
  } catch {
    return null
  }

  return null
}

/** Read current from closure-safe ref during module init — set inside store factory. */
let readCurrent: () => ExamCurrent | null = () => null
function currentRef(): ExamCurrent | null {
  return readCurrent()
}

export const useExamStore = defineStore('exam', () => {
  const current = ref<ExamCurrent | null>(null)
  const answers = ref<Record<string | number, string>>({})
  const endsAt = ref<number | string | null>(null)
  const dirty = ref(false)
  const lastSyncedAt = ref<string | null>(null)
  const flagged = ref<number[]>([])
  const offline = ref(
    typeof navigator !== 'undefined' ? !navigator.onLine : false
  )
  const pageIndex = ref(0)

  readCurrent = () => current.value

  function parseCache(raw: string): ExamAttemptCache | null {
    try {
      const parsed = JSON.parse(raw) as ExamAttemptCache
      current.value = parsed.current
      answers.value = parsed.answers || {}
      endsAt.value = parsed.endsAt
      dirty.value = Boolean(parsed.dirty)
      lastSyncedAt.value = parsed.lastSyncedAt || null
      pageIndex.value = parsed.pageIndex || 0
      flagged.value = Array.isArray(parsed.flagged)
        ? parsed.flagged.map(Number).filter((n) => !Number.isNaN(n))
        : []
      return parsed
    } catch {
      return null
    }
  }

  function loadCache(examIdHint?: number | string): ExamAttemptCache | null {
    if (!canUseStorage()) return null

    const key = resolveStorageKey(examIdHint)
    if (!key) return null

    try {
      const raw = localStorage.getItem(key)
      if (!raw) return null
      const parsed = parseCache(raw)

      if (
        key === LEGACY_KEY &&
        current.value?.examId != null &&
        current.value?.attemptId != null
      ) {
        saveCache()
        localStorage.removeItem(LEGACY_KEY)
      }

      return parsed
    } catch {
      return null
    }
  }

  function saveCache(): void {
    if (!canUseStorage()) return

    const key =
      storageKey(current.value?.examId, current.value?.attemptId) || LEGACY_KEY

    try {
      localStorage.setItem(
        key,
        JSON.stringify({
          current: current.value,
          answers: answers.value,
          endsAt: endsAt.value,
          dirty: dirty.value,
          lastSyncedAt: lastSyncedAt.value,
          pageIndex: pageIndex.value,
          flagged: flagged.value,
        } satisfies ExamAttemptCache)
      )
    } catch {
      /* storage full or blocked */
    }
  }

  function clearCache(): void {
    const examIdForCleanup = current.value?.examId
    const key = resolveStorageKey()

    current.value = null
    answers.value = {}
    endsAt.value = null
    dirty.value = false
    lastSyncedAt.value = null
    pageIndex.value = 0
    flagged.value = []

    if (!canUseStorage()) return

    try {
      if (key) localStorage.removeItem(key)
      localStorage.removeItem(LEGACY_KEY)

      if (examIdForCleanup != null) {
        const prefix = `${KEY_PREFIX}${examIdForCleanup}_`
        for (let i = localStorage.length - 1; i >= 0; i--) {
          const k = localStorage.key(i)
          if (k?.startsWith(prefix)) localStorage.removeItem(k)
        }
      }
    } catch {
      /* ignore */
    }
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

  function setFlagged(ids: number[]): void {
    flagged.value = [...ids]
    saveCache()
  }

  function toggleFlag(questionId: number): void {
    if (flagged.value.includes(questionId)) {
      flagged.value = flagged.value.filter((id) => id !== questionId)
    } else {
      flagged.value = [...flagged.value, questionId]
    }
    saveCache()
  }

  function isFlagged(questionId: number): boolean {
    return flagged.value.includes(questionId)
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
    flagged,
    offline,
    pageIndex,
    loadCache,
    saveCache,
    clearCache,
    setAnswer,
    setFlagged,
    toggleFlag,
    isFlagged,
    markSynced,
    setOffline,
    setPage,
  }
})
