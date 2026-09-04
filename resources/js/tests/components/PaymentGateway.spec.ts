import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PaymentGateway from '@/components/PaymentGateway.vue'

const post = vi.fn()

vi.mock('@/api', () => ({
  default: {
    post: (...args: unknown[]) => post(...args),
    get: vi.fn(),
    put: vi.fn(),
  },
}))

describe('PaymentGateway.vue', () => {
  beforeEach(() => {
    post.mockReset()
  })

  it('نمایش درگاه و مبلغ را نشان می‌دهد', () => {
    const wrapper = mount(PaymentGateway, {
      props: {
        gateway: 'zarinpal',
        amount: 150000,
        redirectUrl: 'https://zarinpal.test/pay',
        autoVerify: false,
      },
    })

    expect(wrapper.get('[data-testid="gateway-name"]').text()).toContain(
      'زرین‌پال'
    )
    expect(wrapper.get('[data-testid="gateway-amount"]').text()).toContain(
      'ریال'
    )
    expect(wrapper.find('[data-testid="pay-button"]').exists()).toBe(true)
  })

  it('callback موفق را هندل می‌کند', async () => {
    post.mockResolvedValueOnce({
      data: { success: true, message: 'تراکنش موفق' },
    })

    const wrapper = mount(PaymentGateway, {
      props: {
        authority: 'A0001',
        callbackStatus: 'OK',
        idempotencyKey: 'ik-test-uuid',
        verifyEndpoint: '/wallet/verify',
        autoVerify: true,
      },
    })

    await flushPromises()

    expect(post).toHaveBeenCalledWith(
      '/wallet/verify',
      null,
      expect.objectContaining({
        params: expect.objectContaining({
          Authority: 'A0001',
          Status: 'OK',
          ik: 'ik-test-uuid',
        }),
      })
    )
    expect(wrapper.find('[data-testid="payment-success"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="payment-message"]').text()).toContain(
      'موفق'
    )
    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('callback ناموفق را هندل می‌کند', async () => {
    post.mockResolvedValueOnce({
      data: { success: false, message: 'پرداخت لغو شد' },
    })

    const wrapper = mount(PaymentGateway, {
      props: {
        authority: 'A0002',
        callbackStatus: 'NOK',
        autoVerify: true,
      },
    })

    await flushPromises()

    expect(wrapper.find('[data-testid="payment-failure"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="payment-message"]').text()).toContain(
      'لغو'
    )
    expect(wrapper.emitted('failure')).toBeTruthy()
  })

  it('خطای شبکه را به‌عنوان failure emit می‌کند', async () => {
    post.mockRejectedValueOnce({
      message: 'Network Error',
      response: { data: { message: 'خطای ارتباط با درگاه' } },
    })

    const wrapper = mount(PaymentGateway, {
      props: { authority: 'A0003', autoVerify: true },
    })

    await flushPromises()

    expect(wrapper.find('[data-testid="payment-failure"]').exists()).toBe(true)
    expect(wrapper.emitted('failure')).toBeTruthy()
  })
})
