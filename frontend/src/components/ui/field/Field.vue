<script setup lang="ts">
defineProps<{ label?: string; error?: string[]; hint?: string }>()
</script>

<template>
  <div class="space-y-1.5">
    <label v-if="label" class="block text-sm font-medium leading-none text-foreground">
      {{ label }}
    </label>
    <slot />
    <p v-if="error && error.length" class="text-xs font-medium text-destructive">
      {{ error[0] }}
    </p>
    <p v-else-if="hint" class="text-xs text-muted-foreground">
      {{ hint }}
    </p>
  </div>
</template>
