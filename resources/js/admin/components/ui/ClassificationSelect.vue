<template>
  <div class="cls-dropdown">
    <label v-if="label" class="cls-dropdown__label">{{ label }}</label>
    <select
      v-if="multiple"
      multiple
      class="cls-dropdown__field cls-dropdown__field--multi"
      :value="selectedIds"
      @change="onMultiChange"
    >
      <option v-for="item in items" :key="item.id" :value="String(item.id)">
        {{ item.name }}
      </option>
    </select>
    <select
      v-else
      class="cls-dropdown__field"
      :value="singleValue"
      @change="onSingleChange"
    >
      <option v-if="showAll" value="">همه</option>
      <option v-for="item in items" :key="item.id" :value="String(item.id)">
        {{ item.name }}
      </option>
    </select>
    <p v-if="hint" class="cls-dropdown__hint">{{ hint }}</p>
    <p v-if="multiple && selectedIds.length" class="cls-dropdown__count">
      {{ selectedIds.length }} مورد انتخاب شده
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [Array, Number, String, null],
    default: () => [],
  },
  items: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: true },
  showAll: { type: Boolean, default: true },
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const selectedIds = computed(() => {
  if (!props.multiple) return []
  return Array.isArray(props.modelValue) ? props.modelValue.map(String) : []
})

const singleValue = computed(() => {
  if (
    props.modelValue === null ||
    props.modelValue === undefined ||
    props.modelValue === ''
  ) {
    return ''
  }
  return String(props.modelValue)
})

function onMultiChange(event) {
  const options = Array.from(event.target.selectedOptions || [])
  const next = options.map((opt) => {
    const n = Number(opt.value)
    return Number.isFinite(n) ? n : opt.value
  })
  emit('update:modelValue', next)
}

function onSingleChange(event) {
  const raw = event.target.value
  emit('update:modelValue', raw === '' ? '' : raw)
}
</script>

<style scoped>
.cls-dropdown__label {
  @apply mb-1.5 block text-xs font-bold text-slate-500;
}
.cls-dropdown__field {
  @apply h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-orange-400;
}
.cls-dropdown__field--multi {
  @apply min-h-[7.5rem] py-2;
}
.cls-dropdown__hint {
  @apply mt-1 text-[11px] text-slate-400;
}
.cls-dropdown__count {
  @apply mt-1 text-[11px] font-medium text-orange-600;
}
</style>
