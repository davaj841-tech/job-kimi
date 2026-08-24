<template>
  <section
    v-if="displayStats.length"
    class="relative z-20 -mt-6 px-4 pb-2 sm:-mt-8"
  >
    <div
      ref="root"
      class="mx-auto grid max-w-5xl grid-cols-2 gap-3 rounded-2xl border border-surface-line bg-white p-4 shadow-[0_12px_40px_-12px_rgba(15,39,68,0.18)] sm:grid-cols-4 sm:gap-4 sm:p-6"
    >
      <div
        v-for="stat in displayStats"
        :key="stat.label"
        class="animate-on-scroll text-center"
      >
        <div
          class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-xl"
          :class="stat.tint"
        >
          <component :is="stat.icon" class="h-5 w-5" :class="stat.iconClass" />
        </div>
        <div class="text-xl font-black tabular-nums text-desk-dark sm:text-2xl">
          <span
            class="js-count"
            :data-value="stat.value"
            :data-decimals="stat.decimals"
            >0</span
          >{{ stat.suffix }}
        </div>
        <div class="mt-0.5 text-[11px] text-desk-muted sm:text-xs">
          {{ stat.label }}
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  AcademicCapIcon,
  BriefcaseIcon,
  DocumentTextIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'
import { animateCountUp } from '../../composables/useScrollAnimations'

const props = withDefaults(
  defineProps<{
    users?: number
    jobs?: number
    files?: number
    examsCount?: number
  }>(),
  {
    users: 0,
    jobs: 0,
    files: 0,
    examsCount: 0,
  }
)

const root = ref<HTMLElement | null>(null)

const displayStats = computed(() =>
  [
    {
      icon: BriefcaseIcon,
      value: props.jobs,
      suffix: '+',
      label: 'آگهی استخدام',
      decimals: 0,
      tint: 'bg-sky-50',
      iconClass: 'text-sky-600',
    },
    {
      icon: UsersIcon,
      value: props.users,
      suffix: '+',
      label: 'کاربر فعال',
      decimals: 0,
      tint: 'bg-brand-soft',
      iconClass: 'text-brand',
    },
    {
      icon: DocumentTextIcon,
      value: props.files,
      suffix: '+',
      label: 'فایل آموزشی',
      decimals: 0,
      tint: 'bg-amber-50',
      iconClass: 'text-desk-orange',
    },
    {
      icon: AcademicCapIcon,
      value: props.examsCount,
      suffix: '+',
      label: 'آزمون برگزارشده',
      decimals: 0,
      tint: 'bg-emerald-50',
      iconClass: 'text-desk-green',
    },
  ].filter((stat) => Number(stat.value) > 0)
)

onMounted(() => {
  root.value?.querySelectorAll<HTMLElement>('.js-count').forEach((el) => {
    animateCountUp(el, Number(el.dataset.value || 0), {
      decimals: Number(el.dataset.decimals || 0),
    })
  })
})
</script>
