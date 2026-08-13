<template>
  <div
    v-if="items.length"
    class="relative overflow-hidden rounded-2xl"
    @touchstart="onTouchStart"
    @touchend="onTouchEnd"
  >
    <div
      class="flex transition-transform duration-500"
      :style="{ transform: `translateX(${index * 100}%)` }"
    >
      <a
        v-for="b in items"
        :key="b.id"
        :href="b.link || '#'"
        class="relative min-w-full"
        @click="!b.link && $event.preventDefault()"
      >
        <img
          v-if="b.image"
          :src="b.image"
          :alt="b.title"
          class="w-full object-cover"
          :class="position === 'home_hero' ? 'h-44 md:h-56' : 'h-40 md:h-56'"
          loading="lazy"
          decoding="async"
        />
        <div
          v-else
          class="flex items-center justify-center bg-[#0a1c33] text-white"
          :class="position === 'home_hero' ? 'h-44 md:h-56' : 'h-40 md:h-56'"
        >
          <p class="text-lg font-bold">{{ b.title }}</p>
        </div>
      </a>
    </div>
    <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
      <button
        v-for="(b, i) in items"
        :key="b.id"
        type="button"
        class="h-2 w-2 rounded-full"
        :class="i === index ? 'bg-white' : 'bg-white/40'"
        @click="index = i"
      />
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import api from '../api/client'

const props = defineProps({
  position: { type: String, default: 'home_top' },
  banners: { type: Array, default: null },
})

const items = ref([])
const index = ref(0)
let timer
let touchX = 0

watch(
  () => props.banners,
  (v) => {
    if (Array.isArray(v)) items.value = v
  },
  { immediate: true }
)

onMounted(async () => {
  if (!Array.isArray(props.banners)) {
    try {
      const { data } = await api.get('/banners', {
        params: { position: props.position },
      })
      items.value = data.data || []
    } catch {
      items.value = []
    }
  }
  timer = setInterval(() => {
    if (items.value.length > 1)
      index.value = (index.value + 1) % items.value.length
  }, 5000)
})

onUnmounted(() => clearInterval(timer))

function onTouchStart(e) {
  touchX = e.changedTouches[0].clientX
}
function onTouchEnd(e) {
  const dx = e.changedTouches[0].clientX - touchX
  if (Math.abs(dx) < 40) return
  if (dx > 0) index.value = (index.value + 1) % items.value.length
  else index.value = (index.value - 1 + items.value.length) % items.value.length
}
</script>
