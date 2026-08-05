<template>
  <div>
    <v-card class="mb-4">
      <v-card-title>Registrar compra</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="submit">
          <v-text-field v-model="form.fornecedor" label="Fornecedor" :error-messages="errors.fornecedor" />

          <div v-for="(item, index) in form.produtos" :key="index" class="d-flex ga-2 align-center mb-2">
            <v-select
              v-model="item.id"
              :items="productStore.items"
              item-title="nome"
              item-value="id"
              label="Produto"
              :error-messages="errors[`produtos.${index}.id`]"
            />
            <v-text-field
              v-model.number="item.quantidade"
              label="Quantidade"
              type="number"
              :error-messages="errors[`produtos.${index}.quantidade`]"
            />
            <v-text-field
              v-model.number="item.preco_unitario"
              label="Preço unitário"
              type="number"
              step="0.01"
              :error-messages="errors[`produtos.${index}.preco_unitario`]"
            />
            <v-btn icon="mdi-delete" variant="text" @click="removeItem(index)" />
          </div>
          <p v-if="errors.produtos" class="text-error text-caption">{{ errors.produtos[0] }}</p>

          <v-btn variant="text" @click="addItem">Adicionar produto</v-btn>
          <p class="text-subtitle-1">Subtotal estimado: {{ subtotalPreview }}</p>

          <v-btn type="submit" color="primary" :loading="loading" :disabled="loading">
            Registrar compra
          </v-btn>
        </v-form>
      </v-card-text>
    </v-card>

    <v-data-table :items="purchaseStore.items" :loading="purchaseStore.loading" :headers="headers">
      <template #item.produtos="{ item }">
        {{ item.produtos.map((p) => `${p.nome} x${p.quantidade}`).join(', ') }}
      </template>
    </v-data-table>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { usePurchaseForm } from '@/composables/usePurchaseForm'
import { usePurchaseStore } from '@/stores/purchase'
import { useProductStore } from '@/stores/product'

const { form, errors, loading, subtotalPreview, addItem, removeItem, submit } = usePurchaseForm()
const purchaseStore = usePurchaseStore()
const productStore = useProductStore()

const headers = [
  { title: 'Fornecedor', key: 'fornecedor' },
  { title: 'Total', key: 'total' },
  { title: 'Itens', key: 'produtos', sortable: false },
]

onMounted(() => {
  purchaseStore.fetchAll()
  productStore.fetchAll()
})
</script>
