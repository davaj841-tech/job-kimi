<template>
  <section class="bg-surface-page py-6 sm:py-7">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-3 flex items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-black text-desk-text sm:text-xl">📝 آزمون‌های آنلاین</h2>
          <p class="mt-0.5 text-xs text-desk-muted">تمرین هدفمند برای آزمون‌های استخدامی</p>
        </div>
        <RouterLink
          to="/exams"
          class="text-xs font-bold text-brand hover:underline sm:text-sm"
        >
          مشاهده همه
        </RouterLink>
      </div>

      <div v-if="loading" class="py-10 text-center text-sm text-desk-muted">
        در حال بارگذاری...
      </div>
      <HomeRail v-else-if="displayExams.length">
        <article
          v-for="exam in displayExams"
          :key="exam.id"
          class="home-rail-card cursor-pointer"
          @click="goExam(exam)"
        >
          <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <span class="text-xl" aria-hidden="true">📝</span>
            <div class="flex flex-wrap items-center gap-1.5">
              <span
                v-if="exam.is_free"
                class="rounded-md bg-desk-green/15 px-2 py-0.5 text-[11px] font-bold text-desk-green"
              >
                رایگان
              </span>
              <span
                v-else
                class="rounded-md bg-surface-page px-2 py-0.5 text-[11px] font-bold text-desk-muted"
              >
                ویژه
              </span>
            </div>
          </div>
          <h3 class="line-clamp-2 text-sm font-bold text-desk-text">
            {{ exam.title }}
          </h3>
          <div class="mt-auto pt-1.5">
            <StarRating
              :avg="Number(exam.avg_rating) || 0"
              :count="Number(exam.ratings_count) || 0"
              readonly
              show-value
              compact
            />
            <p class="mt-1 text-[11px] text-desk-muted">
              {{ exam.duration_minutes }} دقیقه ·
              {{ exam.total_questions }} سوال
            </p>
            <button
              type="button"
              class="mt-2 inline-flex w-full items-center justify-center rounded-lg bg-desk-dark px-3 py-2 text-xs font-bold text-white hover:bg-desk-blue"
              @click.stop="goExam(exam)"
            >
              شروع آزمون
            </button>
          </div>
        </article>
      </HomeRail>
      <div
        v-else-if="error"
        class="rounded-2xl border border-dashed border-red-200 bg-surface p-8 text-center"
      >
        <p class="text-sm text-red-500">{{ error }}</p>
      </div>
      <div
        v-else
        class="rounded-2xl border border-dashed border-desk-blue/30 bg-surface p-8 text-center"
      >
        <p class="text-sm text-desk-muted">
          هنوز آزمونی منتشر نشده است.
        </p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import HomeRail from './HomeRail.vue'
import StarRating from '../StarRating.vue'

const props = defineProps({
  exams: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const auth = useAuthStore()
const router = useRouter()

const displayExams = computed(() => (props.exams || []).slice(0, 12))

function examPath(exam) {
  const slug = exam.slug || exam.id
  return `/exams/${slug}`
}

function goExam(exam) {
  const path = examPath(exam)
  if (!auth.isAuthenticated) {
    router.push({ path: '/login', query: { redirect: path } })
    return
  }
  router.push(path)
}
</script>
