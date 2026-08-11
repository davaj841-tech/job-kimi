<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[80] flex items-end justify-center bg-black/50 p-4 sm:items-center"
        @click.self="close"
      >
        <div
          class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl dark:bg-slate-900"
          role="dialog"
          aria-modal="true"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-ink dark:text-white">
              {{ title }}
            </h3>
            <button
              type="button"
              class="rounded-lg p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800"
              aria-label="بستن"
              @click="close"
            >
              ✕
            </button>
          </div>
          <slot />
          <div v-if="$slots.footer" class="mt-4 border-t border-surface-line pt-4 dark:border-slate-700">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: boolean
  title: string
}>()

const emit = defineEmits<{
  'update:modelValue': [boolean]
}>()

function close() {
  emit('update:modelValue', false)
}
</script>
