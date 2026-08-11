import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useExamStore } from './exam'

/**
 * Session UX layer on top of the persisted exam cache store.
 * HTTP autosave/submit stays in the take view (existing API contract).
 */
export const useExamSessionStore = defineStore('examSession', () => {
  const examStore = useExamStore()

  const flagged = ref<number[]>([])
  const tabSwitchCount = ref(0)
  const isFullscreen = ref(false)
  const showAnswerSheet = ref(false)
  const showExitConfirm = ref(false)
  const showSubmitConfirm = ref(false)
  /** null = all subjects */
  const subjectFilter = ref<string | null>(null)
  /** question text scale: 0.9 | 1 | 1.15 | 1.3 */
  const fontScale = ref(1)

  const questions = computed(
    () => (examStore.current?.questions as any[]) || []
  )

  const subjectTabs = computed(() => {
    const map = new Map<string, { slug: string; label: string; count: number }>()
    for (const q of questions.value) {
      const slug = String(q.subject || 'general')
      const label = String(q.subject_name || q.subject_label || q.subject || 'عمومی')
      const cur = map.get(slug)
      if (cur) cur.count += 1
      else map.set(slug, { slug, label, count: 1 })
    }
    return Array.from(map.values())
  })

  const filteredQuestions = computed(() => {
    if (!subjectFilter.value) return questions.value
    return questions.value.filter(
      (q) => String(q.subject || 'general') === subjectFilter.value
    )
  })

  const currentIndex = computed({
    get: () => examStore.pageIndex,
    set: (v: number) => examStore.setPage(v),
  })

  const QUESTIONS_PER_PAGE = 5

  const currentQuestion = computed(
    () => questions.value[currentIndex.value] || null
  )

  const pageStart = computed(
    () => Math.floor(filteredLocalIndex.value / QUESTIONS_PER_PAGE) * QUESTIONS_PER_PAGE
  )

  const pageQuestions = computed(() =>
    filteredQuestions.value.slice(pageStart.value, pageStart.value + QUESTIONS_PER_PAGE)
  )

  const totalPages = computed(() =>
    Math.max(1, Math.ceil((filteredQuestions.value.length || 1) / QUESTIONS_PER_PAGE))
  )

  const currentPage = computed(() =>
    Math.floor(filteredLocalIndex.value / QUESTIONS_PER_PAGE)
  )

  const filteredLocalIndex = computed(() => {
    const id = currentQuestion.value?.id
    if (id == null) return 0
    const idx = filteredQuestions.value.findIndex((q) => q.id === id)
    return idx >= 0 ? idx : 0
  })

  const answeredCount = computed(
    () =>
      Object.values(examStore.answers || {}).filter(
        (v) => v !== null && v !== undefined && v !== ''
      ).length
  )

  const unansweredInFilter = computed(() =>
    filteredQuestions.value.filter((q) => {
      const a = examStore.answers?.[q.id]
      return a === null || a === undefined || a === ''
    })
  )

  const flaggedCount = computed(() => flagged.value.length)
  const progressPercent = computed(() => {
    const total = questions.value.length || 1
    return Math.round((answeredCount.value / total) * 100)
  })
  const isLastQuestion = computed(
    () => currentIndex.value >= questions.value.length - 1
  )
  const isFirstQuestion = computed(() => currentIndex.value <= 0)
  const isLastInFilter = computed(
    () => pageStart.value + QUESTIONS_PER_PAGE >= filteredQuestions.value.length
  )
  const isFirstInFilter = computed(() => pageStart.value <= 0)

  function isFlagged(questionId: number): boolean {
    return flagged.value.includes(questionId)
  }

  function toggleFlag(questionId: number): void {
    if (flagged.value.includes(questionId)) {
      flagged.value = flagged.value.filter((id) => id !== questionId)
    } else {
      flagged.value = [...flagged.value, questionId]
    }
  }

  function navigateTo(index: number): void {
    if (index >= 0 && index < questions.value.length) {
      examStore.setPage(index)
    }
  }

  function navigateToQuestionId(id: number): void {
    const idx = questions.value.findIndex((q) => q.id === id)
    if (idx >= 0) navigateTo(idx)
  }

  function setSubjectFilter(slug: string | null): void {
    subjectFilter.value = slug
    const list = !slug
      ? questions.value
      : questions.value.filter(
          (q) => String(q.subject || 'general') === slug
        )
    if (list.length) {
      navigateToQuestionId(list[0].id)
    }
  }

  function next(): void {
    const list = filteredQuestions.value
    const target = pageStart.value + QUESTIONS_PER_PAGE
    if (target < list.length) {
      navigateToQuestionId(list[target].id)
      return
    }
    navigateTo(currentIndex.value + 1)
  }

  function prev(): void {
    const list = filteredQuestions.value
    const target = pageStart.value - QUESTIONS_PER_PAGE
    if (target >= 0 && list[target]) {
      navigateToQuestionId(list[target].id)
      return
    }
    navigateTo(currentIndex.value - 1)
  }

  function skip(): void {
    next()
  }

  function goToUnanswered(): void {
    const list = unansweredInFilter.value
    if (!list.length) return
    const currentId = currentQuestion.value?.id
    const after = list.findIndex((q) => q.id === currentId)
    const target =
      after >= 0 && after < list.length - 1 ? list[after + 1] : list[0]
    if (target) navigateToQuestionId(target.id)
  }

  function bumpFont(delta: number): void {
    const steps = [0.9, 1, 1.15, 1.3]
    const i = steps.findIndex((s) => Math.abs(s - fontScale.value) < 0.01)
    const next = steps[Math.min(steps.length - 1, Math.max(0, (i < 0 ? 1 : i) + delta))]
    fontScale.value = next
  }

  function recordTabSwitch(): void {
    tabSwitchCount.value += 1
  }

  function resetSessionUx(): void {
    flagged.value = []
    tabSwitchCount.value = 0
    isFullscreen.value = false
    showAnswerSheet.value = false
    showExitConfirm.value = false
    showSubmitConfirm.value = false
    subjectFilter.value = null
    fontScale.value = 1
  }

  return {
    examStore,
    flagged,
    tabSwitchCount,
    isFullscreen,
    showAnswerSheet,
    showExitConfirm,
    showSubmitConfirm,
    subjectFilter,
    fontScale,
    questions,
    subjectTabs,
    filteredQuestions,
    currentIndex,
    currentQuestion,
    pageQuestions,
    totalPages,
    currentPage,
    pageStart,
    questionsPerPage: QUESTIONS_PER_PAGE,
    filteredLocalIndex,
    answeredCount,
    unansweredInFilter,
    flaggedCount,
    progressPercent,
    isLastQuestion,
    isFirstQuestion,
    isLastInFilter,
    isFirstInFilter,
    isFlagged,
    toggleFlag,
    navigateTo,
    navigateToQuestionId,
    setSubjectFilter,
    next,
    prev,
    skip,
    goToUnanswered,
    bumpFont,
    recordTabSwitch,
    resetSessionUx,
  }
})
