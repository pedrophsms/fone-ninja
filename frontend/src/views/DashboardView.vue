<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Package, PackageMinus, Receipt, Wallet } from 'lucide-vue-next'
import Card from '@/components/ui/card/Card.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import CardTitle from '@/components/ui/card/CardTitle.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import { useProductStore } from '@/stores/product'
import { useSaleStore } from '@/stores/sale'
import { usePurchaseStore } from '@/stores/purchase'
import { formatMoney, formatQuantity } from '@/lib/utils'

const LOW_STOCK_THRESHOLD = 10

const productStore = useProductStore()
const saleStore = useSaleStore()
const purchaseStore = usePurchaseStore()
const router = useRouter()

const loading = computed(() => productStore.loading || saleStore.loading || purchaseStore.loading)

const activeSales = computed(() => saleStore.items.filter((s) => s.status === 'completed'))
const receita = computed(() =>
  activeSales.value.reduce((sum, s) => sum + Number(s.total), 0),
)
const lucroAcumulado = computed(() =>
  activeSales.value.reduce((sum, s) => sum + Number(s.lucro), 0),
)
const ticketMedio = computed(() =>
  activeSales.value.length ? receita.value / activeSales.value.length : 0,
)
const valorEmEstoque = computed(() =>
  productStore.items.reduce(
    (sum, p) => sum + Number(p.custo_medio) * p.estoque,
    0,
  ),
)
const baixoEstoque = computed(() =>
  productStore.items
    .filter((p) => p.estoque <= LOW_STOCK_THRESHOLD)
    .sort((a, b) => a.estoque - b.estoque),
)
const ultimasVendas = computed(() =>
  [...saleStore.items].sort((a, b) => b.id - a.id).slice(0, 5),
)

const vazio = computed(
  () =>
    productStore.items.length === 0 &&
    saleStore.items.length === 0 &&
    purchaseStore.items.length === 0,
)

onMounted(() => {
  productStore.fetchAll()
  saleStore.fetchAll()
  purchaseStore.fetchAll()
})

function formatDate(value?: string): string {
  if (!value) return ''
  const date = new Date(value)
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
}
</script>

