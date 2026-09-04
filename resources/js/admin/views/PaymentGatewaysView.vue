<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">درگاه‌های پرداخت</h1>
        <p class="mt-1 text-sm text-slate-500">
          فعال‌سازی، اعتبارنامه‌ها و درگاه پیش‌فرض — بدون قطع زرین‌پال موجود.
        </p>
      </div>
      <button class="btn-muted" :disabled="loading" @click="load">
        بروزرسانی
      </button>
    </div>

    <p
      v-if="error"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ error }}
    </p>
    <p
      v-if="ok"
      class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
    >
      {{ ok }}
    </p>

    <div
      v-if="loading && !gateways.length"
      class="rounded-xl bg-white p-6 text-sm text-slate-500 shadow-sm"
    >
      در حال بارگذاری…
    </div>

    <div
      v-for="gw in gateways"
      :key="gw.name"
      class="rounded-xl bg-white p-5 shadow-sm"
    >
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-gray-800">
            {{ gw.display_name }}
            <span
              class="ms-2 font-mono text-xs font-normal text-slate-400"
              dir="ltr"
              >{{ gw.name }}</span
            >
          </h2>
          <div class="mt-1 flex flex-wrap gap-2 text-xs">
            <span
              class="rounded-full px-2 py-0.5 font-bold"
              :class="
                gw.is_active
                  ? 'bg-emerald-100 text-emerald-800'
                  : 'bg-slate-100 text-slate-500'
              "
            >
              {{ gw.is_active ? 'فعال' : 'غیرفعال' }}
            </span>
            <span
              v-if="gw.is_default"
              class="rounded-full bg-brand/10 px-2 py-0.5 font-bold text-brand"
            >
              پیش‌فرض
            </span>
            <span
              class="rounded-full px-2 py-0.5 font-bold"
              :class="
                gw.configured
                  ? 'bg-sky-100 text-sky-800'
                  : 'bg-amber-100 text-amber-800'
              "
            >
              {{ gw.configured ? 'پیکربندی کامل' : 'ناقص' }}
            </span>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            class="btn-muted"
            :disabled="busy === gw.name"
            @click="testGw(gw.name)"
          >
            تست اتصال
          </button>
          <button
            v-if="!gw.is_default"
            class="btn-muted"
            :disabled="busy === gw.name || !gw.is_active || !gw.configured"
            @click="setDefault(gw.name)"
          >
            پیش‌فرض کردن
          </button>
          <button
            class="btn-orange"
            :disabled="busy === gw.name"
            @click="save(gw)"
          >
            ذخیره
          </button>
        </div>
      </div>

      <div class="mb-4 flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2 text-sm">
          <input v-model="drafts[gw.name].is_active" type="checkbox" />
          فعال باشد
        </label>
        <div class="flex items-center gap-2 text-sm">
          <span class="text-slate-500">ترتیب</span>
          <input
            v-model.number="drafts[gw.name].sort_order"
            type="number"
            min="0"
            max="999"
            class="field w-20"
          />
        </div>
        <div class="min-w-[12rem] flex-1">
          <label class="mb-1 block text-xs text-slate-500">نام نمایشی</label>
          <input v-model="drafts[gw.name].display_name" class="field" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div v-for="field in gw.fields" :key="field.key">
          <label class="mb-1 block text-xs text-slate-500">{{
            field.label
          }}</label>
          <textarea
            v-if="field.key === 'private_key'"
            v-model="drafts[gw.name].settings[field.key]"
            class="field min-h-[6rem] font-mono text-xs"
            dir="ltr"
            :placeholder="field.secret ? '••••' : ''"
          />
          <input
            v-else
            v-model="drafts[gw.name].settings[field.key]"
            class="field"
            dir="ltr"
            :type="field.secret ? 'password' : 'text'"
            :placeholder="field.secret ? '••••' : ''"
            autocomplete="off"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import adminApi from '../api/client'

const loading = ref(false)
const busy = ref('')
const error = ref('')
const ok = ref('')
const gateways = ref([])
const drafts = reactive({})

function fieldValue(gw, field) {
  const key = field.key
  if (field.column === 'merchant_id' && gw.merchant_id) return gw.merchant_id
  if (field.column === 'api_key' && gw.api_key) return gw.api_key
  return gw.settings?.[key] ?? ''
}

function syncDrafts(list) {
  for (const gw of list) {
    const settings = {}
    for (const field of gw.fields || []) {
      settings[field.key] = fieldValue(gw, field)
    }
    drafts[gw.name] = {
      display_name: gw.display_name || '',
      is_active: !!gw.is_active,
      sort_order: gw.sort_order ?? 0,
      settings,
    }
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await adminApi.get('/admin/payment-gateways')
    const list = data?.data?.gateways || data?.gateways || []
    gateways.value = list
    syncDrafts(list)
  } catch (e) {
    error.value = e?.response?.data?.message || 'بارگذاری درگاه‌ها ناموفق بود.'
  } finally {
    loading.value = false
  }
}

async function save(gw) {
  busy.value = gw.name
  error.value = ''
  ok.value = ''
  try {
    const draft = drafts[gw.name]
    const { data } = await adminApi.put(`/admin/payment-gateways/${gw.name}`, {
      display_name: draft.display_name,
      is_active: draft.is_active,
      sort_order: draft.sort_order,
      settings: draft.settings,
    })
    const list = data?.data?.gateways || data?.gateways || []
    gateways.value = list
    syncDrafts(list)
    ok.value = `درگاه ${gw.display_name} ذخیره شد.`
  } catch (e) {
    error.value = e?.response?.data?.message || 'ذخیره ناموفق بود.'
  } finally {
    busy.value = ''
  }
}

async function setDefault(name) {
  busy.value = name
  error.value = ''
  ok.value = ''
  try {
    const { data } = await adminApi.post('/admin/payment-gateways/default', {
      name,
    })
    const list = data?.data?.gateways || data?.gateways || []
    gateways.value = list
    syncDrafts(list)
    ok.value = 'درگاه پیش‌فرض به‌روز شد.'
  } catch (e) {
    error.value = e?.response?.data?.message || 'تنظیم پیش‌فرض ناموفق بود.'
  } finally {
    busy.value = ''
  }
}

async function testGw(name) {
  busy.value = name
  error.value = ''
  ok.value = ''
  try {
    const { data } = await adminApi.post(`/admin/payment-gateways/${name}/test`)
    const result = data?.data || data
    if (result?.ok) {
      ok.value = result.message || 'تست موفق بود.'
    } else {
      error.value = result?.message || 'تست ناموفق بود.'
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'تست اتصال ناموفق بود.'
  } finally {
    busy.value = ''
  }
}

onMounted(load)
</script>
