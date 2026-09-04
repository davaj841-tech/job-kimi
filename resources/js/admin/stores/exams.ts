import { defineStore } from 'pinia'
import adminApi from '../api/client'

interface ExamsFilters {
  search: string
  job_classification_ids: (number | string)[]
  status: string
  is_free: string
  sort: string
}

interface ExamsState {
  exams: Record<string, unknown>[]
  meta: Record<string, unknown>
  categories: Record<string, unknown>[]
  jobPosts: Record<string, unknown>[]
  classifications: Record<string, unknown>[]
  filters: ExamsFilters
  loading: boolean
  selectedExam: Record<string, unknown> | null
  stats: Record<string, unknown> | null
  statsLoading: boolean
}

export const useExamsStore = defineStore('adminExams', {
  state: (): ExamsState => ({
    exams: [],
    meta: {},
    categories: [],
    jobPosts: [],
    classifications: [],
    filters: {
      search: '',
      job_classification_ids: [],
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
      })
      this.exams = data.data || []
    },

    async fetchExams(page = 1) {
      this.loading = true
      try {
        const params: Record<string, unknown> = {
          ...this.filters,
          page,
          per_page: 20,
        }
        if (this.filters.job_classification_ids.length) {
          params.job_classification_ids =
            this.filters.job_classification_ids.join(',')
        } else {
          delete params.job_classification_ids
        }
        const { data } = await adminApi.get('/admin/exams', { params })
        this.exams = data.data || []
        this.meta = data.meta || {}
      } finally {
        this.loading = false
      }
    },

    async fetchCategories() {
      const { data } = await adminApi.get('/admin/exam-categories')
      this.categories = data.data || []
    },

    async fetchJobPosts() {
      const { data } = await adminApi.get('/admin/exam-job-posts')
      this.jobPosts = data.data || []
    },

    async fetchClassifications() {
      const { data } = await adminApi.get('/admin/job-classifications')
      this.classifications = data.data?.flat || data.data || []
      return this.classifications
    },

    async fetchExam(id: number | string) {
      const { data } = await adminApi.get(`/admin/exams/${id}`)
      this.selectedExam = data.data || null
      return this.selectedExam
    },

    async createExam(payload: Record<string, unknown>) {
      const { data } = await adminApi.post('/admin/exams', payload)
      await this.fetchExams((this.meta.current_page as number) || 1)
      return data.data
    },

    async updateExam(id: number | string, payload: Record<string, unknown>) {
      const { data } = await adminApi.put(`/admin/exams/${id}`, payload)
      await this.fetchExams((this.meta.current_page as number) || 1)
      return data.data
    },

    async deleteExam(id: number | string) {
      await adminApi.delete(`/admin/exams/${id}`)
      await this.fetchExams((this.meta.current_page as number) || 1)
    },

    async fetchStats(id: number | string) {
      this.statsLoading = true
      try {
        const { data } = await adminApi.get(`/admin/exams/${id}/stats`)
        this.stats = data.data || null
        return this.stats
      } finally {
        this.statsLoading = false
      }
    },

    resetFilters() {
      this.filters = {
        search: '',
        job_classification_ids: [],
        status: '',
        is_free: '',
        sort: 'desc',
      }
    },
  },
})
