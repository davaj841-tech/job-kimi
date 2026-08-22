<template>
  <div class="payment-gateway" data-testid="payment-gateway">
    <div v-if="loading" data-testid="payment-loading">در حال اتصال به درگاه…</div>

    <div v-else-if="status === 'idle'" class="payment-gateway__idle">
      <p class="payment-gateway__name" data-testid="gateway-name">
        درگاه: {{ gatewayLabel }}
      </p>
      <p v-if="amount != null" class="payment-gateway__amount" data-testid="gateway-amount">
        مبلغ: {{ formattedAmount }} ریال
      </p>
      <button
        type="button"
        class="btn-primary"
        data-testid="pay-button"
        :disabled="!redirectUrl"
        @click="goToGateway"
      >
        پرداخت
      </button>
    </div>

    <div
      v-else
      class="payment-gateway__result"
      :data-testid="status === 'success' ? 'payment-success' : 'payment-failure'"
    >
      <p data-testid="payment-message">{{ message }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import api from '@/api'

const props = withDefaults(
  defineProps<{
    gateway?: string
    amount?: number | null
    redirectUrl?: string | null
    /** When set, component verifies callback on mount */
    authority?: string | null
    callbackStatus?: string | null
    verifyEndpoint?: string
    autoVerify?: boolean
  }>(),
  {
    gateway: 'zarinpal',
    amount: null,
    redirectUrl: null,
    authority: null,
    callbackStatus: null,
    verifyEndpoint: '/wallet/verify',
    autoVerify: true,
  }
)

const emit = defineEmits<{
  success: [payload: unknown]
  failure: [payload: unknown]
  redirect: [url: string]
}>()

const loading = ref(false)
const status = ref<'idle' | 'success' | 'failure'>('idle')
const message = ref('')

const gatewayLabel = computed(() => {
  const map: Record<string, string> = {
    zarinpal: 'زرین‌پال',
    wallet: 'کیف پول',
  }
  return map[props.gateway] || props.gateway
})

const formattedAmount = computed(() =>
  props.amount == null ? '' : Number(props.amount).toLocaleString('fa-IR')
)

function goToGateway(): void {
  if (!props.redirectUrl) return
  emit('redirect', props.redirectUrl)
  if (typeof window !== 'undefined') {
    window.location.assign(props.redirectUrl)
  }
}

async function handleCallback(
  authority: string,
  callbackStatus: string | null = null
): Promise<void> {
  loading.value = true
  try {
    const { data } = await api.post(props.verifyEndpoint, null, {
      params: {
        Authority: authority,
        Status: callbackStatus || '',
      },
    })

    const ok = Boolean(data?.success)
    if (ok) {
      status.value = 'success'
      message.value = data.message || 'پرداخت با موفقیت انجام شد.'
      emit('success', data)
    } else {
      status.value = 'failure'
      message.value = data?.message || 'پرداخت تایید نشد.'
      emit('failure', data)
    }
  } catch (e: unknown) {
    status.value = 'failure'
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    message.value =
      err.response?.data?.message || err.message || 'تایید پرداخت ناموفق بود.'
    emit('failure', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (props.autoVerify && props.authority) {
    void handleCallback(props.authority, props.callbackStatus)
  }
})

defineExpose({
  handleCallback,
  status,
  message,
  goToGateway,
})
</script>
