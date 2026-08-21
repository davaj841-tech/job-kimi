import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { ExamQuestion } from '@/types/exam'
import { useExamStore } from './exam'

/**
 * Session UX layer on top of the persisted exam cache store.
 * HTTP autosave/submit stays in the take view (existing API contract).
 */
export const useExamSessionStore = defineStore('examSession', () => {
  const examStore = useExamStore()

  const tabSwitchCount = ref(0)
  const isFullscreen = ref(false)
  const showAnswerSheet = ref(false)
  const showExitConfirm = ref(false)
  const showSubmitConfirm = ref(false)
  /** null = all subjects */
  const subjectFilter = ref<string | null>(null)
  const remainingOnly = ref(false)
  /** question text scale: 0.9 | 1 | 1.15 | 1.3 */
  const fontScale = ref(1)

  const questions = computed(
    () => (examStore.current?.questions as ExamQuestion[]) || []
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

  const subjectFiltered = computed(() => {
    if (!subjectFilter.value) return questions.value
    return questions.value.filter(
      (q) => String(q.subject || 'general') === subjectFilter.value
    )
  })

  const unansweredInFilter = computed(() =>
    subjectFiltered.value.filter((q) => {
      const a = examStore.answers?.[q.id] ?? examStore.answers?.[String(q.id)]
      return a === null || a === undefined || a === ''
    })
  )

  const filteredQuestions = computed(() => {
    if (remainingOnly.value) return unansweredInFilter.value
    return subjectFiltered.value
  })

  const currentIndex = computed({
    get: () => examStore.pageIndex,
    set: (v: number) => examStore.setPage(v),
  })

  const questionsPerPage = ref(5)

  function setQuestionsPerPage(n: number): void {
    const v = Math.max(1, Math.min(20, Number(n) || 5))
    questionsPerPage.value = v
  }

  const currentQuestion = computed(
    () => questions.value[currentIndex.value] || null
  )

  const pageStart = computed(
    () =>
      Math.floor(filteredLocalIndex.value / questionsPerPage.value) *
      questionsPerPage.value
  )

  const pageQuestions = computed(() =>
    filteredQuestions.value.slice(
      pageStart.value,
      pageStart.value + questionsPerPage.value
    )
  )

  const totalPages = computed(() =>
    Math.max(
      1,
      Math.ceil(
        (filteredQuestions.value.length || 1) / questionsPerPage.value
      )
    )
  )

  const currentPage = computed(() =>
    Math.floor(filteredLocalIndex.value / questionsPerPage.value)
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

  const flaggedCount = computed(() => examStore.flagged.length)
  const progressPercent = computed(() => {
    const total = questions.value.length || 1
    return Math.round((answeredCount.value / total) * 100)
  })
  const isLastQuestion = computed(
    () => currentIndex.value >= questions.value.length - 1
  )
  const isFirstQuestion = computed(() => currentIndex.value <= 0)
  const isLastInFilter = computed(
    () =>
      pageStart.value + questionsPerPage.value >=
      filteredQuestions.value.length
  )
  const isFirstInFilter = computed(() => pageStart.value <= 0)

  function isFlagged(questionId: number): boolean {
    return examStore.isFlagged(questionId)
  }

  function toggleFlag(questionId: number): void {
    examStore.toggleFlag(questionId)
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
    remainingOnly.value = false
    const list = !slug
      ? questions.value
      : questions.value.filter(
          (q) => String(q.subject || 'general') === slug
        )
    if (list.length) {
      navigateToQuestionId(list[0].id)
    }
  }

  function toggleRemaining(): void {
    remainingOnly.value = !remainingOnly.value
    const list = remainingOnly.value
      ? unansweredInFilter.value
      : subjectFiltered.value
    if (list.length) navigateToQuestionId(list[0].id)
  }

  function next(): void {
    const list = filteredQuestions.value
    const target = pageStart.value + questionsPerPage.value
    if (target < list.length) {
      navigateToQuestionId(list[target].id)
      return
    }
    navigateTo(currentIndex.value + 1)
  }

  function prev(): void {
    const list = filteredQuestions.value
    const target = pageStart.value - questionsPerPage.value
    if (target >= 0 && list[target]) {
      navigateToQuestionId(list[target].id)
      return
    }
    navigateTo(currentIndex.value - 1)
  }

  function goToUnanswered(): void {
    toggleRemaining()
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
    examStore.setFlagged([])
    tabSwitchCount.value = 0
    isFullscreen.value = false
    showAnswerSheet.value = false
    showExitConfirm.value = false
    showSubmitConfirm.value = false
    subjectFilter.value = null
    remainingOnly.value = false
    fontScale.value = 1
  }

  return {
    examStore,
    tabSwitchCount,
    isFullscreen,
    showAnswerSheet,
    showExitConfirm,
    showSubmitConfirm,
    subjectFilter,
    remainingOnly,
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
    questionsPerPage,
    setQuestionsPerPage,
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
    toggleRemaining,
    next,
    prev,
    goToUnanswered,
    bumpFont,
    recordTabSwitch,
    resetSessionUx,
  }
})
