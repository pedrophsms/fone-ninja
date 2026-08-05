import { reactive, ref } from 'vue'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'

interface ProductFormState {
  nome: string
  preco_venda: number | null
}

export function useProductForm() {
  const form = reactive<ProductFormState>({ nome: '', preco_venda: null })
  const errors = reactive<Record<string, string[]>>({})
  const loading = ref(false)
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
    return Object.keys(errors).length === 0
  }

  async function submit() {
    if (!validate()) return
    loading.value = true
    try {
      await productStore.create({ nome: form.nome, preco_venda: form.preco_venda! })
      snackbar.showSuccess('Produto cadastrado com sucesso')
      form.nome = ''
      form.preco_venda = null
    } catch (error) {
      const fieldErrors = handle(error)
      if (fieldErrors) Object.assign(errors, fieldErrors)
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, submit }
}
