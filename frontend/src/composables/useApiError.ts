import type { ApiError } from '@/api/http'
import { useSnackbarStore } from '@/stores/snackbar'

export function useApiError() {
  const snackbar = useSnackbarStore()

  function handle(error: unknown): Record<string, string[]> | undefined {
    const apiError = error as ApiError
    if (apiError.fieldErrors) {
      return apiError.fieldErrors
    }
    snackbar.showError(apiError.message ?? 'Erro inesperado')
    return undefined
  }

  return { handle }
}
