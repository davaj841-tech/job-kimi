<template>
  <div class="space-y-2">
    <FileUploader
      v-model="file"
      :label="label"
      :hint="hint"
      :accept="accept"
      :max-size-mb="maxSizeMb"
      :existing-url="preview"
      @update:model-value="onFile"
    />
    <button
      v-if="preview || file"
      type="button"
      class="text-xs text-red-600 hover:underline"
      @click="remove"
    >
      حذف تصویر
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import FileUploader from './FileUploader.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: 'آپلود تصویر' },
  hint: { type: String, default: 'JPG, PNG, WEBP — حداکثر ۲ مگابایت' },
  accept: { type: String, default: 'image/*' },
  maxSizeMb: { type: Number, default: 2 },
})

const emit = defineEmits(['update:modelValue', 'file'])

const file = ref(null)
const preview = ref(props.modelValue || '')

watch(
  () => props.modelValue,
  (v) => {
    preview.value = v || ''
  }
)

function onFile(val) {
  file.value = val
  if (val instanceof File) {
    emit('file', val)
  }
}

function remove() {
  file.value = null
  preview.value = ''
  emit('update:modelValue', '')
  emit('file', null)
}
</script>
