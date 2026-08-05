import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from './auth'
import { authService } from '@/services/authService'

vi.mock('@/services/authService', () => ({
  authService: { login: vi.fn(), logout: vi.fn() },
}))

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  it('stores the token and user on successful login', async () => {
    vi.mocked(authService.login).mockResolvedValue({
      usuario: { id: 1, nome: 'Test User', email: 'test@example.com' },
      token: 'abc123',
    })
    const store = useAuthStore()

    await store.login({ email: 'test@example.com', senha: 'password' })

    expect(store.token).toBe('abc123')
    expect(store.user?.email).toBe('test@example.com')
    expect(localStorage.getItem('fone-ninja-token')).toBe('abc123')
  })

  it('clears token, user, and localStorage on logout', () => {
    const store = useAuthStore()
    store.token = 'abc123'
    localStorage.setItem('fone-ninja-token', 'abc123')

    store.logout()

    expect(store.token).toBeNull()
    expect(store.user).toBeNull()
    expect(localStorage.getItem('fone-ninja-token')).toBeNull()
  })
})
