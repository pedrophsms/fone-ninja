import { http } from '@/api/http'
import type { CreateSalePayload, Sale, SalePreview, SalePreviewPayload } from '@/types/sale'

export const saleService = {
  list() {
    return http.get<{ data: Sale[] }>('/vendas').then((r) => r.data.data)
  },
  create(payload: CreateSalePayload, idempotencyKey: string) {
    return http.post<{ data: Sale }>('/vendas', payload, { idempotencyKey }).then((r) => r.data.data)
  },
  preview(payload: SalePreviewPayload) {
    return http.post<SalePreview>('/vendas/preview', payload).then((r) => r.data)
  },
  cancel(id: number) {
    return http.post<{ data: Sale }>(`/vendas/${id}/cancelar`).then((r) => r.data.data)
  },
}
