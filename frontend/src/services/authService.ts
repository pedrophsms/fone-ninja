import { http } from '@/api/http'
import type { LoginPayload, LoginResponse, RegisterPayload } from '@/types/auth'

export const authService = {
  login(payload: LoginPayload) {
    return http.post<LoginResponse>('/login', payload).then((r) => r.data)
  },
  register(payload: RegisterPayload) {
    return http.post<LoginResponse>('/registro', payload).then((r) => r.data)
  },
  logout() {
    return http.post('/logout').then(() => undefined)
  },
}
