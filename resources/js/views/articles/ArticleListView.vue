<template>
  <PageShell
    title="📰 مقالات و وبلاگ"
    subtitle="راهنماهای استخدامی و نکات آمادگی آزمون"
  >
    <div class="mb-4 flex gap-2">
      <button
        type="button"
        class="chip"
        :class="tab === 'all' ? 'chip-active' : ''"
        @click="tab = 'all'"
      >
        همه
      </button>
      <button
        type="button"
        class="chip"
        :class="tab === 'article' ? 'chip-active' : ''"
        @click="tab = 'article'"
      >
        📰 مقالات
      </button>
      <button
        type="button"
        class="chip"
        :class="tab === 'blog' ? 'chip-active' : ''"
        @click="tab = 'blog'"
      >
        ✍️ وبلاگ
      </button>
    </div>

    <LoadingSpinner v-if="loading" />
    <ul v-else class="page-card divide-y divide-surface-line overflow-hidden">
      <li v-for="item in filtered" :key="`${item._kind}-${item.id}`">
        <RouterLink
          :to="
            item._kind === 'article'
              ? `/articles/${item.slug}`
              : `/blog/${item.slug}`
          "
          class="flex gap-3 px-4 py-4 transition hover:bg-surface-page sm:px-5"
        >
          <span class="mt-0.5 text-xl" aria-hidden="true">{{
            item._kind === 'article' ? '📰' : '✍️'
          }}</span>
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-bold text-desk-orange">
              {{ item._kind === 'article' ? 'مقاله' : 'وبلاگ' }}
            </p>
            <h2 class="text-sm font-bold leading-7 text-desk-text sm:text-base">
              {{ item.title }}
            </h2>
            <p class="mt-1 text-xs text-desk-muted">
              <template v-if="item._kind === 'article'">
                {{ item.content_type_label || 'مقاله' }} ·
                {{ item.company_name || '—' }}
              </template>
              <template v-else>
                {{ item.category || 'وبلاگ' }} ·
                {{ item.author_name || '—' }}
              </template>
            </p>
            <p
              v-if="item.excerpt"
              class="mt-2 line-clamp-2 text-sm leading-6 text-desk-muted"
            >
              {{ item.excerpt }}
            </p>
          </div>
        </RouterLink>
      </li>
      <li
        v-if="!filtered.length"
        class="px-4 py-12 text-center text-sm text-desk-muted"
      >
        موردی یافت نشد.
      </li>
    </ul>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import { unwrapList } from '../../utils/format'
import { setListPageMeta } from '../../services/meta'

const loading = ref(true)
const tab = ref('all')
const articles = ref([])
const posts = ref([])

const items = computed(() => {
  const a = (articles.value || []).map((x) => ({ ...x, _kind: 'article' }))
  const b = (posts.value || []).map((x) => ({ ...x, _kind: 'blog' }))
  return [...a, ...b]
})

const filtered = computed(() => {
  if (tab.value === 'all') return items.value
  return items.value.filter((x) => x._kind === tab.value)
})

onMounted(async () => {
  setListPageMeta({
    title: 'مقالات استخدامی | جاب‌آزمون',
    description:
      'راهنمای جامع آزمون‌های استخدامی، منابع مطالعه و استراتژی قبولی',
    path: '/articles',
  })
  try {
    const [artRes, blogRes] = await Promise.all([
      api.get('/articles', { params: { per_page: 30 } }).catch(() => null),
      api.get('/blog-posts', { params: { per_page: 30 } }).catch(() => null),
    ])
    articles.value = unwrapList(artRes?.data)
    posts.value = unwrapList(blogRes?.data)
  } finally {
    loading.value = false
  }
})
</script>
