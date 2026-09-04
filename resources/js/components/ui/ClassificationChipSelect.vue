<template>
  <div class="cls-select">
    <p v-if="label" class="cls-select__label">{{ label }}</p>
    <div v-if="variant === 'list'" class="cls-select__list-box">
      <label
        v-if="showAll && multiple"
        class="cls-select__list-row"
        @click.prevent="clearAll"
      >
        <input
          type="checkbox"
          class="cls-select__input"
          :checked="noneSelected"
          tabindex="-1"
          @click.prevent
        />
        <span class="cls-select__list-icon cls-select__list-icon--all">📋</span>
        <span class="cls-select__list-text">همه</span>
      </label>

      <label
        v-for="item in items"
        :key="item.id"
        class="cls-select__list-row"
        :class="isSelected(item.id) ? 'cls-select__list-row--on' : ''"
      >
        <input
          :type="multiple ? 'checkbox' : 'radio'"
          class="cls-select__input"
          :name="multiple ? undefined : listGroupName"
          :checked="isSelected(item.id)"
          @change="toggle(item.id)"
        />
        <img
          v-if="item.logo_url"
          :src="item.logo_url"
          :alt="item.name"
          class="cls-select__list-logo"
        />
        <span v-else class="cls-select__list-icon" aria-hidden="true">{{
          chipIcon(item)
        }}</span>
        <span class="cls-select__list-text">{{ item.name }}</span>
      </label>
    </div>

    <div
      v-else
      class="cls-select__track"
      :class="
        variant === 'card'
          ? 'cls-select__track--card'
          : variant === 'grid'
            ? 'cls-select__track--grid'
            : 'cls-select__track--chip'
      "
    >
      <button
        v-if="showAll"
        type="button"
        class="cls-select__item"
        :class="[
          variant === 'card'
            ? 'cls-select__card'
            : variant === 'grid'
              ? 'cls-select__grid'
              : 'cls-select__chip',
          noneSelected ? activeClass : inactiveClass,
        ]"
        @click="clearAll"
      >
        <span
          v-if="variant === 'card'"
          class="cls-select__card-icon cls-select__card-icon--all"
        >
          <DesktopIcon name="grid" :size="22" />
        </span>
        <span :class="variant === 'card' ? 'cls-select__card-text' : ''"
          >همه</span
        >
      </button>

      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="cls-select__item"
        :class="[
          variant === 'card'
            ? 'cls-select__card'
            : variant === 'grid'
              ? 'cls-select__grid'
              : 'cls-select__chip',
          isSelected(item.id) ? activeClass : inactiveClass,
        ]"
        @click="toggle(item.id)"
      >
        <span
          v-if="variant === 'card'"
          class="cls-select__card-icon"
          :style="{ background: item.color || '#1e3a5f' }"
        >
          <img
            v-if="item.logo_url"
            :src="item.logo_url"
            :alt="item.name"
            class="h-full w-full object-cover"
          />
          <DesktopIcon
            v-else-if="isNamedIcon(item.icon)"
            :name="item.icon"
            :size="22"
          />
          <span v-else>{{ chipIcon(item) }}</span>
        </span>
        <img
          v-else-if="item.logo_url"
          :src="item.logo_url"
          :alt="item.name"
          class="h-4 w-4 shrink-0 rounded-full object-cover"
        />
        <span
          v-else-if="variant === 'chip'"
          class="text-sm leading-none"
          aria-hidden="true"
        >
          {{ chipIcon(item) }}
        </span>
        <span :class="variant === 'card' ? 'cls-select__card-text' : ''">{{
          item.name
        }}</span>
      </button>
    </div>
    <p v-if="hint" class="cls-select__hint">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import DesktopIcon from '../DesktopIcon.vue'
import { classificationChipIcon } from '../../composables/useClassificationMultiSelect'

const listGroupName = `cls-${Math.random().toString(36).slice(2, 9)}`

const props = defineProps({
  modelValue: {
    type: [Array, Number, String, null],
    default: () => [],
  },
  items: { type: Array, default: () => [] },
  /** true = چند انتخابی | false = یک مورد */
  multiple: { type: Boolean, default: true },
  showAll: { type: Boolean, default: true },
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  /** chip = نوار افقی | card = کارت | grid = شبکه | list = لیست چندانتخابی */
  variant: {
    type: String,
    default: 'chip',
    validator: (v) => ['chip', 'card', 'grid', 'list'].includes(v),
  },
})

