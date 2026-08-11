<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div
      class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
    >
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-bold">جزئیات تراکنش</h3>
        <button type="button" @click="$emit('close')">✕</button>
      </div>
      <div v-if="tx" class="space-y-1 text-sm">
        <div
          v-for="r in rows"
          :key="r.label"
          class="flex justify-between gap-4 border-b border-slate-100 py-2"
        >
          <span class="text-slate-500">{{ r.label }}</span>
          <span class="text-left font-medium text-slate-800" dir="auto">{{
            r.value
          }}</span>
        </div>
      </div>
      <div class="mt-5 flex flex-wrap justify-end gap-2">
        <button
          v-if="tx?.status === 'success'"
          type="button"
          class="btn-orange"
          :disabled="regenLoading"
          @click="regenerate"
        >
          بازتولید فاکتور
        </button>
        <button type="button" class="btn-muted" @click="$emit('close')">
          بستن
        </button>
      </div>
      <p v-if="regenMsg" class="mt-2 text-center text-xs text-slate-600">
        {{ regenMsg }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import adminApi from '../../api/client'
import { formatDateTime, apiErrorMessage } from '../../../utils/format'

const props = defineProps({
  open: Boolean,
  tx: { type: Object, default: null },
})
defineEmits(['close'])

const regenLoading = ref(false)
const regenMsg = ref('')

watch(
  () => props.open,
  () => {
    regenMsg.value = ''
  }
)

const rows = computed(() => {
  const tx = props.tx
  if (!tx) return []
  return [
    { label: 'کاربر', value: tx.user?.name || tx.user_name || '—' },
    { label: 'موبایل', value: tx.user?.mobile || tx.user_mobile || '—' },
    { label: 'مبلغ', value: `${fa(tx.amount)} ریال` },
    {
      label: 'تخفیف',
      value: tx.discount_amount ? `${fa(tx.discount_amount)} ریال` : '—',
    },
    { label: 'شماره فاکتور', value: tx.invoice_number || '—' },
    { label: 'نوع', value: typeLabel(tx.type) },
    { label: 'درگاه', value: gatewayLabel(tx.gateway) },
    { label: 'وضعیت', value: statusLabel(tx.status) },
    { label: 'شماره پیگیری', value: tx.reference_id || '—' },
    { label: 'توضیحات', value: tx.description || '—' },
    { label: 'تاریخ', value: formatDateTime(tx.created_at) },
  ]
})

async function regenerate() {
  if (!props.tx?.id) return
  regenLoading.value = true
  regenMsg.value = ''
  try {
    const { data } = await adminApi.post(
      `/admin/transactions/${props.tx.id}/regenerate-invoice`
    )
    if (props.tx) {
      // Parent owns the selected transaction object; update in place for immediate UI.
      // eslint-disable-next-line vue/no-mutating-props -- intentional shared object update
      props.tx.invoice_number =
        data.data?.invoice_number || props.tx.invoice_number
      // eslint-disable-next-line vue/no-mutating-props -- intentional shared object update
      props.tx.invoice_pdf = data.data?.invoice_pdf || props.tx.invoice_pdf
    }
    regenMsg.value = data.message || 'فاکتور بازتولید شد.'
  } catch (e) {
    regenMsg.value = apiErrorMessage(e)
  } finally {
    regenLoading.value = false
  }
}

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
function typeLabel(t) {
  return (
    {
      deposit: 'واریز',
      purchase: 'خرید',
      refund: 'بازگشت وجه',
      withdrawal: 'برداشت',
    }[t] || t
  )
}
function gatewayLabel(g) {
  return { zarinpal: 'زرین‌پال', wallet: 'کیف پول' }[g] || g
}
function statusLabel(s) {
  return { success: 'موفق', pending: 'در انتظار', failed: 'ناموفق' }[s] || s
}
</script>

<style scoped>
.btn-muted {
  @apply rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50;
}
</style>
