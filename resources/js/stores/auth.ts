import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/api'
import { normalizeIranMobile } from '@/utils/iranMobile'

export interface User {
  id: number
  name: string | null
  email?: string | null
  mobile: string
  avatar?: string | null
  role?: string
  wallet_balance?: number | string
  province?: string | null
  national_code?: string | null
  home_phone?: string | null
  military_status?: string | null
  insurance_history?: string | null
  birth_date?: string | null
  birth_province?: string | null
  birth_city?: string | null
  marital_status?: string | null
  field_of_study?: string | null
  address?: string | null
  postal_code?: string | null
  [key: string]: unknown
}

interface AuthApiPayload {
  data?: {
    token?: string
    access_token?: string
    user?: User
  }
}

export const useAuthStore = defineStore('auth', () => {
  const storedUser = localStorage.getItem('user')
  const user = ref<User | null>(
    storedUser ? (JSON.parse(storedUser) as User) : null
  )
  const token = ref<string>(localStorage.getItem('token') || '')
  const loading = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))

  function persist(): void {
    if (token.value) {
      localStorage.setItem('token', token.value)
    } else {
      localStorage.removeItem('token')
    }
    if (user.value) {
      localStorage.setItem('user', JSON.stringify(user.value))
    } else {
      localStorage.removeItem('user')
    }
  }

  function applyAuthPayload(data: AuthApiPayload): void {
    token.value = data.data?.token || data.data?.access_token || ''
    user.value = data.data?.user || null
    persist()
  }

  type CaptchaPayload = {
    turnstile_token?: string
    captcha_id?: string
    captcha_answer?: string
  }

  function withCaptcha(
    base: Record<string, unknown>,
    captcha?: CaptchaPayload | string
  ): Record<string, unknown> {
    const out = { ...base }
    if (!captcha) return out
    if (typeof captcha === 'string') {
      if (captcha) out.turnstile_token = captcha
      return out
    }
    if (captcha.turnstile_token) out.turnstile_token = captcha.turnstile_token
    if (captcha.captcha_id) out.captcha_id = captcha.captcha_id
    if (captcha.captcha_answer) out.captcha_answer = captcha.captcha_answer
    return out
  }

  async function sendOtp(
    mobile: string,
    captcha?: CaptchaPayload | string
  ): Promise<unknown> {
    loading.value = true
    try {
      const { data } = await api.post(
        '/auth/otp/send',
        withCaptcha({ mobile: normalizeIranMobile(mobile) || mobile }, captcha)
      )
      return data
    } finally {
      loading.value = false
    }
  }

  async function verifyOtp(
    mobile: string,
    code: string,
    captcha?: CaptchaPayload | string,
    province?: string
  ): Promise<unknown> {
    loading.value = true
    try {
      const payload = withCaptcha(
        { mobile: normalizeIranMobile(mobile) || mobile, code },
        captcha
      )
      if (province) payload.province = province
      const { data } = await api.post('/auth/otp/verify', payload)
      applyAuthPayload(data)
      return data
    } finally {
      loading.value = false
    }
  }

  async function loginPassword(
    login: string,
    password: string,
    captcha?: CaptchaPayload | string
  ): Promise<unknown> {
    loading.value = true
    try {
      const ident = normalizeIranMobile(login) || login
      const { data } = await api.post(
        '/auth/login',
        withCaptcha({ login: ident, password }, captcha)
      )
      applyAuthPayload(data)
      return data
    } finally {
      loading.value = false
    }
  }

  async function register(
    payload: Record<string, unknown>,
    captcha?: CaptchaPayload | string
  ): Promise<unknown> {
    loading.value = true
    try {
      const body = { ...payload }
      if (typeof body.mobile === 'string' && body.mobile) {
        body.mobile = normalizeIranMobile(body.mobile) || body.mobile
      }
      const { data } = await api.post('/auth/register', withCaptcha(body, captcha))
      if (data.data?.token) {
        applyAuthPayload(data)
      }
      return data
    } finally {
      loading.value = false
    }
  }

  async function fetchMe(): Promise<User | null> {
    if (!token.value) return null
    const { data } = await api.get('/auth/me')
    user.value = (data.data?.user || data.data || null) as User | null
    persist()
    return user.value
  }

  async function updateProfile(
    payload: Record<string, unknown>
  ): Promise<User | null> {
    loading.value = true
    try {
      const { data } = await api.put('/auth/profile', payload)
      user.value = (data.data?.user || data.data || user.value) as User | null
      persist()
      return user.value
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) {
        await api.post('/auth/logout')
      }
    } catch {
      // ignore
    }
    token.value = ''
    user.value = null
    persist()
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    sendOtp,
    verifyOtp,
    loginPassword,
    register,
    fetchMe,
    updateProfile,
    logout,
  }
})
