<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Field from '@/components/ui/field/Field.vue'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'

type Mode = 'login' | 'register'

const mode = ref<Mode>('login')
const email = ref('')
const senha = ref('')
const nome = ref('')
const senhaConfirmation = ref('')
const errors = reactive<Record<string, string[]>>({})
const loading = ref(false)
const authStore = useAuthStore()
const { handle } = useApiError()
const router = useRouter()

function switchMode(next: Mode) {
  mode.value = next
  Object.keys(errors).forEach((key) => delete errors[key])
}

function validate(): boolean {
  Object.keys(errors).forEach((key) => delete errors[key])
  if (mode.value === 'register') {
    if (nome.value.trim().length < 3) errors.nome = ['Nome deve ter no mínimo 3 caracteres']
    if (senha.value.length < 8) errors.senha = ['Senha deve ter no mínimo 8 caracteres']
    if (senhaConfirmation.value !== senha.value) {
      errors.senha_confirmation = ['As senhas não conferem']
    }
  }
  if (!email.value.trim()) errors.email = ['Email é obrigatório']
  if (!senha.value) errors.senha = ['Senha é obrigatória']
  return Object.keys(errors).length === 0
}

async function submit() {
  if (loading.value || !validate()) return
  loading.value = true
  try {
    if (mode.value === 'register') {
      await authStore.register({
        nome: nome.value,
        email: email.value,
        senha: senha.value,
        senha_confirmation: senhaConfirmation.value,
      })
    } else {
      await authStore.login({ email: email.value, senha: senha.value })
    }
    router.push('/dashboard')
  } catch (error) {
    const fieldErrors = handle(error)
    if (fieldErrors) Object.assign(errors, fieldErrors)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="grid w-full max-w-4xl grid-cols-1 overflow-hidden rounded-lg border bg-card shadow-sm md:grid-cols-5">
    <div class="relative hidden flex-col justify-between gap-8 bg-background p-10 md:col-span-2 md:flex">
      <div>
        <p class="flex items-baseline gap-1">
          <span class="font-display text-3xl font-semibold tracking-tight text-foreground">FONE NINJA</span>
          <span class="text-base text-brass">*</span>
        </p>
        <p class="mt-1 text-xs uppercase tracking-[0.25em] text-muted-foreground">ERP de estoque</p>
      </div>

      <div class="space-y-1.5">
        <p class="text-sm leading-relaxed text-muted-foreground">
          Produtos, compras e vendas em um só lugar.
        </p>
        <p class="font-display text-xl leading-snug text-foreground">
          O controle do inventário, com a precisão de um caixa.
        </p>
      </div>

      <div class="rounded border border-dashed border-border p-4 font-mono text-xs text-muted-foreground">
        <div class="flex items-baseline justify-between">
          <span class="text-foreground">FONE NINJA</span>
          <span class="text-brass">*</span>
        </div>
        <p class="mt-0.5 uppercase tracking-[0.2em]">recibo · inventário</p>
        <div class="my-3 border-t border-dashed" />
        <div class="space-y-1">
          <div class="flex justify-between"><span>Fone Bluetooth</span><span class="tabular-nums">99,90</span></div>
          <div class="flex justify-between"><span>Cabo USB-C 1m</span><span class="tabular-nums">19,90</span></div>
        </div>
        <div class="my-3 border-t border-dashed" />
        <div class="flex justify-between text-foreground">
          <span>TOTAL</span><span class="font-semibold tabular-nums">119,80</span>
        </div>
      </div>
    </div>

    <div class="flex flex-col justify-center p-8 sm:p-12 md:col-span-3">
      <h1 class="font-display text-2xl font-semibold tracking-tight text-foreground">
        {{ mode === 'login' ? 'Entrar na loja' : 'Criar conta' }}
      </h1>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ mode === 'login' ? 'Acesse para operar o estoque.' : 'Comece a controlar seu inventário.' }}
      </p>

      <form class="mt-8 space-y-5" @submit.prevent="submit">
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          leave-active-class="transition-all duration-150 ease-out motion-reduce:duration-0"
          leave-to-class="opacity-0 -translate-y-1"
        >
          <Field v-if="mode === 'register'" label="Nome" :error="errors.nome">
            <Input v-model="nome" placeholder="Seu nome" autocomplete="name" />
          </Field>
        </Transition>
        <Field label="Email" :error="errors.email">
          <Input v-model="email" type="email" placeholder="voce@loja.com" autocomplete="email" />
        </Field>
        <Field label="Senha" :error="errors.senha">
          <Input v-model="senha" type="password" placeholder="••••••••" autocomplete="current-password" />
        </Field>
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          leave-active-class="transition-all duration-150 ease-out motion-reduce:duration-0"
          leave-to-class="opacity-0 -translate-y-1"
        >
          <Field v-if="mode === 'register'" label="Confirmar senha" :error="errors.senha_confirmation">
            <Input v-model="senhaConfirmation" type="password" placeholder="••••••••" autocomplete="new-password" />
          </Field>
        </Transition>

        <Button type="submit" class="w-full" size="lg" :disabled="loading">
          <span v-if="loading">Aguarde…</span>
          <template v-else>
            {{ mode === 'login' ? 'Entrar' : 'Criar conta' }}
            <ArrowRight />
          </template>
        </Button>
      </form>

      <p class="mt-8 text-center text-sm text-muted-foreground">
        <template v-if="mode === 'login'">
          Ainda não tem conta?
          <button
            type="button"
            class="font-medium text-foreground underline underline-offset-4 hover:text-brass focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @click="switchMode('register')"
          >
            Criar conta
          </button>
        </template>
        <template v-else>
          Já tem conta?
          <button
            type="button"
            class="font-medium text-foreground underline underline-offset-4 hover:text-brass focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            @click="switchMode('login')"
          >
            Entrar
          </button>
        </template>
      </p>
    </div>
  </div>
</template>
