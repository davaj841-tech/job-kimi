<template>
  <span class="ja-brand shrink-0 overflow-hidden" :class="`size-${size}`">
    <img
      v-if="src"
      :key="src"
      :src="src"
      :alt="siteName"
      class="ja-logo"
      @error="broken = true"
    />
    <span
      v-if="!src || withText"
      class="truncate font-display font-black tracking-tight"
      :class="textClass"
    >
      {{ siteName }}
    </span>
  </span>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useSiteTheme } from '../composables/useSiteTheme'

const props = defineProps({
  /** mobile: هدر موبایل — desktop: هدر دسکتاپ/پنل */
  variant: { type: String, default: 'desktop' },
  size: { type: String, default: 'md' },
  textClass: { type: String, default: 'text-[15px]' },
  withText: { type: Boolean, default: false },
})

const broken = ref(false)
const { siteName, siteLogo, logoMobile, logoDark, ensureLoaded } =
  useSiteTheme()

onMounted(() => {
  void ensureLoaded()
})

watch([() => props.variant, logoMobile, logoDark, siteLogo], () => {
  broken.value = false
})

const src = computed(() => {
  if (broken.value) return ''
  return siteLogo.value || logoDark.value || logoMobile.value || ''
})
</script>

<style scoped>
.ja-brand {
  display: inline-flex;
  align-items: center;
}
.ja-logo {
  display: block;
  width: auto;
  object-fit: contain;
  object-position: right center;
  border: 0;
  outline: 0;
  box-shadow: none;
  background: transparent;
  border-radius: 0;
  padding: 0;
}
.size-sm {
  max-width: 7.5rem;
  height: 2rem;
}
.size-sm .ja-logo {
  height: 2rem;
  max-width: 7.5rem;
  max-height: 2rem;
}
.size-md {
  max-width: 11rem;
  height: 2.75rem;
}
.size-md .ja-logo {
  height: 2.75rem;
  max-width: 11rem;
  max-height: 2.75rem;
}
.size-lg {
  max-width: 14rem;
  height: 3.25rem;
}
.size-lg .ja-logo {
  height: 3.25rem;
  max-width: 14rem;
  max-height: 3.25rem;
}
@media (min-width: 1024px) {
  .size-md {
    max-width: 13rem;
    height: 3.25rem;
  }
  .size-md .ja-logo {
    height: 3.25rem;
    max-width: 13rem;
    max-height: 3.25rem;
  }
  .size-lg {
    max-width: 16rem;
    height: 3.75rem;
  }
  .size-lg .ja-logo {
    height: 3.75rem;
    max-width: 16rem;
    max-height: 3.75rem;
  }
}
</style>
