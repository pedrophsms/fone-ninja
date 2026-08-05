<script setup lang="ts">
import { type HTMLAttributes } from 'vue'
import {
  SelectContent,
  SelectIcon,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'reka-ui'
import { Check, ChevronDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

export interface SelectOption {
  value: string
  label: string
}

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: number | string | null
    items: SelectOption[]
    placeholder?: string
    disabled?: boolean
    class?: HTMLAttributes['class']
  }>(),
  { placeholder: 'Selecionar' },
)

const emit = defineEmits<{ 'update:modelValue': [number | string | null] }>()

function onValueChange(value: unknown) {
  emit('update:modelValue', typeof value === 'string' ? value : null)
}
</script>

<template>
  <SelectRoot
    :model-value="modelValue != null ? String(modelValue) : undefined"
    :disabled="disabled"
    @update:model-value="onValueChange"
  >
    <SelectTrigger
      :class="
        cn(
          'flex h-9 w-full items-center justify-between gap-2 whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 data-[placeholder]:text-muted-foreground',
          props.class,
        )
      "
    >
      <SelectValue :placeholder="placeholder" />
      <SelectIcon as-child>
        <ChevronDown class="size-4 opacity-50" />
      </SelectIcon>
    </SelectTrigger>
    <SelectPortal>
      <SelectContent
        class="relative z-50 max-h-96 min-w-32 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
      >
        <SelectViewport class="p-1">
          <SelectItem
            v-for="item in items"
            :key="item.value"
            :value="item.value"
            class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
          >
            <SelectItemText>{{ item.label }}</SelectItemText>
            <SelectItemIndicator class="absolute right-2 flex size-3.5 items-center justify-center">
              <Check class="size-4" />
            </SelectItemIndicator>
          </SelectItem>
        </SelectViewport>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
