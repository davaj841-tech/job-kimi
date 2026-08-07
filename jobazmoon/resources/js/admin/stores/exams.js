import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useExamsStore = defineStore('adminExams', {
  state: () => ({
    exams: [],
    meta: {},
    categories: [],
    jobPosts: [],
    classifications: [],
    filters: {
      search: '',
      category_id: '',
      status: '',
      is_free: '',
      sort: 'desc',
    },
    loading: false,
    selectedExam: null,
    stats: null,
    statsLoading: false,
  }),

  actions: {
    async fetchExamOptions() {
      const { data } = await adminApi.get('/admin/exams', {
        params: { per_page: 100, sort: 'desc' },
      });
      this.exams = data.data || [];
    },

    async fetchExams(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/exams', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.exams = data.data || [];
        this.meta = data.meta || {};
      } finally {
        this.loading = false;
      }
    },

    async fetchCategories() {
      const { data } = await adminApi.get('/admin/exam-categories');
      this.categories = data.data || [];
    },

    async fetchJobPosts() {
      const { data } = await adminApi.get('/admin/exam-job-posts');
      this.jobPosts = data.data || [];
    },

    async fetchClassifications() {
      const { data } = await adminApi.get('/admin/job-classifications');
      this.classifications = data.data?.flat || data.data || [];
      return this.classifications;
    },

    async fetchExam(id) {
      const { data } = await adminApi.get(`/admin/exams/${id}`);
      this.selectedExam = data.data || null;
      return this.selectedExam;
    },

    async createExam(payload) {
      const { data } = await adminApi.post('/admin/exams', payload);
      await this.fetchExams(this.meta.current_page || 1);
      return data.data;
    },

    async updateExam(id, payload) {
      const { data } = await adminApi.put(`/admin/exams/${id}`, payload);
      await this.fetchExams(this.meta.current_page || 1);
      return data.data;
    },

    async deleteExam(id) {
      await adminApi.delete(`/admin/exams/${id}`);
      await this.fetchExams(this.meta.current_page || 1);
    },

    async fetchStats(id) {
      this.statsLoading = true;
      try {
        const { data } = await adminApi.get(`/admin/exams/${id}/stats`);
        this.stats = data.data || null;
        return this.stats;
      } finally {
        this.statsLoading = false;
      }
    },

    resetFilters() {
      this.filters = {
        search: '',
        category_id: '',
        status: '',
        is_free: '',
        sort: 'desc',
      };
    },
  },
});
