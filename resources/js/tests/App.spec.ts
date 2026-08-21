import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import App from '../App.vue'

vi.mock('@/api', () => ({
  default: {
    post: vi.fn().mockResolvedValue({ data: {} }),
    get: vi.fn().mockResolvedValue({ data: {} }),
  },
}))

describe('App.vue', () => {
  let wrapper: VueWrapper | null = null

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
  })

  it('mounts with router and pinia', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/', component: { template: '<div>Home</div>' } }],
    })
    await router.push('/')
    await router.isReady()

    wrapper = mount(App, {
      global: {
        plugins: [pinia, router],
        stubs: {
          MainLayout: {
            template: '<div class="main-layout"><slot /></div>',
          },
        },
      },
    })

    await flushPromises()
    expect(wrapper.exists()).toBe(true)
    expect(wrapper.find('.main-layout').exists()).toBe(true)
  })
})
