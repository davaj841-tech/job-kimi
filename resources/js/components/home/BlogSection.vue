<template>
  <section class="bg-desk-gray py-14">
    <div class="desk-container">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="desk-section-title">از وبلاگ جاب‌آزمون</h2>
          <p class="mt-1 text-sm text-desk-muted">راهنماها و نکات کاربردی برای قبولی</p>
        </div>
        <RouterLink to="/blog" class="text-sm font-bold text-desk-orange hover:underline">
          همه مقالات
        </RouterLink>
      </div>

      <div class="grid grid-cols-4 gap-5">
        <RouterLink
          v-for="post in displayPosts"
          :key="post.id || post.slug || post.title"
          :to="post.slug ? `/blog/${post.slug}` : '/blog'"
          class="desk-card overflow-hidden"
        >
          <div
            class="flex aspect-video items-center justify-center"
            :class="post.tint"
          >
            <DesktopIcon name="book" :size="30" class="text-white" />
          </div>
          <div class="p-4 text-right">
            <div class="mb-2 flex flex-wrap items-center justify-end gap-2 text-[11px] text-desk-muted">
              <span class="rounded-md bg-white px-2 py-0.5">{{ post.category || 'عمومی' }}</span>
              <span>{{ formatDate(post.published_at || post.created_at) }}</span>
            </div>
            <h3 class="line-clamp-2 text-base font-semibold leading-7 text-desk-text">
              {{ post.title }}
            </h3>
          </div>
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { formatDate } from '../../utils/format';
import DesktopIcon from '../DesktopIcon.vue';

const props = defineProps({
  posts: { type: Array, default: () => [] },
});

const fallbackPosts = [
  {
    id: 'b1',
    title: 'سوالات متداول مصاحبه شغلی و نحوه پاسخگویی',
    category: 'مصاحبه',
    created_at: '2024-03-01',
  },
  {
    id: 'b2',
    title: 'نحوه رزومه‌نویسی حرفه‌ای + نمونه رزومه',
    category: 'رزومه',
    created_at: '2024-03-05',
  },
  {
    id: 'b3',
    title: 'بهترین منابع برای آزمون آموزش و پرورش 1403',
    category: 'منابع',
    created_at: '2024-03-10',
  },
  {
    id: 'b4',
    title: '10 تکنیک طلایی برای قبولی در آزمون‌های استخدامی',
    category: 'تکنیک',
    created_at: '2024-03-15',
  },
];

const tints = ['bg-desk-blue', 'bg-desk-orange', 'bg-[#0f766e]', 'bg-[#7c3aed]'];

const displayPosts = computed(() => {
  const source = props.posts?.length ? props.posts : fallbackPosts;
  return source.slice(0, 4).map((post, i) => ({
    ...post,
    tint: tints[i % tints.length],
  }));
});
</script>
