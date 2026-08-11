/**
 * Generates docs/site-errors-template.xlsx — fill and send back for bugfix.
 */
import * as XLSX from 'xlsx'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const outDir = path.resolve(__dirname, '../docs')
fs.mkdirSync(outDir, { recursive: true })

const headers = [
  'شماره',
  'تاریخ مشاهده',
  'آدرس صفحه (URL)',
  'دستگاه (موبایل/تبلت/دسکتاپ)',
  'مرورگر',
  'شدت (کم/متوسط/بحرانی)',
  'عنوان خطا',
  'شرح کامل مشکل',
  'مراحل بازتولید',
  'پیام خطا یا متن دقیق روی صفحه',
  'مسیر اسکرین‌شات (اختیاری)',
  'وضعیت (جدید/در حال بررسی/حل شد)',
  'اولویت شما (۱ تا ۵)',
  'یادداشت اضافی',
]

const samples = [
  [
    1,
    '1405/05/20',
    'https://example.com/',
    'موبایل',
    'Chrome',
    'متوسط',
    'نمونه: دکمه ثبت‌نام کار نمی‌کند',
    'با زدن دکمه هیچ اتفاقی نمی‌افتد',
    '1) ورود به صفحه اول 2) زدن دکمه ثبت‌نام',
    'بدون پیام خطا',
    '',
    'جدید',
    3,
    'این ردیف نمونه است — پاک کنید و خطاهای واقعی را بنویسید',
  ],
  [
    2,
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    'جدید',
    '',
    '',
  ],
  [
    3,
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    'جدید',
    '',
    '',
  ],
]

const guideHeaders = ['ستون', 'راهنما']
const guideRows = [
  ['آدرس صفحه', 'آدرس دقیق صفحه‌ای که مشکل دارد را کپی کنید'],
  ['شدت', 'کم = ظاهر جزئی | متوسط = کار نمی‌کند ولی راه جایگزین هست | بحرانی = سایت از کار افتاده'],
  ['مراحل بازتولید', 'قدم‌به‌قدم بنویسید تا بتوانم همان خطا را ببینم'],
  ['اسکرین‌شات', 'اگر عکس دارید در پوشه docs/error-screenshots بگذارید و نام فایل را بنویسید'],
  ['وضعیت', 'برای شما معمولاً «جدید» بگذارید؛ من بعد از رفع به «حل شد» تغییر می‌دهم'],
]

const wb = XLSX.utils.book_new()
const wsData = [headers, ...samples]
const ws = XLSX.utils.aoa_to_sheet(wsData)
ws['!cols'] = headers.map((h) => ({ wch: Math.min(36, Math.max(14, h.length + 4)) }))
XLSX.utils.book_append_sheet(wb, ws, 'خطاها')

const guide = XLSX.utils.aoa_to_sheet([guideHeaders, ...guideRows])
guide['!cols'] = [{ wch: 18 }, { wch: 70 }]
XLSX.utils.book_append_sheet(wb, guide, 'راهنما')

const outXlsx = path.join(outDir, 'site-errors-template.xlsx')
XLSX.writeFile(wb, outXlsx)

// UTF-8 BOM CSV for Excel compatibility without xlsx
const csvPath = path.join(outDir, 'site-errors-template.csv')
const csvLines = [headers, ...samples].map((row) =>
  row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')
)
fs.writeFileSync(csvPath, '\uFEFF' + csvLines.join('\n'), 'utf8')

const readme = `# گزارش خطاهای سایت جاب‌آزمون

## چطور استفاده کنید؟

1. فایل \`site-errors-template.xlsx\` را با Excel یا Google Sheets باز کنید.
2. ردیف نمونه را پاک یا نگه دارید.
3. هر باگ را در یک ردیف بنویسید (هرچه دقیق‌تر بهتر).
4. اگر اسکرین‌شات دارید، در پوشه \`docs/error-screenshots/\` بگذارید و نام فایل را در ستون مربوطه بنویسید.
5. فایل پرشده را در چت برای من بفرستید تا همان موارد را رفع کنم.

## نکته

- ترجیحاً همان فایل Excel را بفرستید (نه فقط عکس).
- برای هر خطا یک ردیف جدا بنویسید.
- آدرس صفحه و مراحل بازتولید خیلی مهم است.
`

fs.writeFileSync(path.join(outDir, 'README-site-errors.md'), readme, 'utf8')
fs.mkdirSync(path.join(outDir, 'error-screenshots'), { recursive: true })
fs.writeFileSync(
  path.join(outDir, 'error-screenshots', '.gitkeep'),
  '',
  'utf8'
)

console.log('Wrote', outXlsx)
console.log('Wrote', csvPath)
