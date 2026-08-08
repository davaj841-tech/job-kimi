import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useJobSourcesStore = defineStore('adminJobSources', {
  state: () => ({
    sources: [],
    meta: {},
    options: {
      source_types: [],
      reliability_levels: [],
      quality_statuses: [],
      crawler_types: [],
      endpoint_types: [],
      parser_types: [],
      http_methods: ['GET'],
    },
    current: null,
    loading: false,
    filters: {
      search: '',
      source_type: '',
      reliability_level: '',
      quality_status: '',
      is_enabled: '',
      is_approved: '',
      page: 1,
    },
    lastTestResult: null,
  }),
  actions: {
    async fetchOptions() {
      const { data } = await adminApi.get('/admin/job-sources/options');
      this.options = data.data || this.options;
    },
    async fetchSources(page = 1) {
      this.loading = true;
      this.filters.page = page;
      try {
        const { data } = await adminApi.get('/admin/job-sources', { params: this.filters });
        this.sources = data.data?.data || [];
        this.meta = data.data?.meta || {};
      } finally {
        this.loading = false;
      }
    },
    async fetchSource(id) {
      const { data } = await adminApi.get(`/admin/job-sources/${id}`);
      this.current = data.data;
      return this.current;
    },
    async saveSource(payload, id = null) {
      const { data } = id
        ? await adminApi.put(`/admin/job-sources/${id}`, payload)
        : await adminApi.post('/admin/job-sources', payload);
      return data.data;
    },
    async destroySource(id) {
      await adminApi.delete(`/admin/job-sources/${id}`);
    },
    async approve(id) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/approve`);
      return data.data;
    },
    async unapprove(id) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/unapprove`);
      return data.data;
    },
    async enable(id) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/enable`);
      return data.data;
    },
    async disable(id) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/disable`);
      return data.data;
    },
    async testCrawl(id) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/test-crawl`);
      this.lastTestResult = data.data;
      return data;
    },
    async resetHealth(id) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/reset-health`);
      return data.data;
    },
    async saveEndpoint(sourceId, payload, endpointId = null) {
      const { data } = endpointId
        ? await adminApi.put(`/admin/job-sources/${sourceId}/endpoints/${endpointId}`, payload)
        : await adminApi.post(`/admin/job-sources/${sourceId}/endpoints`, payload);
      return data.data;
    },
    async destroyEndpoint(sourceId, endpointId) {
      await adminApi.delete(`/admin/job-sources/${sourceId}/endpoints/${endpointId}`);
    },
  },
});
