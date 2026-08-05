import { computed, reactive, ref } from 'vue'
import { usePurchaseStore } from '@/stores/purchase'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'
import type { PurchaseItemPayload } from '@/types/purchase'

interface PurchaseFormState {
  fornecedor: string
  produtos: PurchaseItemPayload[]
}

export function usePurchaseForm() {
  const form = reactive<PurchaseFormState>({
    fornecedor: '',
    produtos: [{ id: 0, quantidade: 1, preco_unitario: 0 }],
  })
  const errors = reactive<Record<string, string[]>>({})
  const loading = ref(false)
  const purchaseStore = usePurchaseStore()
  const productStore = useProductStore()
  const { handle } = useApiError()
  const snackbar = useSnackbarStore()

  const subtotalPreview = computed(() =>
    form.produtos.reduce((sum, item) => sum + item.quantidade * item.preco_unitario, 0),
  )

  function addItem() {
    form.produtos.push({ id: 0, quantidade: 1, preco_unitario: 0 })
  }

  function removeItem(index: number) {
    if (form.produtos.length > 1) form.produtos.splice(index, 1)
  }

  function validate(): boolean {
    Object.keys(errors).forEach((key) => delete errors[key])
    if (!form.fornecedor.trim()) {
      errors.fornecedor = ['Fornecedor é obrigatório']
    }
    const ids = form.produtos.map((p) => p.id)
    if (new Set(ids).size !== ids.length) {
      errors.produtos = ['Não é possível repetir o mesmo produto na mesma compra']
    }
    form.produtos.forEach((item, index) => {
      if (!item.id) errors[`produtos.${index}.id`] = ['Selecione um produto']
      if (item.quantidade < 1) errors[`produtos.${index}.quantidade`] = ['Quantidade mínima é 1']
      if (item.preco_unitario < 0.01) {
        errors[`produtos.${index}.preco_unitario`] = ['Preço unitário deve ser no mínimo 0.01']
      }
    })
    return Object.keys(errors).length === 0
  }

  async function submit() {
    if (loading.value) return
    if (!validate()) return
    loading.value = true
    const idempotencyKey = crypto.randomUUID()
    try {
      await purchaseStore.create({ fornecedor: form.fornecedor, produtos: form.produtos }, idempotencyKey)
      snackbar.showSuccess('Compra registrada com sucesso')
      form.fornecedor = ''
      form.produtos = [{ id: 0, quantidade: 1, preco_unitario: 0 }]
      await productStore.fetchAll()
    } catch (error) {
      const fieldErrors = handle(error)
      if (fieldErrors) Object.assign(errors, fieldErrors)
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, subtotalPreview, addItem, removeItem, submit }
}
