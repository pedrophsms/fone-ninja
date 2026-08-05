import { defineStore } from 'pinia'
import { saleService } from '@/services/saleService'
import type { CreateSalePayload, Sale, SalePreview, SalePreviewPayload } from '@/types/sale'

interface SaleState {
  items: Sale[]
  loading: boolean
}

export const useSaleStore = defineStore('sale', {
  state: (): SaleState => ({ items: [], loading: false }),
  actions: {
    async fetchAll() {
      this.loading = true
      try {
        this.items = await saleService.list()
      } finally {
        this.loading = false
      }
    },
    async create(payload: CreateSalePayload, idempotencyKey: string) {
      const created = await saleService.create(payload, idempotencyKey)
      this.items.unshift(created)
      return created
    },
    async cancel(id: number) {
      const cancelled = await saleService.cancel(id)
      const index = this.items.findIndex((sale) => sale.id === id)
      if (index !== -1) this.items[index] = cancelled
      return cancelled
    },
    async preview(payload: SalePreviewPayload): Promise<SalePreview> {
      return saleService.preview(payload)
    },
  },
})
