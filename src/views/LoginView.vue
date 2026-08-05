<template>
  <v-container class="fill-height" max-width="400">
    <v-form @submit.prevent="submit">
      <v-card-title>Login</v-card-title>
      <v-text-field
        v-model="email"
        label="Email"
        type="email"
        :error-messages="errors.email"
      />
      <v-text-field
        v-model="senha"
        label="Senha"
        type="password"
        :error-messages="errors.senha"
      />
      <v-btn type="submit" color="primary" block :loading="loading" :disabled="loading">
        Entrar
      </v-btn>
    </v-form>
  </v-container>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'

const email = ref('')
const senha = ref('')
const errors = reactive<Record<string, string[]>>({})
const loading = ref(false)
const authStore = useAuthStore()
const { handle } = useApiError()
const router = useRouter()

async function submit() {
  loading.value = true
  try {
    await authStore.login({ email: email.value, senha: senha.value })
    router.push('/produtos')
  } catch (error) {
    const fieldErrors = handle(error)
    if (fieldErrors) Object.assign(errors, fieldErrors)
  } finally {
    loading.value = false
  }
}
</script>
