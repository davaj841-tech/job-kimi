<template>
  <PageShell>
    <LoadingSpinner v-if="loading" />
    <template v-else-if="exam">
      <div class="mb-2 flex items-start gap-3">
        <span class="text-3xl" aria-hidden="true">📝</span>
        <h1 class="page-title mb-0 flex-1 leading-8 sm:text-2xl">{{ exam.title }}</h1>
      </div>
      <div class="mb-3 flex flex-wrap items-center gap-3">
        <StarRating :avg="exam.avg_rating || 0" readonly show-value />
        <span
          v-if="exam.ratings_count"
          class="text-xs text-desk-muted"
        >
          ({{ exam.ratings_count }} رأی)
        </span>
        <button class="text-xs font-bold text-brand hover:underline" @click="shareOpen = true">
          اشتراک‌گذاری
        </button>
      </div>
      <p class="mb-4 text-sm leading-6 text-desk-muted">{{ exam.description }}</p>
      <div class="page-card mb-4 grid grid-cols-2 gap-3 p-4 text-sm text-desk-text">
        <div>
          مدت: <b>{{ exam.duration_minutes }} دقیقه</b>
        </div>
        <div>
          سوالات: <b>{{ exam.total_questions }}</b>
        </div>
        <div>
          نمره قبولی: <b>{{ exam.passing_score }}</b>
        </div>
        <div>
          هزینه:
          <b class="text-brand">{{
            exam.is_free ? 'رایگان' : formatPrice(exam.price)
          }}</b>
        </div>
      </div>

      <div
        v-if="exam.has_negative_marking"
        class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900"
      >
        <p class="font-bold">هشدار نمره منفی</p>
        <p class="mt-1">
          این آزمون نمره منفی دارد. به ازای هر پاسخ غلط،
          {{ ratioText }} نمره همان سوال از مجموع کسر می‌شود. سوالات بدون پاسخ
          نمره منفی نمی‌گیرند.
        </p>
      </div>

      <p
        v-if="auth.isAuthenticated && !exam.is_eligible && !exam.is_free"
        class="mb-3 rounded-lg bg-brand-soft p-3 text-sm text-brand"
      >
        برای این آزمون نیاز به اشتراک دارید.
        <RouterLink to="/subscription" class="font-bold underline"
          >خرید اشتراک</RouterLink
        >
      </p>

      <div
        v-if="exam.active_attempt"
        class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4"
      >
        <p class="mb-1 text-sm font-bold text-amber-900">
          ⏳ یک تلاش ناتمام دارید
        </p>
        <p class="mb-3 text-xs text-amber-800">
          {{ exam.active_attempt.answered || 0 }} سوال پاسخ داده شده ·
          {{ remainingLabel }} باقی‌مانده
        </p>
        <div class="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            class="btn-primary flex-1"
            :disabled="busy"
            @click="goStart({ resume: true })"
          >
            ادامه همان آزمون
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-bold text-amber-900"
            :disabled="busy"
            @click="goStart({ restart: true })"
          >
            شروع مجدد
          </button>
        </div>
      </div>

      <button
        v-else
        class="btn-primary mb-4 w-full sm:w-auto"
        :disabled="auth.isAuthenticated && !(exam.is_eligible || exam.is_free)"
        @click="goStart()"
      >
        شروع آزمون کامل
      </button>

      <!-- Subjects: stats only -->
      <div v-if="exam.subjects?.length" class="mb-4">
        <h2 class="mb-2 text-sm font-bold text-desk-dark">دروس این آزمون (آمار)</h2>
        <p class="mb-3 text-[11px] text-desk-muted">
          شروع درس به‌صورت جداگانه ممکن نیست؛ پس از شروع آزمون کامل، تب دروس در بالای صفحه در دسترس است.
        </p>
        <div class="space-y-2">
          <div
            v-for="s in exam.subjects"
            :key="s.slug"
            class="card-soft flex items-center justify-between gap-3 p-3"
          >
            <div class="flex items-center gap-2">
              <span class="text-lg">{{ s.icon || subjectEmoji(s.slug) }}</span>
              <div>
                <p class="text-sm font-bold">{{ s.name }}</p>
                <p class="text-[11px] text-ink-muted">
                  {{ s.question_count }} سوال
                </p>
              </div>
            </div>
            <span
              class="rounded-lg bg-surface-page px-3 py-1.5 text-[11px] font-bold text-desk-muted"
            >
              فقط آمار
            </span>
          </div>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-center text-sm text-brand">
        {{ error }}
      </p>
      <ShareModal
        :open="shareOpen"
        :title="exam.title"
        :description="exam.description"
        :url="shareUrl"
        @close="shareOpen = false"
      />
    </template>
  </PageShell>
</template>

<script setup>
import { setExamMeta } from '../../services/meta'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import ShareModal from '../../components/ShareModal.vue'
import StarRating from '../../components/StarRating.vue'
import { formatPrice } from '../../utils/format'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const exam = ref(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const shareOpen = ref(false)
const shareUrl = computed(
  () => `${window.location.origin}/exams/${exam.value?.slug || ''}`
)

const ratioText = computed(() => {
  const ratio = Number(exam.value?.negative_mark_ratio ?? 0.3333)
  if (Math.abs(ratio - 1 / 3) < 0.01) return 'یک‌سوم'
  return `${Math.round(ratio * 100)}٪`
})

const remainingLabel = computed(() => {
  const sec = Number(exam.value?.active_attempt?.remaining_seconds || 0)
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${m}:${String(s).padStart(2, '0')}`
})

function subjectEmoji(slug) {
  const map = {
    islamic: '📖',
    general: '📚',
    intelligence: '🧠',
    specialized: '🎯',
    math: '🔢',
    language: '🔤',
  }
  return map[slug] || '📘'
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/exams/${route.params.slug}`)
    exam.value = data.data
    setExamMeta(exam.value)
  } catch (_) {
    error.value = 'آزمون یافت نشد.'
  } finally {
    loading.value = false
  }
})

function goStart(opts = {}) {
  if (!exam.value) return
  if (!auth.isAuthenticated) {
    router.push({
      path: '/login',
      query: { redirect: `/exams/${exam.value.slug || exam.value.id}` },
    })
    return
  }
  const q = {}
  if (opts.resume) q.resume = '1'
  if (opts.restart) q.restart = '1'
  router.push({ path: `/exams/${exam.value.slug}/start`, query: q })
}
</script>
