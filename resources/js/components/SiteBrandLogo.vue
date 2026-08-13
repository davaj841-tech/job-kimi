<template>
  <span class="inline-flex items-center gap-2">
    <img
      v-if="src"
      :src="src"
      :alt="siteName"
      class="object-contain object-right"
      :class="imgClass"
      @error="broken = true"
    />
    <span
      v-if="!src || showText"
      class="font-display font-black tracking-tight"
      :class="textClass"
    >
      {{ siteName }}
    </span>
  </span>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useDarkMode } from '../composables/useDarkMode'
import { useSiteTheme } from '../composables/useSiteTheme'

const props = defineProps({
  /** Force logo variant for dark backgrounds (e.g. navy header). null = follow theme */
  forDarkBg: { type: Boolean, default: undefined },
  imgClass: { type: String, default: 'h-8 w-auto max-w-[9rem]' },
  textClass: { type: String, default: 'text-[15px]' },
  /** Show site name beside logo when image exists */
  withText: { type: Boolean, default: false },
})

const broken = ref(false)
const { isDark } = useDarkMode()
const { siteName, ensureLoaded, resolveBrandLogo } = useSiteTheme()

onMounted(() => {
  void ensureLoaded()
})

watch([isDark, () => props.forDarkBg], () => {
  broken.value = false
})

const darkBg = computed(() => {
  if (props.forDarkBg === true) return true
  if (props.forDarkBg === false) return false
  return !!isDark.value
})

const src = computed(() => {
  if (broken.value) return ''
  return resolveBrandLogo({ forDarkBg: darkBg.value })
})

const showText = computed(() => props.withText || !src.value)
</script>
