export const OPTION_FA: Record<string, string> = { a: 'الف', b: 'ب', c: 'ج', d: 'د' }

export type AnswerOptionItem = {
  options?: Record<string, string | null | undefined>
  option_a?: string | null
  option_b?: string | null
  option_c?: string | null
  option_d?: string | null
  [key: string]: unknown
}

export function optionFaLetter(key: string | number | null | undefined): string {
  const k = String(key || '').trim().toLowerCase()
  return OPTION_FA[k] || ''
}

export function optionText(
  item: AnswerOptionItem | null | undefined,
  key: string | number | null | undefined
): string {
  if (!item || key == null || key === '') return ''
  const k = String(key).trim().toLowerCase()
  const opts = item.options || {}
  const fromOptions = opts[k]
  const fromField = item[`option_${k}`]
  return String(fromOptions ?? fromField ?? '').trim()
}

/** «الف) متن گزینه» */
export function formatChoiceAnswer(
  item: AnswerOptionItem | null | undefined,
  key: string | number | null | undefined
): string {
  if (key == null || key === '') return 'نزده'
  const letter = optionFaLetter(key)
  const text = optionText(item, key)
  if (letter && text) return `${letter}) ${text}`
  return letter || '—'
}
