<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LayoutDashboard, Package, Truck, Receipt, LogOut, Moon, Sun } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'
import AppToast from '@/components/AppToast.vue'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const { dark, toggle, initTheme } = useTheme()

const navItems = [
  { to: '/dashboard', label: 'Painel', icon: LayoutDashboard },
  { to: '/produtos', label: 'Produtos', icon: Package },
  { to: '/compras', label: 'Compras', icon: Truck },
  { to: '/vendas', label: 'Vendas', icon: Receipt },
]

const userName = computed(() => authStore.user?.nome?.split(' ')[0] ?? 'Usuário')

function isActive(to: string) {
  return route.path === to || route.path.startsWith(`${to}/`)
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(initTheme)
</script>

<template>
  <div class="min-h-screen">
    <AppToast />
    <template v-if="authStore.token">
      <div class="flex min-h-screen">
        <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-border/40 bg-card/80 backdrop-blur-xl backdrop-saturate-150 md:flex">
          <div class="flex flex-col gap-1 border-b border-border/40 px-6 py-6">
            <router-link to="/dashboard" class="flex items-baseline gap-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
              <span class="font-display text-2xl font-semibold tracking-tight text-foreground">FONE NINJA</span>
              <span class="text-sm text-brass">*</span>
            </router-link>
            <p class="text-xs uppercase tracking-[0.2em] text-muted-foreground">ERP de estoque</p>
          </div>

          <nav class="flex-1 space-y-1 px-3 py-4">
            <router-link
              v-for="item in navItems"
              :key="item.to"
              :to="item.to"
              class="nav-link flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:scale-[0.97] active:transition-transform active:duration-75"
              :class="isActive(item.to) ? 'bg-secondary font-medium text-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
            >
              <component :is="item.icon" class="size-4 shrink-0" :class="isActive(item.to) ? 'text-brass' : ''" />
              {{ item.label }}
            </router-link>
          </nav>

          <div class="space-y-2 border-t border-border/40 px-4 py-4">
            <p class="truncate px-2 text-xs font-medium text-foreground">{{ userName }}</p>
            <div class="flex items-center justify-between">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                :aria-label="dark ? 'Mudar para tema claro' : 'Mudar para tema escuro'"
                @click="toggle"
              >
                <Moon v-if="!dark" class="size-4" />
                <Sun v-else class="size-4" />
                {{ dark ? 'Claro' : 'Escuro' }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @click="handleLogout"
              >
                <LogOut class="size-4" />
                Sair
              </button>
            </div>
          </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
          <header class="sticky top-0 z-40 flex items-center gap-3 border-b border-border/40 bg-card/80 backdrop-blur-xl backdrop-saturate-150 px-4 py-3 md:hidden">
            <router-link to="/dashboard" class="flex items-baseline gap-1">
              <span class="font-display text-lg font-semibold text-foreground">FONE NINJA</span>
              <span class="text-xs text-brass">*</span>
            </router-link>
            <div class="ml-auto flex items-center gap-1">
              <router-link
                v-for="item in navItems"
                :key="item.to"
                :to="item.to"
                class="rounded-md p-2 transition-colors"
                :class="isActive(item.to) ? 'bg-secondary text-foreground' : 'text-muted-foreground'"
                :aria-label="item.label"
              >
                <component :is="item.icon" class="size-5" />
              </router-link>
              <button
                type="button"
                class="rounded-md p-2 text-muted-foreground"
                :aria-label="dark ? 'Mudar para tema claro' : 'Mudar para tema escuro'"
                @click="toggle"
              >
                <Moon v-if="!dark" class="size-5" />
                <Sun v-else class="size-5" />
              </button>
            </div>
          </header>

          <main class="flex-1">
            <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 md:py-10">
              <router-view />
            </div>
          </main>
        </div>
      </div>
    </template>
    <main v-else class="flex min-h-screen items-center justify-center p-4">
      <router-view />
    </main>
  </div>
</template>
