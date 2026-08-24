<template>
  <div
    class="mx-auto flex max-w-2xl flex-col px-4 py-6"
    style="min-height: 70dvh"
  >
    <div v-if="ticket" class="mb-4">
      <h1 class="text-lg font-black">{{ ticket.subject }}</h1>
      <p
        v-if="ticket.tracking_code"
        class="mt-1 text-sm font-bold text-brand"
        dir="ltr"
      >
        شماره پیگیری: {{ ticket.tracking_code }}
      </p>
      <p class="text-xs text-ink-muted">
        وضعیت: {{ ticket.status === 'open' ? 'باز' : 'بسته' }}
      </p>
    </div>

    <div
      class="flex-1 space-y-3 overflow-y-auto rounded-2xl border border-surface-line bg-slate-50 p-3"
    >
      <div
        v-if="ticket"
        class="mr-auto max-w-[85%] rounded-2xl rounded-br-md bg-brand px-3 py-2 text-sm text-white"
      >
        {{ ticket.message }}
      </div>
      <div
        v-for="r in replies"
        :key="r.id"
        class="max-w-[85%] rounded-2xl px-3 py-2 text-sm"
        :class="
          r.is_admin
            ? 'ml-auto rounded-bl-md bg-white text-slate-800 shadow-sm'
            : 'mr-auto rounded-br-md bg-brand text-white'
        "
      >
        {{ r.message }}
      </div>
    </div>

    <form
      v-if="ticket?.status === 'open'"
      class="mt-3 flex gap-2"
      @submit.prevent="reply"
    >
      <input
        v-model="message"
        required
        class="field flex-1"
        placeholder="پاسخ شما..."
      />
      <button
        class="rounded-xl bg-brand px-4 text-sm font-bold text-white"
        :disabled="sending"
      >
        ارسال
      </button>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api/client'
import { unwrapItem } from '../../utils/format'

const route = useRoute()
const ticket = ref(null)
const message = ref('')
const sending = ref(false)
const replies = computed(() => ticket.value?.replies || [])

onMounted(load)

async function load() {
  const { data } = await api.get(`/tickets/${route.params.id}`)
  ticket.value = unwrapItem(data)
}

async function reply() {
  sending.value = true
  try {
    await api.post(`/tickets/${route.params.id}/reply`, {
      message: message.value,
    })
    message.value = ''
    await load()
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.field {
  @apply h-11 rounded-xl border border-surface-line px-3 text-sm outline-none focus:border-brand;
}
</style>
