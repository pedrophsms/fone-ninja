import { describe, expect, it, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import MockAdapter from 'axios-mock-adapter'
import PurchasesView from './PurchasesView.vue'
import { http } from '@/api/http'
import { useProductStore } from '@/stores/product'

describe('PurchasesView', () => {
  let mockHttp: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    mockHttp = new MockAdapter(http, { delayResponse: 20 })
    mockHttp.onGet('/produtos').reply(200, { data: [] })
    mockHttp.onGet('/compras').reply(200, { data: [] })
  })

  afterEach(() => {
    mockHttp.restore()
  })

  it('disables the submit button while a purchase request is in flight, allowing only one call', async () => {
    let callCount = 0
    mockHttp.onPost('/compras').reply(() => {
      callCount += 1
      return [201, { data: { id: 1, fornecedor: 'Fornecedor X', total: '50.00', produtos: [], created_at: '' } }]
    })
    const productStore = useProductStore()
    productStore.items = [{ id: 1, nome: 'Fone X', custo_medio: '5.00', preco_venda: '10.00', estoque: 100 }]

    const wrapper = mount(PurchasesView)
    await wrapper.vm.$nextTick()

    const form = wrapper.vm as unknown as {
      form: { fornecedor: string; produtos: Array<{ id: number; quantidade: number; preco_unitario: number }> }
      submit: () => Promise<void>
      loading: boolean
    }
    form.form.fornecedor = 'Fornecedor X'
    form.form.produtos = [{ id: 1, quantidade: 5, preco_unitario: 10 }]

    const firstSubmit = form.submit()
    const secondSubmit = form.submit()
    await Promise.all([firstSubmit, secondSubmit])

    expect(callCount).toBe(1)
  })
})
