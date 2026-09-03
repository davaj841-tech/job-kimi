<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">
          {{ product?.id ? 'ویرایش فایل' : 'فایل جدید' }}
        </h3>
        <button @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-3" @submit.prevent="submit">
        <input
          v-model="form.title"
          required
          class="field"
          placeholder="عنوان *"
        />
        <textarea
          v-model="form.description"
          rows="3"
          class="field h-auto"
          placeholder="توضیحات"
        />
        <div>
          <ClassificationSelect
            v-model="form.job_classification_id"
            :items="parentClassifications"
            :multiple="false"
            :show-all="true"
            label="طبقه‌بندی"
          />
        </div>
        <input
          v-model.number="form.price"
          type="number"
          min="0"
          required
          class="field"
          placeholder="قیمت (ریال) *"
        />
        <FileUploader
          v-model="form.file"
          accept="application/pdf,.pdf"
          label="فایل اصلی PDF *"
          hint="فایل اصلی محصول — فقط PDF تا ۲۰MB"
          :max-size-mb="20"
        />
        <div>
          <label class="mb-1.5 block text-xs font-bold text-slate-500"
            >فایل‌های تکمیلی (اختیاری)</label
          >
          <input
            type="file"
            multiple
            accept=".pdf,.doc,.docx,.zip,.rar,.jpg,.jpeg,.png,.xls,.xlsx,.ppt,.pptx"
            class="field h-auto py-2"
            @change="onExtraFiles"
          />
          <p class="mt-1 text-[11px] text-slate-400">
            PDF، Word، Excel، ZIP و تصویر — هر کدام تا ۲۰MB (حداکثر ۱۰ فایل)
          </p>
          <ul v-if="form.extra_files.length" class="mt-2 space-y-1">
            <li
              v-for="(file, idx) in form.extra_files"
              :key="`${file.name}-${idx}`"
              class="flex items-center justify-between rounded-lg bg-slate-50 px-2 py-1 text-xs"
            >
              <span class="truncate">{{ file.name }}</span>
              <button
                type="button"
                class="text-red-500"
                @click="removeExtraFile(idx)"
              >
                حذف
              </button>
            </li>
          </ul>
          <ul
            v-if="existingAttachments.length"
            class="mt-2 space-y-1 text-xs text-slate-500"
          >
            <li
              v-for="file in existingAttachments"
              :key="file.index"
              class="rounded-lg bg-slate-50 px-2 py-1"
            >
              پیوست فعلی: {{ file.name }}
            </li>
          </ul>
        </div>
        <FileUploader
          v-model="form.thumbnail"
          accept="image/*"
          label="تصویر پیش‌نمایش"
          hint="JPG/PNG تا ۲MB"
          :existing-url="product?.thumbnail_url || ''"
          :max-size-mb="2"
        />
        <label
          class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 text-sm"
        >
          فعال
          <StatusToggle v-model="form.is_active" />
        </label>
        <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
        <div class="flex justify-end gap-2">
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
import { computed, reactive, ref, watch } from 'vue'
import FileUploader from '../ui/FileUploader.vue'
import ClassificationSelect from '../ui/ClassificationSelect.vue'
import StatusToggle from '../ui/StatusToggle.vue'

const props = defineProps({
  open: Boolean,
  product: { type: Object, default: null },
  classifications: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const form = reactive(empty())
const parentClassifications = computed(() =>
  (props.classifications || []).filter((c) => !c.parent_id)
)
const existingAttachments = computed(() => props.product?.attachments || [])

watch(
  () => [props.open, props.product],
  () => {
    if (!props.open) return
    Object.assign(form, props.product?.id ? map(props.product) : empty())
    error.value = ''
  },
  { immediate: true }
)

function empty() {
  return {
    title: '',
    description: '',
    category: '',
    job_classification_id: '',
    price: 0,
    is_active: true,
    file: null,
    extra_files: [],
    thumbnail: null,
  }
}

function map(p) {
  return {
    title: p.title || '',
    description: p.description || '',
    category: p.category || '',
    job_classification_id: p.job_classification_id || '',
    price: Number(p.price || 0),
    is_active: Boolean(p.is_active),
    file: null,
    extra_files: [],
    thumbnail: null,
  }
}

function onExtraFiles(event) {
  const picked = Array.from(event.target.files || [])
  const merged = [...form.extra_files, ...picked].slice(0, 10)
  form.extra_files = merged
  event.target.value = ''
}

function removeExtraFile(index) {
  form.extra_files.splice(index, 1)
}

function submit() {
  if (!props.product?.id && !(form.file instanceof File)) {
    error.value = 'فایل PDF اصلی الزامی است.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const payload = {
      ...form,
      job_classification_id: form.job_classification_id
        ? Number(form.job_classification_id)
        : null,
    }
    emit('saved', { id: props.product?.id || null, payload })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
