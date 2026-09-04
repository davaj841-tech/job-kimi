<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-gray-800">مدیریت کیف پول‌ها</h1>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard
        title="مجموع موجودی کاربران"
        :value="fa(store.stats.total_balance)"
        icon="₪"
        color="#0f2744"
      />
      <StatCard
        title="مجموع Ledger"
        :value="fa(store.stats.ledger_total ?? 0)"
        icon="≡"
        :color="store.stats.reconciled ? '#059669' : '#dc2626'"
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

    <div
      v-if="store.stats.reconciled === false"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      هشدار یکپارچگی مالی: مجموع موجودی کش‌شده با Ledger همخوانی ندارد. دستور
      <code class="mx-1 rounded bg-red-100 px-1"
        >php artisan wallet:reconcile</code
      >
      را اجرا کنید.
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
      <template #cell-ledger_total="{ row }">{{
        fa(row.ledger_total)
      }}</template>
      <template #cell-reconciled="{ row }">
        <span
          class="rounded-full px-2 py-0.5 text-xs font-bold"
          :class="
            row.reconciled
              ? 'bg-emerald-100 text-emerald-800'
              : 'bg-red-100 text-red-800'
          "
        >
          {{ row.reconciled ? 'OK' : 'Drift' }}
        </span>
      </template>
      <template #cell-wallet_status="{ row }">
        <span
          class="rounded-full px-2 py-0.5 text-xs font-bold"
          :class="
            row.wallet_frozen
              ? 'bg-red-100 text-red-800'
              : 'bg-emerald-100 text-emerald-800'
          "
        >
          {{ row.wallet_frozen ? 'مسدود' : 'فعال' }}
        </span>
      </template>
      <template #cell-last_transaction_at="{ row }">{{
        formatDateTime(row.last_transaction_at)
      }}</template>
      <template #actions="{ row }">
        <div class="flex flex-wrap justify-end gap-1">
          <button class="act" @click="openHistory(row)">تراکنش‌ها</button>
          <button class="act" @click="openLedger(row)">Ledger</button>
          <button
            class="act text-emerald-700"
            @click="openAdjust(row, 'charge')"
          >
            شارژ
          </button>
          <button class="act text-red-600" @click="openAdjust(row, 'deduct')">
            کسر
          </button>
          <button
            v-if="!row.wallet_frozen"
            class="act text-amber-700"
            @click="openFreeze(row, 'freeze')"
          >
            مسدود
          </button>
          <button
            v-else
            class="act text-blue-700"
            @click="openFreeze(row, 'unfreeze')"
          >
            فعال‌سازی
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
  <WalletLedgerModal
    :open="ledgerOpen"
    :user="store.ledgerUser"
    :rows="store.ledger"
    :meta="store.ledgerMeta"
    :loading="store.loading"
    @close="ledgerOpen = false"
    @page="onLedgerPage"
  />
  <WalletAdjustModal
    :open="adjustOpen"
    :mode="adjustMode"
    :user="adjustUser"
    @close="adjustOpen = false"
    @submit="onAdjust"
  />
  <WalletFreezeModal
    ref="freezeModalRef"
    :open="freezeOpen"
    :mode="freezeMode"
    :user="freezeUser"
    @close="freezeOpen = false"
    @submit="onFreeze"
  />
</template>

<script setup>
import { onMounted, ref } from 'vue'
import DataTable from '../components/ui/DataTable.vue'
import PaginationBar from '../components/ui/PaginationBar.vue'
import StatCard from '../components/ui/StatCard.vue'
import WalletAdjustModal from '../components/wallets/WalletAdjustModal.vue'
import WalletFreezeModal from '../components/wallets/WalletFreezeModal.vue'
import WalletHistoryModal from '../components/wallets/WalletHistoryModal.vue'
import WalletLedgerModal from '../components/wallets/WalletLedgerModal.vue'
import { formatDateTime, apiErrorMessage } from '../../utils/format'
import { useToast } from '../../composables/useToast'
import { useWalletsStore } from '../stores/wallets'

const store = useWalletsStore()
const toast = useToast()
const historyOpen = ref(false)
const ledgerOpen = ref(false)
const adjustOpen = ref(false)
const freezeOpen = ref(false)
const adjustMode = ref('charge')
const freezeMode = ref('freeze')
const adjustUser = ref(null)
const freezeUser = ref(null)
const freezeModalRef = ref(null)
const historyUserId = ref(null)
const ledgerUserId = ref(null)

const columns = [
  { key: 'index', label: '#' },
  { key: 'user', label: 'کاربر' },
  { key: 'balance', label: 'موجودی' },
  { key: 'ledger_total', label: 'Ledger' },
  { key: 'reconciled', label: 'تطبیق' },
  { key: 'wallet_status', label: 'وضعیت' },
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

async function openLedger(row) {
  ledgerUserId.value = row.id
  await store.fetchLedger(row.id)
  ledgerOpen.value = true
}

async function onLedgerPage(page) {
  if (ledgerUserId.value) await store.fetchLedger(ledgerUserId.value, page)
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

function openFreeze(row, mode) {
  freezeUser.value = row
  freezeMode.value = mode
  freezeOpen.value = true
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

async function onFreeze({ reason }) {
  freezeModalRef.value?.setSaving(true)
  try {
    if (freezeMode.value === 'freeze') {
      await store.freeze(freezeUser.value.id, reason)
      toast.success('کیف پول مسدود شد')
    } else {
      await store.unfreeze(freezeUser.value.id, reason)
      toast.success('کیف پول فعال شد')
    }
    freezeOpen.value = false
  } catch (e) {
    freezeModalRef.value?.setError(apiErrorMessage(e))
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
