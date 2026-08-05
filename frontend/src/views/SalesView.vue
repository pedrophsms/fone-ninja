<template>
  <div>
    <v-card class="mb-4">
      <v-card-title>Registrar venda</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="submit">
          <v-text-field v-model="form.cliente" label="Cliente" :error-messages="errors.cliente" />

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
          <p v-if="preview" class="text-subtitle-1">
            Total estimado: {{ preview.total }} · Lucro estimado: {{ preview.lucro }}
          </p>

          <v-btn type="submit" color="primary" :loading="loading" :disabled="loading">
            Registrar venda
          </v-btn>
        </v-form>
      </v-card-text>
    </v-card>

    <v-data-table :items="saleStore.items" :loading="saleStore.loading" :headers="headers">
      <template #item.actions="{ item }">
        <v-btn
          size="small"
          variant="text"
          :disabled="item.status === 'cancelled'"
          @click="cancelSale(item.id)"
        >
          Cancelar
        </v-btn>
      </template>
    </v-data-table>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useSaleForm } from '@/composables/useSaleForm'
import { useSaleStore } from '@/stores/sale'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'

const { form, errors, loading, preview, addItem, removeItem, submit } = useSaleForm()
const saleStore = useSaleStore()
const productStore = useProductStore()
const { handle } = useApiError()
const snackbar = useSnackbarStore()

const headers = [
  { title: 'Cliente', key: 'cliente' },
  { title: 'Total', key: 'total' },
  { title: 'Lucro', key: 'lucro' },
  { title: 'Status', key: 'status' },
  { title: 'Ações', key: 'actions', sortable: false },
]

async function cancelSale(id: number) {
  try {
    await saleStore.cancel(id)
    snackbar.showSuccess('Venda cancelada com sucesso')
    await productStore.fetchAll()
  } catch (error) {
    handle(error)
  }
}

onMounted(() => {
  saleStore.fetchAll()
  productStore.fetchAll()
})
</script>
