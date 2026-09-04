export const NAMED_ICON_EMOJI = {
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

/** Preset emojis for admin classification picker */
export const CLASSIFICATION_ICON_OPTIONS = [
  '🏦',
  '🏛️',
  '🛡️',
  '🎓',
  '🏫',
  '🏙️',
  '🏢',
  '💼',
  '📚',
  '👥',
  '📋',
  '🏥',
  '⚖️',
  '🏭',
  '🧪',
  '🔬',
  '⛽',
  '⚡',
  '🛢️',
  '🚢',
  '✈️',
  '🚂',
  '🚌',
  '🚓',
  '🚒',
  '🎖️',
  '⚔️',
  '🕌',
  '⛪',
  '🏟️',
  '🎭',
  '🎬',
  '🎤',
  '🎨',
  '💻',
  '🖥️',
  '📱',
  '📡',
  '🛰️',
  '📰',
  '✍️',
  '📊',
  '📈',
  '💰',
  '💳',
  '🛒',
  '🏪',
  '🏗️',
  '🔧',
  '⚙️',
  '🛠️',
  '🚜',
  '🌾',
  '🌳',
  '🌍',
  '♻️',
  '💊',
  '🩺',
  '🦷',
  '🧬',
  '👶',
  '👩‍⚕️',
  '👨‍🏫',
  '👨‍💼',
  '👷',
  '🧑‍✈️',
  '🧑‍🔬',
  '🧑‍💻',
  '🔑',
  '📌',
  '⭐',
  '🌟',
  '🔥',
  '💡',
  '🎯',
  '🏆',
  '📝',
  '🗂️',
  '📂',
]

const FALLBACK_EMOJIS = CLASSIFICATION_ICON_OPTIONS.slice(0, 24)

/** Resolve stored icon (named key or emoji) to a display emoji */
export function resolveIconEmoji(icon, fallback = '📋') {
  const raw = icon ? String(icon).trim() : ''
  if (!raw) return fallback
  const named = NAMED_ICON_EMOJI[raw] || NAMED_ICON_EMOJI[raw.toLowerCase()]
  if (named) return named
  if (/^[a-z_]+$/i.test(raw)) return fallback
  return raw
}

/** Small emoji/icon for classification chips and cards */
export function classificationIcon(item) {
  const raw = item?.icon ? String(item.icon).trim() : ''
  if (raw) {
    const resolved = resolveIconEmoji(raw, '')
    if (resolved) return resolved
  }

  const key = String(item?.slug || item?.name || item?.id || '')
  let hash = 0
  for (let i = 0; i < key.length; i += 1) {
    hash = (hash + key.charCodeAt(i) * (i + 1)) % FALLBACK_EMOJIS.length
  }
  return FALLBACK_EMOJIS[hash] || '📝'
}
