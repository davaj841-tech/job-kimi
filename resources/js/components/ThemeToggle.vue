<template>
  <button
    type="button"
    class="inline-flex h-9 w-9 items-center justify-center rounded-xl transition"
    :class="btnClass"
    :aria-label="isDark ? 'حالت روشن' : 'حالت تاریک'"
    :title="isDark ? 'حالت روشن' : 'حالت تاریک'"
    @click="toggle"
  >
    <SunIcon v-if="isDark" class="h-5 w-5 text-amber-400" />
    <MoonIcon v-else class="h-5 w-5" :class="moonClass" />
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { MoonIcon, SunIcon } from '@heroicons/vue/24/outline'
import { useDarkMode } from '../composables/useDarkMode'

const props = defineProps({
  /** when header is on navy hero */
  inverted: { type: Boolean, default: false },
})

const { isDark, toggle } = useDarkMode()

const btnClass = computed(() =>
  props.inverted
    ? 'text-white/80 hover:bg-white/10 hover:text-white'
    : 'text-desk-muted hover:bg-slate-100 hover:text-desk-dark dark:text-slate-300 dark:hover:bg-slate-800'
)

const moonClass = computed(() =>
  props.inverted ? 'text-white/85' : 'text-desk-muted dark:text-slate-300'
)
</script>
