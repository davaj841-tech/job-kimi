import { defineStore } from 'pinia';
import adminApi from '../api/client';
import { unwrapList, unwrapMeta } from '../../utils/format';

export const useWalletsStore = defineStore('adminWallets', {
  state: () => ({
    wallets: [],
    meta: {},
    history: [],
    historyMeta: {},
    historyUser: null,
    stats: {
      total_balance: 0,
      charges_today: 0,
      charge_amount_today: 0,
    },
    filters: { search: '' },
    historyType: '',
    loading: false,
  }),

  actions: {
    async fetchStats() {
      const { data } = await adminApi.get('/admin/wallets/stats');
      this.stats = data.data || this.stats;
    },

    async fetchWallets(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/wallets', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.wallets = unwrapList(data);
        this.meta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async fetchHistory(userId, page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get(`/admin/wallets/${userId}/history`, {
          params: { type: this.historyType || undefined, page, per_page: 30 },
        });
        this.historyUser = data.data?.user || null;
        this.history = unwrapList(data);
        this.historyMeta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async charge(userId, amount, description) {
      const { data } = await adminApi.post(`/admin/wallets/${userId}/charge`, { amount, description });
      await this.fetchWallets(this.meta.current_page || 1);
      await this.fetchStats();
      return data.data;
    },

    async deduct(userId, amount, reason) {
      const { data } = await adminApi.post(`/admin/wallets/${userId}/deduct`, { amount, reason });
      await this.fetchWallets(this.meta.current_page || 1);
      await this.fetchStats();
      return data.data;
    },
  },
});
