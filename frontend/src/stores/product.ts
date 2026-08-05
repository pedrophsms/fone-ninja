import { defineStore } from 'pinia'
import { productService } from '@/services/productService'
import type { CreateProductPayload, Product } from '@/types/product'

interface ProductState {
  items: Product[]
  loading: boolean
}

export const useProductStore = defineStore('product', {
  state: (): ProductState => ({ items: [], loading: false }),
  actions: {
    async fetchAll() {
      this.loading = true
      try {
        this.items = await productService.list()
      } finally {
        this.loading = false
      }
    },
    async create(payload: CreateProductPayload) {
      const created = await productService.create(payload)
      this.items.push(created)
      return created
    },
  },
})
