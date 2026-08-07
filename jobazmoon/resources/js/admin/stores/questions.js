import { defineStore } from 'pinia';
import adminApi from '../api/client';

export const useQuestionsStore = defineStore('adminQuestions', {
  state: () => ({
    questions: [],
    meta: {},
    filters: {
      search: '',
      exam_id: '',
      subject: '',
      difficulty: '',
      question_type: '',
    },
    loading: false,
    selectedQuestion: null,
  }),

  actions: {
    async fetchQuestions(page = 1) {
      this.loading = true;
      try {
        const { data } = await adminApi.get('/admin/questions', {
          params: { ...this.filters, page, per_page: 20 },
        });
        this.questions = data.data || [];
        this.meta = data.meta || {};
      } finally {
        this.loading = false;
      }
    },

    async fetchQuestion(id) {
      const { data } = await adminApi.get(`/admin/questions/${id}`);
      this.selectedQuestion = data.data || null;
      return this.selectedQuestion;
    },

    async createQuestion(payload) {
      const { data } = await adminApi.post('/admin/questions', payload);
      await this.fetchQuestions(this.meta.current_page || 1);
      return data.data;
    },

    async updateQuestion(id, payload) {
      const { data } = await adminApi.put(`/admin/questions/${id}`, payload);
      await this.fetchQuestions(this.meta.current_page || 1);
      return data.data;
    },

    async deleteQuestion(id) {
      await adminApi.delete(`/admin/questions/${id}`);
      await this.fetchQuestions(this.meta.current_page || 1);
    },

    async importQuestions({ file, exam_id }) {
      const form = new FormData();
      form.append('file', file);
      form.append('exam_id', String(exam_id));
      const { data } = await adminApi.post('/admin/questions/import', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await this.fetchQuestions(1);
      return data.data;
    },

    /** @deprecated alias — use importQuestions({ file, exam_id }) */
    async importFromExcel({ file, exam_id }) {
      return this.importQuestions({ file, exam_id });
    },

    async exportQuestions() {
      const response = await adminApi.get('/admin/questions/export', {
        params: { ...this.filters },
        responseType: 'blob',
      });
      const url = URL.createObjectURL(new Blob([response.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `questions-${Date.now()}.xlsx`;
      a.click();
      URL.revokeObjectURL(url);
    },

    async generateWithAI(params) {
      const { data } = await adminApi.post('/admin/ai/generate-questions', params);
      return data;
    },

    resetFilters() {
      this.filters = {
        search: '',
        exam_id: '',
        subject: '',
        difficulty: '',
        question_type: '',
      };
    },
  },
});
