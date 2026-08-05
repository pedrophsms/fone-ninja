export interface LoginPayload {
  email: string
  senha: string
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
