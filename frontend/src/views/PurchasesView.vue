<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Plus, Trash2, PackageSearch } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Field from '@/components/ui/field/Field.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Sheet from '@/components/ui/sheet/Sheet.vue'
import SheetTrigger from '@/components/ui/sheet/SheetTrigger.vue'
import SheetContent from '@/components/ui/sheet/SheetContent.vue'
import SheetHeader from '@/components/ui/sheet/SheetHeader.vue'
import SheetTitle from '@/components/ui/sheet/SheetTitle.vue'
import SheetDescription from '@/components/ui/sheet/SheetDescription.vue'
import Select, { type SelectOption } from '@/components/ui/select/Select.vue'
import DataTable, { type DataTableColumn } from '@/components/DataTable.vue'
import { usePurchaseForm } from '@/composables/usePurchaseForm'
import { usePurchaseStore } from '@/stores/purchase'
import { useProductStore } from '@/stores/product'
import { formatMoney } from '@/lib/utils'

const { form, errors, loading, submitted, subtotalPreview, addItem, removeItem, submit } =
  usePurchaseForm()
const purchaseStore = usePurchaseStore()
const productStore = useProductStore()

const open = ref(false)
const formId = 'nova-compra-form'

watch(submitted, (value) => {
  if (value) open.value = false
})

const productOptions = computed<SelectOption[]>(() =>
  productStore.items.map((p) => ({ value: String(p.id), label: p.nome })),
)

const rows = computed(() =>
  purchaseStore.items
    .slice()
    .sort((a, b) => b.id - a.id)
    .map((p) => ({
      id: p.id,
      fornecedor: p.fornecedor,
      itens: p.produtos.map((i) => `${i.nome} x${i.quantidade}`).join(' · '),
      total: p.total,
    })),
)

const columns: DataTableColumn[] = [
  { key: 'fornecedor', title: 'Fornecedor' },
  { key: 'itens', title: 'Itens', className: 'max-w-md' },
  { key: 'total', title: 'Total', align: 'right' },
]

onMounted(() => {
  purchaseStore.fetchAll()
  productStore.fetchAll()
})

defineExpose({ form, submit, loading })
</script>

<template>
  <div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
      <div>
        <p class="text-xs font-medium uppercase tracking-[0.25em] text-muted-foreground">
          Entrada · estoque
        </p>
        <h1 class="font-display text-3xl font-semibold tracking-tight text-foreground">Compras</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Reposição de estoque e atualização do custo médio.
        </p>
      </div>
      <Sheet v-model:open="open">
        <SheetTrigger as-child>
          <Button>
            <Plus />
            Registrar compra
          </Button>
        </SheetTrigger>
        <SheetContent class="p-0">
          <template #header>
            <SheetHeader>
              <SheetTitle>Registrar compra</SheetTitle>
              <SheetDescription>
                Cadastre os itens recebidos e o valor pago por unidade.
              </SheetDescription>
            </SheetHeader>
          </template>
          <form :id="formId" class="space-y-5" @submit.prevent="submit">
            <Field label="Fornecedor" :error="errors.fornecedor">
              <Input
                v-model="form.fornecedor"
                placeholder="Nome do fornecedor"
                autocomplete="off"
              />
            </Field>

            <div class="space-y-4">
              <div
                v-for="(item, index) in form.produtos"
                :key="index"
                class="space-y-3 rounded-md border border-border p-4"
              >
                <div class="flex items-center justify-between">
                  <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                    Item {{ index + 1 }}
                  </p>
                  <Button
                    variant="ghost"
                    size="icon-sm"
                    :disabled="form.produtos.length === 1"
                    aria-label="Remover item"
                    @click="removeItem(index)"
                  >
                    <Trash2 class="text-destructive" />
                  </Button>
                </div>
                <Field :error="errors[`produtos.${index}.id`]">
                  <Select
                    :model-value="item.id"
                    :items="productOptions"
                    placeholder="Selecione o produto"
                    @update:model-value="item.id = Number($event ?? 0)"
                  />
                </Field>
                <div class="grid grid-cols-2 gap-3">
                  <Field label="Quantidade" :error="errors[`produtos.${index}.quantidade`]">
                    <Input
                      :model-value="item.quantidade"
                      type="number"
                      step="1"
                      min="1"
                      placeholder="1"
                      @update:model-value="item.quantidade = Number($event ?? 0)"
                    />
                  </Field>
                  <Field label="Preço unitário" :error="errors[`produtos.${index}.preco_unitario`]">
                    <Input
                      :model-value="item.preco_unitario"
                      type="number"
                      step="0.01"
                      min="0"
                      placeholder="0,00"
                      @update:model-value="item.preco_unitario = Number($event ?? 0)"
                    />
                  </Field>
                </div>
              </div>
            </div>

            <p v-if="errors.produtos" class="text-sm font-medium text-destructive">
              {{ errors.produtos[0] }}
            </p>

            <Button type="button" variant="outline" class="w-full" @click="addItem">
              <Plus class="size-4" />
              Adicionar produto
            </Button>

            <div class="flex items-center justify-between rounded-md bg-secondary/50 px-4 py-3">
              <span class="text-sm text-muted-foreground">Subtotal estimado</span>
              <span class="font-mono text-sm font-semibold tabular-nums text-foreground">
                {{ formatMoney(subtotalPreview) }}
              </span>
            </div>
          </form>
          <template #footer>
            <Button type="submit" :form="formId" class="w-full" size="lg" :disabled="loading">
              {{ loading ? 'Registrando…' : 'Registrar compra' }}
            </Button>
          </template>
        </SheetContent>
      </Sheet>
    </div>

    <Card>
      <CardContent class="px-0 py-0">
        <DataTable
          :columns="columns"
          :rows="rows"
          :loading="purchaseStore.loading"
        >
          <template #cell-itens="{ value }">
            <span class="line-clamp-1 text-sm text-muted-foreground">{{ value }}</span>
          </template>
          <template #cell-total="{ value }">
            <span class="font-mono font-medium tabular-nums text-foreground">{{ formatMoney(value as string) }}</span>
          </template>
          <template #empty>
            <div class="flex flex-col items-center gap-3 py-6">
              <PackageSearch class="size-6 text-muted-foreground" />
              <p class="text-sm text-muted-foreground">Nenhuma compra registrada ainda.</p>
              <Button size="sm" @click="open = true">Registrar a primeira compra</Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </div>
</template>
