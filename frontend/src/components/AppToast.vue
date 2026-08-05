<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { CheckCircle2, AlertCircle } from 'lucide-vue-next'
import { useSnackbarStore } from '@/stores/snackbar'

const snackbar = useSnackbarStore()

let timer: number | undefined

watch(
  () => snackbar.visible,
  (visible) => {
    if (visible) {
      clearTimeout(timer)
      timer = window.setTimeout(() => {
        snackbar.visible = false
      }, 4000)
    }
  },
)

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="animate-in fade-in slide-in-from-bottom-2 duration-300"
      leave-active-class="animate-out fade-out slide-out-to-bottom-2 duration-200"
    >
      <div
        v-if="snackbar.visible"
        role="status"
        class="fixed bottom-6 right-6 z-[60] flex max-w-sm items-start gap-3 rounded-md border bg-card p-4 shadow-lg"
        :class="snackbar.color === 'success' ? 'border-brass/40' : 'border-destructive/40'"
      >
        <CheckCircle2 v-if="snackbar.color === 'success'" class="mt-0.5 size-4 shrink-0 text-brass" />
        <AlertCircle v-else class="mt-0.5 size-4 shrink-0 text-destructive" />
        <p class="text-sm leading-relaxed text-foreground">{{ snackbar.message }}</p>
      </div>
    </Transition>
  </Teleport>
</template>
