export const OPTION_FA = { a: 'الف', b: 'ب', c: 'ج', d: 'د' }

export function optionFaLetter(key) {
  const k = String(key || '').trim().toLowerCase()
  return OPTION_FA[k] || ''
}

export function optionText(item, key) {
  if (!item || key == null || key === '') return ''
  const k = String(key).trim().toLowerCase()
  const opts = item.options || {}
  return String(opts[k] ?? item[`option_${k}`] ?? '').trim()
}

/** «الف) متن گزینه» */
export function formatChoiceAnswer(item, key) {
  if (key == null || key === '') return 'نزده'
  const letter = optionFaLetter(key)
  const text = optionText(item, key)
  if (letter && text) return `${letter}) ${text}`
  return letter || '—'
}
