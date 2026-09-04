import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const srcRoot = path.join(__dirname, '..', 'node_modules', 'ckeditor4')
const destRoot = path.join(__dirname, '..', 'public', 'vendor', 'ckeditor')
const customConfig = path.join(__dirname, 'ckeditor-config.js')

const COPY = ['ckeditor.js', 'contents.css', 'styles.js', 'lang', 'skins', 'plugins']

function copyEntry(name) {
  const from = path.join(srcRoot, name)
  const to = path.join(destRoot, name)
  if (!fs.existsSync(from)) {
    console.warn(`[copy-ckeditor] skip missing: ${name}`)
    return
  }
  fs.cpSync(from, to, { recursive: true })
}

if (!fs.existsSync(srcRoot)) {
  console.error('[copy-ckeditor] node_modules/ckeditor4 not found — run npm install first.')
  process.exit(1)
}

fs.mkdirSync(destRoot, { recursive: true })
for (const item of COPY) {
  copyEntry(item)
}

if (fs.existsSync(customConfig)) {
  fs.copyFileSync(customConfig, path.join(destRoot, 'config.js'))
}

console.log('[copy-ckeditor] copied to public/vendor/ckeditor')
