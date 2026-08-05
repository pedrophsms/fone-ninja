export interface LoginPayload {
  email: string
  senha: string
}

export interface RegisterPayload {
  nome: string
  email: string
  senha: string
  senha_confirmation: string
}

export interface AuthUser {
  id: number
  nome: string
  email: string
}

export interface LoginResponse {
  usuario: AuthUser
  token: string
}
