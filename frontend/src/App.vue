<template>
  <v-app>
    <v-app-bar v-if="authStore.token" title="Fone Ninja ERP">
      <v-btn to="/produtos" variant="text">Produtos</v-btn>
      <v-btn to="/compras" variant="text">Compras</v-btn>
      <v-btn to="/vendas" variant="text">Vendas</v-btn>
      <v-spacer />
      <v-btn variant="text" @click="handleLogout">Sair</v-btn>
    </v-app-bar>
    <v-main>
      <v-container>
        <router-view />
      </v-container>
    </v-main>
    <v-snackbar v-model="snackbar.visible" :color="snackbar.color">
      {{ snackbar.message }}
    </v-snackbar>
  </v-app>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSnackbarStore } from '@/stores/snackbar'

const authStore = useAuthStore()
const snackbar = useSnackbarStore()
const router = useRouter()

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>
