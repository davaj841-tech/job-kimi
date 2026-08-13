export const OPERATOR_PERMISSIONS = [
  { key: 'users', label: 'کاربران' },
  { key: 'tickets', label: 'تیکت‌ها' },
  { key: 'exams', label: 'آزمون‌ها' },
  { key: 'questions', label: 'سوالات' },
  { key: 'blog', label: 'بلاگ' },
  { key: 'generated_contents', label: 'تولید محتوا' },
  { key: 'pdf', label: 'فروشگاه فایل' },
  { key: 'banners', label: 'بنرها' },
  { key: 'pages', label: 'صفحات' },
  { key: 'ai', label: 'هوش مصنوعی' },
  { key: 'job_posts', label: 'آگهی‌ها' },
  { key: 'aggregation', label: 'تجمیع آگهی' },
  { key: 'subscriptions', label: 'اشتراک‌ها' },
  { key: 'transactions', label: 'تراکنش‌ها' },
  { key: 'coupons', label: 'کد تخفیف' },
  { key: 'wallets', label: 'کیف پول‌ها' },
] as const

export const DEFAULT_OPERATOR_PERMISSIONS = [
  'exams',
  'questions',
  'blog',
  'job_posts',
  'tickets',
]

export type OperatorPermissionKey = (typeof OPERATOR_PERMISSIONS)[number]['key']
