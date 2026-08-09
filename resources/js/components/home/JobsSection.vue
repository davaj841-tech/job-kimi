<template>
  <section class="py-14">
    <div class="desk-container">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="desk-section-title">آخرین آگهی‌های استخدام</h2>
          <p class="mt-1 text-sm text-desk-muted">فرصت‌های شغلی ویژه برای آمادگی و اقدام سریع</p>
        </div>
        <RouterLink to="/jobs" class="text-sm font-bold text-desk-orange hover:underline">
          مشاهده همه
        </RouterLink>
      </div>

      <div class="mb-6">
        <JobClassificationFilter v-model="selectedClassification" :items="classifications" />
      </div>

      <div v-if="loading" class="py-10 text-center text-sm text-desk-muted">در حال بارگذاری...</div>
      <div v-else class="grid grid-cols-3 gap-5">
        <article
          v-for="job in displayJobs"
          :key="job.id"
          class="desk-card cursor-pointer overflow-hidden"
          @click="$router.push(`/jobs/${job.id}`)"
        >
          <div
            class="flex aspect-video items-center justify-center"
            :class="job.tint"
          >
            <DesktopIcon name="briefcase" :size="36" class="text-white/90" />
          </div>
          <div class="p-4 text-right">
            <div class="mb-2 flex flex-wrap items-center justify-end gap-2">
              <span
                v-if="job.is_featured"
                class="rounded-md bg-desk-green/15 px-2 py-0.5 text-[11px] font-bold text-desk-green"
              >
                ویژه
              </span>
              <span class="rounded-md bg-desk-gray px-2 py-0.5 text-[11px] text-desk-muted">
                {{ job.classification_name || job.company_name || 'عمومی' }}
              </span>
            </div>
            <h3 class="mb-1 line-clamp-2 text-base font-semibold text-desk-text">{{ job.title }}</h3>
            <p class="mb-2 text-sm text-desk-muted">{{ job.classification_name || job.company_name }}</p>
            <p class="text-xs text-desk-muted">
              {{ locationOf(job) }}
            </p>
          </div>
        </article>
      </div>

      <p v-if="!loading && !displayJobs.length" class="py-10 text-center text-sm text-desk-muted">
        آگهی‌ای یافت نشد.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import DesktopIcon from '../DesktopIcon.vue';
import JobClassificationFilter from '../JobClassificationFilter.vue';

const props = defineProps({
  jobs: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['filter']);

const selectedClassification = ref(null);
const tints = ['bg-desk-blue', 'bg-desk-orange', 'bg-[#0f766e]', 'bg-[#7c3aed]', 'bg-[#be123c]', 'bg-[#0369a1]'];

watch(selectedClassification, (id) => emit('filter', id));

const filtered = computed(() => {
  const list = props.jobs || [];
  if (!selectedClassification.value) return list;
  const parent = (props.classifications || []).find((c) => Number(c.id) === Number(selectedClassification.value));
  const ids = new Set([
    Number(selectedClassification.value),
    ...((parent?.child_ids || []).map(Number)),
  ]);
  return list.filter((j) => ids.has(Number(j.job_classification_id)));
});

const displayJobs = computed(() =>
  filtered.value.slice(0, 6).map((job, i) => ({
    ...job,
    tint: tints[i % tints.length],
  }))
);

function locationOf(job) {
  const provinces = Array.isArray(job.provinces) && job.provinces.length
    ? job.provinces.join('، ')
    : job.province;
  return [job.city, provinces].filter(Boolean).join('، ') || '—';
}
</script>
