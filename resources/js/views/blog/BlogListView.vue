<template>
  <PageShell title="✍️ بلاگ استخدامی" subtitle="نکات و راهنماهای آمادگی آزمون">
    <LoadingSpinner v-if="loading" />
    <ul v-else class="page-card divide-y divide-surface-line overflow-hidden">
      <li v-for="post in posts" :key="post.id">
        <RouterLink
          :to="`/blog/${post.slug}`"
          class="flex gap-3 px-4 py-4 transition hover:bg-surface-page sm:px-5"
        >
          <span class="mt-0.5 text-xl" aria-hidden="true">✍️</span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-desk-text sm:text-base">
              {{ post.title }}
            </p>
            <p class="mt-1 line-clamp-2 text-sm leading-6 text-desk-muted">
              {{ post.excerpt }}
            </p>
            <p class="mt-2 text-[11px] text-desk-muted">
              {{ post.category }} · {{ post.author_name }}
            </p>
          </div>
        </RouterLink>
      </li>
      <li
        v-if="!posts.length"
        class="px-4 py-12 text-center text-sm text-desk-muted"
      >
        مطلبی منتشر نشده است.
      </li>
    </ul>
  </PageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import { setListPageMeta } from '../../services/meta'

const posts = ref([])
const loading = ref(true)

onMounted(async () => {
  setListPageMeta({
    title: 'بلاگ استخدامی | جاب‌آزمون',
    description:
      'مقالات و نکات آمادگی آزمون‌های استخدامی، مصاحبه شغلی و رزومه‌نویسی',
    path: '/blog',
  })
  try {
    const { data } = await api.get('/blog-posts')
    posts.value = data.data?.data || data.data || []
  } finally {
    loading.value = false
  }
})
</script>
