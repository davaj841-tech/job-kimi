<template>
  <section class="bg-surface-page py-6 sm:py-7">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-3 flex items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-black text-desk-dark sm:text-xl">آزمون‌های آنلاین</h2>
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
          class="w-[16rem] shrink-0 cursor-pointer rounded-2xl border border-surface-line bg-white p-3.5 text-right shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
          @click="goExam(exam)"
        >
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <span
              v-if="exam.is_free"
              class="rounded-md bg-desk-green/15 px-2 py-0.5 text-[11px] font-bold text-desk-green"
            >
              رایگان
            </span>
            <span
              v-else
              class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-desk-muted"
            >
              ویژه
            </span>
            <span
              v-if="exam.has_negative_marking"
              class="rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-bold text-desk-orange"
            >
              نمره منفی
            </span>
          </div>
          <h3 class="line-clamp-2 text-sm font-bold text-desk-text">
            {{ exam.title }}
          </h3>
          <p class="mt-1.5 mb-3 text-[11px] text-desk-muted">
            {{ exam.duration_minutes }} دقیقه ·
            {{ exam.total_questions }} سوال
          </p>
          <button
            type="button"
            class="inline-flex w-full items-center justify-center rounded-lg bg-desk-dark px-3 py-2 text-xs font-bold text-white hover:bg-desk-blue"
            @click.stop="goExam(exam)"
          >
            شروع آزمون
          </button>
        </article>
      </HomeRail>
      <div
        v-else-if="error"
        class="rounded-2xl border border-dashed border-red-200 bg-white p-8 text-center"
      >
        <p class="text-sm text-red-500">{{ error }}</p>
      </div>
      <div
        v-else
        class="rounded-2xl border border-dashed border-desk-blue/30 bg-white p-8 text-center"
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
