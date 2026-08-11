<template>
  <PageShell title="رزومه‌های من" subtitle="ساخت و دانلود رزومه فارسی">
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
    <div v-else class="space-y-2">
      <RouterLink
        v-for="item in items"
        :key="item.id"
        :to="`/resumes/${item.id}`"
        class="card-soft block p-3 transition hover:border-brand/30"
      >
        <p class="text-sm font-bold">{{ item.title }}</p>
        <p class="mt-1 text-xs text-ink-muted">
          قالب {{ item.template_id }} · ویرایش و دانلود PDF
        </p>
      </RouterLink>
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
const message = ref('')

onMounted(async () => {
  try {
    if (!auth.user) await auth.fetchMe()
    const { data } = await api.get('/resumes')
    items.value = data.data || []
  } finally {
    loading.value = false
  }
})

function defaultData() {
  const u = auth.user || {}
  return {
    personal: {
      full_name: u.name || 'نام کاربر',
      birth_date: '1370-01-01',
      national_code: u.national_code || '0000000000',
      mobile: u.mobile || '09120000000',
      email: u.email || 'user@example.com',
      address: u.province || '',
      photo: u.avatar || null,
    },
    education: [
      {
        degree: 'کارشناسی',
        field: 'مهندسی',
        university: 'دانشگاه تهران',
        start_year: 1395,
        end_year: 1399,
        gpa: null,
      },
    ],
    experience: [],
    skills: [{ name: 'کار تیمی', level: 'متوسط' }],
    languages: [],
    summary: '',
    target_job: 'کارشناس',
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
</script>
