<template>
  <div class="space-y-6">
    <div
      v-if="!walletEnabled"
      class="rounded-2xl border border-surface-line bg-surface p-6 text-center text-sm text-ink-muted dark:border-slate-700 dark:bg-slate-800"
    >
      کیف پول موقتاً غیرفعال است.
    </div>

    <template v-else>
      <div v-if="loading" class="space-y-4">
        <Skeleton class="h-40 rounded-2xl" />
        <Skeleton class="h-64 rounded-2xl" />
      </div>

      <template v-else>
        <Card
          class="overflow-hidden bg-gradient-to-l from-brand to-brand-dark p-8 text-white shadow-lg"
        >
          <p class="text-sm text-white/80">موجودی کیف پول</p>
          <p class="mt-2 text-4xl font-black tracking-tight">
            {{ formatPrice(balance) }}
          </p>
          <div class="mt-6 flex flex-wrap gap-3">
            <button
              type="button"
              class="rounded-xl bg-white px-6 py-2.5 text-sm font-bold text-brand transition hover:bg-brand-soft"
              @click="showCharge = true"
            >
              شارژ کیف پول
            </button>
            <RouterLink
              to="/support"
              class="rounded-xl bg-white/15 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-white/25"
            >
              درخواست پشتیبانی
            </RouterLink>
          </div>
        </Card>

        <Card v-if="showCharge" class="space-y-3 p-5">
          <h2 class="text-lg font-bold dark:text-white">شارژ کیف پول</h2>
          <label class="block text-sm font-medium dark:text-slate-300"
            >مبلغ (ریال)</label
          >
          <input
            v-model.number="amount"
            type="number"
            inputmode="numeric"
            class="input-field touch-target"
            min="10000"
            step="1000"
          />
          <label class="block text-sm font-medium dark:text-slate-300"
            >درگاه پرداخت</label
          >
          <select v-model="gateway" class="input-field">
            <option v-for="g in gateways" :key="g.name" :value="g.name">
              {{ g.display_name }}
            </option>
          </select>
          <div class="flex gap-2">
            <button
              type="button"
              class="btn-primary flex-1"
              :disabled="charging || amount < 10000 || !gateway"
              @click="charge"
            >
              {{ charging ? '...' : 'پرداخت آنلاین' }}
            </button>
            <button
              type="button"
              class="rounded-lg border border-surface-line px-4 text-sm dark:border-slate-600"
              @click="showCharge = false"
            >
              انصراف
            </button>
          </div>
        </Card>

        <Card class="p-5">
          <h2 class="mb-4 text-lg font-bold dark:text-white">تراکنش‌ها</h2>
          <EmptyState
            v-if="!transactions.length"
            title="تراکنشی نیست"
            description="پس از شارژ، تاریخچه اینجا نمایش داده می‌شود."
          />
          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[480px] text-sm">
              <thead>
                <tr
                  class="border-b border-surface-line text-right text-ink-muted dark:border-slate-700"
                >
                  <th class="pb-2 font-medium">شرح</th>
                  <th class="pb-2 font-medium">وضعیت</th>
                  <th class="pb-2 font-medium">مبلغ</th>
                  <th class="pb-2 font-medium"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="tx in transactions"
                  :key="tx.id"
                  class="border-b border-surface-line/70 dark:border-slate-700/70"
                >
                  <td class="py-3">
                    <p class="font-medium dark:text-white">
                      {{ tx.description || tx.type }}
                    </p>
                    <p
                      v-if="tx.invoice_number"
                      class="text-xs text-ink-muted"
                      dir="ltr"
                    >
                      {{ tx.invoice_number }}
                    </p>
                  </td>
                  <td class="py-3">
                    <Badge :variant="statusVariant(tx.status)">{{
                      statusLabel(tx.status)
                    }}</Badge>
                  </td>
                  <td
                    class="py-3 font-bold"
                    :class="
                      tx.type === 'deposit' ? 'text-emerald-600' : 'text-brand'
                    "
                  >
                    {{ formatPrice(tx.amount) }}
                  </td>
                  <td class="py-3 text-left">
                    <button
                      v-if="tx.status === 'success' && tx.type === 'purchase'"
                      type="button"
                      class="text-xs font-bold text-brand underline"
                      @click="downloadInvoice(tx)"
                    >
                      فاکتور
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '../../api/client'
import EmptyState from '../../components/EmptyState.vue'
import Badge from '../../components/ui/Badge.vue'
import Card from '../../components/ui/Card.vue'
import Skeleton from '../../components/ui/Skeleton.vue'
import { useFeature } from '../../composables/useFeature'
import { useToast } from '../../composables/useToast'

const { enabled: walletEnabled } = useFeature('wallet')
const toast = useToast()

const loading = ref(true)
const balance = ref(0)
const transactions = ref<any[]>([])
const amount = ref(100000)
const charging = ref(false)
const showCharge = ref(false)
const gateways = ref([{ name: 'zarinpal', display_name: 'زرین‌پال' }])
const gateway = ref('zarinpal')

function formatPrice(v: number | string) {
  return new Intl.NumberFormat('fa-IR').format(Number(v || 0)) + ' ریال'
}

function statusLabel(s: string) {
  return { success: 'موفق', pending: 'در انتظار', failed: 'ناموفق' }[s] || s
}

function statusVariant(s: string) {
  if (s === 'success') return 'success'
  if (s === 'pending') return 'warning'
  return 'danger'
}

async function loadGateways() {
  try {
    const { data } = await api.get('/payment-gateways')
    const list = data.data || []
    if (list.length) {
      gateways.value = list
      const def = list.find((g: any) => g.is_default) || list[0]
      gateway.value = def.name
    }
  } catch {
    // keep default
  }
}

async function load() {
  const { data } = await api.get('/wallet')
  balance.value = data.data?.balance || 0
  transactions.value = data.data?.transactions || []
}

onMounted(async () => {
  if (!walletEnabled.value) {
    loading.value = false
    return
  }
  try {
    await Promise.all([load(), loadGateways()])
  } finally {
    loading.value = false
  }
})

async function charge() {
  charging.value = true
  try {
    const { data } = await api.post('/wallet/charge', {
      amount: amount.value,
      gateway: gateway.value,
    })
    if (data.data?.payment_url) {
      window.location.href = data.data.payment_url
    }
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'خطا در ایجاد پرداخت.')
  } finally {
    charging.value = false
  }
}

async function downloadInvoice(tx: any) {
  try {
    const { data } = await api.get(`/transactions/${tx.id}/invoice`, {
      responseType: 'blob',
    })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = `${tx.invoice_number || 'invoice-' + tx.id}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.error('دانلود فاکتور ناموفق بود.')
  }
}
</script>
