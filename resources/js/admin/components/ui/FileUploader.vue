<template>
  <div>
    <div
      class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-8 text-center transition"
      :class="
        dragging
          ? 'border-orange-400 bg-orange-50'
          : 'border-slate-200 bg-slate-50 hover:border-orange-300'
      "
      @click="pick"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <p class="mb-1 text-sm font-bold text-slate-700">{{ label }}</p>
      <p class="text-xs text-slate-500">{{ hint }}</p>
      <p v-if="fileName" class="mt-3 text-xs font-medium text-orange-600">
        {{ fileName }}
      </p>
      <div
        v-if="progress > 0 && progress < 100"
        class="mt-3 h-1.5 w-40 overflow-hidden rounded bg-slate-200"
      >
        <div
          class="h-full bg-orange-500 transition-all"
          :style="{ width: `${progress}%` }"
        />
      </div>
      <img
        v-if="previewUrl"
        :src="previewUrl"
        alt=""
        class="mt-3 h-24 w-24 rounded-lg object-cover"
      />
    </div>
    <input
      ref="input"
      type="file"
      class="hidden"
      :accept="accept"
      @change="onChange"
    />
    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [File, Object, String], default: null },
  label: { type: String, default: 'آپلود فایل' },
  hint: { type: String, default: 'کشیدن و رها کردن یا کلیک' },
  accept: { type: String, default: '*/*' },
  maxSizeMb: { type: Number, default: 20 },
  existingUrl: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const input = ref(null)
const dragging = ref(false)
const error = ref('')
const progress = ref(0)
const localPreview = ref('')

const fileName = computed(() =>
  props.modelValue instanceof File ? props.modelValue.name : ''
)
const previewUrl = computed(() => localPreview.value || props.existingUrl || '')

watch(
  () => props.modelValue,
  (val) => {
    if (val instanceof File && val.type.startsWith('image/')) {
      localPreview.value = URL.createObjectURL(val)
    } else if (!(val instanceof File)) {
      localPreview.value = ''
    }
  }
)

function pick() {
  input.value?.click()
}

function onDrop(e) {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) setFile(file)
}

function onChange(e) {
  const file = e.target.files?.[0]
  if (file) setFile(file)
}

function setFile(file) {
  error.value = ''
  const maxBytes = props.maxSizeMb * 1024 * 1024
  if (file.size > maxBytes) {
    error.value = `حداکثر حجم ${props.maxSizeMb}MB`
    return
  }
  if (props.accept && props.accept !== '*/*') {
    const ok = props.accept.split(',').some((part) => {
      const p = part.trim()
      if (p.startsWith('.'))
        return file.name.toLowerCase().endsWith(p.toLowerCase())
      if (p.endsWith('/*')) return file.type.startsWith(p.replace('/*', '/'))
      return file.type === p
    })
    if (!ok) {
      error.value = 'نوع فایل مجاز نیست.'
      return
    }
  }
  progress.value = 30
  emit('update:modelValue', file)
  setTimeout(() => {
    progress.value = 100
  }, 200)
}
</script>