const emit = defineEmits(['update:modelValue'])

const named = [
  'school',
  'bank',
  'shield',
  'building',
  'city',
  'briefcase',
  'grid',
  'book',
  'users',
]

const activeClass = computed(() =>
  props.variant === 'card'
    ? 'border-desk-orange bg-orange-50 shadow-sm'
    : props.variant === 'grid'
      ? 'border-orange-400 bg-orange-50 text-orange-800'
      : 'page-chip-on bg-brand text-white'
)

const inactiveClass = computed(() =>
  props.variant === 'card'
    ? 'border-surface-line bg-white hover:border-desk-orange/40'
    : props.variant === 'grid'
      ? 'border-slate-200 bg-white text-slate-700 hover:border-orange-300 hover:bg-orange-50'
      : 'page-chip bg-slate-100 text-desk-text dark:bg-slate-800 dark:text-slate-100'
)

const selectedIds = computed(() => {
  if (props.multiple) {
    return Array.isArray(props.modelValue) ? props.modelValue : []
  }
  if (
    props.modelValue === null ||
    props.modelValue === undefined ||
    props.modelValue === ''
  ) {
    return []
  }
  return [props.modelValue]
})

const noneSelected = computed(() => !selectedIds.value.length)

function isSelected(id) {
  return selectedIds.value.some((x) => Number(x) === Number(id))
}

function toggle(id) {
  if (props.multiple) {
    const next = [...selectedIds.value]
    const idx = next.findIndex((x) => Number(x) === Number(id))
    if (idx >= 0) next.splice(idx, 1)
    else next.push(id)
    emit('update:modelValue', next)
    return
  }
  const n = Number(id)
  if (selectedIds.value.some((x) => Number(x) === n)) {
    emit('update:modelValue', '')
  } else {
    emit('update:modelValue', id)
  }
}

function clearAll() {
  emit('update:modelValue', props.multiple ? [] : '')
}

function chipIcon(item) {
  return classificationChipIcon(item)
}

function isNamedIcon(icon) {
  return named.includes(icon)
}
</script>

<style scoped>
.cls-select__label {
  @apply mb-1.5 block text-xs font-medium text-desk-muted dark:text-slate-400;
}
.cls-select__hint {
  @apply mt-1 text-[11px] text-slate-400;
}
.cls-select__track {
  @apply flex gap-2;
}
.cls-select__track--chip {
  @apply flex items-center overflow-x-auto pb-1;
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.cls-select__track--chip::-webkit-scrollbar {
  display: none;
}
.cls-select__track--card {
  @apply -mx-1 overflow-x-auto px-1 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden;
}
.cls-select__track.grid,
.cls-select__track--grid {
  @apply flex-wrap overflow-visible pb-0;
}
.cls-select__chip {
  @apply inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-xs font-bold transition;
}
.cls-select__grid {
  @apply rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition;
}
.cls-select__card {
  @apply flex w-[88px] shrink-0 flex-col items-center gap-2 rounded-2xl border px-2 py-3 text-center transition;
}
.cls-select__card-icon {
  @apply flex h-12 w-12 items-center justify-center overflow-hidden rounded-full text-lg text-white;
}
.cls-select__card-icon--all {
  @apply bg-desk-dark;
}
.cls-select__card-text {
  @apply line-clamp-2 text-[11px] font-bold text-desk-text;
}
.cls-select__list-box {
  @apply max-h-48 overflow-y-auto rounded-xl border border-surface-line bg-white p-2 dark:border-slate-700 dark:bg-slate-900;
}
.cls-select__list-row {
  @apply flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-desk-text transition hover:bg-slate-50 dark:text-slate-100 dark:hover:bg-slate-800;
}
.cls-select__list-row--on {
  @apply bg-orange-50 dark:bg-orange-950/40;
}
.cls-select__input {
  @apply h-4 w-4 shrink-0 accent-brand;
}
.cls-select__list-icon {
  @apply flex h-6 w-6 shrink-0 items-center justify-center text-base leading-none;
}
.cls-select__list-icon--all {
  @apply text-sm;
}
.cls-select__list-logo {
  @apply h-6 w-6 shrink-0 rounded-full object-cover;
}
.cls-select__list-text {
  @apply flex-1 text-right leading-snug;
}
</style>
