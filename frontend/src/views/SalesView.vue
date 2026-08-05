<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Plus, Trash2, ReceiptText } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Field from '@/components/ui/field/Field.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Sheet from '@/components/ui/sheet/Sheet.vue'
import SheetTrigger from '@/components/ui/sheet/SheetTrigger.vue'
import SheetContent from '@/components/ui/sheet/SheetContent.vue'
import SheetHeader from '@/components/ui/sheet/SheetHeader.vue'
import SheetTitle from '@/components/ui/sheet/SheetTitle.vue'
import SheetDescription from '@/components/ui/sheet/SheetDescription.vue'
import Select, { type SelectOption } from '@/components/ui/select/Select.vue'
import DataTable, { type DataTableColumn } from '@/components/DataTable.vue'
import { useSaleForm } from '@/composables/useSaleForm'
import { useSaleStore } from '@/stores/sale'
import { useProductStore } from '@/stores/product'
import { useSnackbarStore } from '@/stores/snackbar'
import { useApiError } from '@/composables/useApiError'
import { formatMoney } from '@/lib/utils'

const { form, errors, loading, submitted, preview, addItem, removeItem, submit } = useSaleForm()
const saleStore = useSaleStore()
const productStore = useProductStore()
const snackbar = useSnackbarStore()
const { handle } = useApiError()

const open = ref(false)
const formId = 'nova-venda-form'

watch(submitted, (value) => {
  if (value) open.value = false
})

const productOptions = computed<SelectOption[]>(() =>
  productStore.items.map((p) => ({ value: String(p.id), label: p.nome })),
)

const rows = computed(() =>
  saleStore.items
    .slice()
    .sort((a, b) => b.id - a.id)
    .map((s) => ({
      id: s.id,
      cliente: s.cliente,
      total: s.total,
      lucro: s.lucro,
      status: s.status,
      data: s.created_at,
      itens: s.produtos.length,
    })),
)

const columns: DataTableColumn[] = [
  { key: 'cliente', title: 'Cliente' },
  { key: 'itens', title: 'Itens', align: 'right' },
  { key: 'total', title: 'Total', align: 'right' },
  { key: 'lucro', title: 'Lucro', align: 'right' },
  { key: 'status', title: 'Status' },
  { key: 'data', title: 'Data' },
  { key: 'acoes', title: '', sortable: false, className: 'w-24 text-right' },
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

function formatDate(value?: string): string {
  if (!value) return '—'
  const date = new Date(value)
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
}

onMounted(() => {
  saleStore.fetchAll()
  productStore.fetchAll()
})
</script>

<template>
  <div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
      <div>
        <p class="text-xs font-medium uppercase tracking-[0.25em] text-muted-foreground">
          Saída · lucro
        </p>
        <h1 class="font-display text-3xl font-semibold tracking-tight text-foreground">Vendas</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Registre a saída de estoque e imprima o recibo.
        </p>
      </div>
      <Sheet v-model:open="open">
        <SheetTrigger as-child>
          <Button>
            <Plus />
            Registrar venda
          </Button>
        </SheetTrigger>
        <SheetContent class="p-0">
          <template #header>
            <SheetHeader>
              <SheetTitle>Registrar venda</SheetTitle>
              <SheetDescription>
                Escolha os produtos e o preço praticado. O recibo atualiza ao vivo.
              </SheetDescription>
            </SheetHeader>
          </template>
          <form :id="formId" class="space-y-5" @submit.prevent="submit">
            <Field label="Cliente" :error="errors.cliente">
              <Input
                v-model="form.cliente"
                placeholder="Nome do cliente"
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

            <div
              v-if="preview && preview.itens.length"
              class="rounded-md border border-dashed border-brass/50 bg-background p-4 font-mono text-xs text-muted-foreground"
              aria-label="Recibo de venda"
            >
              <div class="flex items-baseline justify-between">
                <span class="text-sm text-foreground">FONE NINJA</span>
                <span class="text-brass">*</span>
              </div>
              <p class="mt-0.5 uppercase tracking-[0.2em]">recibo · venda</p>
              <div class="my-3 border-t border-dashed" />
              <div class="space-y-1.5">
                <div
                  v-for="item in preview.itens"
                  :key="item.id"
                  class="flex items-baseline justify-between gap-3"
                >
                  <span class="truncate">{{ item.nome }}</span>
                  <span class="shrink-0 tabular-nums">{{ item.quantidade }} x {{ formatMoney(item.preco_unitario) }}</span>
                </div>
              </div>
              <div class="my-3 border-t border-dashed" />
              <div class="flex justify-between text-foreground">
                <span>TOTAL</span>
                <span class="font-semibold tabular-nums">{{ formatMoney(preview.total) }}</span>
              </div>
              <div class="flex justify-between text-brass">
                <span>LUCRO</span>
                <span class="font-semibold tabular-nums">{{ formatMoney(preview.lucro) }}</span>
              </div>
            </div>
          </form>
          <template #footer>
            <Button type="submit" :form="formId" class="w-full" size="lg" :disabled="loading">
              {{ loading ? 'Registrando…' : 'Registrar venda' }}
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
          :loading="saleStore.loading"
        >
          <template #cell-itens="{ value }">
            <span class="font-mono tabular-nums text-muted-foreground">{{ value }}</span>
          </template>
          <template #cell-total="{ value }">
            <span class="font-mono font-medium tabular-nums text-foreground">{{ formatMoney(value as string) }}</span>
          </template>
          <template #cell-lucro="{ value }">
            <span class="font-mono font-medium tabular-nums text-brass">{{ formatMoney(value as string) }}</span>
          </template>
          <template #cell-status="{ value }">
            <Badge v-if="value === 'cancelled'" variant="outline" class="text-muted-foreground">
              Cancelada
            </Badge>
            <Badge v-else variant="success">Concluída</Badge>
          </template>
          <template #cell-data="{ value }">
            <span class="text-muted-foreground">{{ formatDate(value as string) }}</span>
          </template>
          <template #cell-acoes="{ row }">
            <div class="flex justify-end">
              <Button
                size="sm"
                variant="ghost"
                class="text-destructive hover:text-destructive"
                :disabled="row.status === 'cancelled'"
                @click="cancelSale(row.id as number)"
              >
                Cancelar
              </Button>
            </div>
          </template>
          <template #empty>
            <div class="flex flex-col items-center gap-3 py-6">
              <ReceiptText class="size-6 text-muted-foreground" />
              <p class="text-sm text-muted-foreground">Nenhuma venda registrada ainda.</p>
              <Button size="sm" @click="open = true">Registrar a primeira venda</Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </div>
</template>
