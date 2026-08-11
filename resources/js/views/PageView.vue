<template>
  <PageShell>
    <LoadingSpinner v-if="loading" />
    <template v-else-if="page">
      <h1 class="mb-4 text-2xl font-black">{{ page.title }}</h1>
      <div
        class="prose-legal text-sm leading-7 text-ink/80"
        v-html="page.content"
      />
    </template>
    <p v-else class="text-center text-sm text-desk-muted">صفحه یافت نشد</p>
  </PageShell>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api/client'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import PageShell from '../components/layout/PageShell.vue'
import { unwrapItem } from '../utils/format'

const route = useRoute()
const page = ref(null)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/pages/${route.params.slug}`)
    page.value = unwrapItem(data)
    if (page.value?.meta_title)
      document.title = `${page.value.meta_title} | جاب‌آزمون`
  } catch {
    page.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.params.slug, load)
</script>
