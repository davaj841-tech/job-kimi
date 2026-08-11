<template>
  <section
    v-if="items.length"
    class="border-t border-surface-line bg-white py-6 sm:py-7"
  >
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-3 flex items-end justify-between">
        <h2 class="text-lg font-black text-desk-dark sm:text-xl">آخرین مقالات</h2>
        <RouterLink
          to="/articles"
          class="text-xs font-bold text-brand hover:underline"
        >
          مشاهده همه
        </RouterLink>
      </div>
      <HomeRail>
        <RouterLink
          v-for="post in items"
          :key="`${post._kind}-${post.id}`"
          :to="post._kind === 'article' ? `/articles/${post.slug}` : `/blog/${post.slug}`"
          class="w-[16.5rem] shrink-0 rounded-2xl border border-surface-line bg-surface-page p-3.5 text-right transition hover:bg-white hover:shadow-sm"
        >
          <p class="text-[10px] font-bold text-desk-orange">
            {{ post._kind === 'article' ? 'مقاله' : 'وبلاگ' }}
          </p>
          <p class="mt-1.5 line-clamp-2 text-sm font-bold text-desk-text">{{ post.title }}</p>
          <p
            v-if="post.excerpt"
            class="mt-1 line-clamp-2 text-[11px] leading-5 text-desk-muted"
          >
            {{ post.excerpt }}
          </p>
        </RouterLink>
      </HomeRail>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import HomeRail from './HomeRail.vue'

const props = defineProps({
  articles: { type: Array, default: () => [] },
  posts: { type: Array, default: () => [] },
})

const items = computed(() => {
  const fromArticles = (props.articles || []).map((a) => ({ ...a, _kind: 'article' }))
  const fromBlog = (props.posts || []).map((p) => ({ ...p, _kind: 'blog' }))
  return [...fromArticles, ...fromBlog].slice(0, 12)
})
</script>
