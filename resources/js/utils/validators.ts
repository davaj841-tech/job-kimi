import { isIranMobile, normalizeIranMobile } from '@/utils/iranMobile'

/**
 * اعتبارسنجی شماره موبایل ایرانی (۰۹xxxxxxxxx).
 */
export function isValidIranMobile(value: unknown): boolean {
  const normalized = normalizeIranMobile(value)
  return Boolean(normalized) && isIranMobile(normalized)
}

/**
 * اعتبارسنجی کد ملی ایران با الگوریتم کنترل رقم.
 */
export function isValidNationalCode(value: unknown): boolean {
  const raw = String(value ?? '').replace(/\D/g, '')
  if (!/^\d{10}$/.test(raw)) {
    return false
  }

  if (/^(\d)\1{9}$/.test(raw)) {
    return false
  }

  const check = Number(raw[9])
  let sum = 0
  for (let i = 0; i < 9; i++) {
    sum += Number(raw[i]) * (10 - i)
  }
  const remainder = sum % 11
  return remainder < 2 ? check === remainder : check === 11 - remainder
}

export const validators = {
  isValidIranMobile,
  isValidNationalCode,
  normalizeIranMobile,
}

export default validators
