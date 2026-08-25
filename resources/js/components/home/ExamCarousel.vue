<template>
  <section class="bg-white py-8 sm:py-12">
    <div class="mx-auto max-w-7xl px-4">
      <div class="animate-on-scroll mb-5 flex items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-black text-desk-dark sm:text-xl">
            آزمون‌های پرطرفدار
          </h2>
          <p class="mt-1 text-xs text-desk-muted">تمرین هدفمند برای استخدام</p>
        </div>
        <RouterLink
          to="/exams"
          class="text-xs font-bold text-brand hover:underline sm:text-sm"
        >
          همه آزمون‌ها
        </RouterLink>
      </div>

      <div v-if="loading" class="py-8 text-center text-sm text-desk-muted">
        در حال بارگذاری...
      </div>
      <p v-else-if="error" class="py-6 text-center text-sm text-brand">
        {{ error }}
      </p>
      <div
        v-else-if="displayExams.length"
        ref="carousel"
        class="scrollbar-hide -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-1"
      >
        <article
          v-for="exam in displayExams"
          :key="exam.id"
          class="animate-on-scroll w-[16rem] shrink-0 snap-start sm:w-72"
        >
          <div
            class="flex h-full flex-col border border-surface-line bg-surface-page p-4 transition hover:border-desk-dark/20"
          >
            <p class="mb-3 text-[10px] font-bold text-desk-muted">
              {{ exam.is_free ? 'رایگان' : 'ویژه' }}
            </p>
            <h3
              class="line-clamp-2 min-h-[2.75rem] text-sm font-bold text-desk-text"
            >
              {{ exam.title }}
            </h3>
            <div
              class="mt-3 flex items-center gap-3 text-[11px] text-desk-muted"
            >
              <span>{{ toFaDigits(exam.total_questions || '—') }} سوال</span>
              <span>{{ toFaDigits(exam.duration_minutes || '—') }} دقیقه</span>
            </div>
            <button
              type="button"
              class="mt-4 w-full rounded-xl bg-desk-dark py-2.5 text-xs font-bold text-white transition hover:bg-desk-blue"
              @click="goExam(exam)"
            >
              شرکت در آزمون
            </button>
          </div>
        </article>
      </div>
      <p v-else class="py-8 text-center text-sm text-desk-muted">
        آزمونی یافت نشد.
      </p>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps<{
  exams: any[]
  loading?: boolean
  error?: string
}>()

const router = useRouter()
const carousel = ref<HTMLElement | null>(null)

const displayExams = computed(() => (props.exams || []).slice(0, 8))

function toFaDigits(v: string | number) {
  return String(v).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)])
}

function goExam(exam: any) {
  const slug = exam.slug || exam.id
  router.push(`/exams/${slug}/start`)
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
