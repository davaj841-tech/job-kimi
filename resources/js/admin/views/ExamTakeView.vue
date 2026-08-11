<template>
  <AdminLayout>
    <div class="mx-auto max-w-3xl">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <RouterLink
            to="/admin/exams"
            class="text-xs font-bold text-orange-600 hover:underline"
            >← بازگشت به آزمون‌ها</RouterLink
          >
          <h1 class="mt-1 text-xl font-black text-slate-800">
            {{ exam?.title || 'آزمون‌گیری' }}
          </h1>
          <p class="mt-1 text-xs text-slate-500">
            صفحه {{ fa(pageIndex + 1) }} از {{ fa(totalPages) }} · پاسخ‌داده‌شده
            {{ fa(answeredCount) }} / {{ fa(questions.length) }}
          </p>
        </div>
        <div
          class="rounded-xl bg-orange-50 px-3 py-2 text-sm font-bold text-orange-700"
        >
          ⏱ {{ timerText }}
        </div>
      </div>

      <div
        v-if="loading"
        class="rounded-2xl bg-white p-10 text-center text-sm text-slate-500 shadow-sm"
      >
        در حال شروع آزمون...
      </div>
      <div
        v-else-if="error"
        class="rounded-2xl bg-white p-8 text-center text-sm text-red-500 shadow-sm"
      >
        {{ error }}
      </div>
      <template v-else>
        <div class="mb-4 h-1.5 overflow-hidden rounded-full bg-slate-200">
          <div
            class="h-full rounded-full bg-orange-500 transition-all"
            :style="{ width: `${((pageIndex + 1) / totalPages) * 100}%` }"
          />
        </div>

        <div class="space-y-4">
          <div
            v-for="(q, localIdx) in pageQuestions"
            :key="q.id"
            class="rounded-2xl bg-white p-5 shadow-sm"
          >
            <div class="mb-3 flex items-center gap-2">
              <span
                class="flex h-7 w-7 items-center justify-center rounded-full bg-orange-50 text-xs font-bold text-orange-600"
              >
                {{ fa(pageIndex * PER_PAGE + localIdx + 1) }}
              </span>
              <span
                v-if="q.subject"
                class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] text-slate-500"
                >{{ q.subject }}</span
              >
            </div>
            <div
              class="mb-4 text-sm leading-8 text-slate-800"
              v-html="q.question_text"
            />
            <div class="space-y-2">
              <button
                v-for="(opt, optIdx) in optionsFor(q)"
                :key="opt.key"
                type="button"
                class="flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-right text-sm transition"
                :class="
                  answers[q.id] === opt.key
                    ? 'border-orange-400 bg-orange-50'
                    : 'border-slate-200 bg-white hover:border-slate-300'
                "
                @click="answers[q.id] = opt.key"
              >
                <span
                  class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold"
                >
                  {{ PERSIAN_LETTERS[optIdx] }}
                </span>
                <span class="flex-1 leading-7" v-html="opt.label" />
              </button>
            </div>
          </div>
        </div>

        <div class="mt-5 flex gap-2">
          <button
            type="button"
            class="flex-1 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold disabled:opacity-40"
            :disabled="pageIndex === 0"
            @click="pageIndex--"
          >
            ← قبلی
          </button>
          <button
            v-if="pageIndex < totalPages - 1"
            type="button"
            class="flex-1 rounded-xl bg-orange-500 px-4 py-3 text-sm font-bold text-white"
            @click="pageIndex++"
          >
            بعدی →
          </button>
          <button
            v-else
            type="button"
            class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white disabled:opacity-50"
            :disabled="submitting"
            @click="submit"
          >
            {{ submitting ? 'در حال ثبت...' : '✅ ثبت و مشاهده نتیجه' }}
          </button>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import adminApi from '../api/client'
import AdminLayout from '../components/layout/AdminLayout.vue'
import { useToast } from '../../composables/useToast'

const PER_PAGE = 5
const PERSIAN_LETTERS = ['الف', 'ب', 'ج', 'د']

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const exam = ref(null)
const attemptId = ref(null)
const questions = ref([])
const answers = reactive({})
const pageIndex = ref(0)
const endTime = ref(0)
const now = ref(Date.now())
let timer
let autosaveTimer

const totalPages = computed(() =>
  Math.max(1, Math.ceil(questions.value.length / PER_PAGE))
)
const pageQuestions = computed(() =>
  questions.value.slice(
    pageIndex.value * PER_PAGE,
    pageIndex.value * PER_PAGE + PER_PAGE
  )
)
const answeredCount = computed(
  () =>
    Object.values(answers).filter(
      (v) => v !== null && v !== undefined && v !== ''
    ).length
)
const timerText = computed(() => {
  const left = Math.max(0, endTime.value * 1000 - now.value)
  const totalSec = Math.floor(left / 1000)
  const m = Math.floor(totalSec / 60)
  const s = totalSec % 60
  return `${fa(m)}:${fa(String(s).padStart(2, '0'))}`
})

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}

function optionsFor(q) {
  const opts = q.options || {}
  return ['a', 'b', 'c', 'd']
    .map((key) => ({ key, label: opts[key] || '' }))
    .filter((o) => o.label)
}

function payloadAnswers() {
  const out = {}
  Object.entries(answers).forEach(([id, val]) => {
    if (val) out[id] = val
  })
  return out
}

async function autosave() {
  if (!attemptId.value) return
  try {
    await adminApi.post(
      `/exams/${route.params.id}/autosave/${attemptId.value}`,
      {
        answers: payloadAnswers(),
      }
    )
  } catch (_) {
    // silent — submit still sends local answers
  }
}

async function start() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await adminApi.post(
      `/admin/exams/${route.params.id}/practice/start`
    )
    const payload = data.data || data
    attemptId.value = payload.attempt_id
    exam.value = payload.exam
    questions.value = payload.questions || []
    endTime.value =
      payload.end_time ||
      Math.floor(Date.now() / 1000) + (payload.duration_minutes || 60) * 60
    questions.value.forEach((q) => {
      answers[q.id] = null
    })
  } catch (e) {
    error.value = e.response?.data?.message || 'شروع آزمون ممکن نشد.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (!attemptId.value || submitting.value) return
  submitting.value = true
  try {
    await adminApi.post(
      `/admin/exams/${route.params.id}/practice/submit/${attemptId.value}`,
      {
        answers: payloadAnswers(),
      }
    )
    toast.success('نتیجه آزمون ذخیره شد.')
    router.push({
      name: 'admin-exam-result',
      params: { id: route.params.id, attemptId: attemptId.value },
    })
  } catch (e) {
    toast.error(e.response?.data?.message || 'ثبت آزمون ناموفق بود.')
  } finally {
    submitting.value = false
  }
}

watch(
  answers,
  () => {
    if (!attemptId.value) return
    clearTimeout(autosaveTimer)
    autosaveTimer = setTimeout(autosave, 800)
  },
  { deep: true }
)

onMounted(async () => {
  await start()
  timer = setInterval(() => {
    now.value = Date.now()
    if (
      endTime.value &&
      now.value >= endTime.value * 1000 &&
      !submitting.value &&
      attemptId.value
    ) {
      submit()
    }
  }, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  clearTimeout(autosaveTimer)
})
</script>
