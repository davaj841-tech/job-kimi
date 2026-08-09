import { defineStore } from 'pinia';
import adminApi from '../api/client';
import { unwrapList, unwrapMeta } from '../../utils/format';

interface SubscriptionsFilters {
  status: string;
  plan_id: string;
  search: string;
}

interface SubscriptionsStats {
  active_subscriptions: number;
  monthly_revenue: number;
  renewals_today: number;
  expiring_soon: number;
}

interface SubscriptionsState {
  plans: Record<string, unknown>[];
  subscribers: Record<string, unknown>[];
  meta: Record<string, unknown>;
  stats: SubscriptionsStats;
  filters: SubscriptionsFilters;
  loading: boolean;
}

export const useSubscriptionsStore = defineStore('adminSubscriptions', {
  state: (): SubscriptionsState => ({
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
        this.plans = unwrapList(data) as Record<string, unknown>[];
      } finally {
        this.loading = false;
      }
    },

    async createPlan(payload: Record<string, unknown>) {
      const { data } = await adminApi.post('/admin/subscriptions/plans', payload);
      await this.fetchPlans();
      return data.data;
    },

    async updatePlan(id: number | string, payload: Record<string, unknown>) {
      const { data } = await adminApi.put(`/admin/subscriptions/plans/${id}`, payload);
      await this.fetchPlans();
      return data.data;
    },

    async deletePlan(id: number | string) {
      await adminApi.delete(`/admin/subscriptions/plans/${id}`);
      await this.fetchPlans();
    },

    async fetchSubscribers(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/subscriptions/subscribers', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.subscribers = unwrapList(data) as Record<string, unknown>[];
        this.meta = unwrapMeta(data) || {};
      } finally {
        this.loading = false;
      }
    },

    async renewSubscriber(id: number | string, payload: Record<string, unknown> = {}) {
      await adminApi.post(`/admin/subscriptions/subscribers/${id}/renew`, payload);
      await this.fetchSubscribers((this.meta.current_page as number) || 1);
      await this.fetchStats();
    },

    async cancelSubscriber(id: number | string) {
      await adminApi.post(`/admin/subscriptions/subscribers/${id}/cancel`);
      await this.fetchSubscribers((this.meta.current_page as number) || 1);
      await this.fetchStats();
    },
  },
});
