<template>
  <div>
    <p class="mb-1.5 text-xs font-medium text-desk-muted">
      عکس پرسنلی ۳×۴ (اختیاری)
    </p>
    <div class="flex items-center gap-3">
      <div
        class="h-32 w-24 overflow-hidden rounded border border-dashed border-surface-line bg-slate-50 dark:border-slate-700 dark:bg-slate-800"
      >
        <img
          v-if="modelValue"
          :src="modelValue"
          alt=""
          class="h-full w-full object-cover"
        />
      </div>
      <div class="space-y-2">
        <label class="inline-flex cursor-pointer rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold dark:bg-slate-800">
          انتخاب عکس
          <input
            type="file"
            accept="image/*"
            class="hidden"
            @change="onFile"
          />
        </label>
        <button
          v-if="modelValue"
          type="button"
          class="block text-xs text-brand"
          @click="downloadPhoto"
        >
          دانلود عکس
        </button>
        <button
          v-if="modelValue"
          type="button"
          class="block text-xs text-desk-muted"
          @click="$emit('update:modelValue', '')"
        >
          حذف عکس
        </button>
        <p class="text-[11px] text-desk-muted">JPG یا PNG — نسبت ۳ به ۴ (عرض ۳، ارتفاع ۴)</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

function onFile(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file || !file.type.startsWith('image/')) return
  const img = new Image()
  const url = URL.createObjectURL(file)
  img.onload = () => {
    const w = 360
    const h = 480
    const canvas = document.createElement('canvas')
    canvas.width = w
    canvas.height = h
    const ctx = canvas.getContext('2d')
    const srcRatio = img.width / img.height
    const dstRatio = w / h
    let sx = 0
    let sy = 0
    let sw = img.width
    let sh = img.height
    if (srcRatio > dstRatio) {
      sw = img.height * dstRatio
      sx = (img.width - sw) / 2
    } else {
      sh = img.width / dstRatio
      sy = (img.height - sh) / 2
    }
    ctx.drawImage(img, sx, sy, sw, sh, 0, 0, w, h)
    emit('update:modelValue', canvas.toDataURL('image/jpeg', 0.86))
    URL.revokeObjectURL(url)
  }
  img.src = url
}

function downloadPhoto() {
  if (!props.modelValue) return
  const a = document.createElement('a')
  a.href = props.modelValue
  a.download = 'personnel-photo-3x4.jpg'
  a.click()
}
</script>
