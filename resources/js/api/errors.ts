import type { AxiosError } from 'axios'
import { useToast } from '@/composables/useToast'
import { apiErrorMessage } from '@/utils/format'

declare module 'axios' {
  export interface AxiosRequestConfig {
    skipGlobalErrorHandler?: boolean
  }
}

export interface ApiErrorWithFlags extends AxiosError {
  globalErrorNotified?: boolean
}

export function notifyGlobalApiError(error: ApiErrorWithFlags): void {
  if (error.config?.skipGlobalErrorHandler || error.globalErrorNotified) return

  const status = error.response?.status
  if (status !== 403 && status !== 429) return

  const toast = useToast()
  let message = apiErrorMessage(error)

  if (status === 403) {
    message = message || 'دسترسی مجاز نیست.'
  } else if (status === 429) {
    message =
      message || 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.'
    const retryAfter = error.response?.headers?.['retry-after']
    if (retryAfter && !String(message).includes(String(retryAfter))) {
      message = `${message} (${retryAfter} ثانیه)`
    }
  }

  if (message) {
    toast.error(message)
    error.globalErrorNotified = true
  }
}
