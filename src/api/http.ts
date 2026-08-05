import axios, { type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'

export interface ApiError {
  message: string
  fieldErrors?: Record<string, string[]>
}

declare module 'axios' {
  export interface AxiosRequestConfig {
    idempotencyKey?: string
  }
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
})

http.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const authStore = useAuthStore()
  if (authStore.token) {
    config.headers.set('Authorization', `Bearer ${authStore.token}`)
  }
  if (config.idempotencyKey) {
    config.headers.set('Idempotency-Key', config.idempotencyKey)
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: unknown) => Promise.reject(normalizeError(error)),
)

function normalizeError(error: unknown): ApiError {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status
    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined

    if (status === 422 && data?.errors) {
      return { message: data.message ?? 'Dados inválidos', fieldErrors: data.errors }
    }
    if (data?.message) {
      return { message: data.message }
    }
    if (status === 401) {
      return { message: 'Sessão expirada, faça login novamente' }
    }
    if (status === 429) {
      return { message: 'Muitas tentativas, aguarde um momento e tente novamente' }
    }
    return { message: 'Erro de comunicação com o servidor' }
  }
  return { message: 'Erro inesperado' }
}
