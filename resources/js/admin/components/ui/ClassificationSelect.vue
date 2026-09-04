<template>
  <div class="cls-dropdown">
    <label v-if="label" class="cls-dropdown__label">{{ label }}</label>
    <select
      v-if="multiple"
      class="cls-dropdown__field"
      :value="selectedIds[0] || ''"
      @change="onMultiAsSingleChange"
    >
      <option v-if="showAll" value="">همه طبقه‌بندی‌ها</option>
      <option
        v-for="item in orderedItems"
        :key="item.id"
        :value="String(item.id)"
      >
        {{ optionLabel(item) }}
      </option>
    </select>
    <select
      v-else
      class="cls-dropdown__field"
      :value="singleValue"
      @change="onSingleChange"
    >
      <option v-if="showAll" value="">همه طبقه‌بندی‌ها</option>
      <option v-else value="" disabled>انتخاب کنید…</option>
      <option
        v-for="item in orderedItems"
        :key="item.id"
        :value="String(item.id)"
      >
        {{ optionLabel(item) }}
      </option>
    </select>
    <p v-if="hint" class="cls-dropdown__hint">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { resolveIconEmoji } from '../../../utils/classificationIcon'

const props = defineProps({
  modelValue: {
    type: [Array, Number, String, null],
    default: () => [],
  },
  items: { type: Array, default: () => [] },
  /** Kept for API compat; always renders a compact dropdown select */
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

/** Parents first, then their children (indented) */
const orderedItems = computed(() => {
  const list = Array.isArray(props.items) ? props.items : []
  const parents = list.filter((c) => !c.parent_id)
  const children = list.filter((c) => c.parent_id)
  const used = new Set()
  const out = []

  for (const p of parents) {
    out.push({ ...p, _depth: 0 })
    used.add(Number(p.id))
    for (const ch of children.filter(
      (c) => Number(c.parent_id) === Number(p.id)
    )) {
      out.push({ ...ch, _depth: 1 })
      used.add(Number(ch.id))
    }
  }
  for (const c of list) {
    if (!used.has(Number(c.id))) {
      out.push({ ...c, _depth: c.parent_id ? 1 : 0 })
    }
  }
  return out
})

function optionLabel(item) {
  const icon = resolveIconEmoji(item.icon, '')
  const prefix = item._depth ? ' └ ' : ''
  const name = item.name || item.raw_name || `#${item.id}`
  return icon ? `${prefix}${icon} ${name}` : `${prefix}${name}`
}

function onMultiAsSingleChange(event) {
  const raw = event.target.value
  if (raw === '') {
    emit('update:modelValue', [])
    return
  }
  const n = Number(raw)
  emit('update:modelValue', [Number.isFinite(n) ? n : raw])
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
.cls-dropdown__hint {
  @apply mt-1 text-[11px] text-slate-400;
}
</style>
