import { defineStore } from 'pinia'
import adminApi from '../api/client'
import { unwrapList, unwrapMeta } from '../../utils/format'

interface TransactionsFilters {
  date_from: string
  date_to: string
  gateway: string
  type: string
  status: string
}

interface TransactionsStats {
  revenue_today: number
  revenue_week: number
  revenue_month: number
  success_count: number
  failed_count: number
  pending_count: number
}

interface TransactionsState {
  transactions: Record<string, unknown>[]
  meta: Record<string, unknown>
  selected: Record<string, unknown> | null
  stats: TransactionsStats
  filters: TransactionsFilters
  loading: boolean
}

export const useTransactionsStore = defineStore('adminTransactions', {
  state: (): TransactionsState => ({
    transactions: [],
    meta: {},
    selected: null,
    stats: {
      revenue_today: 0,
      revenue_week: 0,
      revenue_month: 0,
      success_count: 0,
      failed_count: 0,
      pending_count: 0,
    },
    filters: {
      date_from: '',
      date_to: '',
      gateway: '',
      type: '',
      status: '',
    },
    loading: false,
  }),

  actions: {
    async fetchStats() {
      const { data } = await adminApi.get('/admin/transactions/stats')
      this.stats = data.data || this.stats
    },

    async fetchTransactions(page = 1) {
      this.loading = true
      try {
        const { data } = await adminApi.get('/admin/transactions', {
          params: { ...this.filters, page, per_page: 20 },
        })
        this.transactions = unwrapList(data) as Record<string, unknown>[]
        this.meta = unwrapMeta(data) || {}
      } finally {
        this.loading = false
      }
    },

    async fetchTransaction(id: number | string) {
      const { data } = await adminApi.get(`/admin/transactions/${id}`)
      this.selected = data.data || null
      return this.selected
    },
  },
})
