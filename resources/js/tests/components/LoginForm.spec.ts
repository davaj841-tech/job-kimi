import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LoginForm from '../../components/LoginForm.vue'

describe('LoginForm', () => {
  it('submits mobile number', async () => {
    const wrapper = mount(LoginForm)
    await wrapper.find('input[type="tel"]').setValue('09123456789')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted()).toHaveProperty('submit')
    expect(wrapper.emitted('submit')?.[0]).toEqual(['09123456789'])
  })
})
