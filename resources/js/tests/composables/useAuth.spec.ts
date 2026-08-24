import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuth } from '@/composables/useAuth'

const post = vi.fn()
const get = vi.fn()

vi.mock('@/api', () => ({
  default: {
    post: (...args: unknown[]) => post(...args),
    get: (...args: unknown[]) => get(...args),
    put: vi.fn(),
  },
}))

describe('useAuth', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    post.mockReset()
    get.mockReset()
  })

  it('ورود: توکن و کاربر را ذخیره می‌کند', async () => {
    post.mockResolvedValueOnce({
      data: {
        data: {
          token: 'tok-login',
          user: { id: 7, name: 'علی', mobile: '09121234567' },
        },
      },
    })

    const { login, token, user, isAuthenticated, hasToken, checkToken } =
      useAuth()
    await login('09121234567', 'secret')

    expect(post).toHaveBeenCalledWith(
      '/auth/login',
      expect.objectContaining({ login: '09121234567', password: 'secret' })
    )
    expect(token.value).toBe('tok-login')
    expect(user.value?.id).toBe(7)
    expect(isAuthenticated.value).toBe(true)
    expect(hasToken.value).toBe(true)
    expect(checkToken()).toBe(true)
    expect(localStorage.getItem('token')).toBe('tok-login')
  })

  it('خروج: نشست و توکن را پاک می‌کند', async () => {
    post
      .mockResolvedValueOnce({
        data: {
          data: {
            token: 'tok-x',
            user: { id: 1, name: 'A', mobile: '09120000000' },
          },
        },
      })
      .mockResolvedValueOnce({ data: { success: true } })

    const auth = useAuth()
    await auth.login('user@test.com', 'pass')
    await auth.logout()

    expect(post).toHaveBeenCalledWith('/auth/logout')
    expect(auth.token.value).toBe('')
    expect(auth.user.value).toBeNull()
    expect(auth.isAuthenticated.value).toBe(false)
    expect(auth.checkToken()).toBe(false)
    expect(localStorage.getItem('token')).toBeNull()
  })

  it('بررسی توکن: false وقتی توکن نیست، true وقتی هست', () => {
    const auth = useAuth()
    expect(auth.checkToken()).toBe(false)
    expect(auth.hasToken.value).toBe(false)

    localStorage.setItem('token', 'persisted-tok')
    // store token still empty until hydrated — checkToken می‌خواند از localStorage
    expect(auth.checkToken()).toBe(true)
  })
})
