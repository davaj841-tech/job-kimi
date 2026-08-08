import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useAggregationScheduleStore = defineStore('adminAggregationSchedule', {
  state: () => ({
    schedule: {
      enabled: false,
      timezone: 'Asia/Tehran',
      max_concurrent: 5,
      dispatch_delay_seconds: 0,
      retry_tries: 2,
      times: [],
    },
    meta: {},
    loading: false,
    saving: false,
  }),
  actions: {
    async fetch() {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/aggregation-schedule');
        this.schedule = data.data?.schedule || this.schedule;
        this.meta = data.data?.meta || {};
        return this.schedule;
      } finally {
        this.loading = false;
      }
    },
    async save(payload) {
      this.saving = true;
      try {
        const { data } = await adminApi.put('/admin/aggregation-schedule', payload);
        this.schedule = data.data?.schedule || this.schedule;
        return this.schedule;
      } finally {
        this.saving = false;
      }
    },
    async addTime(payload) {
      const { data } = await adminApi.post('/admin/aggregation-schedule/times', payload);
      this.schedule = data.data?.schedule || this.schedule;
      return this.schedule;
    },
    async updateTime(id, payload) {
      const { data } = await adminApi.put(`/admin/aggregation-schedule/times/${id}`, payload);
      this.schedule = data.data?.schedule || this.schedule;
      return this.schedule;
    },
    async removeTime(id) {
      const { data } = await adminApi.delete(`/admin/aggregation-schedule/times/${id}`);
      this.schedule = data.data?.schedule || this.schedule;
      return this.schedule;
    },
    async dispatchNow(dryRun = false) {
      const { data } = await adminApi.post('/admin/aggregation-schedule/dispatch-now', {
        dry_run: dryRun,
      });
      return data.data;
    },
  },
});
