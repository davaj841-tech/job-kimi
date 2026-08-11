import { SITE_THEMES, type SiteThemeId } from '../theme/presets'
import { useSiteTheme } from './useSiteTheme'

/** @deprecated use SITE_THEMES — kept for existing imports */
export const HOMEPAGE_LAYOUTS = SITE_THEMES
export type HomepageLayout = SiteThemeId

export function useHomepageLayout() {
  return useSiteTheme()
}
