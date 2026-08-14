<template>
  <section class="bg-surface-page py-6 sm:py-7">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-3 flex items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-black text-desk-text sm:text-xl">💼 آخرین استخدام‌ها</h2>
          <p class="mt-0.5 text-xs text-desk-muted">فرصت‌های تازه، فیلترشده بر اساس رسته</p>
        </div>
        <RouterLink
          to="/jobs"
          class="text-xs font-bold text-brand hover:underline sm:text-sm"
        >
          مشاهده همه
        </RouterLink>
      </div>

      <div
        v-if="classifications.length"
        class="mb-4 flex flex-wrap gap-2"
      >
        <button
          type="button"
          class="rounded-full px-3 py-1.5 text-xs font-bold transition"
          :class="chipClass(null)"
          @click="selected = null"
        >
          همه
        </button>
        <button
          v-for="item in classifications"
          :key="item.id"
          type="button"
          class="rounded-full px-3 py-1.5 text-xs font-bold transition"
          :class="chipClass(item.id)"
          @click="selected = item.id"
        >
          {{ item.name }}
        </button>
      </div>

      <div
        v-if="loading"
        class="py-8 text-center text-sm text-desk-muted"
      >
        در حال بارگذاری...
      </div>
      <HomeRail v-else-if="cards.length">
        <button
          v-for="job in cards"
          :key="job.id"
          type="button"
          class="home-rail-card"
          @click="$router.push(`/jobs/${job.id}`)"
        >
          <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <span class="text-xl" aria-hidden="true">💼</span>
            <span
              v-if="job.is_new || isRecent(job)"
              class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600"
              >جدید</span
            >
            <span v-else class="text-[10px] font-bold text-desk-muted">{{
              yearLabel
            }}</span>
          </div>
          <p class="line-clamp-2 flex-1 text-sm font-bold text-desk-text">
            {{ job.classification_name || job.company_name || job.title }}
          </p>
          <p class="mt-1 line-clamp-1 text-[11px] text-desk-muted">{{ job.title }}</p>
        </button>
      </HomeRail>
      <p
        v-else
        class="py-8 text-center text-sm text-desk-muted"
      >
        آگهی‌ای یافت نشد.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import HomeRail from './HomeRail.vue'

const props = defineProps({
  jobs: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const selected = ref(null)
const yearLabel = '۱۴۰۵'

const cards = computed(() => {
  const list = props.jobs || []
  if (!selected.value) return list.slice(0, 12)
  const parent = (props.classifications || []).find(
    (c) => Number(c.id) === Number(selected.value),
  )
  const ids = new Set([
    Number(selected.value),
    ...(parent?.child_ids || []).map(Number),
  ])
  return list.filter((j) => ids.has(Number(j.job_classification_id))).slice(0, 12)
})

function chipClass(id) {
  const on = id === null ? !selected.value : Number(selected.value) === Number(id)
  return on
    ? 'bg-desk-dark text-white'
    : 'bg-slate-100 text-desk-text hover:bg-slate-200'
}

function letter(job) {
  return (job.company_name || job.classification_name || job.title || '؟').charAt(0)
}

function isRecent(job) {
  const d = job.published_at || job.created_at
  if (!d) return false
  const t = new Date(d).getTime()
  return !Number.isNaN(t) && Date.now() - t < 7 * 24 * 60 * 60 * 1000
}
</script>
