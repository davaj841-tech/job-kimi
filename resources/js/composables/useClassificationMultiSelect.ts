import { ref } from 'vue'

type Id = number | string

/**
 * Multi-select classification chips for filters (jobs, exams, home).
 */
export function useClassificationMultiSelect() {
  const selectedIds = ref<Id[]>([])

  function isSelected(id: Id) {
    return selectedIds.value.some((x) => Number(x) === Number(id))
  }

  function toggle(id: Id) {
    const n = Number(id)
    const idx = selectedIds.value.findIndex((x) => Number(x) === n)
    if (idx >= 0) {
      selectedIds.value.splice(idx, 1)
    } else {
      selectedIds.value.push(id)
    }
  }

  function clear() {
    selectedIds.value = []
  }

  function apiParams(): Record<string, string> {
    if (!selectedIds.value.length) return {}
    return { job_classification_ids: selectedIds.value.map(String).join(',') }
  }

  return { selectedIds, isSelected, toggle, clear, apiParams }
}

const NAMED_ICON_EMOJI: Record<string, string> = {
  school: '🎓',
  bank: '🏦',
  shield: '🛡️',
  building: '🏛️',
  city: '🏙️',
  briefcase: '💼',
  grid: '📋',
  book: '📚',
  users: '👥',
}

function isEmojiOrSymbol(value: string): boolean {
  // Non-ASCII (emoji / symbols); avoid \x00..\x1F in regex (eslint no-control-regex)
  for (const ch of value) {
    if ((ch.codePointAt(0) ?? 0) > 0x7f) return true
  }
  return false
}

export function classificationChipIcon(
  item: Record<string, unknown> | null | undefined,
  fallbacks: string[] = [
    '🏛️',
    '🏦',
    '🎓',
    '🏥',
    '⚖️',
    '🛡️',
    '🏭',
    '💼',
    '📚',
    '🧪',
  ]
) {
  const raw = item?.icon ? String(item.icon).trim() : ''
  if (raw !== '') {
    if (isEmojiOrSymbol(raw)) return raw
    const mapped = NAMED_ICON_EMOJI[raw.toLowerCase()]
    if (mapped) return mapped
  }
  const key = String(item?.slug || item?.name || item?.id || '')
  let hash = 0
  for (let i = 0; i < key.length; i += 1) {
    hash = (hash + key.charCodeAt(i) * (i + 1)) % fallbacks.length
  }
  return fallbacks[hash] || '📝'
}

export function jobMatchesClassifications(
  job: Record<string, unknown>,
  selected: Id[],
  classifications: Array<Record<string, unknown>>
) {
  if (!selected.length) return true
  const expanded = new Set<number>()
  for (const id of selected) {
    expanded.add(Number(id))
    const parent = classifications.find((c) => Number(c.id) === Number(id))
    for (const childId of (parent?.child_ids as Id[]) || []) {
      expanded.add(Number(childId))
    }
  }
  if (expanded.has(Number(job.job_classification_id))) return true
  if (expanded.has(Number(job.classification_parent_id))) return true
  return false
}