<template>
  <div class="space-y-10">
    <template v-if="loading && vazio">
      <div class="space-y-4">
        <Skeleton class="h-12 w-2/3" />
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Skeleton v-for="i in 4" :key="i" class="h-24" />
        </div>
      </div>
    </template>

    <template v-else-if="vazio">
      <Card class="flex min-h-72 flex-col items-center justify-center gap-6 text-center">
        <CardHeader class="items-center">
          <CardTitle class="text-2xl">Sua loja está pronta para o primeiro movimento</CardTitle>
          <p class="mt-2 max-w-md text-sm text-muted-foreground">
            Cadastre um produto e registre a primeira venda. O painel passa a mostrar lucro,
            receita e o estado do estoque.
          </p>
        </CardHeader>
        <CardContent class="flex flex-col gap-3 sm:flex-row">
          <Button @click="router.push('/produtos')">
            Cadastrar produto
            <ArrowRight />
          </Button>
          <Button variant="outline" @click="router.push('/vendas')">
            Ir para vendas
          </Button>
        </CardContent>
      </Card>
    </template>

    <template v-else>
      <section class="flex flex-col gap-2 border-b pb-8">
        <p class="text-xs font-medium uppercase tracking-[0.25em] text-muted-foreground">
          Painel · caixa da loja
        </p>
        <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
          <div>
            <p class="text-sm text-muted-foreground">Lucro acumulado</p>
            <p class="font-display text-4xl font-semibold tracking-tight text-brass sm:text-5xl">
              {{ formatMoney(lucroAcumulado) }}
            </p>
          </div>
          <p class="text-xs text-muted-foreground">
            {{ activeSales.length }} venda{{ activeSales.length === 1 ? '' : 's' }} concluída{{
              activeSales.length === 1 ? '' : 's'
            }}
            · {{ formatQuantity(productStore.items.length) }} produto{{
              productStore.items.length === 1 ? '' : 's'
            }}
          </p>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardContent class="flex items-center gap-4 px-6 py-5">
            <div class="flex size-10 items-center justify-center rounded-md bg-secondary">
              <Wallet class="size-5 text-foreground" />
            </div>
            <div>
              <p class="text-xs uppercase tracking-wider text-muted-foreground">Receita total</p>
              <p class="font-mono text-lg font-semibold tabular-nums text-foreground">
                {{ formatMoney(receita) }}
              </p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="flex items-center gap-4 px-6 py-5">
            <div class="flex size-10 items-center justify-center rounded-md bg-secondary">
              <Receipt class="size-5 text-foreground" />
            </div>
            <div>
              <p class="text-xs uppercase tracking-wider text-muted-foreground">Ticket médio</p>
              <p class="font-mono text-lg font-semibold tabular-nums text-foreground">
                {{ activeSales.length ? formatMoney(ticketMedio) : '—' }}
              </p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="flex items-center gap-4 px-6 py-5">
            <div class="flex size-10 items-center justify-center rounded-md bg-secondary">
              <Package class="size-5 text-foreground" />
            </div>
            <div>
              <p class="text-xs uppercase tracking-wider text-muted-foreground">Valor em estoque</p>
              <p class="font-mono text-lg font-semibold tabular-nums text-foreground">
                {{ formatMoney(valorEmEstoque) }}
              </p>
            </div>
          </CardContent>
        </Card>
      </section>

      <section class="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader class="flex-row items-center justify-between">
            <CardTitle class="text-base">Últimas vendas</CardTitle>
            <Button variant="ghost" size="sm" @click="router.push('/vendas')">
              Ver todas <ArrowRight class="size-3.5" />
            </Button>
          </CardHeader>
          <CardContent>
            <div v-if="ultimasVendas.length === 0" class="flex flex-col items-start gap-3 py-6">
              <p class="text-sm text-muted-foreground">Nenhuma venda registrada ainda.</p>
              <Button size="sm" @click="router.push('/vendas')">Registrar primeira venda</Button>
            </div>
            <ul v-else class="divide-y divide-border">
              <li
                v-for="sale in ultimasVendas"
                :key="sale.id"
                class="flex items-center justify-between gap-4 py-3"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-foreground">{{ sale.cliente }}</p>
                  <p class="text-xs text-muted-foreground">
                    #{{ sale.id }} · {{ formatDate(sale.created_at) }}
                  </p>
                </div>
                <div class="flex items-center gap-3">
                  <Badge v-if="sale.status === 'cancelled'" variant="outline" class="text-muted-foreground">
                    Cancelada
                  </Badge>
                  <p class="font-mono text-sm font-semibold tabular-nums text-foreground">
                    {{ formatMoney(sale.total) }}
                  </p>
                </div>
              </li>
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
              <PackageMinus class="size-4 text-brass" />
              Estoque baixo
              <span v-if="baixoEstoque.length" class="text-xs font-normal text-muted-foreground">
                ≤ {{ LOW_STOCK_THRESHOLD }} unid.
              </span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="baixoEstoque.length === 0" class="py-6">
              <p class="text-sm text-muted-foreground">Nenhum produto perto de zerar. Tudo em ordem.</p>
            </div>
            <ul v-else class="divide-y divide-border">
              <li
                v-for="product in baixoEstoque"
                :key="product.id"
                class="flex items-center justify-between gap-4 py-3"
              >
                <p class="truncate text-sm font-medium text-foreground">{{ product.nome }}</p>
                <Badge :variant="product.estoque === 0 ? 'destructive' : 'outline'" class="font-mono tabular-nums">
                  {{ formatQuantity(product.estoque) }} unid.
                </Badge>
              </li>
            </ul>
          </CardContent>
        </Card>
      </section>
    </template>
  </div>
</template>
