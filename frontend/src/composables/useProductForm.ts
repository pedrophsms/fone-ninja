import { reactive, ref } from 'vue'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'
import type { CreateProductPayload } from '@/types/product'

interface ProductFormState {
  nome: string
  preco_venda: number | null
  estoque_inicial: number | null
}

export function useProductForm() {
  const form = reactive<ProductFormState>({ nome: '', preco_venda: null, estoque_inicial: null })
  const errors = reactive<Record<string, string[]>>({})
  const loading = ref(false)
  const submitted = ref(false)
  const productStore = useProductStore()
  const { handle } = useApiError()
  const snackbar = useSnackbarStore()

  function validate(): boolean {
    Object.keys(errors).forEach((key) => delete errors[key])
    if (form.nome.trim().length < 3) {
      errors.nome = ['Nome deve ter no mínimo 3 caracteres']
    }
    if (!form.preco_venda || form.preco_venda <= 0) {
      errors.preco_venda = ['Preço de venda deve ser positivo']
    }
    if (
      form.estoque_inicial !== null &&
      (form.estoque_inicial < 0 || !Number.isInteger(form.estoque_inicial))
    ) {
      errors.estoque_inicial = ['Estoque inicial não pode ser negativo']
    }
    return Object.keys(errors).length === 0
  }

  async function submit() {
    if (!validate()) return
    loading.value = true
    try {
      const payload: CreateProductPayload = { nome: form.nome, preco_venda: form.preco_venda! }
      if (form.estoque_inicial !== null) {
        payload.estoque_inicial = form.estoque_inicial
      }
      await productStore.create(payload)
      snackbar.showSuccess('Produto cadastrado com sucesso')
      submitted.value = true
      form.nome = ''
      form.preco_venda = null
      form.estoque_inicial = null
    } catch (error) {
      const fieldErrors = handle(error)
      if (fieldErrors) Object.assign(errors, fieldErrors)
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, submitted, submit }
}
