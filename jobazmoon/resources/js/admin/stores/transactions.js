import { defineStore } from 'pinia';
import adminApi from '../api/client';
import { unwrapList, unwrapMeta } from '../../utils/format';

export const useTransactionsStore = defineStore('adminTransactions', {
  state: () => ({
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
      const { data } = await adminApi.get('/admin/transactions/stats');
      this.stats = data.data || this.stats;
    },

    async fetchTransactions(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/transactions', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.transactions = unwrapList(data);
        this.meta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async fetchTransaction(id) {
      const { data } = await adminApi.get(`/admin/transactions/${id}`);
      this.selected = data.data || null;
      return this.selected;
    },
  },
});
