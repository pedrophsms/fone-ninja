import { reactive, ref, watch } from 'vue'
import { useSaleStore } from '@/stores/sale'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'
import type { SaleItemPayload, SalePreview } from '@/types/sale'

interface SaleFormState {
  cliente: string
  produtos: SaleItemPayload[]
}

export function useSaleForm() {
  const form = reactive<SaleFormState>({
    cliente: '',
    produtos: [{ id: 0, quantidade: 1, preco_unitario: 0 }],
  })
  const errors = reactive<Record<string, string[]>>({})
  const loading = ref(false)
  const submitted = ref(false)
  const preview = ref<SalePreview | null>(null)
  const saleStore = useSaleStore()
  const productStore = useProductStore()
  const { handle } = useApiError()
  const snackbar = useSnackbarStore()

  watch(
    () => form.produtos.map((item) => `${item.id}:${item.quantidade}:${item.preco_unitario}`).join('|'),
    async () => {
      const selected = form.produtos.filter((item) => item.id)
      if (selected.length === 0) {
        preview.value = null
        return
      }
      try {
        preview.value = await saleStore.preview({ produtos: form.produtos })
      } catch {
        preview.value = null
      }
    },
  )

  function addItem() {
    form.produtos.push({ id: 0, quantidade: 1, preco_unitario: 0 })
  }

  function removeItem(index: number) {
    if (form.produtos.length > 1) form.produtos.splice(index, 1)
  }

  function validate(): boolean {
    Object.keys(errors).forEach((key) => delete errors[key])
    if (!form.cliente.trim()) {
      errors.cliente = ['Cliente é obrigatório']
    }
    const ids = form.produtos.map((p) => p.id)
    if (new Set(ids).size !== ids.length) {
      errors.produtos = ['Não é possível repetir o mesmo produto na mesma venda']
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
      const created = await saleStore.create(
        { cliente: form.cliente, produtos: form.produtos },
        idempotencyKey,
      )
      snackbar.showSuccess(
        `Venda registrada com sucesso — total ${created.total}, lucro ${created.lucro}`,
      )
      submitted.value = true
      form.cliente = ''
      form.produtos = [{ id: 0, quantidade: 1, preco_unitario: 0 }]
      preview.value = null
      await productStore.fetchAll()
    } catch (error) {
      const fieldErrors = handle(error)
      if (fieldErrors) Object.assign(errors, fieldErrors)
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, submitted, preview, addItem, removeItem, submit }
}
