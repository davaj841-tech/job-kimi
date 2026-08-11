/**
 * Capture README screenshots with Playwright.
 *
 * Prerequisites:
 *   php artisan serve --port=8000
 *   npm run build   (or vite running)
 *   npx playwright install chromium
 *
 * Usage:
 *   npm run screenshots
 */
import { chromium } from 'playwright'
import { mkdir } from 'node:fs/promises'
import { existsSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.resolve(__dirname, '..')
const outDir = path.join(root, 'docs', 'screenshots')
const baseUrl = process.env.SCREENSHOT_BASE_URL || 'http://127.0.0.1:8000'
const login = process.env.SCREENSHOT_LOGIN || 'admin'
const password = process.env.SCREENSHOT_PASSWORD || 'admin1234'
const adminLogin = process.env.SCREENSHOT_ADMIN_LOGIN || 'admin'
const adminPassword = process.env.SCREENSHOT_ADMIN_PASSWORD || 'admin1234'

function phpContext() {
  const script = path.join(root, 'scripts', 'screenshot-context.php')
  const result = spawnSync('php', [script], { cwd: root, encoding: 'utf8' })
  if (result.status !== 0) {
    throw new Error(
      `screenshot-context.php failed:\n${result.stderr || result.stdout}`
    )
  }
  return JSON.parse(result.stdout.trim().split('\n').pop())
}

async function apiLogin(page, endpoint, body) {
  return page.evaluate(
    async ({ endpoint, body, baseUrl }) => {
      const res = await fetch(`${baseUrl}${endpoint}`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
      })
      const json = await res.json()
      if (!res.ok) {
        throw new Error(`Login failed ${res.status}: ${JSON.stringify(json)}`)
      }
      return json
    },
    { endpoint, body, baseUrl }
  )
}

async function settle(page, ms = 900) {
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {})
  await page.waitForTimeout(ms)
}

async function shot(page, file, size) {
  await page.setViewportSize(size)
  await settle(page)
  await page.screenshot({ path: path.join(outDir, file), fullPage: false })
  console.log(`✓ ${file}`)
}

async function setUserAuth(page, token, user) {
  await page.goto(baseUrl + '/', { waitUntil: 'domcontentloaded' })
  await page.evaluate(
    ({ token, user }) => {
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
    },
    { token, user }
  )
}

async function setAdminAuth(page, token, user) {
  await page.goto(baseUrl + '/admin/login', { waitUntil: 'domcontentloaded' })
  await page.evaluate(
    ({ token, user }) => {
      localStorage.setItem('admin_token', token)
      localStorage.setItem('admin_user', JSON.stringify(user))
    },
    { token, user }
  )
}

async function startExamAttempt(page, examId, token) {
  return page.evaluate(
    async ({ baseUrl, examId, token }) => {
      const res = await fetch(`${baseUrl}/api/v1/exams/${examId}/start`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      })
      const json = await res.json()
      return { ok: res.ok, status: res.status, json }
    },
    { baseUrl, examId, token }
  )
}

async function capture() {
  await mkdir(outDir, { recursive: true })
  const ctx = phpContext()
  console.log('Exam:', ctx.exam?.slug)

  const channel = process.env.PLAYWRIGHT_CHANNEL || 'chrome'
  let browser
  try {
    browser = await chromium.launch({
      headless: true,
      channel,
    })
  } catch (err) {
    console.warn(`channel=${channel} failed, trying msedge…`, err.message)
    browser = await chromium.launch({
      headless: true,
      channel: 'msedge',
    })
  }
  const context = await browser.newContext({
    locale: 'fa-IR',
    colorScheme: 'light',
  })
  const page = await context.newPage()

  await page.goto(baseUrl + '/', {
    waitUntil: 'domcontentloaded',
    timeout: 60000,
  })
  await settle(page, 1200)

  await page.goto(baseUrl + '/', { waitUntil: 'domcontentloaded' })
  await shot(page, 'home.png', { width: 1280, height: 720 })

  await page.goto(baseUrl + '/exams', { waitUntil: 'domcontentloaded' })
  await shot(page, 'exam-list.png', { width: 1280, height: 720 })

  const userLogin = await apiLogin(page, '/api/v1/auth/login', {
    login,
    password,
  })
  const userToken = userLogin.data?.token || userLogin.data?.access_token
  const userPayload = userLogin.data?.user
  if (!userToken) throw new Error('No user token from login')

  await setUserAuth(page, userToken, userPayload)

  await page.goto(baseUrl + '/subscription', { waitUntil: 'domcontentloaded' })
  await shot(page, 'subscription.png', { width: 1280, height: 720 })

  await page.goto(baseUrl + '/wallet', { waitUntil: 'domcontentloaded' })
  await shot(page, 'wallet.png', { width: 1280, height: 720 })

  const start = await startExamAttempt(page, ctx.exam.id, userToken)
  if (start.ok) {
    await page.goto(`${baseUrl}/exams/${ctx.exam.id}/take`, {
      waitUntil: 'domcontentloaded',
    })
    await shot(page, 'exam-start.png', { width: 1280, height: 720 })
    await shot(page, 'exam-question.png', { width: 1280, height: 720 })
  } else {
    console.warn('start exam failed:', start.status, JSON.stringify(start.json))
    await page.goto(`${baseUrl}/exams/${ctx.exam.slug}`, {
      waitUntil: 'domcontentloaded',
    })
    await shot(page, 'exam-start.png', { width: 1280, height: 720 })
    await shot(page, 'exam-question.png', { width: 1280, height: 720 })
  }

  if (ctx.attempt) {
    await page.goto(
      `${baseUrl}/exams/${ctx.attempt.exam_id}/result/${ctx.attempt.id}`,
      { waitUntil: 'domcontentloaded' }
    )
    await shot(page, 'exam-result.png', { width: 1280, height: 720 })
  } else {
    await page.goto(`${baseUrl}/exams/${ctx.exam.slug}`, {
      waitUntil: 'domcontentloaded',
    })
    await shot(page, 'exam-result.png', { width: 1280, height: 720 })
  }

  await page.goto(baseUrl + '/', { waitUntil: 'domcontentloaded' })
  await shot(page, 'mobile-pwa.png', { width: 390, height: 844 })

  await page.evaluate(() => localStorage.clear())
  const adminLoginRes = await apiLogin(page, '/api/v1/admin/auth/login', {
    username: adminLogin,
    password: adminPassword,
  })
  const adminToken =
    adminLoginRes.data?.token || adminLoginRes.data?.access_token
  const adminUser = adminLoginRes.data?.user
  if (!adminToken) throw new Error('No admin token')

  await setAdminAuth(page, adminToken, adminUser)
  await page.goto(baseUrl + '/admin/dashboard', {
    waitUntil: 'domcontentloaded',
  })
  await shot(page, 'admin-dashboard.png', { width: 1280, height: 720 })

  await browser.close()

  const required = [
    'home.png',
    'exam-list.png',
    'exam-start.png',
    'exam-question.png',
    'exam-result.png',
    'wallet.png',
    'subscription.png',
    'admin-dashboard.png',
    'mobile-pwa.png',
  ]
  const missing = required.filter((f) => !existsSync(path.join(outDir, f)))
  if (missing.length) {
    throw new Error(`Missing screenshots: ${missing.join(', ')}`)
  }

  console.log('\nAll 9 screenshots ready in docs/screenshots/')
}

capture().catch((err) => {
  console.error(err)
  process.exit(1)
})
