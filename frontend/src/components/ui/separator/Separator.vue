<script setup lang="ts">
import { type HTMLAttributes, computed } from 'vue'
import { Separator as RekaSeparator } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes['class']
    orientation?: 'horizontal' | 'vertical'
  }>(),
  { orientation: 'horizontal' },
)

const classes = computed(() =>
  cn(
    'shrink-0 bg-border',
    props.orientation === 'vertical' ? 'h-full w-px' : 'h-px w-full',
    props.class,
  ),
)
</script>

<template>
  <RekaSeparator :orientation="orientation" :class="classes" />
</template>
