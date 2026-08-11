import { defineStore } from 'pinia'
import adminApi from '../api/client'

interface ExamSubject {
  slug: string
  name: string
  icon?: string
  id?: number
  [key: string]: unknown
}

interface ExamSubjectsState {
  subjects: ExamSubject[]
  loading: boolean
  loaded: boolean
}

export const useExamSubjectsStore = defineStore('adminExamSubjects', {
  state: (): ExamSubjectsState => ({
    subjects: [],
    loading: false,
    loaded: false,
  }),

  getters: {
    options(state): { value: string; label: string }[] {
      return state.subjects.map((s) => ({
        value: s.slug,
        label: `${s.icon || '📘'} ${s.name}`,
      }))
    },
  },

  actions: {
    async fetchSubjects(force = false) {
      if (this.loaded && !force) return this.subjects
      this.loading = true
      try {
        const { data } = await adminApi.get('/admin/exam-subjects')
        this.subjects = data.data || []
        this.loaded = true
        return this.subjects
      } finally {
        this.loading = false
      }
    },

    labelFor(slug: string) {
      return this.subjects.find((s) => s.slug === slug)?.name || slug || '—'
    },

    async createSubject(payload: Record<string, unknown>) {
      const { data } = await adminApi.post('/admin/exam-subjects', payload)
      await this.fetchSubjects(true)
      return data.data
    },

    async updateSubject(id: number | string, payload: Record<string, unknown>) {
      const { data } = await adminApi.put(`/admin/exam-subjects/${id}`, payload)
      await this.fetchSubjects(true)
      return data.data
    },

    async deleteSubject(id: number | string) {
      await adminApi.delete(`/admin/exam-subjects/${id}`)
      await this.fetchSubjects(true)
    },
  },
})
