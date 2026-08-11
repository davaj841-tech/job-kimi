<template>
  <article
    class="group relative max-h-[120px] cursor-pointer overflow-hidden rounded-2xl border border-surface-line bg-white p-3.5 transition-all duration-300 hover:border-brand/30 hover:shadow-lg hover:shadow-brand/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand/40"
    role="button"
    tabindex="0"
    @click="$emit('click')"
    @keydown.enter.prevent="$emit('click')"
  >
    <div class="flex items-start gap-3">
      <div
        class="flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl border border-surface-line bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
      >
        <img
          v-if="logo"
          :src="logo"
          :alt="companyName"
          class="h-full w-full object-cover"
        />
        <BuildingOfficeIcon
          v-else
          class="h-6 w-6 text-desk-muted"
        />
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h3
              class="truncate text-sm font-bold text-desk-text transition-colors group-hover:text-brand dark:text-white dark:group-hover:text-brand"
            >
              {{ job.title }}
            </h3>
            <p class="mt-0.5 truncate text-xs text-desk-muted">
              {{ companyName }}
              <span
                v-if="job.is_featured"
                class="mr-1 text-desk-orange"
                >· ویژه</span
              >
            </p>
          </div>
          <button
            type="button"
            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 md:h-9 md:w-9"
            :aria-label="job.is_bookmarked ? 'حذف نشان' : 'نشان‌گذاری'"
            @click.stop="$emit('bookmark', job.id)"
          >
            <BookmarkIcon
              class="h-5 w-5 transition-colors"
              :class="
                job.is_bookmarked
                  ? 'fill-amber-400 text-amber-400'
                  : 'text-desk-muted'
              "
            />
          </button>
        </div>

        <div
          class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-desk-muted"
        >
          <span class="inline-flex items-center gap-1">
            <MapPinIcon class="h-3.5 w-3.5" />
            {{ location }}
          </span>
          <span
            v-if="jobTypeLabel"
            class="inline-flex items-center gap-1"
          >
            <BriefcaseIcon class="h-3.5 w-3.5" />
            {{ jobTypeLabel }}
          </span>
          <span class="inline-flex items-center gap-1">
            <ClockIcon class="h-3.5 w-3.5" />
            {{ timeAgo }}
          </span>
          <span
            v-if="daysLeft !== null"
            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-medium"
            :class="deadlineClass"
          >
            <template v-if="daysLeft > 0">{{ toFaDigits(daysLeft) }} روز مانده</template>
            <template v-else-if="daysLeft === 0">امروز آخرین فرصت</template>
            <template v-else>منقضی</template>
          </span>
        </div>
      </div>
    </div>

    <div
      class="pointer-events-none absolute left-3 top-1/2 hidden -translate-y-1/2 opacity-0 transition-opacity group-hover:opacity-100 md:flex"
    >
      <ChevronLeftIcon class="h-5 w-5 text-desk-muted" />
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import {
  BookmarkIcon,
  BriefcaseIcon,
  BuildingOfficeIcon,
  ChevronLeftIcon,
  ClockIcon,
  MapPinIcon,
} from '@heroicons/vue/24/outline'
import { formatDistanceToNow, toFaDigits } from '../../utils/format'

const props = defineProps({
  job: { type: Object, required: true },
})

defineEmits(['click', 'bookmark'])

const TYPE_LABELS = {
  full_time: 'تمام‌وقت',
  part_time: 'پاره‌وقت',
  remote: 'دورکاری',
  contract: 'قراردادی',
  internship: 'کارآموزی',
  military: 'امریه',
}

const companyName = computed(
  () =>
    props.job.company?.name ||
    props.job.organization_name ||
    props.job.classification_name ||
    props.job.company_name ||
    'سازمان',
)

const logo = computed(() => props.job.company?.logo || null)

const location = computed(
  () => props.job.location || [props.job.city, props.job.province].filter(Boolean).join('، ') || 'سراسر کشور',
)

const jobTypeLabel = computed(() => {
  const t = props.job.type || props.job.employment_type
  return TYPE_LABELS[t] || t || ''
})

const timeAgo = computed(() =>
  formatDistanceToNow(props.job.published_at || props.job.created_at),
)

const daysLeft = computed(() => {
  const raw = props.job.deadline || props.job.registration_deadline
  if (!raw) return null
  const end = new Date(raw)
  if (Number.isNaN(end.getTime())) return null
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  end.setHours(0, 0, 0, 0)
  return Math.ceil((end - today) / (1000 * 60 * 60 * 24))
})

const deadlineClass = computed(() => {
  if (daysLeft.value === null) return ''
  if (daysLeft.value < 0) return 'bg-slate-100 text-slate-500 dark:bg-slate-800'
  if (daysLeft.value <= 3) return 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'
  if (daysLeft.value <= 7) return 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400'
  return 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400'
})
</script>
