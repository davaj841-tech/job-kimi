import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../../stores/auth'

vi.mock('@/api', () => ({
  default: {
    post: vi.fn().mockResolvedValue({
      data: {
        data: {
          token: 'tok-1',
          user: { id: 1, name: 'Ali', mobile: '09120000000' },
        },
      },
    }),
    get: vi.fn().mockResolvedValue({ data: { data: null } }),
    put: vi.fn().mockResolvedValue({ data: { data: null } }),
  },
}))

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('logs in with password and persists token', async () => {
    const store = useAuthStore()
    await store.loginPassword('user@test.com', 'secret')

    expect(store.token).toBe('tok-1')
    expect(store.user?.id).toBe(1)
    expect(store.isAuthenticated).toBe(true)
    expect(localStorage.getItem('token')).toBe('tok-1')
  })

  it('clears session on logout', async () => {
    const store = useAuthStore()
    await store.loginPassword('user@test.com', 'secret')
    await store.logout()

    expect(store.token).toBe('')
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(localStorage.getItem('token')).toBeNull()
  })

  it('sends and verifies OTP', async () => {
    const store = useAuthStore()
    await store.sendOtp('09123456789')
    expect(store.loading).toBe(false)

    await store.verifyOtp('09123456789', '123456')
    expect(store.token).toBe('tok-1')
    expect(store.isAuthenticated).toBe(true)
  })
})
