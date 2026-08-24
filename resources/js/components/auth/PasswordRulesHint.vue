<template>
  <ul class="mt-1.5 flex flex-wrap gap-1.5 text-[10px] font-medium">
    <li
      class="rounded-md px-1.5 py-0.5"
      :class="
        hasMin
          ? 'bg-emerald-50 text-emerald-700'
          : 'bg-slate-100 text-slate-500'
      "
    >
      حداقل ۸ کاراکتر
    </li>
    <li
      class="rounded-md px-1.5 py-0.5"
      :class="
        hasLetter
          ? 'bg-emerald-50 text-emerald-700'
          : 'bg-slate-100 text-slate-500'
      "
    >
      حروف انگلیسی
    </li>
    <li
      class="rounded-md px-1.5 py-0.5"
      :class="
        hasNumber
          ? 'bg-emerald-50 text-emerald-700'
          : 'bg-slate-100 text-slate-500'
      "
    >
      عدد
    </li>
    <li
      class="rounded-md px-1.5 py-0.5"
      :class="
        hasSymbol
          ? 'bg-emerald-50 text-emerald-700'
          : 'bg-slate-100 text-slate-500'
      "
    >
      کاراکتر ویژه
    </li>
  </ul>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  password: { type: String, default: '' },
})

const hasMin = computed(() => (props.password || '').length >= 8)
const hasLetter = computed(() => /[A-Za-z]/.test(props.password || ''))
const hasNumber = computed(() => /[0-9]/.test(props.password || ''))
const hasSymbol = computed(() => /[^A-Za-z0-9]/.test(props.password || ''))
</script>
