<template>
  <section class="bg-desk-gray py-14">
    <div class="desk-container">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="desk-section-title">آزمون‌های آنلاین</h2>
          <p class="mt-1 text-sm text-desk-muted">تمرین هدفمند برای آزمون‌های استخدامی</p>
        </div>
        <RouterLink to="/exams" class="text-sm font-bold text-desk-orange hover:underline">
          همه آزمون‌ها
        </RouterLink>
      </div>

      <div v-if="loading" class="py-10 text-center text-sm text-desk-muted">در حال بارگذاری...</div>
      <div v-else class="grid grid-cols-4 gap-5">
        <article
          v-for="exam in displayExams"
          :key="exam.id"
          class="desk-card overflow-hidden"
        >
          <div class="flex aspect-video items-center justify-center bg-desk-blue">
            <DesktopIcon name="clipboard" :size="32" class="text-white" />
          </div>
          <div class="p-4 text-right">
            <div class="mb-2 flex flex-wrap justify-end gap-2">
              <span
                v-if="exam.is_free"
                class="rounded-md bg-desk-green/15 px-2 py-0.5 text-[11px] font-bold text-desk-green"
              >
                رایگان
              </span>
              <span
                v-if="exam.has_negative_marking"
                class="rounded-md bg-orange-50 px-2 py-0.5 text-[11px] font-bold text-desk-orange"
              >
                نمره منفی
              </span>
            </div>
            <h3 class="mb-2 line-clamp-2 text-base font-semibold text-desk-text">{{ exam.title }}</h3>
            <p class="mb-3 text-xs text-desk-muted">
              {{ exam.duration_minutes }} دقیقه · {{ exam.total_questions }} سوال
            </p>
            <RouterLink
              :to="exam.slug ? `/exams/${exam.slug}` : '/exams'"
              class="inline-flex w-full items-center justify-center rounded-lg bg-desk-dark px-3 py-2.5 text-sm font-bold text-white hover:bg-desk-blue"
            >
              شروع آزمون
            </RouterLink>
          </div>
        </article>
      </div>

      <div
        v-if="!loading && !displayExams.length"
        class="rounded-2xl border border-dashed border-desk-blue/30 bg-white p-8 text-center"
      >
        <p class="mb-3 text-sm text-desk-muted">برای مشاهده آزمون‌ها وارد حساب شوید.</p>
        <RouterLink to="/login" class="text-sm font-bold text-desk-orange hover:underline">ورود</RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import DesktopIcon from '../DesktopIcon.vue';

const props = defineProps({
  exams: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const displayExams = computed(() => (props.exams || []).slice(0, 4));
</script>
