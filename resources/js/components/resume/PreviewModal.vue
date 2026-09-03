<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="modelValue"
        data-resume-preview-modal
        class="preview-modal-root fixed relative inset-0 z-[100] flex flex-col bg-slate-900/70"
      >
        <div
          class="preview-modal-toolbar relative z-20 flex shrink-0 items-center justify-between gap-3 border-b border-surface-line bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
          <p class="min-w-0 truncate text-sm font-bold">پیش‌نمایش رزومه A4</p>
          <div class="flex shrink-0 items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white shadow-md disabled:opacity-50 sm:text-sm"
              :disabled="busy"
              @click="onPrint"
            >
              <PrinterIcon class="h-4 w-4 shrink-0" />
              چاپ
            </button>
            <button
              type="button"
              class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
              aria-label="بستن"
              @click="$emit('update:modelValue', false)"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
        </div>
        <div
          ref="scrollEl"
          class="relative flex flex-1 items-start justify-center overflow-auto bg-slate-300 p-3 pb-20 dark:bg-slate-800 sm:p-4 sm:pb-24"
        >
          <div class="preview-stage shadow-2xl" :style="stageStyle">
            <ResumePreview :data="data" :template-id="templateId" />
          </div>
        </div>
        <button
          type="button"
          class="preview-print-fab absolute bottom-4 left-1/2 z-30 inline-flex -translate-x-1/2 items-center gap-2 rounded-full bg-brand px-5 py-3 text-sm font-bold text-white shadow-lg disabled:opacity-50 sm:bottom-6"
          :disabled="busy"
          @click="onPrint"
        >
          <PrinterIcon class="h-5 w-5 shrink-0" />
          چاپ رزومه
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { PrinterIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import ResumePreview from './ResumePreview.vue'
import { printResumePreview } from '../../utils/resumePrint'
import { useToast } from '../../composables/useToast'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  data: { type: Object, required: true },
  template: { type: String, default: 'modern' },
  templateId: { type: Number, default: 1 },
})
defineEmits(['update:modelValue'])

const toast = useToast()
const scrollEl = ref(null)
const scale = ref(0.45)
const busy = ref(false)

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

async function onPrint() {
  busy.value = true
  try {
    await printResumePreview()
  } catch (e) {
    toast.error(e?.message || 'چاپ ممکن نشد.')
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.preview-modal-root {
  padding-top: env(safe-area-inset-top, 0);
  padding-bottom: env(safe-area-inset-bottom, 0);
}
.preview-modal-toolbar {
  padding-top: max(0.75rem, env(safe-area-inset-top, 0));
}
.preview-print-fab {
  margin-bottom: env(safe-area-inset-bottom, 0);
}
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
