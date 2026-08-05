import { defineStore } from 'pinia'

interface SnackbarState {
  visible: boolean
  message: string
  color: 'success' | 'error'
}

export const useSnackbarStore = defineStore('snackbar', {
  state: (): SnackbarState => ({
    visible: false,
    message: '',
    color: 'success',
  }),
  actions: {
    showSuccess(message: string) {
      this.message = message
      this.color = 'success'
      this.visible = true
    },
    showError(message: string) {
      this.message = message
      this.color = 'error'
      this.visible = true
    },
  },
})
