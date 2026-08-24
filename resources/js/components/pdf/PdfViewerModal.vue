<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex flex-col bg-slate-950/90"
        role="dialog"
        aria-modal="true"
      >
        <div
          class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-bold">{{ title }}</p>
            <p v-if="maxPages" class="text-xs text-slate-400">
              پیش‌نمایش · حداکثر {{ maxPages }} صفحه
            </p>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-lg px-3 py-1.5 text-xs hover:bg-white/10 disabled:opacity-40"
              :disabled="page <= 1 || loading"
              @click="page--"
            >
              قبلی
            </button>
            <span class="text-xs tabular-nums"
              >{{ page }} / {{ numPages || '—' }}</span
            >
            <button
              type="button"
              class="rounded-lg px-3 py-1.5 text-xs hover:bg-white/10 disabled:opacity-40"
              :disabled="page >= displayPages || loading"
              @click="page++"
            >
              بعدی
            </button>
            <button
              type="button"
              class="rounded-lg p-2 hover:bg-white/10"
              @click="close"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
        </div>

        <div class="flex flex-1 items-center justify-center overflow-auto p-4">
          <div v-if="loading" class="text-sm text-slate-300">
            در حال بارگذاری PDF…
          </div>
          <div
            v-else-if="error"
            class="max-w-sm text-center text-sm text-red-300"
          >
            {{ error }}
          </div>
          <canvas
            v-show="!loading && !error"
            ref="canvasRef"
            class="max-h-full max-w-full rounded-lg bg-white shadow-2xl"
          />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import * as pdfjsLib from 'pdfjs-dist'
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url'

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /** Blob URL or array buffer source */
  source: { type: [String, null], default: null },
  title: { type: String, default: 'مشاهده PDF' },
  maxPages: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue'])

const canvasRef = ref(null)
const loading = ref(false)
const error = ref('')
const page = ref(1)
const numPages = ref(0)
const pdfDoc = ref(null)

const displayPages = computed(() => {
  if (!numPages.value) return 0
  if (props.maxPages > 0) return Math.min(numPages.value, props.maxPages)
  return numPages.value
})

function close() {
  emit('update:modelValue', false)
}

async function renderPage() {
  if (!pdfDoc.value || !canvasRef.value) return
  const p = Math.min(Math.max(page.value, 1), displayPages.value || 1)
  page.value = p
  const pdfPage = await pdfDoc.value.getPage(p)
  const viewport = pdfPage.getViewport({ scale: 1.35 })
  const canvas = canvasRef.value
  const ctx = canvas.getContext('2d')
  canvas.height = viewport.height
  canvas.width = viewport.width
  await pdfPage.render({ canvasContext: ctx, viewport }).promise
}

async function load() {
  if (!props.source) {
    error.value = 'فایل در دسترس نیست. ابتدا خریداری کنید.'
    return
  }
  loading.value = true
  error.value = ''
  try {
    const loadingTask = pdfjsLib.getDocument(props.source)
    pdfDoc.value = await loadingTask.promise
    numPages.value = pdfDoc.value.numPages
    page.value = 1
    await nextTick()
    await renderPage()
  } catch {
    error.value = 'بارگذاری PDF ناموفق بود.'
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.modelValue, props.source],
  async ([open]) => {
    if (open) await load()
    else {
      pdfDoc.value = null
      numPages.value = 0
    }
  }
)

watch(page, async () => {
  if (props.modelValue && pdfDoc.value) await renderPage()
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
