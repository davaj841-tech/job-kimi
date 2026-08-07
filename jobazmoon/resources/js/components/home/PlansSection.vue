<template>
  <section class="bg-white py-14">
    <div class="desk-container">
      <div class="mb-6 text-center">
        <h2 class="desk-section-title">پلن‌های اشتراک</h2>
        <p class="mt-2 text-sm text-desk-muted">دسترسی کامل به آزمون‌ها و امکانات ویژه</p>
      </div>

      <div v-if="loading" class="py-10 text-center text-sm text-desk-muted">در حال بارگذاری...</div>
      <div v-else class="mx-auto grid max-w-5xl grid-cols-3 gap-5">
        <article
          v-for="(plan, index) in displayPlans"
          :key="plan.id || plan.name"
          class="desk-card relative overflow-hidden p-6 text-right"
          :class="index === 1 ? 'ring-2 ring-desk-orange' : ''"
        >
          <span
            v-if="index === 1"
            class="absolute left-4 top-4 rounded-md bg-desk-orange px-2 py-0.5 text-[11px] font-bold text-white"
          >
            پیشنهادی
          </span>
          <h3 class="mb-2 text-lg font-bold text-desk-text">{{ plan.name }}</h3>
          <p class="mb-1 text-2xl font-black text-desk-orange">{{ formatPrice(plan.price) }}</p>
          <p class="mb-4 text-sm text-desk-muted">{{ plan.duration_days }} روز اعتبار</p>
          <ul class="mb-6 space-y-2">
            <li
              v-for="(feature, i) in (plan.features || defaultFeatures)"
              :key="i"
              class="flex items-center justify-end gap-2 text-sm text-desk-text"
            >
              <span>{{ feature }}</span>
              <DesktopIcon name="check" :size="16" class="text-desk-green" />
            </li>
          </ul>
          <RouterLink
            to="/subscription"
            class="inline-flex w-full items-center justify-center rounded-xl bg-desk-dark px-4 py-3 text-sm font-bold text-white transition hover:bg-desk-blue"
            :class="index === 1 ? '!bg-desk-orange hover:!bg-orange-500' : ''"
          >
            انتخاب پلن
          </RouterLink>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { formatPrice } from '../../utils/format';
import DesktopIcon from '../DesktopIcon.vue';

const props = defineProps({
  plans: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const defaultFeatures = ['دسترسی به آزمون‌ها', 'دانلود فایل‌ها', 'پشتیبانی'];

const fallbackPlans = [
  { id: 'f1', name: 'پایه', price: 0, duration_days: 7, features: ['آزمون‌های رایگان', 'مشاهده آگهی‌ها'] },
  { id: 'f2', name: 'حرفه‌ای', price: 490000, duration_days: 30, features: ['تمام آزمون‌ها', 'دانلود فایل', 'رزومه‌ساز'] },
  { id: 'f3', name: 'سازمانی', price: 1290000, duration_days: 90, features: ['همه امکانات', 'پشتیبانی ویژه', 'گزارش پیشرفت'] },
];

const displayPlans = computed(() => {
  const list = props.plans?.length ? props.plans : fallbackPlans;
  return list.slice(0, 3);
});
</script>
