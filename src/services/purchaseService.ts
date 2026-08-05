import { http } from '@/api/http'
import type { CreatePurchasePayload, Purchase } from '@/types/purchase'

export const purchaseService = {
  list() {
    return http.get<{ data: Purchase[] }>('/compras').then((r) => r.data.data)
  },
  create(payload: CreatePurchasePayload, idempotencyKey: string) {
    return http.post<{ data: Purchase }>('/compras', payload, { idempotencyKey }).then((r) => r.data.data)
  },
}
