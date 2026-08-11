import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EmptyState from '../../components/EmptyState.vue'

describe('EmptyState', () => {
  it('renders title and description', () => {
    const wrapper = mount(EmptyState, {
      props: {
        title: 'خالی',
        description: 'موردی نیست',
        icon: '∅',
      },
    })

    expect(wrapper.text()).toContain('خالی')
    expect(wrapper.text()).toContain('موردی نیست')
  })
})
