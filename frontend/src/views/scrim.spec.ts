import { describe, expect, it, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import ProductsView from '@/views/ProductsView.vue'
import { http } from '@/api/http'

describe('sheet', () => {
  let mockHttp: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    mockHttp = new MockAdapter(http)
    mockHttp.onGet('/produtos').reply(200, { data: [] })
  })

  afterEach(() => {
    mockHttp.restore()
  })

  async function openSheet() {
    const wrapper = mount(ProductsView)
    await new Promise((resolve) => setTimeout(resolve, 0))
    await wrapper.vm.$nextTick()
    const button = wrapper.findAll('button').find((b) => b.text().includes('Novo produto'))
    await button!.trigger('click')
    await new Promise((resolve) => setTimeout(resolve, 80))
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('renders a scrim behind the sheet', async () => {
    await openSheet()
    expect(document.body.innerHTML).toContain('bg-black/50')
  })

  it('keeps the sheet open when clicking inside it', async () => {
    await openSheet()
    const content = document.querySelector('[role="dialog"]')!
    const inner = content.querySelector('input')!
    inner.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true }))
    await new Promise((resolve) => setTimeout(resolve, 30))
    expect(document.querySelector('[role="dialog"]')).toBeTruthy()
  })
})
