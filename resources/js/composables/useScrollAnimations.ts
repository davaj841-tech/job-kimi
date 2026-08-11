import { onMounted, onUnmounted } from 'vue'
import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

gsap.registerPlugin(ScrollTrigger)

export function useScrollAnimations(rootSelector = '.home-2026') {
  let ctx: gsap.Context | null = null

  onMounted(() => {
    const root = document.querySelector(rootSelector)
    if (!root) return
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    ctx = gsap.context(() => {
      gsap.utils.toArray<HTMLElement>('.animate-on-scroll').forEach((el) => {
        gsap.from(el, {
          y: 24,
          opacity: 0,
          duration: 0.55,
          ease: 'power2.out',
          scrollTrigger: {
            trigger: el,
            start: 'top 90%',
            toggleActions: 'play none none none',
          },
        })
      })
    }, root)
  })

  onUnmounted(() => {
    ctx?.revert()
  })
}

export function animateCountUp(
  el: HTMLElement | null,
  end: number,
  options: { decimals?: number; duration?: number } = {}
) {
  if (!el) return
  const decimals = options.decimals ?? 0
  const obj = { val: 0 }
  gsap.to(obj, {
    val: end,
    duration: options.duration ?? 1.8,
    ease: 'power2.out',
    scrollTrigger: {
      trigger: el,
      start: 'top 85%',
      once: true,
    },
    onUpdate: () => {
      el.textContent = Number(obj.val).toLocaleString('fa-IR', {
        maximumFractionDigits: decimals,
        minimumFractionDigits: decimals,
      })
    },
  })
}
