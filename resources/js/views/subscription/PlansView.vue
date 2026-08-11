<template>
  <div class="px-4 py-4">
    <h1 class="section-title mb-4">پلن‌های اشتراک</h1>
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
        class="mb-3"
        :amount="Number(selectedPlan.price)"
        type="subscription"
        @update:coupon="onCoupon"
      />
      <div v-if="gateways.length" class="card-soft p-3">
        <label class="mb-2 block text-sm font-medium"
          >درگاه پرداخت آنلاین</label
        >
        <select v-model="gateway" class="input-field">
          <option v-for="g in gateways" :key="g.name" :value="g.name">
            {{ g.display_name }}
          </option>
        </select>
      </div>
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="card-soft cursor-pointer p-4 transition"
        :class="selectedId === plan.id ? 'ring-2 ring-brand' : ''"
        @click="selectedId = plan.id"
      >
        <div class="mb-2 flex items-center justify-between">
          <h2 class="font-bold">{{ plan.name }}</h2>
          <span class="price text-sm">{{ displayPrice(plan) }}</span>
        </div>
        <p class="mb-3 text-xs text-ink-muted">{{ plan.duration_days }} روز</p>
        <ul class="mb-3 space-y-1 text-xs text-ink-soft">
          <li v-for="(f, i) in plan.features || []" :key="i">• {{ f }}</li>
        </ul>
        <div class="flex gap-2">
          <button
            class="btn-primary flex-1"
            @click.stop="subscribe(plan.id, 'wallet')"
          >
            کیف پول
          </button>
          <button
            class="btn-ghost flex-1 border border-surface-line"
            @click.stop="subscribe(plan.id, gateway)"
          >
            پرداخت آنلاین
          </button>
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
