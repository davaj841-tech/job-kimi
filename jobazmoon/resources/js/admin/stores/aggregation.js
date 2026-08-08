import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useAggregationStore = defineStore('adminAggregation', {
  state: () => ({
    stats: null,
    jobs: [],
    meta: {},
    current: null,
    loading: false,
    filters: {
      status: 'pending',
      search: '',
      job_source_id: '',
      page: 1,
    },
  }),
  actions: {
    async fetchStats() {
      const { data } = await adminApi.get('/admin/aggregation/quality-stats');
      this.stats = data.data;
      return this.stats;
    },
    async fetchPendingJobs(page = 1) {
      this.loading = true;
      this.filters.page = page;
      try {
        const { data } = await adminApi.get('/admin/aggregation/pending-jobs', {
          params: {
            status: this.filters.status || 'pending',
            search: this.filters.search || undefined,
            job_source_id: this.filters.job_source_id || undefined,
            page,
            per_page: 15,
          },
        });
        this.jobs = data.data?.data || [];
        this.meta = data.data?.meta || {};
      } finally {
        this.loading = false;
      }
    },
    async fetchJob(id) {
      const { data } = await adminApi.get(`/admin/aggregation/jobs/${id}`);
      this.current = data.data;
      return this.current;
    },
    async updateJob(id, payload) {
      const { data } = await adminApi.put(`/admin/aggregation/jobs/${id}`, payload);
      this.current = data.data;
      return this.current;
    },
    async approveJob(id) {
      const { data } = await adminApi.post(`/admin/aggregation/jobs/${id}/approve`);
      return data.data;
    },
    async rejectJob(id, reason = null) {
      const { data } = await adminApi.post(`/admin/aggregation/jobs/${id}/reject`, { reason });
      return data.data;
    },
  },
});
