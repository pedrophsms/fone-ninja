<script setup lang="ts">
import { type HTMLAttributes, computed } from 'vue'
import { DialogContent, DialogOverlay, DialogPortal, type DialogContentProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<DialogContentProps & { class?: HTMLAttributes['class']; side?: 'right' | 'left' }>(),
  { side: 'right' },
)

const sideClass = computed(() =>
  props.side === 'left'
    ? 'left-0 inset-y-0 border-r data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left'
    : 'right-0 inset-y-0 border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right',
)
</script>

<template>
  <DialogPortal>
    <DialogOverlay
      class="fixed inset-0 z-50 bg-black/50 backdrop-blur-[1px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
    />
    <DialogContent
      :class="
        cn(
          'fixed z-50 flex h-full w-3/4 max-w-xl flex-col bg-card shadow-lg duration-300 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
          sideClass,
          props.class,
        )
      "
      v-bind="props"
      :disable-outside-pointer-events="true"
    >
      <div v-if="$slots.header" class="border-b px-6 py-5 sm:px-8">
        <slot name="header" />
      </div>
      <div class="flex-1 overflow-y-auto px-6 py-6 sm:px-8">
        <slot />
      </div>
      <div v-if="$slots.footer" class="border-t px-6 py-5 sm:px-8">
        <slot name="footer" />
      </div>
    </DialogContent>
  </DialogPortal>
</template>
