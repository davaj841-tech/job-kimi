import { storeToRefs } from 'pinia'
import { useThemeStore } from '../stores/themeStore'

export function useDarkMode() {
  const theme = useThemeStore()
  theme.init()

  const { isDark } = storeToRefs(theme)

  return {
    isDark,
    toggle: () => theme.toggle(),
    setDark: (val: boolean) => theme.setDark(val),
  }
}
