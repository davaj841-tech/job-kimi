<template>
  <PageShell title="رزومه‌های من" subtitle="ساخت، ویرایش و دانلود رزومه فارسی">
    <template #actions>
      <button
        class="btn-primary !h-9 !w-auto px-3 text-xs"
        :disabled="creating"
        @click="create"
      >
        {{ creating ? '…' : 'رزومه جدید' }}
      </button>
    </template>
    <LoadingSpinner v-if="loading" />
    <div
      v-else
      class="space-y-2"
    >
      <div
        v-for="item in items"
        :key="item.id"
        class="card-soft flex items-center gap-3 p-3"
      >
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-bold">{{ item.title }}</p>
          <p class="mt-1 text-xs text-ink-muted">
            قالب {{ item.template_id }}
            <span v-if="item.updated_at"> · آخرین ویرایش {{ formatDate(item.updated_at) }}</span>
          </p>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
          <button
            type="button"
            class="rounded-xl border border-surface-line px-3 py-1.5 text-xs font-bold hover:border-brand hover:text-brand"
            @click="router.push(`/resumes/${item.id}`)"
          >
            ویرایش
          </button>
          <button
            type="button"
            class="rounded-xl px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50 disabled:opacity-50"
            :disabled="deletingId === item.id"
            @click="remove(item)"
          >
            {{ deletingId === item.id ? '…' : 'حذف' }}
          </button>
        </div>
      </div>
      <p
        v-if="!items.length"
        class="py-10 text-center text-sm text-ink-muted"
      >
        هنوز رزومه‌ای ندارید. با «رزومه جدید» شروع کنید.
      </p>
    </div>
    <p
      v-if="message"
      class="mt-3 text-center text-sm text-brand"
    >
      {{ message }}
    </p>
  </PageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api/client'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PageShell from '../../components/layout/PageShell.vue'
import { useAuthStore } from '../../stores/auth'
import { apiErrorMessage } from '../../utils/format'

const router = useRouter()
const auth = useAuthStore()
const items = ref([])
const loading = ref(true)
const creating = ref(false)
const deletingId = ref(null)
const message = ref('')

onMounted(async () => {
  try {
    if (!auth.user) await auth.fetchMe()
    await load()
  } finally {
    loading.value = false
  }
})

async function load() {
  const { data } = await api.get('/resumes')
  items.value = data.data || []
}

function formatDate(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('fa-IR')
  } catch {
    return ''
  }
}

function defaultData() {
  const u = auth.user || {}
  return {
    personal: {
      full_name: u.name || '',
      birth_date: '',
      national_code: '',
      mobile: u.mobile || '',
      home_phone: '',
      email: u.email || '',
      address: '',
      photo: null,
      birth_province: '',
      birth_city: '',
      marital_status: '',
      field_of_study: '',
      military_status: '',
      insurance_history: '',
      postal_code: '',
    },
    education: [],
    experience: [],
    skills: [],
    languages: [],
    summary: '',
    target_job: '',
  }
}

async function create() {
  message.value = ''
  creating.value = true
  try {
    const { data } = await api.post('/resumes', {
      title: `رزومه ${auth.user?.name || 'من'}`,
      template_id: 1,
      data: defaultData(),
    })
    router.push(`/resumes/${data.data.id}`)
  } catch (e) {
    message.value = apiErrorMessage(e, 'ایجاد رزومه ناموفق بود.')
  } finally {
    creating.value = false
  }
}

async function remove(item) {
  if (!window.confirm(`رزومه «${item.title}» حذف شود؟`)) return
  deletingId.value = item.id
  message.value = ''
  try {
    await api.delete(`/resumes/${item.id}`)
    items.value = items.value.filter((r) => r.id !== item.id)
  } catch (e) {
    message.value = apiErrorMessage(e, 'حذف رزومه ناموفق بود.')
  } finally {
    deletingId.value = null
  }
}
</script>
