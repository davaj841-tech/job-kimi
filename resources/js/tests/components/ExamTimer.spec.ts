import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ExamTimer from '@/components/ExamTimer.vue'

describe('ExamTimer.vue', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('شمارش معکوس را نمایش می‌دهد', async () => {
    const endsAt = Date.now() + 65_000
    const wrapper = mount(ExamTimer, {
      props: { endsAt, intervalMs: 1000 },
    })

    expect(wrapper.get('[data-testid="exam-timer-value"]').text()).toMatch(/01:0[45]/)
    vi.advanceTimersByTime(1000)
    await flushPromises()
    expect(wrapper.get('[data-testid="exam-timer-value"]').text()).toMatch(/01:0[34]/)
  })

  it('هشدار ۵ دقیقه مانده را emit می‌کند', async () => {
    const endsAt = Date.now() + 5 * 60 * 1000
    const wrapper = mount(ExamTimer, {
      props: { endsAt, intervalMs: 1000 },
    })

    // دقیقاً روی آستانه ۵ دقیقه → warning
    expect(wrapper.find('[data-testid="exam-timer-warning"]').exists()).toBe(true)
    expect(wrapper.emitted('warning')).toBeTruthy()

    vi.advanceTimersByTime(1000)
    await flushPromises()
    expect(wrapper.classes().join(' ')).toContain('exam-timer--warning')
  })

  it('اتمام خودکار: رویداد expired را می‌فرستد', async () => {
    const endsAt = Date.now() + 2000
    const wrapper = mount(ExamTimer, {
      props: { endsAt, intervalMs: 1000 },
    })

    vi.advanceTimersByTime(2500)
    await flushPromises()

    expect(wrapper.emitted('expired')).toBeTruthy()
    expect(wrapper.classes().join(' ')).toContain('exam-timer--expired')
    expect(wrapper.get('[data-testid="exam-timer-value"]').text()).toBe('00:00')
  })
})
