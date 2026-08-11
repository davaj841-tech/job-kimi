<template>
  <Teleport to="body">
    <Transition name="job-drawer">
      <div
        v-if="open"
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
      >
        <div
          class="absolute inset-0 bg-black/50 backdrop-blur-sm"
          @click="close"
        />

        <div
          class="absolute inset-y-0 left-0 flex h-full w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-slate-900 md:max-w-lg"
        >
          <div
            class="sticky top-0 z-10 flex items-center justify-between border-b border-surface-line bg-white/90 p-3 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90"
          >
            <button
              type="button"
              class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
              aria-label="بستن"
              @click="close"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>
            <div class="flex items-center gap-1">
              <RouterLink
                v-if="job?.id"
                :to="`/jobs/${job.id}`"
                class="rounded-lg px-3 py-2 text-xs font-bold text-brand hover:bg-brand-soft"
                @click="close"
              >
                صفحه کامل
              </RouterLink>
              <button
                type="button"
                class="flex h-11 w-11 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
                @click="onBookmark"
              >
                <BookmarkIcon
                  class="h-5 w-5"
                  :class="
                    job?.is_bookmarked
                      ? 'fill-amber-400 text-amber-400'
                      : 'text-desk-muted'
                  "
                />
              </button>
            </div>
          </div>

          <div
            v-if="loading"
            class="flex flex-1 items-center justify-center p-8"
          >
            <div
              class="h-8 w-8 animate-spin rounded-full border-2 border-brand border-t-transparent"
            />
          </div>

          <div
            v-else-if="job"
            class="flex-1 overflow-y-auto pb-24"
          >
            <div class="p-6 text-center">
              <div
                class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800"
              >
                <BuildingOfficeIcon class="h-8 w-8 text-desk-muted" />
              </div>
              <h2 class="text-lg font-black text-desk-text dark:text-white">
                {{ job.title }}
              </h2>
              <p class="mt-1 text-sm text-desk-muted">{{ companyName }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 px-5">
              <InfoBox
                :icon="MapPinIcon"
                label="موقعیت"
                :value="location"
              />
              <InfoBox
                :icon="BriefcaseIcon"
                label="نوع همکاری"
                :value="jobTypeLabel"
              />
              <InfoBox
                :icon="AcademicCapIcon"
                label="تحصیلات"
                :value="job.education || '—'"
              />
              <InfoBox
                :icon="CalendarIcon"
                label="مهلت ثبت‌نام"
                :value="deadlineText"
                :alert="daysLeft !== null && daysLeft <= 3"
              />
            </div>

            <div
              v-if="tags.length"
              class="mt-4 flex flex-wrap gap-2 px-5"
            >
              <span
                v-for="tag in tags"
                :key="tag"
                class="rounded-lg bg-slate-100 px-3 py-1 text-xs text-desk-muted dark:bg-slate-800"
              >
                {{ tag }}
              </span>
            </div>

            <div
              v-if="job.description"
              class="mt-6 px-5"
            >
              <h3 class="mb-3 text-sm font-bold text-desk-text dark:text-white">
                توضیحات
              </h3>
              <div
                class="prose prose-sm max-w-none leading-relaxed text-desk-muted dark:prose-invert"
                v-html="job.description"
              />
            </div>

            <div
              v-if="requirementItems.length"
              class="mt-6 px-5"
            >
              <h3 class="mb-3 text-sm font-bold text-desk-text dark:text-white">
                نیازمندی‌ها
              </h3>
              <ul class="space-y-2">
                <li
                  v-for="(req, i) in requirementItems"
                  :key="i"
                  class="flex items-start gap-2 text-sm text-desk-muted"
                >
                  <CheckCircleIcon
                    class="mt-0.5 h-4 w-4 flex-shrink-0 text-brand"
                  />
                  <span>{{ req }}</span>
                </li>
              </ul>
            </div>
            <div
              v-else-if="job.requirements"
              class="mt-6 px-5"
            >
              <h3 class="mb-3 text-sm font-bold text-desk-text dark:text-white">
                نیازمندی‌ها
              </h3>
              <div
                class="prose prose-sm max-w-none text-desk-muted dark:prose-invert"
                v-html="job.requirements"
              />
            </div>

            <div
              v-if="job.source_url"
              class="mt-6 px-5"
            >
              <a
                :href="job.source_url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 text-sm text-brand hover:underline"
              >
                <LinkIcon class="h-4 w-4" />
                مشاهده در سایت منبع
              </a>
            </div>
          </div>

          <div
            class="sticky bottom-0 border-t border-surface-line bg-white/90 p-4 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90"
          >
            <a
              v-if="applyHref"
              :href="applyHref"
              target="_blank"
              rel="noopener"
              class="btn-primary flex w-full items-center justify-center shadow-lg shadow-brand/25"
            >
              ثبت‌نام / ارسال رزومه
            </a>
            <RouterLink
              v-else-if="job?.id"
              :to="`/jobs/${job.id}`"
              class="btn-primary flex w-full items-center justify-center"
              @click="close"
            >
              جزئیات و آزمون‌های مرتبط
            </RouterLink>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import {
  AcademicCapIcon,
  BookmarkIcon,
  BriefcaseIcon,
  BuildingOfficeIcon,
  CalendarIcon,
  CheckCircleIcon,
  LinkIcon,
  MapPinIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import api from '../../api/client'
import { formatDate, unwrapItem } from '../../utils/format'
import InfoBox from './InfoBox.vue'

const props = defineProps({
  modelValue: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'bookmark'])

const open = computed({
  get: () => !!props.modelValue,
  set: (v) => {
    if (!v) emit('update:modelValue', null)
  },
})

const job = ref(null)
const loading = ref(false)

const TYPE_LABELS = {
  full_time: 'تمام‌وقت',
  part_time: 'پاره‌وقت',
  remote: 'دورکاری',
  contract: 'قراردادی',
  internship: 'کارآموزی',
  military: 'امریه',
}

watch(
  () => props.modelValue,
  async (val) => {
    if (!val) {
      job.value = null
      return
    }
    job.value = { ...val }
    if (!val.description && val.id) {
      loading.value = true
      try {
        const { data } = await api.get(`/job-posts/${val.id}`)
        job.value = { ...val, ...unwrapItem(data) }
      } catch (_) {
        /* keep list payload */
      } finally {
        loading.value = false
      }
    }
  },
  { immediate: true },
)

const companyName = computed(
  () =>
    job.value?.company?.name ||
    job.value?.organization_name ||
    job.value?.classification_name ||
    job.value?.company_name ||
    '—',
)

const location = computed(
  () =>
    job.value?.location ||
    [job.value?.city, job.value?.province].filter(Boolean).join('، ') ||
    'سراسر کشور',
)

const jobTypeLabel = computed(() => {
  const t = job.value?.type || job.value?.employment_type
  return TYPE_LABELS[t] || t || '—'
})

const tags = computed(() => {
  const t = job.value?.tags
  return Array.isArray(t) ? t : []
})

const daysLeft = computed(() => {
  const raw = job.value?.deadline || job.value?.registration_deadline
  if (!raw) return null
  const end = new Date(raw)
  if (Number.isNaN(end.getTime())) return null
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  end.setHours(0, 0, 0, 0)
  return Math.ceil((end - today) / (1000 * 60 * 60 * 24))
})

const deadlineText = computed(() => {
  const raw = job.value?.deadline || job.value?.registration_deadline
  if (!raw) return '—'
  return formatDate(raw)
})

const requirementItems = computed(() => {
  const r = job.value?.requirements
  if (Array.isArray(r)) return r.filter(Boolean)
  if (typeof r === 'string' && r && !r.includes('<')) {
    return r
      .split(/\n|•|·|-/)
      .map((s) => s.trim())
      .filter((s) => s.length > 2)
      .slice(0, 12)
  }
  return []
})

const applyHref = computed(
  () => job.value?.registration_link || job.value?.source_url || null,
)

function close() {
  emit('update:modelValue', null)
}

function onBookmark() {
  if (job.value?.id) emit('bookmark', job.value.id)
}
</script>

<style scoped>
.job-drawer-enter-active,
.job-drawer-leave-active {
  transition: opacity 0.25s ease;
}
.job-drawer-enter-active .absolute.left-0,
.job-drawer-leave-active .absolute.left-0 {
  transition: transform 0.3s ease;
}
.job-drawer-enter-from,
.job-drawer-leave-to {
  opacity: 0;
}
.job-drawer-enter-from .absolute.left-0,
.job-drawer-leave-to .absolute.left-0 {
  transform: translateX(-100%);
}
</style>
