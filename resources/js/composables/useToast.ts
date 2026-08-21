import { reactive } from 'vue'

export type ToastType = 'info' | 'success' | 'error'

interface ToastState {
  visible: boolean
  message: string
  type: ToastType
}

const state = reactive<ToastState>({
  visible: false,
  message: '',
  type: 'info',
})

let timer: ReturnType<typeof setTimeout> | undefined

export function useToast() {
  function show(message: string, type: ToastType = 'info', ms = 2800): void {
    if (!message?.trim()) return
    state.message = message
    state.type = type
    state.visible = true
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => {
      state.visible = false
    }, ms)
  }

  return {
    state,
    show,
    success: (msg: string) => show(msg, 'success'),
    error: (msg: string) => show(msg, 'error'),
    info: (msg: string) => show(msg, 'info'),
  }
}
