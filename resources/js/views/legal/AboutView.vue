<template>
  <PageShell :title="title" :subtitle="subtitle">
    <LoadingSpinner v-if="loading" />
    <template v-else>
      <article
        class="prose-legal mb-8 space-y-4 text-sm leading-7 text-ink/80"
        v-html="html"
      />
      <section v-if="team.length" class="mt-12">
        <div class="mb-8 text-center">
          <p class="text-xs font-bold tracking-wide text-brand">تیم جاب‌آزمون</p>
          <h2 class="mt-1 text-xl font-black text-desk-text">مدیران سایت</h2>
          <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-desk-muted" dir="rtl">
            آشنایی با افرادی که این سامانه را اداره می‌کنند.
          </p>
        </div>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <article
            v-for="member in team"
            :key="member.id"
            class="flex flex-col items-center overflow-hidden rounded-3xl border border-surface-line bg-white p-5 shadow-sm"
          >
            <h3 class="text-sm font-black text-desk-text">{{ member.name }}</h3>
            <p v-if="member.role" class="mt-1 text-xs font-bold text-brand">
              {{ member.role }}
            </p>
            <div
              class="mt-4 h-40 w-[120px] overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-sm"
            >
              <img
                v-if="member.photo_url"
                :src="member.photo_url"
                :alt="member.name"
                class="h-full w-full object-cover object-top"
              />
              <div
                v-else
                class="flex h-full w-full items-center justify-center text-2xl font-black text-slate-400"
              >
                {{ initials(member.name) }}
              </div>
            </div>
            <p
              v-if="member.bio"
              class="mt-4 w-full text-right text-xs leading-7 text-desk-muted"
              dir="rtl"
            >
              {{ member.bio }}
            </p>
          </article>
        </div>
      </section>
    </template>
  </PageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import { applySeoPayload } from '../../services/meta'
import { unwrapItem } from '../../utils/format'

const loading = ref(true)
const page = ref(null)

const title = computed(() => page.value?.title || 'درباره ما')
const subtitle = computed(() =>
  page.value?.updated_at
    ? `آخرین بروزرسانی: ${new Date(page.value.updated_at).toLocaleDateString('fa-IR')}`
    : ''
)
const html = computed(() => page.value?.content || '')
const team = computed(() =>
  Array.isArray(page.value?.team) ? page.value.team : []
)

function initials(name) {
  return String(name || '')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
}

onMounted(async () => {
  try {
    const { data } = await api.get('/pages/about')
    page.value = unwrapItem(data)
    if (page.value?.seo?.meta) {
      applySeoPayload(page.value.seo, {
        breadcrumbs: [
          { name: 'خانه', url: `${window.location.origin}/` },
          { name: page.value.title || 'درباره ما', url: `${window.location.origin}/about` },
        ],
      })
    } else if (page.value) {
      applySeoPayload({
        meta: {
          meta_title: page.value.meta_title || page.value.title || 'درباره ما',
          meta_description: page.value.meta_description || '',
          canonical_url: `${window.location.origin}/about`,
        },
        schema: page.value.schema,
      })
    }
  } catch {
    page.value = null
  } finally {
    loading.value = false
  }
})
</script>
