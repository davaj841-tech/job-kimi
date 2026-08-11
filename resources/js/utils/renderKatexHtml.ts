import katex from 'katex'
import 'katex/dist/katex.min.css'

/**
 * Render HTML that may contain math delimiters:
 * $...$, $$...$$, \(...\), \[...\]
 */
export function renderKatexHtml(raw: string | null | undefined): string {
  if (!raw) return ''
  try {
    const html = String(raw)
      .replace(/\$\$([\s\S]+?)\$\$/g, (_, expr: string) =>
        katex.renderToString(expr.trim(), {
          throwOnError: false,
          displayMode: true,
        })
      )
      .replace(/\\\[([\s\S]+?)\\\]/g, (_, expr: string) =>
        katex.renderToString(expr.trim(), {
          throwOnError: false,
          displayMode: true,
        })
      )
      .replace(/\$([^$\n]+?)\$/g, (_, expr: string) =>
        katex.renderToString(expr.trim(), {
          throwOnError: false,
          displayMode: false,
        })
      )
      .replace(/\\\(([\s\S]+?)\\\)/g, (_, expr: string) =>
        katex.renderToString(expr.trim(), {
          throwOnError: false,
          displayMode: false,
        })
      )

    return html
  } catch {
    return String(raw)
  }
}
