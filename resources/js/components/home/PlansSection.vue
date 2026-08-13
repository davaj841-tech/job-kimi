<template>
  <section
    class="py-6 sm:py-8"
    :class="variant === 'dark' ? 'bg-[#0f172a]' : variant === 'compact' ? 'bg-surface-page' : 'bg-surface'"
  >
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-4 flex items-end justify-between gap-3">
        <div>
          <h2
            class="text-lg font-black sm:text-xl"
            :class="variant === 'dark' ? 'text-white' : 'text-desk-text'"
          >
            ⭐ اشتراک ویژه
          </h2>
          <p
            class="mt-0.5 text-xs"
            :class="variant === 'dark' ? 'text-white/55' : 'text-desk-muted'"
          >
            دسترسی کامل، جمع‌وجور و شفاف
          </p>
        </div>
        <RouterLink
          to="/subscription"
          class="text-xs font-bold hover:underline"
          :class="variant === 'dark' ? 'text-desk-orange' : 'text-brand'"
        >
          جزئیات پلن‌ها
        </RouterLink>
      </div>

      <div
        v-if="loading"
        class="py-6 text-center text-sm"
        :class="variant === 'dark' ? 'text-white/50' : 'text-desk-muted'"
      >
        در حال بارگذاری...
      </div>

      <div
        v-else-if="variant === 'rows' || variant === 'strip'"
        class="space-y-2"
      >
        <article
          v-for="(plan, index) in displayPlans"
          :key="plan.id || plan.name"
          class="flex flex-wrap items-center justify-between gap-3 rounded-xl px-3 py-2.5"
          :class="rowClass(index)"
        >
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="text-sm font-bold text-desk-text">{{ plan.name }}</h3>
              <span
                v-if="index === 1"
                class="rounded bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                >محبوب</span
              >
            </div>
            <p class="text-[11px] text-desk-muted">{{ plan.duration_days }} روز</p>
          </div>
          <div class="flex items-center gap-3">
            <p class="text-sm font-black text-desk-orange">{{ formatPrice(plan.price) }}</p>
            <RouterLink
              to="/subscription"
              class="rounded-lg bg-desk-dark px-3 py-1.5 text-xs font-bold text-white hover:bg-desk-blue"
            >
              خرید
            </RouterLink>
          </div>
        </article>
      </div>

      <div
        v-else
        class="grid grid-cols-1 gap-3 sm:grid-cols-3"
      >
        <article
          v-for="(plan, index) in displayPlans"
          :key="plan.id || plan.name"
          class="relative rounded-2xl p-4"
          :class="cardClass(index)"
        >
          <span
            v-if="index === 1"
            class="absolute left-3 top-3 rounded-md bg-emerald-500 px-2 py-0.5 text-[10px] font-bold text-white"
          >
            محبوب‌ترین
          </span>
          <h3
            class="mb-1 text-sm font-bold"
            :class="variant === 'dark' ? 'text-white' : 'text-desk-text'"
          >
            {{ plan.name }}
          </h3>
          <p class="text-lg font-black text-desk-orange">{{ formatPrice(plan.price) }}</p>
          <p
            class="mb-2 text-[11px]"
            :class="variant === 'dark' ? 'text-white/50' : 'text-desk-muted'"
          >
            {{ plan.duration_days }} روز اعتبار
          </p>
          <ul class="mb-3 space-y-1">
            <li
              v-for="(feature, i) in shortFeatures(plan)"
              :key="i"
              class="flex items-center justify-end gap-1.5 text-[11px]"
              :class="variant === 'dark' ? 'text-white/75' : 'text-desk-text'"
            >
              <span>{{ feature }}</span>
              <span class="text-emerald-500">✓</span>
            </li>
          </ul>
          <RouterLink
            to="/subscription"
            class="inline-flex w-full items-center justify-center rounded-lg px-3 py-2 text-xs font-bold text-white transition"
            :class="index === 1 ? 'bg-desk-orange hover:bg-orange-500' : 'bg-desk-dark hover:bg-desk-blue'"
          >
            خرید اشتراک
          </RouterLink>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { formatPrice } from '../../utils/format'

const props = defineProps({
  plans: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  variant: { type: String, default: 'compact' },
})

const defaultFeatures = ['دسترسی به آزمون‌ها', 'دانلود فایل‌ها', 'پشتیبانی']

const fallbackPlans = [
  { id: 'f1', name: 'یک‌ماهه', price: 0, duration_days: 30, features: ['آزمون‌های رایگان', 'مشاهده آگهی‌ها'] },
  { id: 'f2', name: 'سه‌ماهه', price: 490000, duration_days: 90, features: ['تمام آزمون‌ها', 'دانلود فایل', 'رزومه‌ساز'] },
  { id: 'f3', name: 'شش‌ماهه', price: 890000, duration_days: 180, features: ['همه امکانات', 'پشتیبانی ویژه'] },
]

const displayPlans = computed(() => {
  const list = props.plans?.length ? props.plans : fallbackPlans
  return list.slice(0, 3)
})

function shortFeatures(plan) {
  return (plan.features || defaultFeatures).slice(0, 3)
}

function cardClass(index) {
  if (props.variant === 'dark') {
    return index === 1
      ? 'bg-white/10 ring-2 ring-desk-orange'
      : 'bg-white/5 ring-1 ring-white/10'
  }
  return index === 1
    ? 'bg-white ring-2 ring-desk-orange shadow-sm'
    : 'bg-white ring-1 ring-surface-line'
}

function rowClass(index) {
  return index === 1
    ? 'border border-desk-orange/40 bg-orange-50'
    : 'border border-surface-line bg-surface-page'
}
</script>
