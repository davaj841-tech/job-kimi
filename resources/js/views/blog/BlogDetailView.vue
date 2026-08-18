<template>
  <PageShell>
    <LoadingSpinner v-if="loading" />
    <article v-else-if="post" class="page-card p-4 sm:p-8">
      <div class="mb-3 flex items-start justify-between gap-3">
        <h1 class="page-title leading-8 sm:text-2xl">{{ post.title }}</h1>
        <button
          class="shrink-0 text-xs font-bold text-brand hover:underline"
          @click="shareOpen = true"
        >
          اشتراک‌گذاری
        </button>
      </div>
      <p class="mb-4 text-xs text-desk-muted">
        {{ post.category }} · {{ post.author_name }}
      </p>
      <div
        class="prose-ja mb-8 whitespace-pre-wrap text-sm leading-7 text-desk-text"
        v-html="post.content"
      />

      <RelatedCatalog
        :exams="post.catalog_exams || []"
        :pdfs="post.catalog_pdfs || []"
      />

      <section class="border-t border-surface-line pt-4">
        <h2 class="mb-3 text-sm font-bold">نظرات</h2>
        <form
          v-if="auth.isAuthenticated"
          class="mb-4 space-y-2"
          @submit.prevent="sendComment"
        >
          <textarea
            v-model="comment"
            required
            rows="3"
            class="input-field min-h-[80px]"
            placeholder="نظر شما..."
          />
          <button class="btn-primary max-w-xs text-sm" :disabled="sending">
            {{ sending ? '...' : 'ارسال نظر' }}
          </button>
          <p v-if="commentMsg" class="text-xs text-emerald-700">
            {{ commentMsg }}
          </p>
        </form>
        <p v-else class="mb-3 text-xs text-desk-muted">
          برای ثبت نظر
          <RouterLink to="/login" class="text-brand underline"
            >وارد شوید</RouterLink
          >.
        </p>
        <div class="space-y-2">
          <div v-for="c in comments" :key="c.id" class="card-soft p-3 text-sm">
            <p class="font-bold">{{ c.user?.name || 'کاربر' }}</p>
            <p class="mt-1 text-desk-muted">{{ c.content }}</p>
          </div>
          <p v-if="!comments.length" class="text-xs text-desk-muted">
            هنوز نظری نیست.
          </p>
        </div>
      </section>

      <ShareModal
        :open="shareOpen"
        :title="post.title"
        :description="post.excerpt || ''"
        :url="shareUrl"
        @close="shareOpen = false"
      />
    </article>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import RelatedCatalog from '../../components/RelatedCatalog.vue'
import ShareModal from '../../components/ShareModal.vue'
import { setBlogPostMeta } from '../../services/meta'
import { useAuthStore } from '../../stores/auth'
import { unwrapList } from '../../utils/format'

const route = useRoute()
const auth = useAuthStore()
const post = ref(null)
const loading = ref(true)
const comments = ref([])
const comment = ref('')
const sending = ref(false)
const commentMsg = ref('')
const shareOpen = ref(false)
const shareUrl = computed(
  () => `${window.location.origin}/blog/${post.value?.slug || ''}`
)

onMounted(async () => {
  try {
    const { data } = await api.get(`/blog-posts/${route.params.slug}`)
    post.value = data.data
    setBlogPostMeta(post.value)
    if (post.value?.id) {
      const c = await api.get(`/blog-posts/${post.value.id}/comments`)
      comments.value = unwrapList(c.data)
    }
  } finally {
    loading.value = false
  }
})

async function sendComment() {
  sending.value = true
  commentMsg.value = ''
  try {
    const { data } = await api.post(`/blog-posts/${post.value.id}/comments`, {
      content: comment.value,
    })
    comment.value = ''
    commentMsg.value = data.message || 'ثبت شد'
    if (data.data?.status === 'approved') comments.value.unshift(data.data)
  } catch (e) {
    commentMsg.value = e.response?.data?.message || 'خطا'
  } finally {
    sending.value = false
  }
}
</script>
