import { defineStore } from 'pinia'
import adminApi from '../api/client'

interface JobSourcesFilters {
  search: string
  source_type: string
  reliability_level: string
  quality_status: string
  is_enabled: string
  is_approved: string
  page: number
}

interface JobSourceOptions {
  source_types: unknown[]
  reliability_levels: unknown[]
  quality_statuses: unknown[]
  crawler_types: unknown[]
  endpoint_types: unknown[]
  parser_types: unknown[]
  http_methods: string[]
  default_catalog?: DefaultCatalogPayload | null
}

interface DefaultCatalogPayload {
  total: number
  loaded_count: number
  enabled_count: number
  dispatchable_count: number
  items: Record<string, unknown>[]
}

interface JobSourcesState {
  sources: Record<string, unknown>[]
  meta: Record<string, unknown>
  options: JobSourceOptions
  current: Record<string, unknown> | null
  loading: boolean
  filters: JobSourcesFilters
  lastTestResult: Record<string, unknown> | null
  defaultCatalog: DefaultCatalogPayload | null
}

export const useJobSourcesStore = defineStore('adminJobSources', {
  state: (): JobSourcesState => ({
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
    defaultCatalog: null as DefaultCatalogPayload | null,
  }),
  actions: {
    async fetchOptions() {
      const { data } = await adminApi.get('/admin/job-sources/options')
      this.options = data.data || this.options
      if (data.data?.default_catalog?.items?.length) {
        this.defaultCatalog = data.data.default_catalog
      }
    },
    async fetchSources(page = 1) {
      this.loading = true
      this.filters.page = page
      try {
        const { data } = await adminApi.get('/admin/job-sources', {
          params: this.filters,
        })
        this.sources = data.data?.data || []
        this.meta = data.data?.meta || {}
      } finally {
        this.loading = false
      }
    },
    async fetchSource(id: number | string) {
      const { data } = await adminApi.get(`/admin/job-sources/${id}`)
      this.current = data.data
      return this.current
    },
    async saveSource(
      payload: Record<string, unknown>,
      id: number | string | null = null
    ) {
      const { data } = id
        ? await adminApi.put(`/admin/job-sources/${id}`, payload)
        : await adminApi.post('/admin/job-sources', payload)
      return data.data
    },
    async destroySource(id: number | string) {
      await adminApi.delete(`/admin/job-sources/${id}`)
    },
    async approve(id: number | string) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/approve`)
      return data.data
    },
    async unapprove(id: number | string) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/unapprove`)
      return data.data
    },
    async enable(id: number | string) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/enable`)
      return data.data
    },
    async disable(id: number | string) {
      const { data } = await adminApi.post(`/admin/job-sources/${id}/disable`)
      return data.data
    },
    async testCrawl(id: number | string) {
      const { data } = await adminApi.post(
        `/admin/job-sources/${id}/test-crawl`
      )
      this.lastTestResult = data.data
      return data
    },
    async resetHealth(id: number | string) {
      const { data } = await adminApi.post(
        `/admin/job-sources/${id}/reset-health`
      )
      return data.data
    },
    async saveEndpoint(
      sourceId: number | string,
      payload: Record<string, unknown>,
      endpointId: number | string | null = null
    ) {
      const { data } = endpointId
        ? await adminApi.put(
            `/admin/job-sources/${sourceId}/endpoints/${endpointId}`,
            payload
          )
        : await adminApi.post(
            `/admin/job-sources/${sourceId}/endpoints`,
            payload
          )
      return data.data
    },
    async destroyEndpoint(
      sourceId: number | string,
      endpointId: number | string
    ) {
      await adminApi.delete(
        `/admin/job-sources/${sourceId}/endpoints/${endpointId}`
      )
    },
    async seedDefaults() {
      const { data } = await adminApi.post('/admin/job-sources/seed-defaults')
      return data.data
    },
    async fetchCrawlOverview() {
      const { data } = await adminApi.get('/admin/job-sources/crawl-overview')
      return data.data
    },
    async fetchDefaultCatalog() {
      try {
        const { data } = await adminApi.get(
          '/admin/job-sources/default-catalog'
        )
        this.defaultCatalog = data.data
        return data.data
      } catch {
        try {
          const { data } = await adminApi.get(
            '/admin/job-sources/crawl-overview'
          )
          if (data.data?.default_catalog?.items?.length) {
            this.defaultCatalog = data.data.default_catalog
            return data.data.default_catalog
          }
        } catch {
          //
        }
        if (this.defaultCatalog?.items?.length) {
          return this.defaultCatalog
        }
        throw new Error('بارگذاری فهرست منابع پیش‌فرض ناموفق بود.')
      }
    },
    async bulkDisableDefaults() {
      const { data } = await adminApi.post(
        '/admin/job-sources/bulk-disable-defaults'
      )
      return data.data
    },
  },
})
