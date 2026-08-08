import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useCrawlMonitoringStore = defineStore('adminCrawlMonitoring', {
  state: () => ({
    runs: [],
    runsMeta: {},
    errors: [],
    errorsMeta: {},
    currentRun: null,
    loading: false,
    filters: {
      job_source_id: '',
      status: '',
      error_type: '',
      page: 1,
    },
  }),
  actions: {
    async fetchRuns(page = 1) {
      this.loading = true;
      this.filters.page = page;
      try {
        const { data } = await adminApi.get('/admin/crawler-runs', {
          params: {
            job_source_id: this.filters.job_source_id || undefined,
            status: this.filters.status || undefined,
            page,
            per_page: 20,
          },
        });
        this.runs = data.data?.data || [];
        this.runsMeta = data.data?.meta || {};
      } finally {
        this.loading = false;
      }
    },
    async fetchRun(id) {
      const { data } = await adminApi.get(`/admin/crawler-runs/${id}`);
      this.currentRun = data.data;
      return this.currentRun;
    },
    async fetchErrors(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/crawler-runs/errors', {
          params: {
            job_source_id: this.filters.job_source_id || undefined,
            error_type: this.filters.error_type || undefined,
            page,
            per_page: 20,
          },
        });
        this.errors = data.data?.data || [];
        this.errorsMeta = data.data?.meta || {};
      } finally {
        this.loading = false;
      }
    },
  },
});
