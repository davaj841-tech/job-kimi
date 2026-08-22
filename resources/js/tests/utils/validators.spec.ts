import { describe, it, expect } from 'vitest'
import {
  isValidIranMobile,
  isValidNationalCode,
  validators,
} from '@/utils/validators'

describe('validators', () => {
  describe('موبایل ایرانی', () => {
    it('شماره‌های معتبر را می‌پذیرد', () => {
      expect(isValidIranMobile('09123456789')).toBe(true)
      expect(isValidIranMobile('9123456789')).toBe(true)
      expect(isValidIranMobile('+989123456789')).toBe(true)
      expect(isValidIranMobile('00989123456789')).toBe(true)
      expect(isValidIranMobile('۰۹۱۲۳۴۵۶۷۸۹')).toBe(true)
    })

    it('شماره‌های نامعتبر را رد می‌کند', () => {
      expect(isValidIranMobile('')).toBe(false)
      expect(isValidIranMobile('08123456789')).toBe(false)
      expect(isValidIranMobile('0912345678')).toBe(false)
      expect(isValidIranMobile('091234567890')).toBe(false)
      expect(isValidIranMobile('abc')).toBe(false)
      expect(isValidIranMobile(null)).toBe(false)
    })
  })

  describe('کد ملی', () => {
    it('کد ملی معتبر را می‌پذیرد', () => {
      expect(isValidNationalCode('0013542419')).toBe(true)
      expect(isValidNationalCode('0499370899')).toBe(true)
    })

    it('کد ملی نامعتبر را رد می‌کند', () => {
      expect(isValidNationalCode('')).toBe(false)
      expect(isValidNationalCode('123')).toBe(false)
      expect(isValidNationalCode('0000000000')).toBe(false)
      expect(isValidNationalCode('1111111111')).toBe(false)
      expect(isValidNationalCode('1234567890')).toBe(false)
      expect(isValidNationalCode('abcdefghij')).toBe(false)
    })
  })

  it('آبجکت validators توابع را expose می‌کند', () => {
    expect(validators.isValidIranMobile('09120000000')).toBe(true)
    expect(typeof validators.isValidNationalCode).toBe('function')
  })
})
