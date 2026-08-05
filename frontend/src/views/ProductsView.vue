<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Package, Plus } from 'lucide-vue-next'
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
import DataTable, { type DataTableColumn } from '@/components/DataTable.vue'
import { useProductForm } from '@/composables/useProductForm'
import { useProductStore } from '@/stores/product'
import { formatMoney, formatQuantity } from '@/lib/utils'

const { form, errors, loading, submitted, submit } = useProductForm()
const productStore = useProductStore()

const open = ref(false)
const formId = 'novo-produto-form'

watch(submitted, (value) => {
  if (value) open.value = false
})

const rows = computed(() =>
  productStore.items.map((p) => ({
    id: p.id,
    nome: p.nome,
    custo_medio: p.custo_medio,
    preco_venda: p.preco_venda,
    estoque: p.estoque,
  })),
)

const columns: DataTableColumn[] = [
  { key: 'nome', title: 'Produto' },
  { key: 'custo_medio', title: 'Custo médio', align: 'right' },
  { key: 'preco_venda', title: 'Preço de venda', align: 'right' },
  { key: 'estoque', title: 'Estoque', align: 'right' },
]

onMounted(() => {
  productStore.fetchAll()
})
</script>

<template>
  <div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
      <div>
        <p class="text-xs font-medium uppercase tracking-[0.25em] text-muted-foreground">
          Inventário
        </p>
        <h1 class="font-display text-3xl font-semibold tracking-tight text-foreground">Produtos</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          O que está à venda, quanto custa e quanto sobrou.
        </p>
      </div>
      <Sheet v-model:open="open">
        <SheetTrigger as-child>
          <Button>
            <Plus />
            Novo produto
          </Button>
        </SheetTrigger>
        <SheetContent class="p-0">
          <template #header>
            <SheetHeader>
              <SheetTitle>Cadastrar produto</SheetTitle>
              <SheetDescription>
                Define o preço de venda e o estoque inicial. O custo médio é calculado nas compras.
              </SheetDescription>
            </SheetHeader>
          </template>
          <form :id="formId" class="grid gap-5" @submit.prevent="submit">
            <Field label="Nome" :error="errors.nome">
              <Input v-model="form.nome" placeholder="Fone Bluetooth" />
            </Field>
            <Field label="Preço de venda" :error="errors.preco_venda">
              <Input
                :model-value="form.preco_venda"
                type="number"
                step="0.01"
                min="0"
                placeholder="0,00"
                @update:model-value="form.preco_venda = $event ? Number($event) : null"
              />
            </Field>
            <Field label="Estoque inicial" :error="errors.estoque_inicial" hint="Opcional. Zera sem informar.">
              <Input
                :model-value="form.estoque_inicial"
                type="number"
                step="1"
                min="0"
                placeholder="0"
                @update:model-value="form.estoque_inicial = $event ? Number($event) : null"
              />
            </Field>
          </form>
          <template #footer>
            <Button type="submit" :form="formId" class="w-full" size="lg" :disabled="loading">
              {{ loading ? 'Cadastrando…' : 'Cadastrar produto' }}
            </Button>
          </template>
        </SheetContent>
      </Sheet>
    </div>

    <Card>
      <CardContent class="px-0 py-0">
        <DataTable :columns="columns" :rows="rows" :loading="productStore.loading" :initial-sort="{ key: 'nome', desc: false }">
          <template #cell-custo_medio="{ value }">
            <span class="font-mono tabular-nums text-muted-foreground">{{ formatMoney(value as string) }}</span>
          </template>
          <template #cell-preco_venda="{ value }">
            <span class="font-mono font-medium tabular-nums text-foreground">{{ formatMoney(value as string) }}</span>
          </template>
          <template #cell-estoque="{ value }">
            <span
              class="font-mono tabular-nums"
              :class="(value as number) <= 10 ? 'font-semibold text-brass' : 'text-foreground'"
            >
              {{ formatQuantity(value as number) }}
            </span>
          </template>
          <template #empty>
            <div class="flex flex-col items-center gap-3 py-6">
              <Package class="size-6 text-muted-foreground" />
              <p class="text-sm text-muted-foreground">Nenhum produto cadastrado ainda.</p>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>
  </div>
</template>
