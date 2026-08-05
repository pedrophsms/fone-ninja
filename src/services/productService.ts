import { http } from '@/api/http'
import type { CreateProductPayload, Product } from '@/types/product'

export const productService = {
  list() {
    return http.get<{ data: Product[] }>('/produtos').then((r) => r.data.data)
  },
  create(payload: CreateProductPayload) {
    return http.post<{ data: Product }>('/produtos', payload).then((r) => r.data.data)
  },
}
