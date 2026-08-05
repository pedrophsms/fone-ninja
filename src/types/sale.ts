export interface SaleItemPayload {
  id: number
  quantidade: number
  preco_unitario: number
}

export interface CreateSalePayload {
  cliente: string
  produtos: SaleItemPayload[]
}

export interface SaleItem {
  id: number
  nome: string
  quantidade: number
  preco_unitario: string
  subtotal: string
}

export type SaleStatus = 'completed' | 'cancelled'

export interface Sale {
  id: number
  cliente: string
  total: string
  lucro: string
  status: SaleStatus
  produtos: SaleItem[]
  created_at?: string
}

export interface SalePreviewPayload {
  produtos: SaleItemPayload[]
}

export interface SalePreviewItem {
  id: number
  nome: string
  quantidade: number
  preco_unitario: string
  custo_medio: string
  subtotal: string
  lucro_item: string
}

export interface SalePreview {
  total: string
  lucro: string
  itens: SalePreviewItem[]
}
