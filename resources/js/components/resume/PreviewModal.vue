<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex flex-col bg-slate-900/60 backdrop-blur-sm"
      >
        <div class="flex items-center justify-between bg-white px-4 py-3 dark:bg-slate-900">
          <p class="text-sm font-bold">پیش‌نمایش رزومه</p>
          <button
            type="button"
            class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
            @click="$emit('update:modelValue', false)"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4">
          <div class="mx-auto max-w-lg overflow-hidden rounded-xl shadow-2xl">
            <ResumePreview
              :data="data"
              :template="template"
            />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline'
import ResumePreview from './ResumePreview.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  data: { type: Object, required: true },
  template: { type: String, default: 'modern' },
})
defineEmits(['update:modelValue'])
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
