<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">
          {{ post?.id ? 'ویرایش آگهی' : 'آگهی جدید' }}
        </h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-3" @submit.prevent="submit">
        <div>
          <label class="label">عنوان آگهی *</label>
          <input
            v-model="form.title"
            required
            class="field"
            placeholder="عنوان آگهی"
          />
        </div>

        <div>
          <label class="label">برچسب سئو (اختیاری)</label>
          <input
            v-model="form.seo_tag"
            class="field"
            dir="ltr"
            placeholder="مثال: استخدام_بانک_ملت_1405"
            @blur="normalizeSeo"
          />
          <p class="mt-1 text-[11px] text-slate-400">
            برای جستجوی گوگل؛ حروف، عدد و _ مجاز است.
          </p>
        </div>

        <div>
          <label class="label">طبقه‌بندی آگهی *</label>
          <select v-model="form.job_classification_id" required class="field">
            <option disabled value="">انتخاب طبقه‌بندی</option>
            <option v-for="c in classifications" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
          <p class="mt-1 text-[11px] text-slate-400">
            مدیریت مادر/فرزند از دکمه «طبقه‌بندی‌ها» کنار آگهی جدید
          </p>
        </div>

        <CatalogAttachFields
          v-model:auto-catalog="form.auto_catalog"
          v-model:exam-ids="form.exam_ids"
          v-model:pdf-ids="form.pdf_ids"
        />

        <div>
          <label class="label">شرح آگهی *</label>
          <RichEditor v-model="form.description" />
        </div>

        <div>
          <label class="label">استان‌ها (چند انتخابی — اختیاری)</label>
          <div
            class="max-h-36 overflow-y-auto rounded-xl border border-slate-200 p-2"
          >
            <label
              v-for="p in allProvinces"
              :key="p"
              class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50"
            >
              <input
                v-model="form.provinces"
                type="checkbox"
                :value="p"
                class="rounded border-slate-300 text-orange-500"
              />
              {{ p }}
            </label>
          </div>
        </div>

        <div>
          <label class="label">شهر (اختیاری)</label>
          <input v-model="form.city" class="field" placeholder="شهر" />
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <div>
            <label class="label">مهلت ثبت‌نام * (سال / ماه / روز)</label>
            <JalaliDatepicker v-model="form.registration_deadline" />
          </div>
          <div>
            <label class="label">تاریخ آزمون (سال / ماه / روز)</label>
            <JalaliDatepicker v-model="form.exam_date" />
          </div>
        </div>

        <input
          v-model="form.registration_link"
          class="field"
          dir="ltr"
          placeholder="لینک ثبت‌نام"
        />
        <input
          v-model="form.source_url"
          class="field"
          dir="ltr"
          placeholder="منبع"
        />

        <div class="rounded-xl border border-slate-200 p-3">
          <div class="mb-2 flex items-center justify-between">
            <label class="label mb-0"
              >فایل‌های مربوطه (چندتایی — اختیاری)</label
            >
            <button
              type="button"
              class="text-xs font-bold text-orange-600"
              @click="addFileRow"
            >
              + فایل
            </button>
          </div>

          <div v-if="existingAttachments.length" class="mb-3 space-y-2">
            <div
              v-for="att in existingAttachments"
              :key="att.id"
              class="flex items-start justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm"
            >
              <div>
                <a
                  :href="att.url"
                  target="_blank"
                  class="font-bold text-orange-600"
                  >{{ att.title || 'فایل' }}</a
                >
                <p v-if="att.description" class="text-xs text-slate-500">
                  {{ att.description }}
                </p>
              </div>
              <button
                type="button"
                class="text-xs font-bold text-red-600"
                @click="markRemove(att.id)"
              >
                حذف
              </button>
            </div>
          </div>

          <div
            v-for="(row, idx) in form.newFiles"
            :key="idx"
            class="mb-3 space-y-2 rounded-xl border border-dashed border-slate-200 p-3"
          >
            <input
              type="file"
              class="block w-full text-xs"
              accept=".pdf,.doc,.docx,.zip,.rar,.jpg,.jpeg,.png"
              @change="onFilePick(idx, $event)"
            />
            <input
              v-model="row.title"
              class="field"
              placeholder="عنوان فایل (مثال: دفترچه راهنما)"
            />
            <input
              v-model="row.description"
              class="field"
              placeholder="شرح فایل"
            />
            <button
              type="button"
              class="text-xs text-red-600"
              @click="form.newFiles.splice(idx, 1)"
            >
              حذف ردیف
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <select v-model="form.status" class="field">
            <option value="pending">در انتظار</option>
            <option value="approved">تایید شده</option>
            <option value="rejected">رد شده</option>
          </select>
          <label
            class="flex items-center justify-between rounded-xl border border-slate-200 px-3 text-sm"
          >
            ویژه
            <StatusToggle v-model="form.is_featured" />
          </label>
        </div>

        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-muted" @click="$emit('close')">
            انصراف
          </button>
          <button type="submit" class="btn-orange" :disabled="saving">
            {{ saving ? '...' : 'ذخیره' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import CatalogAttachFields from '../catalog/CatalogAttachFields.vue'
import JalaliDatepicker from '../ui/JalaliDatepicker.vue'
import RichEditor from '../ui/RichEditor.vue'
import StatusToggle from '../ui/StatusToggle.vue'

const props = defineProps({
  open: Boolean,
  post: { type: Object, default: null },
  provinces: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved'])

const IRAN_PROVINCES = [
  'آذربایجان شرقی',
  'آذربایجان غربی',
  'اردبیل',
  'اصفهان',
  'البرز',
  'ایلام',
  'بوشهر',
  'تهران',
  'چهارمحال و بختیاری',
  'خراسان جنوبی',
  'خراسان رضوی',
  'خراسان شمالی',
  'خوزستان',
  'زنجان',
  'سمنان',
  'سیستان و بلوچستان',
  'فارس',
  'قزوین',
  'قم',
  'کردستان',
  'کرمان',
  'کرمانشاه',
  'کهگیلویه و بویراحمد',
  'گلستان',
  'گیلان',
  'لرستان',
  'مازندران',
  'مرکزی',
  'هرمزگان',
  'همدان',
  'یزد',
]

const saving = ref(false)
const error = ref('')
const form = reactive(empty())
const existingAttachments = ref([])
const removeAttachmentIds = ref([])
const allProvinces = ref([...IRAN_PROVINCES])

watch(
  () => [props.open, props.post, props.provinces],
  () => {
    if (!props.open) return
    const extra = (props.provinces || []).filter(
      (p) => !IRAN_PROVINCES.includes(p)
    )
    allProvinces.value = [...IRAN_PROVINCES, ...extra]
    Object.assign(form, props.post?.id ? map(props.post) : empty())
    existingAttachments.value = props.post?.attachments
      ? [...props.post.attachments]
      : []
    removeAttachmentIds.value = []
    error.value = ''
  },
  { immediate: true }
)

function empty() {
  return {
    title: '',
    seo_tag: '',
    job_classification_id: '',
    description: '',
    provinces: [],
    city: '',
    registration_deadline: '',
    exam_date: '',
    registration_link: '',
    source_url: '',
    newFiles: [],
    status: 'approved',
    is_featured: false,
    auto_catalog: true,
    exam_ids: [],
    pdf_ids: [],
  }
}

function map(p) {
  return {
    title: p.title || '',
    seo_tag: p.seo_tag || '',
    job_classification_id: p.job_classification_id || '',
    description: p.description || '',
    provinces: Array.isArray(p.provinces)
      ? [...p.provinces]
      : p.province
        ? [p.province]
        : [],
    city: p.city || '',
    registration_deadline: (p.registration_deadline || '').slice(0, 10),
    exam_date: (p.exam_date || '').slice(0, 10),
    registration_link: p.registration_link || '',
    source_url: p.source_url || '',
    newFiles: [],
    status: p.status || 'pending',
    is_featured: Boolean(p.is_featured),
    auto_catalog: p.auto_catalog !== false,
    exam_ids: Array.isArray(p.exam_ids) ? p.exam_ids.map(Number) : [],
    pdf_ids: Array.isArray(p.pdf_ids) ? p.pdf_ids.map(Number) : [],
  }
}

function normalizeSeo() {
  form.seo_tag = String(form.seo_tag || '')
    .trim()
    .replace(/\s+/g, '_')
    .replace(/_+/g, '_')
}

function addFileRow() {
  form.newFiles.push({ file: null, title: '', description: '' })
}

function onFilePick(idx, e) {
  const file = e.target.files?.[0] || null
  form.newFiles[idx].file = file
  if (file && !form.newFiles[idx].title) form.newFiles[idx].title = file.name
}

function markRemove(id) {
  removeAttachmentIds.value.push(id)
  existingAttachments.value = existingAttachments.value.filter(
    (a) => a.id !== id
  )
}

function submit() {
  saving.value = true
  error.value = ''
  try {
    normalizeSeo()
    if (!form.title.trim()) {
      error.value = 'عنوان آگهی الزامی است.'
      return
    }
    if (!form.job_classification_id) {
      error.value = 'انتخاب طبقه‌بندی آگهی الزامی است.'
      return
    }
    if (
      !form.description ||
      form.description === '<br>' ||
      form.description === '<div><br></div>'
    ) {
      error.value = 'شرح آگهی الزامی است.'
      return
    }
    if (!form.registration_deadline) {
      error.value = 'مهلت ثبت‌نام الزامی است.'
      return
    }

    const payload = {
      title: form.title,
      seo_tag: form.seo_tag || null,
      job_classification_id: form.job_classification_id,
      description: form.description,
      provinces: form.provinces,
      city: form.city || null,
      registration_deadline: form.registration_deadline,
      exam_date: form.exam_date || null,
      registration_link: form.registration_link || null,
      source_url: form.source_url || null,
      status: form.status,
      is_featured: form.is_featured,
      auto_catalog: form.auto_catalog,
      exam_ids: form.exam_ids,
      pdf_ids: form.pdf_ids,
      attachments: form.newFiles
        .filter((r) => r.file instanceof File)
        .map((r) => r.file),
      attachment_titles: form.newFiles
        .filter((r) => r.file instanceof File)
        .map((r) => r.title || ''),
      attachment_descriptions: form.newFiles
        .filter((r) => r.file instanceof File)
        .map((r) => r.description || ''),
      remove_attachment_ids: removeAttachmentIds.value,
    }
    emit('saved', { id: props.post?.id || null, payload })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.label {
  @apply mb-1 block text-xs text-slate-500;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
