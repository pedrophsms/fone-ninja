import { defineStore } from 'pinia'
import { purchaseService } from '@/services/purchaseService'
import type { CreatePurchasePayload, Purchase } from '@/types/purchase'

interface PurchaseState {
  items: Purchase[]
  loading: boolean
}

export const usePurchaseStore = defineStore('purchase', {
  state: (): PurchaseState => ({ items: [], loading: false }),
  actions: {
    async fetchAll() {
      this.loading = true
      try {
        this.items = await purchaseService.list()
      } finally {
        this.loading = false
      }
    },
    async create(payload: CreatePurchasePayload, idempotencyKey: string) {
      const created = await purchaseService.create(payload, idempotencyKey)
      this.items.unshift(created)
      return created
    },
  },
})
