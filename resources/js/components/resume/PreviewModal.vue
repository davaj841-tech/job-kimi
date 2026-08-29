<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex flex-col bg-slate-900/70"
      >
        <div
          class="flex items-center justify-between bg-white px-4 py-3 dark:bg-slate-900"
        >
          <p class="text-sm font-bold">پیش‌نمایش رزومه A4</p>
          <button
            type="button"
            class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
            @click="$emit('update:modelValue', false)"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>
        <div
          ref="scrollEl"
          class="flex flex-1 items-start justify-center overflow-auto bg-slate-300 p-3 dark:bg-slate-800 sm:p-4"
        >
          <div class="preview-stage shadow-2xl" :style="stageStyle">
            <ResumePreview :data="data" :template-id="templateId" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import ResumePreview from './ResumePreview.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  data: { type: Object, required: true },
  template: { type: String, default: 'modern' },
  templateId: { type: Number, default: 1 },
})
defineEmits(['update:modelValue'])

const scrollEl = ref(null)
const scale = ref(0.45)

const A4_WIDTH_PX = 794

function updateScale() {
  const pad = 24
  const maxW = Math.min(window.innerWidth - pad, 820)
  scale.value = Math.min(1, Math.max(0.32, maxW / A4_WIDTH_PX))
}

const stageStyle = computed(() => ({
  width: `${A4_WIDTH_PX}px`,
  transform: `scale(${scale.value})`,
  transformOrigin: 'top center',
}))

let ro = null

onMounted(() => {
  updateScale()
  window.addEventListener('resize', updateScale)
  if (typeof ResizeObserver !== 'undefined' && scrollEl.value) {
    ro = new ResizeObserver(updateScale)
    ro.observe(scrollEl.value)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateScale)
  ro?.disconnect()
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      requestAnimationFrame(updateScale)
    }
  }
)
</script>

<style scoped>
.preview-stage {
  flex-shrink: 0;
  margin-bottom: 1rem;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}
.preview-stage :deep(.resume-a4) {
  width: 210mm;
  min-height: 297mm;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
