<template>
  <div
    class="flex flex-wrap items-center"
    :class="compact ? 'gap-1.5' : 'gap-3'"
  >
    <a
      v-if="enamadUrl"
      :href="enamadUrl"
      target="_blank"
      rel="noopener noreferrer"
      class="flex items-center justify-center rounded-md border text-center text-[10px] leading-4 transition hover:opacity-90"
      :class="badgeClass"
      title="نماد اعتماد الکترونیکی"
    >
      <span>نماد اعتماد</span>
    </a>
    <div
      v-else
      class="flex items-center justify-center rounded-md border border-dashed text-center text-[10px] leading-4"
      :class="placeholderClass"
    >
      نماد اعتماد
    </div>

    <a
      v-if="samandehiUrl"
      :href="samandehiUrl"
      target="_blank"
      rel="noopener noreferrer"
      class="flex items-center justify-center rounded-md border text-center text-[10px] leading-4 transition hover:opacity-90"
      :class="badgeClass"
      title="ساماندهی"
    >
      <span>ساماندهی</span>
    </a>
    <div
      v-else
      class="flex items-center justify-center rounded-md border border-dashed text-center text-[10px] leading-4"
      :class="placeholderClass"
    >
      ساماندهی
    </div>

    <div
      class="flex items-center justify-center rounded-md border text-center text-[10px] font-bold leading-4"
      :class="sslClass"
      title="SSL"
    >
      SSL
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api/client'

const props = defineProps({
  dark: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})

const enamadUrl = ref('')
const samandehiUrl = ref('')

const sizeClass = computed(() =>
  props.compact ? 'h-8 w-[4.5rem]' : 'h-16 w-28'
)

const badgeClass = computed(() => [
  sizeClass.value,
  props.dark
    ? 'border-white/30 bg-white/5 text-white/80'
    : 'border-slate-300 bg-white text-slate-600',
])

const placeholderClass = computed(() => [
  sizeClass.value,
  props.dark
    ? 'border-white/30 bg-white/5 text-white/55'
    : 'border-slate-300 bg-slate-50 text-slate-500',
])

const sslClass = computed(() => [
  props.compact ? 'h-8 w-12' : 'h-16 w-24',
  props.dark
    ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200'
    : 'border-emerald-300 bg-emerald-50 text-emerald-700',
])

onMounted(async () => {
  try {
    const { data } = await api.get('/settings/public')
    enamadUrl.value = data.data?.enamad_url || ''
    samandehiUrl.value = data.data?.samandehi_url || ''
  } catch {
    // keep placeholders
  }
})
</script>
