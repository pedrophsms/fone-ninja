import { describe, expect, it } from 'vitest'
import MockAdapter from 'axios-mock-adapter'
import { http } from './http'

describe('http error normalization', () => {
  it('extracts fieldErrors from a 422 validation response', async () => {
    const mock = new MockAdapter(http)
    mock.onPost('/produtos').reply(422, {
      message: 'The given data was invalid.',
      errors: { nome: ['O campo nome é obrigatório.'] },
    })

    await expect(http.post('/produtos', {})).rejects.toMatchObject({
      message: 'The given data was invalid.',
      fieldErrors: { nome: ['O campo nome é obrigatório.'] },
    })

    mock.restore()
  })

  it('extracts a plain message from a business-rule error', async () => {
    const mock = new MockAdapter(http)
    mock.onPost('/vendas').reply(422, {
      message: 'Estoque insuficiente para o produto Fone X',
    })

    await expect(http.post('/vendas', {})).rejects.toMatchObject({
      message: 'Estoque insuficiente para o produto Fone X',
    })

    mock.restore()
  })

  it('forwards the idempotencyKey config field as an Idempotency-Key header', async () => {
    const mock = new MockAdapter(http)
    mock.onPost('/compras').reply((config) => {
      expect(config.headers?.['Idempotency-Key']).toBe('test-key-123')
      return [201, {}]
    })

    await http.post('/compras', {}, { idempotencyKey: 'test-key-123' })

    mock.restore()
  })
})
