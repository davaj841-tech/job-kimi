const GROUPS = {
  it: [
    'Office',
    'Excel پیشرفته',
    'Word',
    'PowerPoint',
    'SQL',
    'Python',
    'Java',
    'JavaScript',
    'TypeScript',
    'PHP',
    'Laravel',
    'Vue.js',
    'React',
    'Git',
    'Docker',
    'Linux',
    'شبکه',
    'امنیت اطلاعات',
    'تحلیل داده',
  ],
  bank: [
    'امور بانکی',
    'اعتبارات',
    'صندوق',
    'مشتری‌مداری',
    'Excel',
    'حسابداری',
    'مبارزه با پولشویی',
    'قوانین بانکی',
    'کار تیمی',
  ],
  account: [
    'حسابداری مالی',
    'حسابداری صنعتی',
    'مالیات',
    'Excel',
    'نرم‌افزار حسابداری',
    'حسابرسی',
    'بودجه‌بندی',
  ],
  law: ['حقوق مدنی', 'آیین دادرسی', 'نگارش حقوقی', 'مذاکره', 'قوانین کار'],
  manage: [
    'مدیریت پروژه',
    'برنامه‌ریزی',
    'رهبری تیم',
    'گزارش‌نویسی',
    'Office',
    'مذاکره',
  ],
  eng: [
    'اتوکد',
    'نقشه‌کشی',
    'کنترل پروژه',
    'HSE',
    'Office',
    'تحلیل فنی',
  ],
  general: [
    'کار تیمی',
    'ارتباط مؤثر',
    'حل مسئله',
    'Office',
    'گزارش‌نویسی',
    'نظم و دقت',
  ],
}

function pick(text) {
  const t = String(text || '')
  if (/کامپیوتر|نرم‌افزار|برنامه|IT|داده|شبکه|هوش/.test(t)) return 'it'
  if (/بانک|پول|اعتبار|مالیات بانکی/.test(t)) return 'bank'
  if (/حسابدار/.test(t)) return 'account'
  if (/حقوق|فقه|قضایی/.test(t)) return 'law'
  if (/مدیریت|MBA|بازرگانی/.test(t)) return 'manage'
  if (/مهندسی|عمران|برق|مکانیک|معماری/.test(t)) return 'eng'
  return 'general'
}

export function suggestedSkillsFor(fieldOfStudy, targetJob) {
  const keys = new Set([pick(fieldOfStudy), pick(targetJob), 'general'])
  const out = []
  keys.forEach((k) => {
    ;(GROUPS[k] || []).forEach((s) => {
      if (!out.includes(s)) out.push(s)
    })
  })
  return out.slice(0, 18)
}
