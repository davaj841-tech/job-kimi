<template>
  <div class="min-h-screen bg-surface-page dark:bg-slate-950">
    <LoadingSpinner v-if="loading" />
    <div v-else-if="pdf" class="mx-auto max-w-7xl px-4 py-6 sm:py-8">
      <div class="grid gap-5 lg:grid-cols-[11rem_1fr] lg:items-start">
        <div class="space-y-4">
          <div
            class="mx-auto aspect-[3/4] w-36 overflow-hidden rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 shadow-md dark:from-slate-800 dark:to-slate-700 sm:w-44 lg:mx-0 lg:w-full"
          >
            <img
              v-if="cover"
              :src="cover"
              :alt="pdf.title"
              class="h-full w-full object-cover"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-3xl text-desk-muted"
            >
              📄
            </div>
          </div>

          <div class="space-y-2.5">
            <template v-if="pdf.is_purchased || pdf.is_free">
              <button
                type="button"
                class="btn-primary flex w-full items-center justify-center gap-2"
                @click="openViewer"
              >
                <EyeIcon class="h-5 w-5" />
                مشاهده PDF
              </button>
              <button
                v-if="pdf.is_purchased || Number(pdf.price) === 0"
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-surface-line bg-surface py-2.5 text-sm font-medium"
                @click="downloadPdf"
              >
                <ArrowDownTrayIcon class="h-5 w-5" />
                دانلود فایل
              </button>
              <p
                v-if="pdf.is_free && !pdf.is_purchased"
                class="text-center text-xs text-desk-muted"
              >
                برای فعال‌سازی رایگان روی مشاهده بزنید
              </p>
            </template>

            <template v-else>
              <div
                class="rounded-xl border border-surface-line bg-surface p-4 text-center"
              >
                <p class="mb-1 text-xs text-desk-muted">قیمت (خرید تکی)</p>
                <p class="text-2xl font-black text-brand">
                  {{ formatPrice(displayPrice) }}
                </p>
                <p
                  v-if="coupon"
                  class="mt-1 text-xs text-desk-muted line-through"
                >
                  {{ formatPrice(pdf.price) }}
                </p>
              </div>

              <CouponBox
                :amount="Number(pdf.price)"
                type="pdf"
                @update:coupon="coupon = $event"
              />

              <div
                class="flex items-center justify-between rounded-xl bg-surface-page px-3 py-2.5 text-sm"
              >
                <span class="text-desk-muted">موجودی کیف پول</span>
                <span
                  class="font-bold"
                  :class="
                    hasEnoughBalance ? 'text-emerald-600' : 'text-red-500'
                  "
                  >{{ formatPrice(walletBalance) }}</span
                >
              </div>

              <button
                v-if="hasEnoughBalance"
                type="button"
                class="btn-primary flex w-full items-center justify-center gap-2"
                :disabled="purchasing"
                @click="buy('wallet')"
              >
                <WalletIcon class="h-5 w-5" />
                {{ purchasing ? 'در حال پردازش…' : 'خرید با کیف پول' }}
              </button>

              <div v-if="gateways.length">
                <label class="mb-1.5 block text-xs text-desk-muted"
                  >درگاه آنلاین</label
                >
                <select v-model="gateway" class="input-field mb-2">
                  <option v-for="g in gateways" :key="g.name" :value="g.name">
                    {{ g.display_name }}
                  </option>
                </select>
              </div>

              <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-brand py-2.5 text-sm font-bold text-brand hover:bg-brand-soft disabled:opacity-50"
                :disabled="purchasing"
                @click="buy(gateway)"
              >
                <CreditCardIcon class="h-5 w-5" />
                پرداخت آنلاین
              </button>

              <p
                v-if="!hasEnoughBalance"
                class="text-center text-xs text-red-500"
              >
                موجودی کافی نیست — شارژ کیف پول یا پرداخت آنلاین
              </p>
            </template>
          </div>

          <div
            class="space-y-2 rounded-xl border border-surface-line bg-surface p-3 text-sm"
          >
            <div class="flex justify-between">
              <span class="text-desk-muted">فرمت</span>
              <span class="font-medium">PDF</span>
            </div>
            <div class="flex justify-between">
              <span class="text-desk-muted">دسته</span>
              <span class="font-medium">{{ pdf.category || '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-desk-muted">فروش</span>
              <span class="font-medium">{{
                toFaDigits(pdf.purchases_count ?? 0)
              }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-desk-muted">دانلود</span>
              <span class="font-medium">{{
                toFaDigits(pdf.download_count ?? 0)
              }}</span>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <span
                v-if="pdf.category"
                class="rounded-lg bg-brand-soft px-2.5 py-1 text-xs font-medium text-brand"
                >{{ pdf.category }}</span
              >
              <span
                v-if="pdf.is_new"
                class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700"
                >جدید</span
              >
              <span
                class="rounded-lg bg-surface-page px-2.5 py-1 text-xs text-desk-muted"
                >خرید تکی · بدون اشتراک</span
              >
            </div>
            <h1 class="text-xl font-black text-desk-text sm:text-2xl">
              {{ pdf.title }}
            </h1>
          </div>

          <div
            class="rounded-xl border border-surface-line bg-surface p-4 sm:p-5"
          >
            <h2 class="mb-3 text-lg font-bold">توضیحات</h2>
            <div
              class="prose prose-sm dark:prose-invert max-w-none leading-relaxed text-desk-muted"
              v-html="descriptionHtml"
            />
          </div>

          <p v-if="message" class="text-center text-sm text-brand">
            {{ message }}
          </p>
        </div>
      </div>
    </div>

    <PdfViewerModal
      v-model="showViewer"
      :source="viewerSource"
      :title="pdf?.title || 'PDF'"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowDownTrayIcon,
  CreditCardIcon,
  EyeIcon,
  WalletIcon,
} from '@heroicons/vue/24/outline'
import api from '../../api/client'
import CouponBox from '../../components/CouponBox.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import PdfViewerModal from '../../components/pdf/PdfViewerModal.vue'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../composables/useToast'
import {
  apiErrorMessage,
  formatPrice,
  toFaDigits,
  unwrapItem,
} from '../../utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const pdf = ref(null)
const loading = ref(true)
const purchasing = ref(false)
const message = ref('')
const coupon = ref(null)
const gateways = ref([{ name: 'zarinpal', display_name: 'زرین‌پال' }])
const gateway = ref('zarinpal')
const walletBalance = ref(0)
const showViewer = ref(false)
const viewerSource = ref(null)
let blobUrl = null

const cover = computed(() => pdf.value?.cover || pdf.value?.thumbnail_url)
const displayPrice = computed(() =>
  coupon.value?.final_amount != null
    ? coupon.value.final_amount
    : pdf.value?.price
)
const hasEnoughBalance = computed(
  () => walletBalance.value >= Number(displayPrice.value || 0)
)
const descriptionHtml = computed(() => {
  const d = pdf.value?.description || ''
  if (!d) return '<p>توضیحی ثبت نشده است.</p>'
  if (d.includes('<')) return d
  return `<p>${d.replace(/\n/g, '<br/>')}</p>`
})

async function loadWallet() {
  try {
    const { data } = await api.get('/wallet')
    const payload = unwrapItem(data)
    walletBalance.value = Number(
      payload.balance ?? auth.user?.wallet_balance ?? 0
    )
  } catch {
    walletBalance.value = Number(auth.user?.wallet_balance ?? 0)
  }
}

async function load() {
  const { data } = await api.get(`/pdf-products/${route.params.id}`)
  pdf.value = unwrapItem(data)
}

onMounted(async () => {
  try {
    if (!auth.user) await auth.fetchMe().catch(() => null)
    await load()
    await loadWallet()
    try {
      const { data } = await api.get('/payment-gateways')
      const list = data.data || []
      if (list.length) {
        gateways.value = list
        gateway.value = (list.find((g) => g.is_default) || list[0]).name
      }
    } catch {
      /* keep default zarinpal */
    }
    if (route.query.payment === 'success') {
      toast.success('پرداخت موفق — PDF به کتابخانه شما اضافه شد.')
      await load()
    }
  } finally {
    loading.value = false
  }
})

async function ensureFreeAccess() {
  if (pdf.value?.is_purchased) return true
  if (Number(pdf.value?.price) === 0) {
    if (!auth.user) {
      router.push({ name: 'login', query: { redirect: route.fullPath } })
      return false
    }
    await buy('wallet', true)
    return !!pdf.value?.is_purchased
  }
  return false
}

async function buy(method, silent = false) {
  if (!auth.user) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  purchasing.value = true
  message.value = ''
  try {
    const payload = { payment_method: method === 'wallet' ? 'wallet' : method }
    if (method !== 'wallet') {
      payload.payment_method = method
      payload.gateway = method
    }
    if (coupon.value?.code) payload.coupon_code = coupon.value.code
    const { data } = await api.post(
      `/pdf-products/${route.params.id}/purchase`,
      payload
    )
    const body = unwrapItem(data)
    if (body?.payment_url || data.data?.payment_url) {
      window.location.href = body.payment_url || data.data.payment_url
      return
    }
    if (!silent) toast.success(data.message || 'خرید با موفقیت انجام شد.')
    await load()
    await loadWallet()
    if (auth.user) await auth.fetchMe().catch(() => null)
  } catch (e) {
    message.value = apiErrorMessage(e, 'خرید ناموفق بود.')
    if (!silent) toast.error(message.value)
  } finally {
    purchasing.value = false
  }
}

async function fetchPdfBlob() {
  const { data } = await api.get(`/pdf-products/${route.params.id}/download`, {
    responseType: 'blob',
  })
  if (blobUrl) URL.revokeObjectURL(blobUrl)
  blobUrl = URL.createObjectURL(data)
  return blobUrl
}

async function openViewer() {
  if (!(pdf.value?.is_purchased || Number(pdf.value?.price) === 0)) return
  if (!pdf.value.is_purchased) {
    const ok = await ensureFreeAccess()
    if (!ok) return
  }
  try {
    viewerSource.value = await fetchPdfBlob()
    showViewer.value = true
  } catch (e) {
    toast.error(apiErrorMessage(e, 'مشاهده ممکن نشد.'))
  }
}

async function downloadPdf() {
  if (!pdf.value?.is_purchased && Number(pdf.value?.price) !== 0) {
    toast.error('ابتدا این PDF را خریداری کنید.')
    return
  }
  if (!pdf.value.is_purchased) {
    const ok = await ensureFreeAccess()
    if (!ok) return
  }
  try {
    const url = await fetchPdfBlob()
    const a = document.createElement('a')
    a.href = url
    a.download = `${pdf.value.title || 'file'}.pdf`
    a.click()
  } catch (e) {
    toast.error(apiErrorMessage(e, 'دانلود ممکن نشد.'))
  }
}
</script>
