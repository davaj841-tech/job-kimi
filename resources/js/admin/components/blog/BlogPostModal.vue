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
          {{ post?.id ? 'ویرایش مقاله' : 'مقاله جدید' }}
        </h3>
        <button @click="$emit('close')">✕</button>
      </div>

      <form class="space-y-3" @submit.prevent="submit">
        <input
          v-model="form.title"
          required
          class="field"
          placeholder="عنوان *"
          @input="onTitle"
        />
        <input v-model="form.slug" class="field" dir="ltr" placeholder="slug" />
        <textarea
          v-model="form.excerpt"
          maxlength="500"
          rows="2"
          class="field h-auto"
          placeholder="خلاصه (حداکثر ۵۰۰)"
        />
        <RichEditor v-model="form.content" />
        <FileUploader
          v-model="form.featured_image_file"
          accept="image/*"
          label="تصویر شاخص"
          hint="JPG/PNG تا ۲MB"
          :existing-url="post?.featured_image_url || ''"
          :max-size-mb="2"
        />
        <input
          v-model="form.category"
          required
          class="field"
          placeholder="دسته‌بندی *"
        />
        <input
          v-model="form.meta_title"
          class="field"
          placeholder="meta_title"
        />
        <textarea
          v-model="form.meta_description"
          rows="2"
          class="field h-auto"
          placeholder="meta_description"
        />
        <select v-model="form.status" class="field">
          <option value="draft">پیش‌نویس</option>
          <option value="published">منتشر شده</option>
        </select>
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
import { onUnmounted, reactive, ref, watch } from 'vue'
import FileUploader from '../ui/FileUploader.vue'
import RichEditor from '../ui/RichEditor.vue'

const DRAFT_KEY = 'admin_blog_draft'

const props = defineProps({
  open: Boolean,
  post: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const form = reactive(empty())
let timer

watch(
  () => [props.open, props.post],
  () => {
    if (!props.open) return
    if (props.post?.id) Object.assign(form, map(props.post))
    else {
      const draft = localStorage.getItem(DRAFT_KEY)
      Object.assign(form, draft ? JSON.parse(draft) : empty())
      form.featured_image_file = null
    }
    error.value = ''
  },
  { immediate: true }
)

watch(
  form,
  () => {
    if (!props.open || props.post?.id) return
    clearTimeout(timer)
    timer = setTimeout(() => {
      const { featured_image_file: _file, ...rest } = form
      localStorage.setItem(DRAFT_KEY, JSON.stringify(rest))
    }, 30000)
  },
  { deep: true }
)

onUnmounted(() => clearTimeout(timer))

function empty() {
  return {
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    category: '',
    meta_title: '',
    meta_description: '',
    status: 'draft',
    featured_image_file: null,
  }
}

function map(p) {
  return {
    title: p.title || '',
    slug: p.slug || '',
    excerpt: p.excerpt || '',
    content: p.content || '',
    category: p.category || '',
    meta_title: p.meta_title || '',
    meta_description: p.meta_description || '',
    status: p.status || 'draft',
    featured_image_file: null,
  }
}

function onTitle() {
  if (props.post?.id) return
  form.slug =
    form.title
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/[^a-z0-9-]/g, '') || `post-${Date.now()}`
}

function submit() {
  saving.value = true
  error.value = ''
  try {
    const payload = { ...form }
    if (!payload.slug) delete payload.slug
    emit('saved', { id: props.post?.id || null, payload })
    if (!props.post?.id) localStorage.removeItem(DRAFT_KEY)
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
