const NAMED_ICON_EMOJI = {
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

const FALLBACK_EMOJIS = [
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

/** Small emoji/icon for classification chips and cards */
export function classificationIcon(item) {
  const raw = item?.icon ? String(item.icon).trim() : ''
  if (raw && NAMED_ICON_EMOJI[raw]) return NAMED_ICON_EMOJI[raw]
  if (raw && !/^[a-z_]+$/.test(raw)) return raw

  const key = String(item?.slug || item?.name || item?.id || '')
  let hash = 0
  for (let i = 0; i < key.length; i += 1) {
    hash = (hash + key.charCodeAt(i) * (i + 1)) % FALLBACK_EMOJIS.length
  }
  return FALLBACK_EMOJIS[hash] || '📝'
}
