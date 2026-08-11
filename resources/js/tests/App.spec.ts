import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import App from '../App.vue'

vi.mock('@/api', () => ({
  default: {
    post: vi.fn().mockResolvedValue({ data: {} }),
    get: vi.fn().mockResolvedValue({ data: {} }),
  },
}))

describe('App.vue', () => {
  it('mounts with router and pinia', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const router = createRouter({
      history: createWebHistory(),
      routes: [{ path: '/', component: { template: '<div>Home</div>' } }],
    })
    await router.push('/')
    await router.isReady()

    const wrapper = mount(App, {
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
