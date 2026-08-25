<template>
  <div class="px-4 py-4">
    <h1 class="section-title mb-3">پلن‌های اشتراک</h1>
    <div
      v-if="!subscriptionEnabled"
      class="card-soft p-6 text-center text-sm text-ink-muted"
    >
      سیستم اشتراک موقتاً غیرفعال است.
    </div>
    <LoadingSpinner v-else-if="loading" />
    <div v-else class="space-y-3">
      <CouponBox
        v-if="selectedPlan"
        class="mb-2"
        :amount="Number(selectedPlan.price)"
        type="subscription"
        @update:coupon="onCoupon"
      />
      <div v-if="gateways.length" class="card-soft p-3">
        <label class="mb-1.5 block text-xs font-medium"
          >درگاه پرداخت آنلاین</label
        >
        <select v-model="gateway" class="input-field h-9 text-sm">
          <option v-for="g in gateways" :key="g.name" :value="g.name">
            {{ g.display_name }}
          </option>
        </select>
      </div>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="plan in plans"
          :key="plan.id"
          class="card-soft cursor-pointer p-3 transition"
          :class="selectedId === plan.id ? 'ring-2 ring-brand' : ''"
          @click="selectedId = plan.id"
        >
          <div class="mb-1 flex items-center justify-between gap-2">
            <h2 class="truncate text-sm font-bold">{{ plan.name }}</h2>
            <span class="price shrink-0 text-xs">{{ displayPrice(plan) }}</span>
          </div>
          <p class="mb-2 text-[11px] text-ink-muted">
            {{ plan.duration_days }} روز
          </p>
          <ul
            v-if="(plan.features || []).length"
            class="mb-2 max-h-16 space-y-0.5 overflow-hidden text-[11px] text-ink-soft"
          >
            <li v-for="(f, i) in (plan.features || []).slice(0, 4)" :key="i">
              • {{ f }}
            </li>
          </ul>
          <div class="flex gap-1.5">
            <button
              class="btn-primary !h-8 flex-1 !text-[11px]"
              @click.stop="subscribe(plan.id, 'wallet')"
            >
              کیف پول
            </button>
            <button
              class="btn-ghost !h-8 flex-1 border border-surface-line !text-[11px]"
              @click.stop="subscribe(plan.id, gateway)"
            >
              آنلاین
            </button>
          </div>
        </div>
      </div>
    </div>
    <p v-if="message" class="mt-3 text-center text-sm text-brand">
      {{ message }}
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../../api/client'
import CouponBox from '../../components/CouponBox.vue'
import LoadingSpinner from '../../components/LoadingSpinner.vue'
import { useFeature } from '../../composables/useFeature'

const { enabled: subscriptionEnabled } = useFeature('subscription')

const plans = ref([])
const loading = ref(true)
const message = ref('')
const selectedId = ref(null)
const coupon = ref(null)
const gateways = ref([{ name: 'zarinpal', display_name: 'زرین‌پال' }])
const gateway = ref('zarinpal')

const selectedPlan = computed(
  () =>
    plans.value.find((p) => p.id === selectedId.value) || plans.value[0] || null
)

function formatPrice(v) {
  return new Intl.NumberFormat('fa-IR').format(Number(v)) + ' ریال'
}

function displayPrice(plan) {
  if (coupon.value && selectedId.value === plan.id) {
    return formatPrice(coupon.value.final_amount)
  }
  return formatPrice(plan.price)
}

function onCoupon(c) {
  coupon.value = c
}

onMounted(async () => {
  if (!subscriptionEnabled.value) {
    loading.value = false
    return
  }
  try {
    const [{ data }, gw] = await Promise.all([
      api.get('/subscription-plans'),
      api.get('/payment-gateways').catch(() => ({ data: { data: [] } })),
    ])
    plans.value = data.data || []
    if (plans.value.length) selectedId.value = plans.value[0].id
    const list = gw.data?.data || []
    if (list.length) {
      gateways.value = list
      gateway.value = (list.find((g) => g.is_default) || list[0]).name
    }
  } finally {
    loading.value = false
  }
})

async function subscribe(planId, payment_method) {
  selectedId.value = planId
  message.value = ''
  try {
    const payload = { plan_id: planId, payment_method }
    if (coupon.value?.code) payload.coupon_code = coupon.value.code
    const { data } = await api.post('/subscription/subscribe', payload)
    if (data.data?.payment_url) {
      window.location.href = data.data.payment_url
      return
    }
    message.value = data.message || 'اشتراک فعال شد.'
  } catch (e) {
    message.value = e.response?.data?.message || 'خرید اشتراک ناموفق بود.'
  }
}
</script>
