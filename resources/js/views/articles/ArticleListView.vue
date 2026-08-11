<template>
  <PageShell title="مقالات استخدامی" subtitle="راهنمای ثبت‌نام و آزمون از آگهی‌های تأییدشده">
    <LoadingSpinner v-if="loading" />
    <ul
      v-else
      class="page-card divide-y divide-surface-line overflow-hidden"
    >
      <li v-for="a in articles" :key="a.id">
        <RouterLink
          :to="`/articles/${a.slug}`"
          class="block px-4 py-4 transition hover:bg-surface-page sm:px-5"
        >
          <h2 class="text-sm font-bold leading-7 text-desk-text sm:text-base">
            {{ a.title }}
          </h2>
          <p class="mt-1 text-xs text-desk-muted">
            {{ a.content_type_label }} · {{ a.company_name || '—' }}
          </p>
          <p class="mt-2 line-clamp-2 text-sm leading-6 text-desk-muted">
            {{ a.excerpt }}
          </p>
        </RouterLink>
      </li>
      <li v-if="!articles.length" class="px-4 py-12 text-center text-sm text-desk-muted">
        مقاله‌ای منتشر نشده است.
      </li>
    </ul>
  </PageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import { unwrapList } from '../../utils/format'

const loading = ref(true)
const articles = ref([])

onMounted(async () => {
  try {
    const { data } = await api.get('/articles', { params: { per_page: 20 } })
    articles.value = unwrapList(data)
  } finally {
    loading.value = false
  }
})
</script>
