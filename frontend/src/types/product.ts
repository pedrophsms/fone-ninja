export interface Product {
  id: number
  nome: string
  custo_medio: string
  preco_venda: string
  estoque: number
}

export interface CreateProductPayload {
  nome: string
  preco_venda: number
  estoque_inicial?: number
}
