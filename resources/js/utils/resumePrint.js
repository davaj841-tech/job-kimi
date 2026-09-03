/**
 * Print the live resume preview (same look as on screen) via hidden iframe.
 */

const PRINT_CSS = `
  @page { size: A4; margin: 10mm; }
  html, body {
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
    color: #0f172a !important;
    color-scheme: light !important;
  }
  body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .resume-a4 {
    width: 190mm !important;
    min-height: auto !important;
    margin: 0 auto !important;
    box-shadow: none !important;
    transform: none !important;
    zoom: 1 !important;
    background: #fff !important;
  }
`

function stylesheetLinksHtml() {
  return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
    .map((link) => (link.href ? `<link rel="stylesheet" href="${link.href}" />` : ''))
    .join('\n')
}

function inlineResumeStylesHtml() {
  return Array.from(document.querySelectorAll('style'))
    .map((el) => {
      const text = el.textContent || ''
      if (!text.includes('.resume-a4') && !text.includes('.cv-')) return ''
      return `<style>${text}</style>`
    })
    .join('\n')
}

function waitForImages(doc) {
  const images = Array.from(doc.images || [])
  if (!images.length) return Promise.resolve()
  return Promise.all(
    images.map(
      (img) =>
        new Promise((resolve) => {
          if (img.complete) {
            resolve()
            return
          }
          img.onload = () => resolve()
          img.onerror = () => resolve()
        })
    )
  )
}

function waitForStyleSheets(doc) {
  const links = Array.from(doc.querySelectorAll('link[rel="stylesheet"]'))
  if (!links.length) return Promise.resolve()
  return Promise.all(
    links.map(
      (node) =>
        new Promise((resolve) => {
          if (node.sheet) {
            resolve()
            return
          }
          node.addEventListener('load', () => resolve(), { once: true })
          node.addEventListener('error', () => resolve(), { once: true })
          setTimeout(resolve, 2500)
        })
    )
  )
}

export function getResumePreviewEl() {
  const inModal = document.querySelector(
    '[data-resume-preview-modal] .resume-a4'
  )
  if (inModal) return inModal
  return document.querySelector('.resume-a4')
}

/** @param {{ el?: HTMLElement }} opts */
export async function printResumePreview(opts = {}) {
  const el = opts.el || getResumePreviewEl()
  if (!el) {
    throw new Error('پیش‌نمایش رزومه پیدا نشد.')
  }

  if (document.fonts?.ready) {
    await document.fonts.ready
  }

  const iframe = document.createElement('iframe')
  iframe.setAttribute('title', 'چاپ رزومه')
  Object.assign(iframe.style, {
    position: 'fixed',
    right: '0',
    bottom: '0',
    width: '0',
    height: '0',
    border: '0',
    opacity: '0',
    pointerEvents: 'none',
  })
  document.body.appendChild(iframe)

  const doc = iframe.contentDocument || iframe.contentWindow?.document
  if (!doc) {
    iframe.remove()
    throw new Error('امکان ساخت صفحه چاپ نبود.')
  }

  const html = el.outerHTML.replace(/\s*id="resume-preview"/, '')
  doc.open()
  doc.write(`<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8" />
<title>چاپ رزومه</title>
${stylesheetLinksHtml()}
${inlineResumeStylesHtml()}
<style>${PRINT_CSS}</style>
</head>
<body>${html}</body>
</html>`)
  doc.close()

  try {
    await waitForStyleSheets(doc)
    await waitForImages(doc)
    await new Promise((r) => setTimeout(r, 300))
    const win = iframe.contentWindow
    if (!win) throw new Error('امکان چاپ نبود.')
    win.focus()
    win.print()
  } finally {
    setTimeout(() => {
      try {
        iframe.remove()
      } catch {
        /* ignore */
      }
    }, 1500)
  }
}
