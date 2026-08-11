import { defineStore } from 'pinia'
import adminApi from '../api/client'

interface ScheduleConfig {
  enabled: boolean
  timezone: string
  max_concurrent: number
  dispatch_delay_seconds: number
  retry_tries: number
  times: Record<string, unknown>[]
}

interface AggregationScheduleState {
  schedule: ScheduleConfig
  meta: Record<string, unknown>
  loading: boolean
  saving: boolean
}

export const useAggregationScheduleStore = defineStore(
  'adminAggregationSchedule',
  {
    state: (): AggregationScheduleState => ({
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
        this.loading = true
        try {
          const { data } = await adminApi.get('/admin/aggregation-schedule')
          this.schedule = data.data?.schedule || this.schedule
          this.meta = data.data?.meta || {}
          return this.schedule
        } finally {
          this.loading = false
        }
      },
      async save(payload: Record<string, unknown>) {
        this.saving = true
        try {
          const { data } = await adminApi.put(
            '/admin/aggregation-schedule',
            payload
          )
          this.schedule = data.data?.schedule || this.schedule
          return this.schedule
        } finally {
          this.saving = false
        }
      },
      async addTime(payload: Record<string, unknown>) {
        const { data } = await adminApi.post(
          '/admin/aggregation-schedule/times',
          payload
        )
        this.schedule = data.data?.schedule || this.schedule
        return this.schedule
      },
      async updateTime(id: number | string, payload: Record<string, unknown>) {
        const { data } = await adminApi.put(
          `/admin/aggregation-schedule/times/${id}`,
          payload
        )
        this.schedule = data.data?.schedule || this.schedule
        return this.schedule
      },
      async removeTime(id: number | string) {
        const { data } = await adminApi.delete(
          `/admin/aggregation-schedule/times/${id}`
        )
        this.schedule = data.data?.schedule || this.schedule
        return this.schedule
      },
      async dispatchNow(dryRun = false) {
        const { data } = await adminApi.post(
          '/admin/aggregation-schedule/dispatch-now',
          {
            dry_run: dryRun,
          }
        )
        return data.data
      },
    },
  }
)
