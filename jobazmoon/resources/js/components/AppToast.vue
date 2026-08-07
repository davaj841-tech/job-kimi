<template>
  <Transition name="toast">
    <div
      v-if="toast.state.visible"
      class="pointer-events-none fixed inset-x-0 top-3 z-[70] flex justify-center px-4"
    >
      <div
        class="pointer-events-auto max-w-app rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
        :class="tone"
      >
        {{ toast.state.message }}
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue';
import { useToast } from '../composables/useToast';

const toast = useToast();

const tone = computed(() => {
    if (toast.state.type === 'success') return 'bg-emerald-600';
    if (toast.state.type === 'error') return 'bg-brand';
    return 'bg-ink';
});
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
