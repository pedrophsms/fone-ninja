<script setup lang="ts">
import { type HTMLAttributes, ref } from 'vue'
import {
  createColumnHelper,
  getCoreRowModel,
  getSortedRowModel,
  useVueTable,
  type SortingState,
} from '@tanstack/vue-table'
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import Table from '@/components/ui/table/Table.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'

export interface DataTableColumn {
  key: string
  title: string
  align?: 'left' | 'right' | 'center'
  sortable?: boolean
  className?: string
}

const props = withDefaults(
  defineProps<{
    columns: DataTableColumn[]
    rows: Record<string, unknown>[]
    loading?: boolean
    initialSort?: { key: string; desc: boolean }
    class?: HTMLAttributes['class']
  }>(),
  { loading: false },
)

const sorting = ref<SortingState>(
  props.initialSort ? [{ id: props.initialSort.key, desc: props.initialSort.desc }] : [],
)

const columnHelper = createColumnHelper<Record<string, unknown>>()

const columnDefs = props.columns.map((col) =>
  columnHelper.accessor(col.key, {
    header: () => col.title,
    enableSorting: col.sortable !== false,
  }),
)

const table = useVueTable({
  get data() {
    return props.rows
  },
  columns: columnDefs,
  getCoreRowModel: getCoreRowModel(),
  getSortedRowModel: getSortedRowModel(),
  state: {
    get sorting() {
      return sorting.value
    },
    set sorting(v: SortingState) {
      sorting.value = v
    },
  },
})

function toggleSort(key: string) {
  const current = sorting.value.find((s) => s.id === key)
  if (!current) {
    sorting.value = [{ id: key, desc: false }]
    return
  }
  sorting.value = current.desc ? [] : [{ id: key, desc: true }]
}
</script>

<template>
  <Table :class="props.class">
    <TableHeader>
      <TableRow class="hover:bg-transparent">
        <TableHead
          v-for="col in columns"
          :key="col.key"
          :class="cn(col.align === 'right' && 'text-right', col.className)"
          role="columnheader"
          :aria-sort="col.sortable !== false && sorting[0]?.id === col.key ? (sorting[0].desc ? 'descending' : 'ascending') : undefined"
        >
          <button
            v-if="col.sortable !== false"
            type="button"
            class="inline-flex items-center gap-1 uppercase tracking-wider focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @click="toggleSort(col.key)"
          >
            {{ col.title }}
            <ArrowDown
              v-if="sorting[0]?.id === col.key && sorting[0].desc"
              class="size-3"
            />
            <ArrowUp v-else-if="sorting[0]?.id === col.key" class="size-3" />
            <ArrowUpDown v-else class="size-3 opacity-40" />
          </button>
          <span v-else>{{ col.title }}</span>
        </TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <template v-if="loading">
        <TableRow v-for="i in 4" :key="i" class="hover:bg-transparent">
          <TableCell v-for="col in columns" :key="col.key" :class="col.align === 'right' && 'text-right'">
            <Skeleton class="h-4 w-full" :class="col.align === 'right' && 'ml-auto max-w-24'" />
          </TableCell>
        </TableRow>
      </template>
      <template v-else-if="rows.length === 0">
        <TableRow class="hover:bg-transparent">
          <TableCell :colspan="columns.length" class="h-28 text-center text-sm text-muted-foreground">
            <slot name="empty">
              Nenhum registro ainda.
            </slot>
          </TableCell>
        </TableRow>
      </template>
      <TransitionGroup
        v-else
        enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:duration-0"
        enter-from-class="opacity-0 translate-y-1"
        move-class=""
      >
        <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
          <TableCell
            v-for="col in columns"
            :key="col.key"
            :class="cn(col.align === 'right' && 'text-right', col.className)"
          >
            <slot :name="`cell-${col.key}`" :row="row.original" :value="row.getValue(col.key)">
              {{ row.getValue(col.key) }}
            </slot>
          </TableCell>
        </TableRow>
      </TransitionGroup>
    </TableBody>
  </Table>
</template>
