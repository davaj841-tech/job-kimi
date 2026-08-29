<template>
  <div class="exam-room flex min-h-dvh flex-col">
    <header class="exam-header sticky top-0 z-50">
      <div
        class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-3 sm:h-[3.75rem] sm:px-5"
      >
        <button
          type="button"
          class="exam-icon-btn"
          aria-label="خروج"
          @click="session.showExitConfirm = true"
        >
          <XMarkIcon class="h-5 w-5" />
        </button>

        <div class="min-w-0 flex-1">
          <div
            class="mb-1 flex items-center justify-between gap-2 text-[11px] sm:text-xs"
          >
            <span class="truncate font-medium text-[#0f2744]">{{
              examTitle
            }}</span>
            <span class="shrink-0 tabular-nums text-slate-500">
              {{ toFaDigits(session.answeredCount) }}/{{
                toFaDigits(session.questions.length)
              }}
            </span>
          </div>
          <div class="h-1.5 overflow-hidden rounded-full bg-slate-200/80">
            <div
              class="h-full rounded-full bg-[#ef394e] transition-all duration-500"
              :style="{ width: `${session.progressPercent}%` }"
            />
          </div>
        </div>

        <div class="exam-timer shrink-0" :class="timerClass">
          <ClockIcon class="h-4 w-4" />
          <span class="font-mono text-sm font-bold tabular-nums sm:text-base">{{
            timerText
          }}</span>
        </div>

        <button
          type="button"
          class="exam-submit-btn"
          @click="session.showSubmitConfirm = true"
        >
          اتمام
        </button>
      </div>

      <div
        class="mx-auto max-w-6xl border-t border-slate-200/70 px-3 py-2 sm:px-5"
      >
        <div
          class="scrollbar-hide -mx-0.5 flex gap-1.5 overflow-x-auto px-0.5 pb-0.5"
        >
          <button
            type="button"
            class="exam-chip"
            :class="session.subjectFilter === null ? 'exam-chip-active' : ''"
            @click="session.setSubjectFilter(null)"
          >
            همه
          </button>
          <button
            v-for="tab in session.subjectTabs"
            :key="tab.slug"
            type="button"
            class="exam-chip"
            :class="session.subjectFilter === tab.slug ? 'exam-chip-brand' : ''"
            @click="session.setSubjectFilter(tab.slug)"
          >
            {{ tab.label }}
            <span class="opacity-60">{{ toFaDigits(tab.count) }}</span>
          </button>
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-1.5">
          <button
            type="button"
            class="exam-tool"
            :class="session.remainingOnly ? 'exam-tool-on' : ''"
            :disabled="
              !session.unansweredInFilter.length && !session.remainingOnly
            "
            @click="session.toggleRemaining()"
          >
            مانده‌ها
            <span
              v-if="session.unansweredInFilter.length"
              class="rounded bg-amber-100 px-1.5 text-[10px] text-amber-800"
              >{{ toFaDigits(session.unansweredInFilter.length) }}</span
            >
          </button>
          <button
            type="button"
            class="exam-tool"
            @click="session.showAnswerSheet = true"
          >
            پاسخبرگ
          </button>
          <div
            class="mr-auto flex overflow-hidden rounded-lg border border-slate-200 bg-white"
          >
            <button
              type="button"
              class="exam-font-btn"
              aria-label="کوچک‌تر"
              @click="session.bumpFont(-1)"
            >
              −
            </button>
            <span
              class="border-x border-slate-200 px-2 py-1 text-[10px] text-slate-400"
              >قلم</span
            >
            <button
              type="button"
              class="exam-font-btn"
              aria-label="بزرگ‌تر"
              @click="session.bumpFont(1)"
            >
              +
            </button>
          </div>
        </div>
      </div>
    </header>

    <main
      class="mx-auto grid w-full max-w-6xl flex-1 grid-cols-1 gap-4 px-3 py-4 pb-28 sm:px-5 lg:grid-cols-[minmax(0,1fr)_220px] lg:pb-4"
      @touchstart.passive="onTouchStart"
      @touchend.passive="onTouchEnd"
    >
      <section class="space-y-4">
        <p class="text-xs font-bold text-[#ef394e]">
          صفحه {{ toFaDigits(session.currentPage + 1) }} از
          {{ toFaDigits(session.totalPages) }}
          <span class="font-medium text-slate-400">
            · سوال {{ toFaDigits(session.pageStart + 1) }}
            تا
            {{ toFaDigits(session.pageStart + session.pageQuestions.length) }}
            از {{ toFaDigits(session.filteredQuestions.length) }}
          </span>
        </p>

        <article
          v-for="(item, localIdx) in session.pageQuestions"
          :id="`exam-q-${item.id}`"
          :key="item.id"
          class="exam-paper scroll-mt-4"
        >
          <div class="mb-4 flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-bold tracking-wide text-[#ef394e]">
                سوال {{ toFaDigits(session.pageStart + localIdx + 1) }}
              </p>
              <p v-if="item.subject" class="mt-1 text-sm text-slate-500">
                {{ item.subject_name || item.subject }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 transition"
              :class="
                session.isFlagged(item.id)
                  ? 'bg-amber-100 text-amber-700'
                  : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'
              "
              aria-label="علامت‌گذاری"
              @click="session.toggleFlag(item.id)"
            >
              <BookmarkIcon
                class="h-5 w-5"
                :class="session.isFlagged(item.id) ? 'fill-current' : ''"
              />
            </button>
          </div>

          <div
            class="exam-question mb-5 text-[#0f172a]"
            :style="{
              fontSize: `${1.08 * session.fontScale}rem`,
              lineHeight: 1.9,
            }"
            v-html="renderKatexHtml(item.question_text)"
          />

          <div class="space-y-2.5">
            <button
              v-for="(opt, optIdx) in optionsFor(item)"
              :key="opt.key"
              type="button"
              class="exam-option"
              :class="
                examStore.answers[item.id] === opt.key
                  ? 'exam-option-selected'
                  : ''
              "
              @click="selectAnswer(item.id, opt.key)"
            >
              <span
                class="exam-option-letter"
                :class="
                  examStore.answers[item.id] === opt.key
                    ? 'exam-option-letter-on'
                    : ''
                "
              >
                <CheckIcon
                  v-if="examStore.answers[item.id] === opt.key"
                  class="h-4 w-4"
                />
                <template v-else>{{ PERSIAN_LETTERS[optIdx] }}</template>
              </span>
              <span
                class="flex-1 text-sm leading-7 text-slate-800"
                :style="{ fontSize: `${0.95 * session.fontScale}rem` }"
                v-html="renderKatexHtml(opt.label)"
              />
            </button>
          </div>
        </article>

        <div
          class="exam-action-bar sticky bottom-3 z-30 flex items-center gap-2 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-lg backdrop-blur lg:static lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none"
        >
          <button
            type="button"
            class="exam-nav-btn"
            :disabled="session.isFirstInFilter"
            @click="goPrevInFilter"
          >
            <ArrowRightIcon class="h-5 w-5" />
            قبلی
          </button>
          <button
            type="button"
            class="exam-nav-btn"
            :class="session.isLastInFilter ? '' : 'exam-nav-btn-primary'"
            :disabled="session.isLastInFilter"
            @click="goNextInFilter"
          >
            بعدی
            <ArrowLeftIcon class="h-5 w-5" />
          </button>
          <button
            type="button"
            class="exam-nav-btn exam-nav-btn-submit"
            @click="session.showSubmitConfirm = true"
          >
            ثبت آزمون
          </button>
        </div>
      </section>

      <aside class="hidden lg:block">
        <div
          class="exam-nav-panel sticky top-4 max-h-[calc(100dvh-6rem)] overflow-y-auto"
        >
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-bold text-[#0f2744]">پیمایش</h3>
            <button
              type="button"
              class="text-xs font-bold text-[#ef394e] hover:underline"
              @click="session.showAnswerSheet = true"
            >
              بررسی
            </button>
          </div>
          <div class="grid grid-cols-5 gap-1.5">
            <button
              v-for="(item, idx) in session.filteredQuestions"
              :key="item.id"
              type="button"
              class="relative h-9 rounded-md text-sm font-medium transition"
              :class="navigatorClass(item)"
              :data-nav-id="item.id"
              @click="jumpTo(item.id)"
            >
              {{ toFaDigits(idx + 1) }}
              <span
                v-if="session.isFlagged(item.id)"
                class="absolute -left-0.5 -top-0.5 h-2 w-2 rounded-full bg-amber-500"
              />
            </button>
          </div>
          <ul
            class="mt-4 space-y-2 border-t border-slate-200 pt-3 text-[11px] text-slate-500"
          >
            <li class="flex items-center gap-2">
              <span class="h-2.5 w-2.5 rounded bg-[#ef394e]" /> پاسخ‌داده‌شده
            </li>
            <li class="flex items-center gap-2">
              <span class="h-2.5 w-2.5 rounded bg-amber-500" /> علامت‌گذاری
            </li>
            <li class="flex items-center gap-2">
              <span class="h-2.5 w-2.5 rounded bg-slate-200" /> بدون پاسخ
            </li>
          </ul>
        </div>
      </aside>
    </main>

    <div class="exam-sync">
      <span v-if="examStore.offline" class="font-medium text-amber-700"
        >آفلاین — ذخیره محلی</span
      >
      <template v-else>
        <CloudArrowUpIcon
          v-if="syncing"
          class="h-4 w-4 animate-pulse text-[#ef394e]"
        />
        <CheckCircleIcon v-else class="h-4 w-4 text-emerald-600" />
        <span>{{
          syncing
            ? 'در حال ذخیره...'
            : examStore.lastSyncedAt
              ? 'ذخیره شد'
              : 'آماده'
        }}</span>
      </template>
    </div>

    <p
      v-if="submitError"
      class="fixed inset-x-0 bottom-20 z-40 px-4 text-center text-xs text-[#ef394e]"
    >
      {{ submitError }}
    </p>

    <AnswerSheetModal
      v-model="session.showAnswerSheet"
      @submit="session.showSubmitConfirm = true"
    />
    <ExitConfirmModal v-model="session.showExitConfirm" @confirm="exitExam" />
    <SubmitConfirmModal
      v-model="session.showSubmitConfirm"
      :unanswered="
        Math.max(0, session.questions.length - session.answeredCount)
      "
      :flagged="session.flaggedCount"
      :loading="submitting"
      @confirm="submit"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ArrowRightIcon,
  BookmarkIcon,
  CheckCircleIcon,
  CheckIcon,
  ClockIcon,
  CloudArrowUpIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { useMagicKeys } from '@vueuse/core'
import screenfull from 'screenfull'
import api from '../../api/client'
import AnswerSheetModal from '../../components/exam/AnswerSheetModal.vue'
import ExitConfirmModal from '../../components/exam/ExitConfirmModal.vue'
import SubmitConfirmModal from '../../components/exam/SubmitConfirmModal.vue'
import { useExamStore } from '../../stores/exam'
import { useExamSessionStore } from '../../stores/examSession'
import type { ExamQuestion, AutosaveResponse } from '@/types/exam'
import { toFaDigits, apiErrorMessage } from '../../utils/format'
import { renderKatexHtml } from '../../utils/renderKatexHtml'

const route = useRoute()
const router = useRouter()
const examStore = useExamStore()
const session = useExamSessionStore()

const PERSIAN_LETTERS = ['الف', 'ب', 'ج', 'د']
const submitting = ref(false)
const submitError = ref('')
const syncing = ref(false)
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | undefined
let debounceTimer: ReturnType<typeof setTimeout> | undefined
let intervalTimer: ReturnType<typeof setInterval> | undefined
let pendingSync = false
let touchX = 0

const q = computed(() => session.pageQuestions[0] || session.currentQuestion)
const examTitle = computed(() => examStore.current?.title || 'آزمون')

watch(
  () => session.currentQuestion?.id,
  (id) => {
    if (!id) return
    nextTick(() => {
      document
        .querySelector(`[data-nav-id="${id}"]`)
        ?.scrollIntoView({ block: 'nearest', inline: 'nearest' })
    })
  }
)

const timerMsLeft = computed(() =>
  Math.max(0, Number(examStore.endsAt || 0) - now.value)
)

const timerText = computed(() => {
  const left = timerMsLeft.value
  const m = Math.floor(left / 60000)
  const s = Math.floor((left % 60000) / 1000)
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

const timerClass = computed(() => {
  const total =
    Number(examStore.current?.duration || 0) * 60 * 1000 ||
    timerMsLeft.value ||
    1
  const ratio = timerMsLeft.value / total
  if (ratio <= 0.1) return 'exam-timer-danger'
  if (ratio <= 0.25) return 'exam-timer-warn'
  return 'exam-timer-ok'
})

function seededShuffle<T>(items: T[], seed: string | number): T[] {
  const arr = [...items]
  let s = Number(seed) || 1
  for (let i = arr.length - 1; i > 0; i--) {
    s = (s * 1664525 + 1013904223) % 4294967296
    const j = s % (i + 1)
    ;[arr[i], arr[j]] = [arr[j], arr[i]]
  }
  return arr
}

const optionsCache = new Map<number, Array<{ key: string; label: string }>>()
function optionsFor(question: ExamQuestion) {
  if (optionsCache.has(question.id)) return optionsCache.get(question.id)!
  const o = question.options || {}
  const base = [
    { key: 'a', label: o.a || question.option_a },
    { key: 'b', label: o.b || question.option_b },
    { key: 'c', label: o.c || question.option_c },
    { key: 'd', label: o.d || question.option_d },
  ].filter((opt): opt is { key: string; label: string } => Boolean(opt.label))
  const seed = `${examStore.current?.attemptId || 0}-${question.id}`
  const shuffled = seededShuffle(base, seed)
  optionsCache.set(question.id, shuffled)
  return shuffled
}

function navigatorClass(item: ExamQuestion) {
  const answered = isAnswered(item?.id)
  const current = Number(session.currentQuestion?.id) === Number(item?.id)
  if (current) return 'ring-2 ring-[#ef394e] bg-[#fff1f2] text-[#ef394e]'
  if (answered) return 'bg-[#ef394e] text-white'
  return 'bg-slate-100 text-slate-700 hover:bg-slate-200'
}

function isAnswered(id: number | string) {
  const a = examStore.answers[id] ?? examStore.answers[String(id)]
  return a !== null && a !== undefined && a !== ''
}

function scrollCurrent() {
  const id = session.currentQuestion?.id ?? session.pageQuestions[0]?.id
  if (id == null) return
  void nextTick(() => {
    document.getElementById(`exam-q-${id}`)?.scrollIntoView({
      behavior: 'smooth',
      block: 'start',
    })
  })
}

function jumpTo(id: number) {
  session.navigateToQuestionId(id)
  scrollCurrent()
}

function selectAnswer(questionId: number, key: string) {
  examStore.setAnswer(questionId, key)
  queueAutosave()
}

function selectOptionByIndex(optIdx: number) {
  if (!q.value) return
  const opts = optionsFor(q.value)
  if (opts[optIdx]) selectAnswer(q.value.id, opts[optIdx].key)
}

function queueAutosave() {
  pendingSync = true
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    void syncAnswers()
  }, 800)
}

async function syncAnswers() {
  if (!examStore.current || submitting.value) return
  if (examStore.offline || !navigator.onLine) {
    examStore.setOffline(true)
    examStore.saveCache()
    return
  }
  if (!pendingSync && !examStore.dirty) return

  syncing.value = true
  try {
    const current = examStore.current
    if (!current) return
    const { examId, attemptId } = current
    await api.post(`/exams/${examId}/autosave/${attemptId}`, {
      answers: examStore.answers,
    })
    pendingSync = false
    examStore.markSynced()
  } catch {
    examStore.saveCache()
  } finally {
    syncing.value = false
  }
}

function onOnline() {
  examStore.setOffline(false)
  void syncAnswers()
}
function onOffline() {
  examStore.setOffline(true)
  examStore.saveCache()
}

function onFullscreenChange() {
  session.isFullscreen = screenfull.isEnabled && screenfull.isFullscreen
}

function exitExam() {
  session.showExitConfirm = false
  void syncAnswers()
  router.push('/exams')
}

function goPrevInFilter() {
  session.prev()
  scrollCurrent()
}

function goNextInFilter() {
  session.next()
  scrollCurrent()
}

function onTouchStart(e: TouchEvent) {
  touchX = e.changedTouches[0]?.clientX || 0
}
function onTouchEnd(e: TouchEvent) {
  const x = e.changedTouches[0]?.clientX || 0
  const dx = x - touchX
  if (Math.abs(dx) < 60) return
  if (dx > 0) {
    session.next()
    scrollCurrent()
  } else {
    session.prev()
    scrollCurrent()
  }
}

const keys = useMagicKeys()
watch(
  () => keys.arrowleft.value,
  (v) => {
    if (v) session.next()
  }
)
watch(
  () => keys.arrowright.value,
  (v) => {
    if (v) session.prev()
  }
)
watch(
  () => keys['1'].value,
  (v) => {
    if (v) selectOptionByIndex(0)
  }
)
watch(
  () => keys['2'].value,
  (v) => {
    if (v) selectOptionByIndex(1)
  }
)
watch(
  () => keys['3'].value,
  (v) => {
    if (v) selectOptionByIndex(2)
  }
)
watch(
  () => keys['4'].value,
  (v) => {
    if (v) selectOptionByIndex(3)
  }
)

onMounted(async () => {
  if (!examStore.current) {
    examStore.loadCache(route.params.id as string)
  }
  if (!examStore.current) {
    router.replace('/exams')
    return
  }

  const cachedPerPage = Number(examStore.current?.perPage)
  if (cachedPerPage) session.setQuestionsPerPage(cachedPerPage)

  examStore.setOffline(!navigator.onLine)
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)
  if (screenfull.isEnabled) {
    screenfull.on('change', onFullscreenChange)
  }

  if (Object.keys(examStore.answers || {}).length === 0) {
    try {
      const current = examStore.current
      if (!current) return
      const { examId, attemptId } = current
      const { data } = await api.get<{ data?: AutosaveResponse }>(
        `/exams/${examId}/autosave/${attemptId}`
      )
      const remote = data.data?.answers || {}
      if (Object.keys(remote).length) {
        examStore.answers = remote
        examStore.saveCache()
      }
    } catch {
      /* server autosave unavailable — local cache remains */
    }
  }

  timer = setInterval(() => {
    now.value = Date.now()
    if (examStore.endsAt && now.value >= Number(examStore.endsAt)) {
      void submit()
    }
  }, 1000)

  intervalTimer = setInterval(() => {
    if (examStore.dirty) void syncAnswers()
  }, 15000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (debounceTimer) clearTimeout(debounceTimer)
  if (intervalTimer) clearInterval(intervalTimer)
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
  if (screenfull.isEnabled) {
    screenfull.off('change', onFullscreenChange)
  }
  if (examStore.dirty) void syncAnswers()
})

watch(
  () => examStore.answers,
  () => examStore.saveCache(),
  { deep: true }
)

watch(
  () => session.unansweredInFilter.length,
  (n) => {
    if (n === 0 && session.remainingOnly) session.remainingOnly = false
  }
)

async function submit() {
  if (submitting.value || !examStore.current) return
  submitting.value = true
  submitError.value = ''
  session.showSubmitConfirm = false
  try {
    await syncAnswers()
    const current = examStore.current
    if (!current) return
    const { examId, attemptId } = current
    await api.post(`/exams/${examId}/submit/${attemptId}`, {
      answers: examStore.answers,
    })
    examStore.clearCache()
    session.resetSessionUx()
    router.replace(`/exams/${examId}/result/${attemptId}`)
  } catch (e: unknown) {
    examStore.saveCache()
    submitError.value = apiErrorMessage(
      e,
      'ثبت آزمون ناموفق بود. لطفاً دوباره تلاش کنید.'
    )
    submitting.value = false
  }
}
</script>

<style scoped>
.exam-room {
  background:
    radial-gradient(
      ellipse 80% 50% at 50% -10%,
      rgba(15, 39, 68, 0.06),
      transparent
    ),
    linear-gradient(180deg, #f4f7fb 0%, #eef2f7 100%);
  color: #0f172a;
}

.exam-header {
  background: rgba(255, 255, 255, 0.92);
  border-bottom: 1px solid rgba(15, 39, 68, 0.08);
  backdrop-filter: blur(10px);
}

.exam-icon-btn {
  display: inline-flex;
  border-radius: 0.6rem;
  padding: 0.45rem;
  color: #64748b;
}
.exam-icon-btn:hover {
  background: #f1f5f9;
  color: #0f2744;
}

.exam-timer {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border-radius: 0.75rem;
  padding: 0.4rem 0.7rem;
}
.exam-timer-ok {
  background: #ecfdf5;
  color: #047857;
}
.exam-timer-warn {
  background: #fffbeb;
  color: #b45309;
}
.exam-timer-danger {
  background: #fef2f2;
  color: #b91c1c;
}

.exam-submit-btn {
  border-radius: 0.7rem;
  background: #0f2744;
  padding: 0.5rem 0.9rem;
  font-size: 0.875rem;
  font-weight: 700;
  color: #fff;
}
.exam-submit-btn:hover {
  background: #173556;
}

.exam-chip {
  flex-shrink: 0;
  border-radius: 999px;
  background: #f1f5f9;
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
}
.exam-chip-active {
  background: #0f2744;
  color: #fff;
}
.exam-chip-brand {
  background: #ef394e;
  color: #fff;
}

.exam-tool {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border-radius: 0.6rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0.35rem 0.65rem;
  font-size: 0.7rem;
  font-weight: 700;
  color: #334155;
}
.exam-tool:disabled {
  opacity: 0.4;
}
.exam-tool:hover:not(:disabled) {
  background: #f8fafc;
}
.exam-tool-on {
  border-color: #f59e0b;
  background: #fffbeb;
  color: #92400e;
}

.exam-font-btn {
  padding: 0.3rem 0.65rem;
  font-size: 0.875rem;
  font-weight: 700;
  color: #475569;
}
.exam-font-btn:hover {
  background: #f8fafc;
}

.exam-paper {
  border: 1px solid rgba(15, 39, 68, 0.08);
  border-radius: 1.1rem;
  background: #fff;
  padding: 1.25rem 1.15rem;
  box-shadow: 0 10px 30px rgba(15, 39, 68, 0.04);
}
@media (min-width: 640px) {
  .exam-paper {
    padding: 1.6rem 1.5rem;
  }
}

.exam-option {
  display: flex;
  width: 100%;
  min-height: 48px;
  align-items: flex-start;
  gap: 0.75rem;
  border-radius: 0.9rem;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  padding: 0.9rem 1rem;
  text-align: right;
  transition:
    border-color 0.15s ease,
    background 0.15s ease,
    box-shadow 0.15s ease;
}
.exam-option:hover {
  border-color: #cbd5e1;
}
.exam-option-selected {
  border-color: #ef394e;
  background: #fff5f6;
  box-shadow: 0 0 0 3px rgba(239, 57, 78, 0.08);
}

.exam-option-letter {
  margin-top: 0.1rem;
  display: inline-flex;
  height: 1.75rem;
  width: 1.75rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1.5px solid #cbd5e1;
  font-size: 0.7rem;
  font-weight: 700;
  color: #475569;
}
.exam-option-letter-on {
  border-color: #ef394e;
  background: #ef394e;
  color: #fff;
}

.exam-nav-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border-radius: 0.8rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0.7rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #334155;
}
.exam-nav-btn:disabled {
  opacity: 0.35;
}
.exam-nav-btn-primary {
  border-color: #ef394e;
  background: #ef394e;
  color: #fff;
}
.exam-nav-btn-primary:hover {
  background: #d92f43;
}
.exam-nav-btn-submit {
  margin-right: auto;
  border-color: #0f2744;
  background: #0f2744;
  color: #fff;
}
.exam-nav-btn-submit:hover {
  background: #1a3a5c;
}
.exam-action-bar .exam-nav-btn {
  min-height: 2.75rem;
  padding: 0.65rem 0.85rem;
}

.exam-nav-panel {
  border: 1px solid rgba(15, 39, 68, 0.08);
  border-radius: 1rem;
  background: #fff;
  padding: 1rem;
  box-shadow: 0 8px 24px rgba(15, 39, 68, 0.04);
}

.exam-sync {
  position: fixed;
  bottom: 1rem;
  left: 1rem;
  z-index: 40;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border-radius: 0.7rem;
  border: 1px solid #e2e8f0;
  background: rgba(255, 255, 255, 0.95);
  padding: 0.45rem 0.75rem;
  font-size: 0.7rem;
  color: #475569;
  box-shadow: 0 6px 16px rgba(15, 39, 68, 0.06);
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>

<style>
html.dark .exam-room {
  background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
  color: #f1f5f9;
}

html.dark .exam-header {
  background: rgba(15, 23, 42, 0.95);
  border-bottom-color: #334155;
}

html.dark .exam-chip {
  background: #334155;
  color: #e2e8f0;
}

html.dark .exam-tool {
  border-color: #475569;
  background: #1e293b;
  color: #e2e8f0;
}

html.dark .exam-paper {
  background: #1e293b;
  border-color: #334155;
}

html.dark .exam-option {
  border-color: #475569;
  background: #1e293b;
  color: #f1f5f9;
}

html.dark .exam-option-selected {
  background: #3f1d24;
}
</style>
