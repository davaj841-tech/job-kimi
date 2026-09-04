<template>
  <section class="home-articles bg-surface-page py-6 sm:py-8">
    <div class="mx-auto max-w-7xl px-4">
      <div class="mb-4 flex items-end justify-between gap-3">
        <div>
          <h2 class="text-lg font-black text-desk-text sm:text-xl">
            📰 مقالات و مطالب سایت
          </h2>
          <p class="mt-0.5 text-xs text-desk-muted sm:text-sm">
            راهنماها و تحلیل‌های استخدام
          </p>
        </div>
        <RouterLink
          to="/articles"
          class="shrink-0 text-xs font-bold text-brand hover:underline sm:text-sm"
        >
          مشاهده همه
        </RouterLink>
      </div>

      <HomeRail v-if="items.length">
        <RouterLink
          v-for="post in items"
          :key="`${post._kind}-${post.id}`"
          :to="
            post._kind === 'article'
              ? `/articles/${post.slug}`
              : `/blog/${post.slug}`
          "
          class="home-rail-card home-articles-card min-w-[11.5rem] sm:min-w-[13rem]"
        >
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <span class="text-2xl leading-none" aria-hidden="true">{{
              post._kind === 'article' ? '📰' : '✍️'
            }}</span>
            <span
              class="rounded-md bg-orange-50 px-1.5 py-0.5 text-[10px] font-bold text-desk-orange dark:bg-orange-500/15 dark:text-orange-300"
            >
              {{ post._kind === 'article' ? 'مقاله' : 'وبلاگ' }}
            </span>
          </div>
          <p
            class="line-clamp-3 flex-1 text-sm font-bold leading-6 text-desk-text sm:line-clamp-2"
          >
            {{ post.title }}
          </p>
          <p
            v-if="post.excerpt"
            class="mt-2 line-clamp-3 text-[11px] leading-5 text-desk-muted sm:line-clamp-2"
          >
            {{ post.excerpt }}
          </p>
        </RouterLink>
      </HomeRail>

      <div
        v-else
        class="rounded-2xl border border-dashed border-surface-line bg-surface px-4 py-8 text-center dark:border-slate-700 dark:bg-slate-900/50"
      >
        <p class="text-sm font-medium text-desk-text">
          هنوز مقاله‌ای منتشر نشده
        </p>
        <p class="mt-1 text-xs text-desk-muted">
          به‌زودی مطالب آموزشی و تحلیلی اینجا قرار می‌گیرد.
        </p>
        <RouterLink
          to="/blog"
          class="mt-3 inline-block text-xs font-bold text-brand hover:underline"
        >
          وبلاگ جاب‌آزمون
        </RouterLink>
      </div>
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
  const fromArticles = (props.articles || []).map((a) => ({
    ...a,
    _kind: 'article',
  }))
  const fromBlog = (props.posts || []).map((p) => ({ ...p, _kind: 'blog' }))
  return [...fromArticles, ...fromBlog].slice(0, 12)
})
</script>

<style scoped>
.home-articles-card {
  min-height: 9.5rem;
}
@media (max-width: 640px) {
  .home-articles-card {
    min-height: 10.5rem;
  }
}
</style>
