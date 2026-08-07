<template>
  <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold text-slate-800">{{ exam?.id ? 'ویرایش آزمون' : 'آزمون جدید' }}</h3>
        <button type="button" class="text-slate-400 hover:text-slate-700" @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">عنوان *</label>
          <input v-model="form.title" required class="input" @input="onTitle" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">برچسب سئو *</label>
          <input
            v-model="form.seo_tag"
            required
            class="input"
            dir="ltr"
            placeholder="مثال: آزمون_استخدامی_بانک_سینا"
            @blur="normalizeSeo"
          />
          <p class="mt-1 text-[11px] text-slate-400">فاصله‌ها به زیرخط تبدیل می‌شوند؛ برای جستجوی گوگل مفید است.</p>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">طبقه‌بندی *</label>
          <select v-model="form.job_classification_id" required class="input">
            <option disabled value="">انتخاب طبقه‌بندی</option>
            <option v-for="c in parentClassifications" :key="c.id" :value="c.id">{{ c.raw_name || c.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">توضیحات</label>
          <textarea v-model="form.description" rows="3" class="input" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">مدت زمان: {{ form.duration_minutes }} دقیقه</label>
          <input v-model.number="form.duration_minutes" type="range" min="5" max="300" step="5" class="w-full" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">نمره قبولی</label>
            <input v-model.number="form.passing_score" type="number" min="0" class="input" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">مجموع نمرات</label>
            <input v-model.number="form.total_marks" type="number" min="1" class="input" />
          </div>
        </div>
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
          <span class="text-sm">آزمون رایگان است؟</span>
          <input v-model="form.is_free" type="checkbox" class="h-4 w-4" />
        </div>
        <div v-if="!form.is_free">
          <label class="mb-1 block text-xs font-medium text-slate-600">قیمت (ریال)</label>
          <input v-model.number="form.price" type="number" min="0" class="input" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">نیاز به اشتراک</label>
          <select v-model="form.subscription_required" class="input">
            <option value="any">همه</option>
            <option value="free">فقط رایگان</option>
            <option value="paid">فقط پولی</option>
          </select>
        </div>
        <div class="rounded-xl border border-slate-200 p-3">
          <div class="mb-2 flex items-center justify-between">
            <span class="text-sm">نمره منفی</span>
            <input v-model="form.has_negative_marking" type="checkbox" class="h-4 w-4" />
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
          <label class="mb-1 block text-xs font-medium text-slate-600">وضعیت</label>
          <select v-model="form.status" class="input">
            <option value="draft">پیش‌نویس</option>
            <option value="published">منتشر شده</option>
            <option value="archived">بایگانی</option>
          </select>
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-muted" @click="$emit('close')">انصراف</button>
          <button type="submit" class="btn-primary-admin" :disabled="saving">
            {{ saving ? 'در حال ذخیره...' : 'ذخیره' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  open: Boolean,
  exam: { type: Object, default: null },
  classifications: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const error = ref('');
const form = reactive(emptyForm());

const parentClassifications = computed(() =>
  (props.classifications || []).filter((c) => !c.parent_id)
);

watch(
  () => [props.open, props.exam],
  () => {
    if (!props.open) return;
    Object.assign(form, props.exam?.id ? mapExam(props.exam) : emptyForm());
    error.value = '';
  },
  { immediate: true }
);

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
    status: 'draft',
  };
}

function mapExam(exam) {
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
    status: exam.status || 'draft',
  };
}

function toSeoTag(text) {
  return String(text || '')
    .trim()
    .replace(/\s+/g, '_')
    .replace(/_+/g, '_')
    .replace(/^_|_$/g, '');
}

function normalizeSeo() {
  form.seo_tag = toSeoTag(form.seo_tag);
}

function onTitle() {
  if (props.exam?.id && form.seo_tag) return;
  form.seo_tag = toSeoTag(form.title);
}

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    normalizeSeo();
    if (!form.seo_tag) {
      error.value = 'برچسب سئو الزامی است.';
      return;
    }
    if (!form.job_classification_id) {
      error.value = 'انتخاب طبقه‌بندی الزامی است.';
      return;
    }
    const payload = {
      ...form,
      job_classification_id: Number(form.job_classification_id),
      price: form.is_free ? 0 : Number(form.price || 0),
      category_id: undefined,
      job_post_id: null,
      slug: undefined,
    };
    emit('saved', { id: props.exam?.id || null, payload });
  } catch (e) {
    error.value = e.response?.data?.message || 'ذخیره ناموفق بود.';
  } finally {
    saving.value = false;
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
