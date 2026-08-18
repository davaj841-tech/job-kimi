<template>
  <label class="relative block">
    <span class="mb-1.5 block text-xs font-medium text-desk-muted">
      {{ label }}
      <span v-if="required" class="text-brand">*</span>
    </span>
    <input
      v-model="query"
      type="text"
      class="input-field"
      :placeholder="placeholder"
      autocomplete="off"
      @focus="open = true"
      @input="open = true"
    />
    <ul
      v-if="open && filtered.length"
      class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-surface-line bg-white py-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
      <li
        v-for="opt in filtered"
        :key="opt"
        class="cursor-pointer px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"
        @mousedown.prevent="pick(opt)"
      >
        {{ opt }}
      </li>
    </ul>
  </label>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  label: { type: String, required: true },
  placeholder: { type: String, default: 'جستجو…' },
  required: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const query = ref(props.modelValue || '')
const open = ref(false)

watch(
  () => props.modelValue,
  (v) => {
    if (v !== query.value) query.value = v || ''
  }
)
watch(query, (v) => emit('update:modelValue', v))

const filtered = computed(() => {
  const q = query.value.trim()
  const list = props.options || []
  if (!q) return list.slice(0, 30)
  return list.filter((o) => String(o).includes(q)).slice(0, 30)
})

function pick(opt) {
  query.value = opt
  emit('update:modelValue', opt)
  open.value = false
}
</script>
