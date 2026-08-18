<template>
      <div
      class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
    >
      <h1 class="mb-2 text-xl font-bold text-slate-800">{{ title }}</h1>
      <p class="text-sm text-slate-500">
        این بخش در فازهای بعدی پیاده‌سازی می‌شود.
      </p>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => route.meta.title || 'به‌زودی')
</script>
