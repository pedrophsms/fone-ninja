import { describe, expect, it, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useProductForm } from './useProductForm'
import { useProductStore } from '@/stores/product'

describe('useProductForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('rejects a name shorter than 3 characters without calling the store', async () => {
    const { form, errors, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create')
    form.nome = 'ab'
    form.preco_venda = 10

    await submit()

    expect(errors.nome).toEqual(['Nome deve ter no mínimo 3 caracteres'])
    expect(store.create).not.toHaveBeenCalled()
  })

  it('rejects a non-positive preco_venda without calling the store', async () => {
    const { form, errors, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create')
    form.nome = 'Fone Bluetooth'
    form.preco_venda = 0

    await submit()

    expect(errors.preco_venda).toEqual(['Preço de venda deve ser positivo'])
    expect(store.create).not.toHaveBeenCalled()
  })

  it('calls the store and resets the form on valid submit', async () => {
    const { form, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create').mockResolvedValue({
      id: 1,
      nome: 'Fone Bluetooth',
      custo_medio: '0.00',
      preco_venda: '50.00',
      estoque: 0,
    })
    form.nome = 'Fone Bluetooth'
    form.preco_venda = 50

    await submit()

    expect(store.create).toHaveBeenCalledWith({ nome: 'Fone Bluetooth', preco_venda: 50 })
    expect(form.nome).toBe('')
    expect(form.preco_venda).toBeNull()
    expect(form.estoque_inicial).toBeNull()
  })

  it('rejects a negative estoque_inicial without calling the store', async () => {
    const { form, errors, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create')
    form.nome = 'Fone Bluetooth'
    form.preco_venda = 50
    form.estoque_inicial = -1

    await submit()

    expect(errors.estoque_inicial).toEqual(['Estoque inicial não pode ser negativo'])
    expect(store.create).not.toHaveBeenCalled()
  })

  it('sends estoque_inicial when set', async () => {
    const { form, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create').mockResolvedValue({
      id: 2,
      nome: 'Fone Gamer',
      custo_medio: '0.00',
      preco_venda: '300.00',
      estoque: 10,
    })
    form.nome = 'Fone Gamer'
    form.preco_venda = 300
    form.estoque_inicial = 10

    await submit()

    expect(store.create).toHaveBeenCalledWith({
      nome: 'Fone Gamer',
      preco_venda: 300,
      estoque_inicial: 10,
    })
  })
})
