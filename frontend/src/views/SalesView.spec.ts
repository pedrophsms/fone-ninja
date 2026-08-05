import { describe, expect, it, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import SalesView from './SalesView.vue'
import { http } from '@/api/http'

describe('SalesView', () => {
  let mockHttp: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    mockHttp = new MockAdapter(http)
    mockHttp.onGet('/produtos').reply(200, { data: [] })
    mockHttp.onPost('/vendas/preview').reply(200, { total: '0.00', lucro: '0.00', itens: [] })
    mockHttp.onGet('/vendas').reply(200, {
      data: [
        {
          id: 1,
          cliente: 'Fulano da Silva',
          total: '100.00',
          lucro: '30.00',
          status: 'cancelled',
          produtos: [],
          created_at: '',
        },
        {
          id: 2,
          cliente: 'Ciclano',
          total: '200.00',
          lucro: '60.00',
          status: 'completed',
          produtos: [],
          created_at: '',
        },
      ],
    })
  })

  afterEach(() => {
    mockHttp.restore()
  })

  it('disables the Cancelar button only for already-cancelled sales', async () => {
    const wrapper = mount(SalesView)
    await new Promise((resolve) => setTimeout(resolve, 0))
    await wrapper.vm.$nextTick()

    const buttons = wrapper.findAll('button').filter((b) => b.text() === 'Cancelar')
    expect(buttons).toHaveLength(2)
    expect(buttons[0].attributes('disabled')).toBeUndefined()
    expect(buttons[1].attributes('disabled')).toBeDefined()
  })
})
