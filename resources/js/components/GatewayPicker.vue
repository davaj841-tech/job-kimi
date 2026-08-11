<template>
  <div v-if="gateways.length" class="mb-3 space-y-2">
    <p class="text-sm font-medium text-ink-soft">انتخاب درگاه پرداخت</p>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="g in gateways"
        :key="g.name"
        type="button"
        class="rounded-xl border px-3 py-2 text-sm font-bold transition"
        :class="
          modelValue === g.name
            ? 'border-brand bg-brand text-white'
            : 'border-surface-line bg-white text-ink-soft'
        "
        @click="$emit('update:modelValue', g.name)"
      >
        {{ g.display_name }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '../api/client'

defineProps({
  modelValue: { type: String, default: 'zarinpal' },
})
defineEmits(['update:modelValue'])

const gateways = ref([])

onMounted(async () => {
  try {
    const { data } = await api.get('/payment-gateways')
    gateways.value = data.data || []
  } catch {
    gateways.value = [
      { name: 'zarinpal', display_name: 'زرین‌پال', is_default: true },
    ]
  }
})
</script>
