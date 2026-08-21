export function normalizeIranMobile(value) {
  if (value == null) return ''
  let v = String(value).trim()
  if (!v) return ''

  v = v.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
  v = v.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
  v = v.replace(/[\s\-()]/g, '')

  if (v.startsWith('0098')) v = v.slice(4)
  else if (v.startsWith('+98')) v = v.slice(3)
  else if (v.startsWith('98') && v.length >= 12) v = v.slice(2)

  if (v.startsWith('9') && v.length === 10) v = `0${v}`

  return /^09\d{9}$/.test(v) ? v : ''
}

export function isIranMobile(value) {
  return /^09\d{9}$/.test(String(value || ''))
}
