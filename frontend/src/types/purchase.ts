export interface PurchaseItemPayload {
  id: number
  quantidade: number
  preco_unitario: number
}

export interface CreatePurchasePayload {
  fornecedor: string
  produtos: PurchaseItemPayload[]
}

export interface PurchaseItem {
  id: number
  nome: string
  quantidade: number
  preco_unitario: string
  subtotal: string
}

export interface Purchase {
  id: number
  fornecedor: string
  total: string
  produtos: PurchaseItem[]
  created_at?: string
}
