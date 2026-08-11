<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">پیش‌نمایش محتوای AI</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>

      <div v-if="item" class="space-y-4">
        <div class="flex flex-wrap gap-2 text-xs">
          <span class="rounded-full bg-slate-100 px-2 py-1">{{
            typeLabel(item.type)
          }}</span>
          <span
            class="rounded-full px-2 py-1"
            :class="statusClass(item.status)"
            >{{ statusLabel(item.status) }}</span
          >
        </div>
        <p class="text-sm text-slate-600">
          <strong>پرامپت:</strong> {{ item.prompt || '—' }}
        </p>

        <div v-if="questions.length" class="space-y-3">
          <div
            v-for="(q, i) in questions"
            :key="i"
            class="rounded-xl border border-slate-100 p-3 text-sm"
          >
            <p class="mb-2 font-bold">{{ i + 1 }}. {{ q.question_text }}</p>
            <ul class="space-y-1 text-slate-600">
              <li>الف) {{ q.option_a }}</li>
              <li>ب) {{ q.option_b }}</li>
              <li>ج) {{ q.option_c }}</li>
              <li>د) {{ q.option_d }}</li>
            </ul>
            <p class="mt-2 text-xs text-emerald-700">
              پاسخ: {{ q.correct_answer }}
            </p>
          </div>
        </div>

        <div
          v-else-if="htmlContent"
          class="prose prose-sm max-w-none rounded-xl bg-slate-50 p-4"
          v-html="htmlContent"
        />

        <pre
          v-else
          class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs text-green-300"
          dir="ltr"
          >{{ pretty }}</pre>

        <div
          v-if="item.status === 'pending'"
          class="flex flex-wrap justify-end gap-2"
        >
          <button
            type="button"
            class="btn-muted text-red-600"
            @click="$emit('reject')"
          >
            رد
          </button>
          <button type="button" class="btn-orange" @click="$emit('approve')">
            تایید
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  open: Boolean,
  item: { type: Object, default: null },
})
defineEmits(['close', 'approve', 'reject'])

const parsed = computed(() => {
  const raw = props.item?.generated_content
  if (!raw) return null
  if (typeof raw === 'object') return raw
  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
})

const questions = computed(() => {
  const meta = props.item?.metadata?.generated_questions
  if (Array.isArray(meta)) return meta
  const p = parsed.value
  if (Array.isArray(p)) return p
  if (Array.isArray(p?.questions)) return p.questions
  return []
})

const htmlContent = computed(() => {
  if (props.item?.type !== 'blog_post') return ''
  const p = parsed.value
  if (p?.content) return p.content
  if (
    typeof props.item?.generated_content === 'string' &&
    props.item.generated_content.includes('<')
  ) {
    return props.item.generated_content
  }
  return p?.body || ''
})

const pretty = computed(() => {
  if (parsed.value) return JSON.stringify(parsed.value, null, 2)
  return String(props.item?.generated_content || '')
})

function typeLabel(t) {
  return (
    {
      exam_question: 'سوالات',
      blog_post: 'مقاله',
      job_crawl: 'آگهی خزش',
      resume_tip: 'مشاوره رزومه',
      job_tip: 'نکته شغلی',
    }[t] || t
  )
}
function statusLabel(s) {
  return (
    { pending: 'در انتظار تأیید', approved: 'تایید شده', rejected: 'رد شده' }[
      s
    ] || s
  )
}
function statusClass(s) {
  return (
    {
      pending: 'bg-yellow-100 text-yellow-800',
      approved: 'bg-emerald-100 text-emerald-800',
      rejected: 'bg-red-100 text-red-700',
    }[s] || 'bg-slate-100'
  )
}
</script>

<style scoped>
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
</style>
