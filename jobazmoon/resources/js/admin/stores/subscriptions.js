import { defineStore } from 'pinia';
import adminApi from '../api/client';
import { unwrapList, unwrapMeta } from '../../utils/format';

export const useSubscriptionsStore = defineStore('adminSubscriptions', {
  state: () => ({
    plans: [],
    subscribers: [],
    meta: {},
    stats: {
      active_subscriptions: 0,
      monthly_revenue: 0,
      renewals_today: 0,
      expiring_soon: 0,
    },
    filters: { status: '', plan_id: '', search: '' },
    loading: false,
  }),

  actions: {
    async fetchStats() {
      const { data } = await adminApi.get('/admin/subscriptions/stats');
      this.stats = data.data || this.stats;
    },

    async fetchPlans() {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/subscriptions/plans');
        this.plans = unwrapList(data);
      } finally {
        this.loading = false;
      }
    },

    async createPlan(payload) {
      const { data } = await adminApi.post('/admin/subscriptions/plans', payload);
      await this.fetchPlans();
      return data.data;
    },

    async updatePlan(id, payload) {
      const { data } = await adminApi.put(`/admin/subscriptions/plans/${id}`, payload);
      await this.fetchPlans();
      return data.data;
    },

    async deletePlan(id) {
      await adminApi.delete(`/admin/subscriptions/plans/${id}`);
      await this.fetchPlans();
    },

    async fetchSubscribers(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/subscriptions/subscribers', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.subscribers = unwrapList(data);
        this.meta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async renewSubscriber(id, payload = {}) {
      await adminApi.post(`/admin/subscriptions/subscribers/${id}/renew`, payload);
      await this.fetchSubscribers(this.meta.current_page || 1);
      await this.fetchStats();
    },

    async cancelSubscriber(id) {
      await adminApi.post(`/admin/subscriptions/subscribers/${id}/cancel`);
      await this.fetchSubscribers(this.meta.current_page || 1);
      await this.fetchStats();
    },
  },
});
