<template>
  <div class="rounded-xl border border-slate-200 p-3">
    <div class="mb-2 flex items-center justify-between gap-2">
      <p class="text-xs font-bold text-slate-600">دسترسی‌های اپراتور</p>
      <div class="flex gap-2">
        <button
          type="button"
          class="text-[11px] font-bold text-orange-600"
          @click="setAll(true)"
        >
          همه
        </button>
        <button
          type="button"
          class="text-[11px] font-bold text-slate-500"
          @click="setAll(false)"
        >
          هیچ‌کدام
        </button>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-1.5">
      <label
        v-for="item in OPERATOR_PERMISSIONS"
        :key="item.key"
        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-slate-50"
      >
        <input
          type="checkbox"
          class="rounded border-slate-300 text-orange-500"
          :checked="modelValue.includes(item.key)"
          :disabled="disabled"
          @change="toggle(item.key, $event.target.checked)"
        />
        <span>{{ item.label }}</span>
      </label>
    </div>
  </div>
</template>

<script setup>
import { OPERATOR_PERMISSIONS } from '../../permissions'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

function toggle(key, on) {
  const next = new Set(props.modelValue)
  if (on) next.add(key)
  else next.delete(key)
  emit('update:modelValue', [...next])
}

function setAll(on) {
  emit(
    'update:modelValue',
    on ? OPERATOR_PERMISSIONS.map((item) => item.key) : []
  )
}
</script>
