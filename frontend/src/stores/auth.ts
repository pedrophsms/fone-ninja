import { defineStore } from 'pinia'
import { authService } from '@/services/authService'
import type { AuthUser, LoginPayload, RegisterPayload } from '@/types/auth'

interface AuthState {
  token: string | null
  user: AuthUser | null
}

const TOKEN_KEY = 'fone-ninja-token'

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem(TOKEN_KEY),
    user: null,
  }),
  actions: {
    async login(payload: LoginPayload) {
      const { usuario, token } = await authService.login(payload)
      this.token = token
      this.user = usuario
      localStorage.setItem(TOKEN_KEY, token)
    },
    async register(payload: RegisterPayload) {
      const { usuario, token } = await authService.register(payload)
      this.token = token
      this.user = usuario
      localStorage.setItem(TOKEN_KEY, token)
    },
    logout() {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_KEY)
    },
  },
})
