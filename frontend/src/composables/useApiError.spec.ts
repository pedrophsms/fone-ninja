import { describe, expect, it, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useApiError } from './useApiError'
import { useSnackbarStore } from '@/stores/snackbar'
import type { ApiError } from '@/api/http'

describe('useApiError', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns fieldErrors without touching the snackbar when present', () => {
    const snackbar = useSnackbarStore()
    const { handle } = useApiError()
    const error: ApiError = { message: 'invalid', fieldErrors: { nome: ['required'] } }

    const result = handle(error)

    expect(result).toEqual({ nome: ['required'] })
    expect(snackbar.visible).toBe(false)
  })

  it('pushes the message to the snackbar when there are no fieldErrors', () => {
    const snackbar = useSnackbarStore()
    const { handle } = useApiError()
    const error: ApiError = { message: 'Estoque insuficiente para o produto X' }

    const result = handle(error)

    expect(result).toBeUndefined()
    expect(snackbar.visible).toBe(true)
    expect(snackbar.color).toBe('error')
    expect(snackbar.message).toBe('Estoque insuficiente para o produto X')
  })
})
