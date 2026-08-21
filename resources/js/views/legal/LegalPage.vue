<template>
  <PageShell :title="title" :subtitle="subtitle">
    <LoadingSpinner v-if="loading" />
    <article
      v-else
      class="prose-legal space-y-4 text-sm leading-7 text-ink/80"
      v-html="html"
    />
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import { setPageMeta, applySeoPayload } from '../../services/meta'
import { unwrapItem } from '../../utils/format'

const props = defineProps({
  slug: { type: String, required: true },
  fallbackTitle: { type: String, default: '' },
  fallbackHtml: { type: String, default: '' },
})

const loading = ref(true)
const page = ref(null)

const title = computed(() => page.value?.title || props.fallbackTitle)
const subtitle = computed(() =>
  page.value?.updated_at
    ? `آخرین بروزرسانی: ${new Date(page.value.updated_at).toLocaleDateString('fa-IR')}`
    : ''
)
const html = computed(() => page.value?.content || props.fallbackHtml)

onMounted(async () => {
  try {
    const { data } = await api.get(`/pages/${props.slug}`)
    page.value = unwrapItem(data)
    if (page.value) {
      const pageUrl = `${window.location.origin}/${props.slug}`
      if (page.value.seo?.meta) {
        applySeoPayload(page.value.seo, {
          breadcrumbs: [
            { name: 'خانه', url: `${window.location.origin}/` },
            { name: page.value.title, url: pageUrl },
          ],
        })
      } else {
        setPageMeta({
          title: page.value.meta_title || page.value.title,
          description: page.value.meta_description || '',
          url: pageUrl,
          schema: page.value.schema || null,
          breadcrumbs: [
            { name: 'خانه', url: `${window.location.origin}/` },
            { name: page.value.title, url: pageUrl },
          ],
        })
      }
    }
  } catch {
    page.value = null
  } finally {
    loading.value = false
  }
})
</script>
