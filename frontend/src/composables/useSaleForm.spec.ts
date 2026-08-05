import { describe, expect, it, beforeEach, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useSaleForm } from './useSaleForm'
import { useSaleStore } from '@/stores/sale'
import { useProductStore } from '@/stores/product'
import { useSnackbarStore } from '@/stores/snackbar'

describe('useSaleForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('rejects duplicate product ids in the same sale', async () => {
    const { form, errors, submit } = useSaleForm()
    const saleStore = useSaleStore()
    vi.spyOn(saleStore, 'create')
    vi.spyOn(saleStore, 'preview').mockResolvedValue({ total: '0.00', lucro: '0.00', itens: [] })
    form.cliente = 'Fulano da Silva'
    form.produtos = [
      { id: 1, quantidade: 2, preco_unitario: 50 },
      { id: 1, quantidade: 1, preco_unitario: 50 },
    ]

    await submit()

    expect(errors.produtos).toEqual(['Não é possível repetir o mesmo produto na mesma venda'])
    expect(saleStore.create).not.toHaveBeenCalled()
  })

  it('shows a success message including total and lucro, and refetches products', async () => {
    const { form, submit } = useSaleForm()
    const saleStore = useSaleStore()
    const productStore = useProductStore()
    const snackbar = useSnackbarStore()
    vi.spyOn(saleStore, 'preview').mockResolvedValue({ total: '100.00', lucro: '30.00', itens: [] })
    vi.spyOn(saleStore, 'create').mockResolvedValue({
      id: 1,
      cliente: 'Fulano da Silva',
      total: '100.00',
      lucro: '30.00',
      status: 'completed',
      produtos: [],
      created_at: '2026-08-05T00:00:00Z',
    })
    vi.spyOn(productStore, 'fetchAll').mockResolvedValue()
    form.cliente = 'Fulano da Silva'
    form.produtos = [{ id: 1, quantidade: 2, preco_unitario: 50 }]

    await submit()

    expect(snackbar.message).toContain('100')
    expect(snackbar.message).toContain('30')
    expect(productStore.fetchAll).toHaveBeenCalledTimes(1)
  })

  it('surfaces an insufficient-stock error via the snackbar, not as a field error', async () => {
    const { form, submit } = useSaleForm()
    const saleStore = useSaleStore()
    const snackbar = useSnackbarStore()
    vi.spyOn(saleStore, 'preview').mockResolvedValue({ total: '0.00', lucro: '0.00', itens: [] })
    vi.spyOn(saleStore, 'create').mockRejectedValue({
      message: 'Estoque insuficiente para o produto Fone X',
    })
    form.cliente = 'Fulano da Silva'
    form.produtos = [{ id: 1, quantidade: 999, preco_unitario: 50 }]

    await submit()

    expect(snackbar.visible).toBe(true)
    expect(snackbar.message).toBe('Estoque insuficiente para o produto Fone X')
  })

  it('loads the total and lucro estimate from the backend preview when products change', async () => {
    const { form, preview } = useSaleForm()
    const saleStore = useSaleStore()
    vi.spyOn(saleStore, 'preview').mockResolvedValue({
      total: '100.00',
      lucro: '30.00',
      itens: [
        {
          id: 1,
          nome: 'Fone X',
          quantidade: 2,
          preco_unitario: '50.00',
          custo_medio: '35.00',
          subtotal: '100.00',
          lucro_item: '30.00',
        },
      ],
    })
    form.produtos = [{ id: 1, quantidade: 2, preco_unitario: 50 }]

    await flushPromises()

    expect(saleStore.preview).toHaveBeenCalledWith({
      produtos: [{ id: 1, quantidade: 2, preco_unitario: 50 }],
    })
    expect(preview.value?.total).toBe('100.00')
    expect(preview.value?.lucro).toBe('30.00')
  })
})
