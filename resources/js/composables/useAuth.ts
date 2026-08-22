import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/**
 * Auth composable — thin wrapper around the Pinia auth store
 * for login, logout, and token checks in components/views.
 */
export function useAuth() {
  const auth = useAuthStore()
  const { user, token, loading, isAuthenticated } = storeToRefs(auth)

  const hasToken = computed(() => Boolean(token.value?.trim()))

  function checkToken(): boolean {
    const stored = localStorage.getItem('token') || token.value || ''
    return Boolean(String(stored).trim())
  }

  async function login(
    loginId: string,
    password: string,
    captcha?: Parameters<typeof auth.loginPassword>[2]
  ) {
    return auth.loginPassword(loginId, password, captcha)
  }

  async function logout() {
    return auth.logout()
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    hasToken,
    checkToken,
    login,
    logout,
    sendOtp: auth.sendOtp,
    verifyOtp: auth.verifyOtp,
    fetchMe: auth.fetchMe,
  }
}
