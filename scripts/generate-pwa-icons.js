import sharp from 'sharp'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const root = path.resolve(__dirname, '..')
const inputSvg = path.join(root, 'resources/assets/icon-maskable.svg')
const outDir = path.join(root, 'public/icons')

const brand = { r: 239, g: 57, b: 78, alpha: 1 }
const sizes = [152, 192, 512]

if (!fs.existsSync(inputSvg)) {
  console.error('Missing SVG:', inputSvg)
  process.exit(1)
}

fs.mkdirSync(outDir, { recursive: true })

const svg = fs.readFileSync(inputSvg)

for (const size of sizes) {
  const out = path.join(outDir, `maskable-icon-${size}.png`)
  await sharp(svg)
    .resize(size, size, {
      fit: 'contain',
      background: brand,
    })
    .png()
    .toFile(out)
  console.log('wrote', path.relative(root, out))
}

// Ensure standard any-purpose icons exist (regenerate from same SVG if missing)
for (const size of [192, 512]) {
  const target = path.join(outDir, `icon-${size}.png`)
  if (!fs.existsSync(target)) {
    await sharp(svg)
      .resize(size, size, { fit: 'contain', background: brand })
      .png()
      .toFile(target)
    console.log('wrote', path.relative(root, target))
  }
}

console.log('PWA icons generated.')
