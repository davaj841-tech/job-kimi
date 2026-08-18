<template>
  <PageShell>
    <LoadingSpinner v-if="loading" />
    <article v-else-if="article" class="page-card p-4 sm:p-8">
      <h1 class="page-title leading-8 sm:text-2xl">{{ article.title }}</h1>
      <p class="page-sub mb-5">
        {{ article.content_type_label }}
        <span v-if="article.company_name"> · {{ article.company_name }}</span>
        <span v-if="article.source_name"> · منبع: {{ article.source_name }}</span>
      </p>
      <p v-if="article.excerpt" class="mb-5 text-sm leading-7 text-desk-text">
        {{ article.excerpt }}
      </p>
      <div class="prose-ja mb-6 text-sm leading-7 text-desk-text">
        {{ contentText }}
      </div>
      <RelatedCatalog
        :exams="article.catalog_exams || []"
        :pdfs="article.catalog_pdfs || []"
      />
      <RouterLink
        v-if="article.job?.id"
        :to="`/jobs/${article.job.id}`"
        class="text-sm font-bold text-brand hover:underline"
      >
        مشاهده آگهی مرتبط
      </RouterLink>
    </article>
    <p v-else class="text-sm text-desk-muted">مقاله یافت نشد.</p>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import RelatedCatalog from '../../components/RelatedCatalog.vue'

const route = useRoute()
const loading = ref(true)
const article = ref(null)

const contentText = computed(() => {
  const html = article.value?.content || ''
  return String(html)
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n')
    .replace(/<\/h[2-4]>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .trim()
})

async function load() {
  loading.value = true
  article.value = null
  try {
    const { data } = await api.get(`/articles/${route.params.slug}`)
    article.value = data.data
    if (article.value?.meta) {
      document.title = article.value.meta.title || article.value.title
      const desc = document.querySelector('meta[name="description"]')
      if (desc && article.value.meta.description) {
        desc.setAttribute('content', article.value.meta.description)
      }
    }
  } catch {
    article.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.params.slug, load)
</script>
