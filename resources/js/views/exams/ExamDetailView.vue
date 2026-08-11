<template>
  <PageShell>
    <LoadingSpinner v-if="loading" />
    <template v-else-if="exam">
      <h1 class="page-title mb-2 leading-8 sm:text-2xl">{{ exam.title }}</h1>
      <div class="mb-3 flex items-center gap-3">
        <StarRating :avg="exam.avg_rating || 0" readonly show-value />
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

      <button
        class="btn-primary mb-4 w-full sm:w-auto"
        :disabled="auth.isAuthenticated && !(exam.is_eligible || exam.is_free)"
        @click="start(null)"
      >
        شروع آزمون کامل
      </button>

      <!-- Subjects -->
      <div v-if="exam.subjects?.length" class="mb-4">
        <h2 class="mb-2 text-sm font-bold text-desk-dark">دروس این آزمون</h2>
        <div class="space-y-2">
          <div
            v-for="s in exam.subjects"
            :key="s.slug"
            class="card-soft flex items-center justify-between gap-3 p-3"
          >
            <div class="flex items-center gap-2">
              <span v-if="s.icon" class="text-lg">{{ s.icon }}</span>
              <div>
                <p class="text-sm font-bold">{{ s.name }}</p>
                <p class="text-[11px] text-ink-muted">
                  {{ s.question_count }} سوال
                </p>
              </div>
            </div>
            <button
              type="button"
              class="shrink-0 rounded-lg bg-surface-page px-3 py-2 text-xs font-bold text-brand transition active:bg-brand-soft disabled:opacity-50"
              :disabled="!s.question_count"
              @click="start(s.slug)"
            >
              شروع درس {{ s.name }}
            </button>
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

async function start(subjectSlug) {
  if (!exam.value) return
  if (!auth.isAuthenticated) {
    router.push({
      path: '/login',
      query: { redirect: `/exams/${exam.value.slug || exam.value.id}` },
    })
    return
  }
  const q = subjectSlug ? `?subject=${encodeURIComponent(subjectSlug)}` : ''
  router.push(`/exams/${exam.value.slug}/start${q}`)
}
</script>



