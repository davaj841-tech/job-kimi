<template>
  <div class="px-4 py-4">
    <h1 class="mb-4 section-title">بلاگ استخدامی</h1>
    <LoadingSpinner v-if="loading" />
    <div v-else class="space-y-2">
      <RouterLink
        v-for="post in posts"
        :key="post.id"
        :to="`/blog/${post.slug}`"
        class="card-soft block p-3"
      >
        <p class="text-sm font-bold">{{ post.title }}</p>
        <p class="mt-1 line-clamp-2 text-xs text-ink-muted">{{ post.excerpt }}</p>
        <p class="mt-2 text-[11px] text-ink-muted">{{ post.category }} · {{ post.author_name }}</p>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../api/client';
import LoadingSpinner from '../../components/LoadingSpinner.vue';

const posts = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const { data } = await api.get('/blog-posts');
    posts.value = data.data?.data || data.data || [];
  } finally {
    loading.value = false;
  }
});
</script>
