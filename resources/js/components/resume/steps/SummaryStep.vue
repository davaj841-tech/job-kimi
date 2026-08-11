<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-lg font-bold text-desk-text dark:text-white">معرفی حرفه‌ای</h2>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-brand px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
        :disabled="aiLoading"
        @click="generateAiSummary"
      >
        <SparklesIcon
          class="h-4 w-4"
          :class="{ 'animate-spin': aiLoading }"
        />
        {{ aiLoading ? 'در حال نوشتن...' : 'نوشتن با AI' }}
      </button>
    </div>

    <p class="text-sm text-desk-muted">
      خلاصه‌ای ۲–۳ خطی بنویسید. می‌توانید از هوش مصنوعی کمک بگیرید.
    </p>

    <div class="relative">
      <textarea
        v-model="local.summary"
        rows="5"
        maxlength="1000"
        class="input-field min-h-[120px] resize-none py-3"
        placeholder="کارشناس با تجربه در …"
      />
      <span class="absolute bottom-3 left-3 text-xs text-desk-muted">
        {{ toFaDigits((local.summary || '').length) }}/۱۰۰۰
      </span>
    </div>

    <div
      v-if="aiSuggestions.length"
      class="space-y-2"
    >
      <p class="text-sm font-medium text-desk-text dark:text-slate-200">پیشنهادات AI:</p>
      <button
        v-for="(suggestion, i) in aiSuggestions"
        :key="i"
        type="button"
        class="w-full rounded-xl border border-brand/20 bg-brand-soft p-3 text-right text-sm text-desk-text transition hover:bg-brand/10 dark:bg-brand/10 dark:text-slate-200"
        @click="local.summary = suggestion"
      >
        {{ suggestion }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { SparklesIcon } from '@heroicons/vue/24/outline'
import { toFaDigits } from '../../../utils/format'

const props = defineProps({
  modelValue: { type: Object, required: true },
})
const emit = defineEmits(['update:modelValue', 'ai-summary'])

const local = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const aiLoading = ref(false)
const aiSuggestions = ref([])

async function generateAiSummary() {
  aiLoading.value = true
  try {
    const suggestion = await new Promise((resolve, reject) => {
      emit('ai-summary', { resolve, reject })
    })
    if (suggestion) aiSuggestions.value = [suggestion]
  } catch (_) {
    /* parent shows toast */
  } finally {
    aiLoading.value = false
  }
}
</script>
