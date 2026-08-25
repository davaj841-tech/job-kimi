<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">منابع رسمی تجمیع</h1>
        <p class="mt-1 text-sm text-slate-500">
          فقط منابع فعال و تاییدشده در whitelist خزیده می‌شوند.
        </p>
      </div>
      <button class="btn-dark" @click="openCreate">منبع جدید</button>
    </div>

    <div
      v-if="quality"
      class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8"
    >
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">سالم (ACTIVE)</p>
        <p class="mt-1 text-xl font-bold text-emerald-700">
          {{ quality.source_health?.healthy ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">محدود</p>
        <p class="mt-1 text-xl font-bold text-amber-600">
          {{ quality.source_health?.limited ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">موقتاً قطع</p>
        <p class="mt-1 text-xl font-bold text-red-600">
          {{ quality.source_health?.temporarily_unavailable ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">فقط دستی</p>
        <p class="mt-1 text-xl font-bold text-slate-600">
          {{ quality.source_health?.manual_only ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">خزش موفق</p>
        <p class="mt-1 text-xl font-bold">
          {{ quality.crawl_quality?.successful_crawls ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">خزش ناموفق</p>
        <p class="mt-1 text-xl font-bold text-red-600">
          {{ quality.crawl_quality?.failed_crawls ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">خالیِ موفق</p>
        <p class="mt-1 text-xl font-bold text-indigo-700">
          {{ quality.crawl_quality?.empty_successful_crawls ?? 0 }}
        </p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-500">هشدارها</p>
        <p class="mt-1 text-xl font-bold text-orange-600">
          {{ (quality.alerts || []).length }}
        </p>
      </div>
    </div>

    <div
      v-if="quality?.alerts?.length"
      class="rounded-xl border border-amber-200 bg-amber-50 p-4"
    >
      <h3 class="mb-2 text-sm font-bold text-amber-900">
        هشدارهای سلامت منابع
      </h3>
      <ul class="space-y-1 text-sm text-amber-900">
        <li v-for="(a, i) in quality.alerts.slice(0, 8)" :key="i">
          <span class="font-medium">{{ a.name }}</span> — {{ a.message }}
        </li>
      </ul>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
        <input
          v-model="store.filters.search"
          class="field md:col-span-2"
          placeholder="جستجو نام/دامنه"
          @keyup.enter="apply"
        />
        <select v-model="store.filters.source_type" class="field">
          <option value="">همه انواع</option>
          <option
            v-for="t in store.options.source_types"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="store.filters.quality_status" class="field">
          <option value="">کیفیت: همه</option>
          <option
            v-for="t in store.options.quality_statuses || []"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="store.filters.is_approved" class="field">
          <option value="">تایید: همه</option>
          <option value="1">تایید شده</option>
          <option value="0">تایید نشده</option>
        </select>
        <select v-model="store.filters.is_enabled" class="field">
          <option value="">فعال: همه</option>
          <option value="1">فعال</option>
          <option value="0">غیرفعال</option>
        </select>
      </div>
      <div class="mt-3 flex gap-2">
        <button class="btn-orange" @click="apply">اعمال</button>
        <button class="btn-muted" @click="clear">پاک کردن</button>
      </div>
    </div>

    <DataTable
      :columns="columns"
      :rows="store.sources"
      :loading="store.loading"
      actions
    >
      <template #cell-name="{ row }">
        <button
          class="text-right font-medium hover:text-orange-600"
          @click="openEdit(row)"
        >
          {{ row.name }}
        </button>
        <div class="text-xs text-slate-400" dir="ltr">{{ row.domain }}</div>
      </template>
      <template #cell-flags="{ row }">
        <span
          class="mr-1 rounded-full px-2 py-0.5 text-xs"
          :class="
            row.is_enabled
              ? 'bg-emerald-100 text-emerald-700'
              : 'bg-slate-100 text-slate-500'
          "
          >{{ row.is_enabled ? 'فعال' : 'خاموش' }}</span
        >
        <span
          class="mr-1 rounded-full px-2 py-0.5 text-xs"
          :class="
            row.is_approved
              ? 'bg-blue-100 text-blue-700'
              : 'bg-amber-100 text-amber-700'
          "
          >{{ row.is_approved ? 'تایید' : 'تاییدنشده' }}</span
        >
        <span
          class="rounded-full px-2 py-0.5 text-xs"
          :class="qualityBadgeClass(row.quality_status)"
          >{{ row.quality_status_label || row.quality_status || '—' }}</span
        >
      </template>
      <template #cell-last_crawled_at="{ row }">
        <div>{{ formatDate(row.last_crawled_at) }}</div>
        <div v-if="row.consecutive_failures" class="text-xs text-red-600">
          شکست متوالی: {{ row.consecutive_failures }}
        </div>
        <div v-else-if="row.last_success_at" class="text-xs text-slate-400">
          موفق: {{ formatDate(row.last_success_at) }}
        </div>
      </template>
      <template #actions="{ row }">
        <div class="flex flex-wrap justify-end gap-1">
          <button class="act" @click="openEdit(row)">مدیریت</button>
          <button
            v-if="!row.is_approved"
            class="act text-blue-700"
            @click="doApprove(row)"
          >
            تایید
          </button>
          <button v-else class="act" @click="doUnapprove(row)">
            لغو تایید
          </button>
          <button
            v-if="!row.is_enabled"
            class="act text-emerald-700"
            @click="doEnable(row)"
          >
            فعال
          </button>
          <button v-else class="act text-amber-700" @click="doDisable(row)">
            غیرفعال
          </button>
          <button class="act" @click="doResetHealth(row)">
            بازنشانی سلامت
          </button>
          <button
            class="act text-indigo-700"
            :disabled="
              !(row.is_enabled && row.is_approved) || testingId === row.id
            "
            @click="doTest(row)"
          >
            {{ testingId === row.id ? '...' : 'تست خزش' }}
          </button>
        </div>
      </template>
    </DataTable>
    <PaginationBar :meta="store.meta" @page="(p) => store.fetchSources(p)" />
  </div>

  <div
    v-if="modalOpen"
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4"
  >
    <div class="mt-8 w-full max-w-3xl rounded-2xl bg-white p-5 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold">
          {{ form.id ? 'ویرایش منبع' : 'منبع جدید' }}
        </h2>
        <button class="btn-muted" @click="modalOpen = false">بستن</button>
      </div>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <input v-model="form.name" class="field" placeholder="نام رسمی" />
        <input
          v-model="form.slug"
          class="field"
          placeholder="slug (اختیاری)"
          dir="ltr"
        />
        <input
          v-model="form.official_url"
          class="field md:col-span-2"
          placeholder="آدرس رسمی"
          dir="ltr"
        />
        <input
          v-model="form.domain"
          class="field"
          placeholder="دامنه (اختیاری)"
          dir="ltr"
        />
        <select v-model="form.source_type" class="field">
          <option
            v-for="t in store.options.source_types"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="form.reliability_level" class="field">
          <option
            v-for="t in store.options.reliability_levels"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="form.crawler_type" class="field">
          <option
            v-for="t in store.options.crawler_types"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <input
          v-model.number="form.priority"
          type="number"
          class="field"
          placeholder="اولویت"
        />
        <input
          v-model="form.crawl_frequency"
          class="field"
          placeholder="تناوب خزش"
        />
        <select v-model="form.quality_status" class="field">
          <option
            v-for="t in store.options.quality_statuses || []"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
        <select v-model="form.schedule_mode" class="field">
          <option value="global">زمان‌بندی سراسری</option>
          <option value="custom">زمان‌بندی سفارشی</option>
        </select>
        <label class="flex items-center gap-2 text-sm"
          ><input v-model="form.is_enabled" type="checkbox" /> فعال</label
        >
        <label class="flex items-center gap-2 text-sm"
          ><input v-model="form.is_approved" type="checkbox" /> تایید شده</label
        >
        <textarea
          v-model="form.notes"
          class="field md:col-span-2"
          rows="2"
          placeholder="یادداشت"
        />
        <textarea
          v-model="form.quality_notes"
          class="field md:col-span-2"
          rows="2"
          placeholder="یادداشت کیفیت / محدودیت منبع"
        />
      </div>

      <div
        v-if="form.schedule_mode === 'custom'"
        class="mt-4 rounded-xl border border-dashed p-3"
      >
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-sm font-bold">زمان‌های سفارشی منبع</h3>
          <button type="button" class="btn-muted" @click="addCustomTime">
            افزودن
          </button>
        </div>
        <div
          v-for="(t, idx) in form.custom_schedule_times"
          :key="idx"
          class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-4"
        >
          <input v-model="t.time" class="field" dir="ltr" placeholder="HH:MM" />
          <input v-model="t.label" class="field" placeholder="برچسب" />
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="t.enabled" type="checkbox" /> فعال</label
          >
          <button
            type="button"
            class="act text-red-600"
            @click="form.custom_schedule_times.splice(idx, 1)"
          >
            حذف
          </button>
        </div>
        <p class="text-xs text-slate-400">
          اگر خالی باشد، این منبع در هیچ اسلاتی اجرا نمی‌شود.
        </p>
      </div>
      <p v-if="formError" class="mt-2 text-sm text-red-600">
        {{ formError }}
      </p>
      <div class="mt-4 flex justify-end gap-2">
        <button class="btn-muted" @click="modalOpen = false">انصراف</button>
        <button class="btn-orange" :disabled="saving" @click="save">
          ذخیره
        </button>
      </div>

      <div v-if="form.id" class="mt-8 border-t pt-4">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="font-bold">Endpointها</h3>
          <button class="btn-muted" @click="addEndpoint">
            افزودن Endpoint
          </button>
        </div>
        <div
          v-for="ep in endpoints"
          :key="ep.id || ep._tmp"
          class="mb-3 grid grid-cols-1 gap-2 rounded-xl border p-3 md:grid-cols-2"
        >
          <input
            v-model="ep.url"
            class="field md:col-span-2"
            placeholder="URL"
            dir="ltr"
          />
          <select v-model="ep.endpoint_type" class="field">
            <option
              v-for="t in store.options.endpoint_types"
              :key="t.value"
              :value="t.value"
            >
              {{ t.value }}
            </option>
          </select>
          <select v-model="ep.parser_type" class="field">
            <option value="">بدون پارسر اختصاصی</option>
            <option
              v-for="t in store.options.parser_types"
              :key="t.value"
              :value="t.value"
            >
              {{ t.value }}
            </option>
          </select>
          <label class="flex items-center gap-2 text-sm"
            ><input v-model="ep.is_enabled" type="checkbox" /> فعال</label
          >
          <div class="flex justify-end gap-2">
            <button class="act" @click="saveEndpoint(ep)">ذخیره</button>
            <button
              v-if="ep.id"
              class="act text-red-600"
              @click="removeEndpoint(ep)"
            >
              حذف
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div
    v-if="testResult"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
      <h3 class="mb-3 font-bold">نتیجه تست خزش</h3>
      <div
        v-if="testResult.summary"
        class="mb-3 grid grid-cols-2 gap-2 text-sm"
      >
        <div>
          HTTP:
          <span dir="ltr">{{ testResult.summary.http_status ?? '—' }}</span>
        </div>
        <div>
          زمان:
          <span dir="ltr">{{ testResult.summary.execution_ms ?? '—' }} ms</span>
        </div>
        <div>کشف‌شده: {{ testResult.summary.found }}</div>
        <div>
          پذیرفته:
          {{
            (testResult.summary.created || 0) +
            (testResult.summary.updated || 0)
          }}
        </div>
        <div>رد شده: {{ testResult.summary.rejected }}</div>
        <div>خطا: {{ testResult.summary.errors }}</div>
        <div class="col-span-2">
          وضعیت کیفیت:
          {{
            testResult.quality_status_label || testResult.quality_status || '—'
          }}
        </div>
        <div class="col-span-2">
          نتیجه سلامت:
          <span dir="ltr">{{
            testResult.health?.outcome || testResult.summary.outcome || '—'
          }}</span>
        </div>
      </div>
      <pre class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs" dir="ltr">{{
        JSON.stringify(testResult, null, 2)
      }}</pre>
      <p class="mt-2 text-xs text-slate-500">
        آگهی‌های جدید در وضعیت pending می‌مانند و منتشر نمی‌شوند.
      </p>
      <button class="btn-orange mt-4" @click="testResult = null">باشه</button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import { useAggregationStore } from '../stores/aggregation'
import { useJobSourcesStore } from '../stores/jobSources'
import { formatDate } from '../../utils/format'

const store = useJobSourcesStore()
const aggregation = useAggregationStore()
const quality = ref(null)
const modalOpen = ref(false)
const saving = ref(false)
const formError = ref('')
const testingId = ref(null)
const testResult = ref(null)
const endpoints = ref([])

const columns = [
  { key: 'name', label: 'منبع' },
  { key: 'source_type_label', label: 'نوع' },
  { key: 'reliability_label', label: 'اعتماد' },
  { key: 'crawler_type_label', label: 'خزنده' },
  { key: 'schedule_mode', label: 'زمان‌بندی' },
  { key: 'flags', label: 'وضعیت' },
  { key: 'last_crawled_at', label: 'آخرین خزش' },
]

const form = reactive({
  id: null,
  name: '',
  slug: '',
  official_url: '',
  domain: '',
  source_type: 'government',
  reliability_level: 'official',
  crawler_type: 'html',
  priority: 50,
  crawl_frequency: 'daily',
  schedule_mode: 'global',
  custom_schedule_times: [],
  quality_status: 'active',
  quality_notes: '',
  is_enabled: false,
  is_approved: false,
  notes: '',
})

function resetForm() {
  Object.assign(form, {
    id: null,
    name: '',
    slug: '',
    official_url: '',
    domain: '',
    source_type: 'government',
    reliability_level: 'official',
    crawler_type: 'html',
    priority: 50,
    crawl_frequency: 'daily',
    schedule_mode: 'global',
    custom_schedule_times: [],
    quality_status: 'active',
    quality_notes: '',
    is_enabled: false,
    is_approved: false,
    notes: '',
  })
  endpoints.value = []
  formError.value = ''
}

function openCreate() {
  resetForm()
  modalOpen.value = true
}

async function openEdit(row) {
  resetForm()
  const full = await store.fetchSource(row.id)
  Object.assign(form, {
    id: full.id,
    name: full.name,
    slug: full.slug,
    official_url: full.official_url,
    domain: full.domain,
    source_type: full.source_type,
    reliability_level: full.reliability_level,
    crawler_type: full.crawler_type,
    priority: full.priority,
    crawl_frequency: full.crawl_frequency,
    schedule_mode: full.schedule_mode || 'global',
    custom_schedule_times: (full.custom_schedule_times || []).map((t) => ({
      ...t,
    })),
    quality_status: full.quality_status || 'active',
    quality_notes: full.quality_notes || '',
    is_enabled: full.is_enabled,
    is_approved: full.is_approved,
    notes: full.notes || '',
  })
  endpoints.value = (full.endpoints || []).map((e) => ({ ...e }))
  modalOpen.value = true
}

function addCustomTime() {
  form.custom_schedule_times.push({ time: '', enabled: true, label: '' })
}

async function save() {
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...form }
    delete payload.id
    const saved = await store.saveSource(payload, form.id)
    form.id = saved.id
    await store.fetchSources(store.filters.page || 1)
    if (form.id) {
      const full = await store.fetchSource(form.id)
      endpoints.value = (full.endpoints || []).map((e) => ({ ...e }))
    }
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در ذخیره منبع'
  } finally {
    saving.value = false
  }
}

function addEndpoint() {
  endpoints.value.push({
    _tmp: Date.now(),
    url: '',
    endpoint_type: 'html',
    http_method: 'GET',
    parser_type: '',
    is_enabled: true,
    sort_order: endpoints.value.length,
  })
}

async function saveEndpoint(ep) {
  formError.value = ''
  try {
    await store.saveEndpoint(
      form.id,
      {
        url: ep.url,
        endpoint_type: ep.endpoint_type,
        http_method: 'GET',
        parser_type: ep.parser_type || null,
        is_enabled: ep.is_enabled,
        sort_order: ep.sort_order || 0,
      },
      ep.id || null
    )
    const full = await store.fetchSource(form.id)
    endpoints.value = (full.endpoints || []).map((e) => ({ ...e }))
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در ذخیره endpoint'
  }
}

async function removeEndpoint(ep) {
  await store.destroyEndpoint(form.id, ep.id)
  endpoints.value = endpoints.value.filter((x) => x.id !== ep.id)
}

async function doApprove(row) {
  await store.approve(row.id)
  await store.fetchSources(store.filters.page || 1)
}
async function doUnapprove(row) {
  await store.unapprove(row.id)
  await store.fetchSources(store.filters.page || 1)
}
async function doEnable(row) {
  await store.enable(row.id)
  await store.fetchSources(store.filters.page || 1)
}
async function doDisable(row) {
  await store.disable(row.id)
  await store.fetchSources(store.filters.page || 1)
}

async function doResetHealth(row) {
  try {
    await store.resetHealth(row.id)
    await store.fetchSources(store.filters.page || 1)
    quality.value = await aggregation.fetchStats()
  } catch (e) {
    formError.value = e.response?.data?.message || 'خطا در بازنشانی سلامت'
  }
}

async function doTest(row) {
  testingId.value = row.id
  try {
    const res = await store.testCrawl(row.id)
    testResult.value = res.data
    await store.fetchSources(store.filters.page || 1)
    quality.value = await aggregation.fetchStats()
  } catch (e) {
    testResult.value = { error: e.response?.data?.message || e.message }
  } finally {
    testingId.value = null
  }
}

function apply() {
  store.fetchSources(1)
}
function clear() {
  store.filters.search = ''
  store.filters.source_type = ''
  store.filters.quality_status = ''
  store.filters.is_enabled = ''
  store.filters.is_approved = ''
  apply()
}

function qualityBadgeClass(status) {
  switch (status) {
    case 'active':
      return 'bg-emerald-100 text-emerald-800'
    case 'limited':
      return 'bg-amber-100 text-amber-800'
    case 'temporarily_unavailable':
      return 'bg-red-100 text-red-700'
    case 'manual_only':
      return 'bg-slate-200 text-slate-700'
    default:
      return 'bg-slate-100 text-slate-600'
  }
}

onMounted(async () => {
  await store.fetchOptions()
  await store.fetchSources(1)
  quality.value = await aggregation.fetchStats()
})
</script>
