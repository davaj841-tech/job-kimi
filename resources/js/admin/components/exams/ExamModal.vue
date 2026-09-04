<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold text-slate-800">
          {{ exam?.id ? 'ویرایش آزمون' : 'آزمون جدید' }}
        </h3>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-700"
          @click="$emit('close')"
        >
          ✕
        </button>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600"
            >عنوان *</label
          >
          <input v-model="form.title" required class="input" @input="onTitle" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600"
            >برچسب سئو *</label
          >
          <input
            v-model="form.seo_tag"
            required
            class="input"
            dir="ltr"
            placeholder="مثال: آزمون_استخدامی_بانک_سینا"
            @blur="normalizeSeo"
          />
          <p class="mt-1 text-[11px] text-slate-400">
            فاصله‌ها به زیرخط تبدیل می‌شوند؛ برای جستجوی گوگل مفید است.
          </p>
        </div>
        <div>
          <ClassificationSelect
            v-model="form.job_classification_id"
            :items="parentClassifications"
            :multiple="false"
            :show-all="false"
            label="طبقه‌بندی *"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600"
            >توضیحات</label
          >
          <p class="mb-2 text-[11px] text-slate-400">
            ویرایشگر کامل: فرمت متن، جدول، فرمول LaTeX، تصویر و لینک.
          </p>
          <RichEditor v-model="form.description" size="exam" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600"
            >مدت زمان: {{ form.duration_minutes }} دقیقه</label
          >
          <input
            v-model.number="form.duration_minutes"
            type="range"
            min="5"
            max="300"
            step="5"
            class="w-full"
          />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600"
              >نمره قبولی</label
            >
            <input
              v-model.number="form.passing_score"
              type="number"
              min="0"
              class="input"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600"
              >مجموع نمرات</label
            >
            <input
              v-model.number="form.total_marks"
              type="number"
              min="1"
              class="input"
            />
          </div>
        </div>
        <div
          class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2"
        >
          <span class="text-sm">آزمون رایگان است؟</span>
          <input v-model="form.is_free" type="checkbox" class="h-4 w-4" />
        </div>
        <div v-if="!form.is_free">
          <label class="mb-1 block text-xs font-medium text-slate-600"
            >قیمت فروش موردی (ریال)</label
          >
          <input
            v-model.number="form.price"
            type="number"
            min="0"
            class="input"
          />
          <p class="mt-1 text-[11px] text-slate-400">
            برای فروش موردی؛ با اشتراک هم از فیلد زیر قابل تنظیم است.
          </p>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600"
            >نیاز به اشتراک</label
          >
          <select v-model="form.subscription_required" class="input">
            <option value="any">همه (موردی یا اشتراک)</option>
            <option value="free">فقط رایگان</option>
            <option value="paid">فقط اشتراک پولی</option>
          </select>
        </div>

        <div class="space-y-3 rounded-xl border border-slate-200 p-3">
          <label class="flex items-center justify-between text-sm">
            <span>آزمون تصادفی (از سوالات پرتکرار طبقه‌بندی)</span>
            <input v-model="form.is_random" type="checkbox" class="h-4 w-4" />
          </label>
          <template v-if="form.is_random">
            <label class="flex items-center gap-2 text-xs text-slate-600">
              <input
                v-model="form.prefer_frequent"
                type="checkbox"
                class="h-4 w-4"
              />
              اولویت با سوالات پرتکرار
            </label>
            <p class="text-[11px] text-slate-400">
              تعداد سوال هر درس را مشخص کنید. سوالات از آزمون‌های منتشرشدهٔ همان
              طبقه‌بندی انتخاب می‌شوند.
            </p>
            <div
              v-for="s in subjectRows"
              :key="s.slug"
              class="flex items-center gap-2"
            >
              <span class="w-28 shrink-0 text-xs font-medium"
                >{{ s.icon }} {{ s.name }}</span
              >
              <input
                v-model.number="form.subject_counts[s.slug]"
                type="number"
                min="0"
                max="200"
                class="input"
                placeholder="0"
              />
            </div>
            <p class="text-xs text-slate-500">
              مجموع سوالات:
              {{
                Object.values(form.subject_counts || {}).reduce(
                  (a, b) => a + Number(b || 0),
                  0
                )
              }}
            </p>

            <div
              v-if="form.job_classification_id"
              class="space-y-2 rounded-lg border border-orange-100 bg-orange-50/40 p-2"
            >
              <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-bold text-slate-700">
                  سوالات تکراری بین آزمون‌ها (اختیاری)
                </p>
                <button
                  type="button"
                  class="text-[11px] font-bold text-orange-600"
                  :disabled="duplicatesLoading"
                  @click="loadExamDuplicates"
                >
                  {{ duplicatesLoading ? '...' : 'بروزرسانی' }}
                </button>
              </div>
              <p class="text-[11px] text-slate-500">
                سوالات انتخاب‌شده در استخر آزمون تصادفی این آزمون قرار می‌گیرند.
              </p>
              <div
                v-if="duplicatesLoading"
                class="py-2 text-center text-xs text-slate-500"
              >
                در حال بارگذاری...
              </div>
              <div
                v-else-if="!duplicateGroups.length"
                class="py-2 text-center text-xs text-slate-500"
              >
                سوال تکراری برای این طبقه‌بندی یافت نشد.
              </div>
              <div
                v-else
                class="max-h-40 space-y-1 overflow-y-auto rounded-lg bg-white p-2"
              >
                <label
                  v-for="group in duplicateGroups"
                  :key="group.fingerprint"
                  class="flex cursor-pointer items-start gap-2 rounded-md px-1 py-1 hover:bg-slate-50"
                >
                  <input
                    v-model="form.duplicate_fingerprints"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 accent-orange-500"
                    :value="group.fingerprint"
                  />
                  <span class="text-[11px] leading-snug text-slate-700">
                    {{ group.preview }}
                    <span class="text-slate-400">
                      ({{ group.count }} بار)
                    </span>
                  </span>
                </label>
              </div>
            </div>
          </template>
        </div>

        <div class="rounded-xl border border-slate-200 p-3">
          <div class="mb-2 flex items-center justify-between">
            <span class="text-sm">نمره منفی</span>
            <input
              v-model="form.has_negative_marking"
              type="checkbox"
              class="h-4 w-4"
            />
          </div>
          <input
            v-if="form.has_negative_marking"
            v-model.number="form.negative_mark_ratio"
            type="number"
            min="0"
            max="1"
            step="0.0001"
            class="input"
            placeholder="نسبت نمره منفی"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600"
            >وضعیت</label
          >
          <select v-model="form.status" class="input">
            <option value="draft">پیش‌نویس</option>
            <option value="published">منتشر شده</option>
            <option value="archived">بایگانی</option>
          </select>
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-muted" @click="$emit('close')">
            انصراف
          </button>
          <button type="submit" class="btn-primary-admin" :disabled="saving">
            {{ saving ? 'در حال ذخیره...' : 'ذخیره' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import adminApi from '../../api/client'
import { useExamSubjectsStore } from '../../stores/examSubjects'
import ClassificationSelect from '../ui/ClassificationSelect.vue'
import RichEditor from '../ui/RichEditor.vue'

const props = defineProps({
  open: Boolean,
  exam: { type: Object, default: null },
  classifications: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const subjectsStore = useExamSubjectsStore()
const saving = ref(false)
const error = ref('')
const form = reactive(emptyForm())
const duplicateGroups = ref([])
const duplicatesLoading = ref(false)

const parentClassifications = computed(() =>
  (props.classifications || []).filter((c) => !c.parent_id)
)

const subjectRows = computed(() =>
  (subjectsStore.subjects || []).filter((s) => s.is_active !== false)
)

onMounted(() => {
  subjectsStore.fetchSubjects().catch(() => {})
})

watch(
  () => subjectsStore.subjects,
  (list) => {
    for (const s of list || []) {
      if (form.subject_counts[s.slug] === undefined) {
        form.subject_counts[s.slug] = 0
      }
    }
  },
  { deep: true }
)

watch(
  () => [props.open, props.exam],
  () => {
    if (!props.open) return
    Object.assign(form, props.exam?.id ? mapExam(props.exam) : emptyForm())
    error.value = ''
    if (form.is_random && form.job_classification_id) {
      loadExamDuplicates()
    } else {
      duplicateGroups.value = []
    }
  },
  { immediate: true }
)

watch(
  () => [form.is_random, form.job_classification_id],
  ([isRandom, classId]) => {
    if (!props.open || !isRandom || !classId) {
      if (!isRandom) form.duplicate_fingerprints = []
      return
    }
    loadExamDuplicates()
  }
)

function emptySubjectCounts() {
  const counts = {}
  for (const s of subjectsStore.subjects || []) {
    counts[s.slug] = 0
  }
  return counts
}

function emptyForm() {
  return {
    title: '',
    seo_tag: '',
    job_classification_id: '',
    description: '',
    duration_minutes: 60,
    passing_score: 50,
    total_marks: 100,
    is_free: true,
    price: 0,
    subscription_required: 'any',
    has_negative_marking: false,
    negative_mark_ratio: 0.3333,
    status: 'published',
    is_random: false,
    prefer_frequent: true,
    subject_counts: emptySubjectCounts(),
    duplicate_fingerprints: [],
  }
}

function mapExam(exam) {
  const cfg = exam.random_config || {}
  const subjects = cfg.subjects || {}
  const counts = emptySubjectCounts()
  Object.keys(subjects).forEach((slug) => {
    counts[slug] = Number(subjects[slug] || 0)
  })
  return {
    title: exam.title || '',
    seo_tag: exam.seo_tag || '',
    job_classification_id: exam.job_classification_id || '',
    description: exam.description || '',
    duration_minutes: exam.duration_minutes || 60,
    passing_score: exam.passing_score || 50,
    total_marks: exam.total_marks || 100,
    is_free: Boolean(exam.is_free),
    price: Number(exam.price || 0),
    subscription_required: exam.subscription_required || 'any',
    has_negative_marking: Boolean(exam.has_negative_marking),
    negative_mark_ratio: Number(exam.negative_mark_ratio ?? 0.3333),
    status: exam.status || 'published',
    is_random: Boolean(exam.is_random),
    prefer_frequent: cfg.prefer_frequent !== false,
    subject_counts: counts,
    duplicate_fingerprints: Array.isArray(cfg.duplicate_fingerprints)
      ? [...cfg.duplicate_fingerprints]
      : [],
  }
}

function toSeoTag(text) {
  return String(text || '')
    .trim()
    .replace(/\s+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '')
}

function normalizeSeo() {
  form.seo_tag = toSeoTag(form.seo_tag)
}

function onTitle() {
  if (props.exam?.id && form.seo_tag) return
  form.seo_tag = toSeoTag(form.title)
}

async function loadExamDuplicates() {
  if (!form.job_classification_id) {
    duplicateGroups.value = []
    return
  }
  duplicatesLoading.value = true
  try {
    const { data } = await adminApi.get('/admin/questions/duplicates', {
      params: {
        job_classification_id: form.job_classification_id,
        per_page: 100,
      },
    })
    const payload = data?.data || data || {}
    duplicateGroups.value = payload.groups || []
  } catch {
    duplicateGroups.value = []
  } finally {
    duplicatesLoading.value = false
  }
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    normalizeSeo()
    if (!form.seo_tag) {
      error.value = 'برچسب سئو الزامی است.'
      return
    }
    if (!form.job_classification_id) {
      error.value = 'انتخاب طبقه‌بندی الزامی است.'
      return
    }
    if (form.is_random) {
      const total = Object.values(form.subject_counts || {}).reduce(
        (a, b) => a + Number(b || 0),
        0
      )
      if (total <= 0) {
        error.value = 'برای آزمون تصادفی حداقل یک درس با تعداد سوال مشخص کنید.'
        return
      }
    }
    const subjects = {}
    Object.entries(form.subject_counts || {}).forEach(([slug, n]) => {
      const num = Number(n || 0)
      if (num > 0) subjects[slug] = num
    })
    const payload = {
      title: form.title,
      seo_tag: form.seo_tag,
      job_classification_id: Number(form.job_classification_id),
      description: form.description,
      duration_minutes: form.duration_minutes,
      passing_score: form.passing_score,
      total_marks: form.total_marks,
      is_free: form.is_free,
      price: form.is_free ? 0 : Number(form.price || 0),
      subscription_required: form.subscription_required,
      has_negative_marking: form.has_negative_marking,
      negative_mark_ratio: form.negative_mark_ratio,
      status: form.status,
      is_random: form.is_random,
      random_config: form.is_random
        ? {
            prefer_frequent: form.prefer_frequent,
            subjects,
            duplicate_fingerprints: form.duplicate_fingerprints || [],
          }
        : null,
      job_post_id: null,
    }
    emit('saved', { id: props.exam?.id || null, payload })
  } catch (e) {
    error.value = e.response?.data?.message || 'ذخیره ناموفق بود.'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.input {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
textarea.input {
  @apply h-auto py-2;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.btn-primary-admin {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
