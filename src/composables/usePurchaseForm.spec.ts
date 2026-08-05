import { describe, expect, it, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePurchaseForm } from './usePurchaseForm'
import { usePurchaseStore } from '@/stores/purchase'
import { useProductStore } from '@/stores/product'

describe('usePurchaseForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('rejects duplicate product ids in the same purchase', async () => {
    const { form, errors, submit } = usePurchaseForm()
    const purchaseStore = usePurchaseStore()
    vi.spyOn(purchaseStore, 'create')
    form.fornecedor = 'Fornecedor X'
    form.produtos = [
      { id: 1, quantidade: 5, preco_unitario: 10 },
      { id: 1, quantidade: 2, preco_unitario: 10 },
    ]

    await submit()

    expect(errors.produtos).toEqual(['Não é possível repetir o mesmo produto na mesma compra'])
    expect(purchaseStore.create).not.toHaveBeenCalled()
  })

  it('computes the subtotal preview as a plain sum of quantidade * preco_unitario', () => {
    const { form, subtotalPreview } = usePurchaseForm()
    form.produtos = [
      { id: 1, quantidade: 5, preco_unitario: 10 },
      { id: 2, quantidade: 2, preco_unitario: 3 },
    ]

    expect(subtotalPreview.value).toBe(56)
  })

  it('generates a fresh idempotency key per submit and refetches products on success', async () => {
    const { form, submit } = usePurchaseForm()
    const purchaseStore = usePurchaseStore()
    const productStore = useProductStore()
    vi.spyOn(purchaseStore, 'create').mockResolvedValue({
      id: 1,
      fornecedor: 'Fornecedor X',
      total: '50.00',
      produtos: [],
      created_at: '2026-08-05T00:00:00Z',
    })
    vi.spyOn(productStore, 'fetchAll').mockResolvedValue()
    form.fornecedor = 'Fornecedor X'
    form.produtos = [{ id: 1, quantidade: 5, preco_unitario: 10 }]

    await submit()

    expect(purchaseStore.create).toHaveBeenCalledTimes(1)
    const [, idempotencyKey] = vi.mocked(purchaseStore.create).mock.calls[0]
    expect(typeof idempotencyKey).toBe('string')
    expect(idempotencyKey.length).toBeGreaterThan(0)
    expect(productStore.fetchAll).toHaveBeenCalledTimes(1)
  })
})
