<template>
  <AdminLayout>
    <div class="space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-800">مدیریت کیف پول‌ها</h1>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <StatCard
          title="مجموع موجودی کاربران"
          :value="fa(store.stats.total_balance)"
          icon="₪"
          color="#0f2744"
        />
        <StatCard
          title="تعداد شارژ امروز"
          :value="fa(store.stats.charges_today)"
          icon="◎"
          color="#2563eb"
        />
        <StatCard
          title="مبلغ شارژ امروز"
          :value="fa(store.stats.charge_amount_today)"
          icon="+"
          color="#059669"
        />
      </div>

      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex flex-wrap gap-2">
          <input
            v-model="store.filters.search"
            class="field max-w-md"
            placeholder="جستجو نام/موبایل"
            @keyup.enter="apply"
          />
          <button class="btn-orange" @click="apply">جستجو</button>
        </div>
      </div>

      <DataTable
        :columns="columns"
        :rows="store.wallets"
        :loading="store.loading"
        actions
      >
        <template #cell-index="{ index }">{{ fa(rowNum(index)) }}</template>
        <template #cell-user="{ row }">
          <div>
            <p class="font-medium">{{ row.name || '—' }}</p>
            <p class="text-xs text-slate-400" dir="ltr">{{ row.mobile }}</p>
          </div>
        </template>
        <template #cell-balance="{ row }">{{ fa(row.balance) }}</template>
        <template #cell-total_charged="{ row }">{{
          fa(row.total_charged)
        }}</template>
        <template #cell-total_withdrawn="{ row }">{{
          fa(row.total_withdrawn)
        }}</template>
        <template #cell-last_transaction_at="{ row }">{{
          formatDateTime(row.last_transaction_at)
        }}</template>
        <template #actions="{ row }">
          <div class="flex flex-wrap justify-end gap-1">
            <button class="act" @click="openHistory(row)">تاریخچه</button>
            <button
              class="act text-emerald-700"
              @click="openAdjust(row, 'charge')"
            >
              شارژ
            </button>
            <button class="act text-red-600" @click="openAdjust(row, 'deduct')">
              کسر
            </button>
          </div>
        </template>
      </DataTable>
      <PaginationBar :meta="store.meta" @page="(p) => store.fetchWallets(p)" />
    </div>

    <WalletHistoryModal
      :open="historyOpen"
      :user="store.historyUser"
      :rows="store.history"
      :loading="store.loading"
      :type-filter="store.historyType"
      @close="historyOpen = false"
      @filter="onHistoryFilter"
    />
    <WalletAdjustModal
      :open="adjustOpen"
      :mode="adjustMode"
      :user="adjustUser"
      @close="adjustOpen = false"
      @submit="onAdjust"
    />
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AdminLayout from '../components/layout/AdminLayout.vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import StatCard from '../components/ui/StatCard.vue'
import WalletAdjustModal from '../components/wallets/WalletAdjustModal.vue'
import WalletHistoryModal from '../components/wallets/WalletHistoryModal.vue'
import { formatDateTime, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import { useWalletsStore } from '../stores/wallets'

const store = useWalletsStore()
const toast = useToast()
const historyOpen = ref(false)
const adjustOpen = ref(false)
const adjustMode = ref('charge')
const adjustUser = ref(null)
const historyUserId = ref(null)

const columns = [
  { key: 'index', label: '#' },
  { key: 'user', label: 'کاربر' },
  { key: 'balance', label: 'موجودی' },
  { key: 'total_charged', label: 'کل شارژ' },
  { key: 'total_withdrawn', label: 'کل برداشت' },
  { key: 'last_transaction_at', label: 'آخرین تراکنش' },
]

function fa(n) {
  return new Intl.NumberFormat('fa-IR').format(Number(n || 0))
}
function rowNum(i) {
  return (
    (store.meta.from ||
      (store.meta.current_page - 1) * (store.meta.per_page || 20) + 1 ||
      1) + i
  )
}

onMounted(async () => {
  await Promise.all([store.fetchStats(), store.fetchWallets()])
})

function apply() {
  store.fetchWallets(1)
}

async function openHistory(row) {
  historyUserId.value = row.id
  store.historyType = ''
  await store.fetchHistory(row.id)
  historyOpen.value = true
}

async function onHistoryFilter(type) {
  store.historyType = type
  if (historyUserId.value) await store.fetchHistory(historyUserId.value)
}

function openAdjust(row, mode) {
  adjustUser.value = row
  adjustMode.value = mode
  adjustOpen.value = true
}

async function onAdjust({ amount, note }) {
  try {
    if (adjustMode.value === 'charge') {
      await store.charge(adjustUser.value.id, amount, note)
      toast.success('شارژ انجام شد')
    } else {
      await store.deduct(adjustUser.value.id, amount, note)
      toast.success('کسر انجام شد')
    }
    adjustOpen.value = false
  } catch (e) {
    toast.error(apiErrorMessage(e))
  }
}
</script>

<style scoped>
.field {
  @apply h-10 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-orange-400;
}
.btn-orange {
  @apply rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white;
}
.act {
  @apply rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100;
}
</style>
