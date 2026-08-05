<template>
  <div>
    <v-card class="mb-4">
      <v-card-title>Cadastrar produto</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="submit">
          <v-text-field v-model="form.nome" label="Nome" :error-messages="errors.nome" />
          <v-text-field
            v-model.number="form.preco_venda"
            label="Preço de venda"
            type="number"
            step="0.01"
            :error-messages="errors.preco_venda"
          />
          <v-text-field
            v-model.number="form.estoque_inicial"
            label="Estoque inicial"
            type="number"
            min="0"
            step="1"
            :error-messages="errors.estoque_inicial"
          />
          <v-btn type="submit" color="primary" :loading="loading" :disabled="loading">
            Cadastrar
          </v-btn>
        </v-form>
      </v-card-text>
    </v-card>

    <v-data-table :items="productStore.items" :loading="productStore.loading" :headers="headers" />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useProductForm } from '@/composables/useProductForm'
import { useProductStore } from '@/stores/product'

const { form, errors, loading, submit } = useProductForm()
const productStore = useProductStore()

const headers = [
  { title: 'Nome', key: 'nome' },
  { title: 'Custo médio', key: 'custo_medio' },
  { title: 'Preço de venda', key: 'preco_venda' },
  { title: 'Estoque', key: 'estoque' },
]

onMounted(() => {
  productStore.fetchAll()
})
</script>
