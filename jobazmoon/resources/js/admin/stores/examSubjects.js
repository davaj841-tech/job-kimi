import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useExamSubjectsStore = defineStore('adminExamSubjects', {
  state: () => ({
    subjects: [],
    loading: false,
    loaded: false,
  }),

  getters: {
    options(state) {
      return state.subjects.map((s) => ({ value: s.slug, label: `${s.icon || '📘'} ${s.name}` }));
    },
  },

  actions: {
    async fetchSubjects(force = false) {
      if (this.loaded && !force) return this.subjects;
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/exam-subjects');
        this.subjects = data.data || [];
        this.loaded = true;
        return this.subjects;
      } finally {
        this.loading = false;
      }
    },

    labelFor(slug) {
      return this.subjects.find((s) => s.slug === slug)?.name || slug || '—';
    },

    async createSubject(payload) {
      const { data } = await adminApi.post('/admin/exam-subjects', payload);
      await this.fetchSubjects(true);
      return data.data;
    },

    async updateSubject(id, payload) {
      const { data } = await adminApi.put(`/admin/exam-subjects/${id}`, payload);
      await this.fetchSubjects(true);
      return data.data;
    },

    async deleteSubject(id) {
      await adminApi.delete(`/admin/exam-subjects/${id}`);
      await this.fetchSubjects(true);
    },
  },
});
