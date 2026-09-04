import { defineStore } from 'pinia'
import adminApi from '../api/client'
import { unwrapList, unwrapMeta } from '../../utils/format'

interface WalletsStats {
  total_balance: number
  ledger_total: number
  reconciled: boolean
  charges_today: number
  charge_amount_today: number
}

interface WalletsState {
  wallets: Record<string, unknown>[]
  meta: Record<string, unknown>
  history: Record<string, unknown>[]
  historyMeta: Record<string, unknown>
  historyUser: Record<string, unknown> | null
  ledger: Record<string, unknown>[]
  ledgerMeta: Record<string, unknown>
  ledgerUser: Record<string, unknown> | null
  stats: WalletsStats
  filters: { search: string }
  historyType: string
  loading: boolean
}

export const useWalletsStore = defineStore('adminWallets', {
  state: (): WalletsState => ({
    wallets: [],
    meta: {},
    history: [],
    historyMeta: {},
    historyUser: null,
    ledger: [],
    ledgerMeta: {},
    ledgerUser: null,
    stats: {
      total_balance: 0,
      ledger_total: 0,
      reconciled: true,
      charges_today: 0,
      charge_amount_today: 0,
    },
    filters: { search: '' },
    historyType: '',
    loading: false,
  }),

  actions: {
    async fetchStats() {
      const { data } = await adminApi.get('/admin/wallets/stats')
      this.stats = data.data || this.stats
    },

    async fetchWallets(page = 1) {
      this.loading = true
      try {
        const { data } = await adminApi.get('/admin/wallets', {
          params: { ...this.filters, page, per_page: 20 },
        })
        this.wallets = unwrapList(data) as Record<string, unknown>[]
        this.meta = unwrapMeta(data) || {}
      } finally {
        this.loading = false
      }
    },

    async fetchHistory(userId: number | string, page = 1) {
      this.loading = true
      try {
        const { data } = await adminApi.get(
          `/admin/wallets/${userId}/history`,
          {
            params: { type: this.historyType || undefined, page, per_page: 30 },
          }
        )
        this.historyUser = data.data?.user || null
        this.history = unwrapList(data) as Record<string, unknown>[]
        this.historyMeta = unwrapMeta(data) || {}
      } finally {
        this.loading = false
      }
    },

    async fetchLedger(userId: number | string, page = 1) {
      this.loading = true
      try {
        const { data } = await adminApi.get(`/admin/wallets/${userId}/ledger`, {
          params: { page, per_page: 30 },
        })
        this.ledgerUser = data.data?.user || null
        this.ledger = unwrapList(data) as Record<string, unknown>[]
        this.ledgerMeta = unwrapMeta(data) || {}
      } finally {
        this.loading = false
      }
    },

    async charge(userId: number | string, amount: number, description: string) {
      const { data } = await adminApi.post(`/admin/wallets/${userId}/charge`, {
        amount,
        description,
      })
      await this.fetchWallets((this.meta.current_page as number) || 1)
      await this.fetchStats()
      return data.data
    },

    async deduct(userId: number | string, amount: number, reason: string) {
      const { data } = await adminApi.post(`/admin/wallets/${userId}/deduct`, {
        amount,
        reason,
      })
      await this.fetchWallets((this.meta.current_page as number) || 1)
      await this.fetchStats()
      return data.data
    },

    async freeze(userId: number | string, reason: string) {
      const { data } = await adminApi.post(`/admin/wallets/${userId}/freeze`, {
        reason,
      })
      await this.fetchWallets((this.meta.current_page as number) || 1)
      return data.data
    },

    async unfreeze(userId: number | string, reason: string) {
      const { data } = await adminApi.post(
        `/admin/wallets/${userId}/unfreeze`,
        {
          reason,
        }
      )
      await this.fetchWallets((this.meta.current_page as number) || 1)
      return data.data
    },
  },
})
