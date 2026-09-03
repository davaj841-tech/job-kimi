<template>
  <section class="bg-surface-page py-6 sm:py-7">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-3 flex items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-black text-desk-text sm:text-xl">
            📝 آزمون‌های آنلاین
          </h2>
          <p class="mt-0.5 text-xs text-desk-muted">
            تمرین هدفمند برای آزمون‌های استخدامی
          </p>
        </div>
        <RouterLink
          to="/exams"
          class="text-xs font-bold text-brand hover:underline sm:text-sm"
        >
          مشاهده همه
        </RouterLink>
      </div>

      <div
        v-if="classifications.length"
        class="scrollbar-hide mb-4 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        <button
          type="button"
          class="inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold transition"
          :class="chipClass(null)"
          @click="selectedClass = null"
        >
          <span class="text-sm leading-none" aria-hidden="true">📋</span>
          همه
        </button>
        <button
          v-for="item in classifications"
          :key="item.id"
          type="button"
          class="inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold transition"
          :class="chipClass(item.id)"
          @click="selectedClass = item.id"
        >
          <span class="text-sm leading-none" aria-hidden="true">{{
            classificationIcon(item)
          }}</span>
          {{ item.name }}
        </button>
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
        v-else-if="hasAnyExams && selectedClass"
        class="rounded-2xl border border-dashed border-desk-blue/30 bg-surface p-8 text-center"
      >
        <p class="text-sm text-desk-muted">
          آزمونی برای این رسته در صفحه اصلی نیست.
        </p>
        <button
          type="button"
          class="mt-3 text-xs font-bold text-brand hover:underline"
          @click="selectedClass = null"
        >
          نمایش همه آزمون‌ها
        </button>
      </div>
      <div
        v-else
        class="rounded-2xl border border-dashed border-desk-blue/30 bg-surface p-8 text-center"
      >
        <p class="text-sm text-desk-muted">هنوز آزمونی منتشر نشده است.</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { classificationIcon } from '../../utils/classificationIcon'
import HomeRail from './HomeRail.vue'

const props = defineProps({
  exams: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const auth = useAuthStore()
const router = useRouter()
const selectedClass = ref(null)

const hasAnyExams = computed(() => (props.exams || []).length > 0)

const displayExams = computed(() => {
  const list = (props.exams || []).slice(0, 12)
  if (!selectedClass.value) return list

  const parent = (props.classifications || []).find(
    (c) => Number(c.id) === Number(selectedClass.value)
  )
  const ids = new Set([
    Number(selectedClass.value),
    ...(parent?.child_ids || []).map(Number),
  ])

  return list.filter((exam) => ids.has(Number(exam.job_classification_id)))
})

function chipClass(id) {
  const on =
    id === null
      ? !selectedClass.value
      : Number(selectedClass.value) === Number(id)
  return on
    ? 'bg-desk-dark text-white'
    : 'bg-slate-100 text-desk-text hover:bg-slate-200'
}

function examPath(exam) {
  const slug = exam.slug || exam.id
  return `/exams/${slug}/start`
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
