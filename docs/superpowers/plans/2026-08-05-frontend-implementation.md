# Inventory ERP Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Vue 3 SPA (Produtos, Compras, Vendas, Login) that consumes the `fone-ninja-backend` Laravel API, per the approved design at `docs/superpowers/specs/2026-08-05-frontend-architecture-design.md`.

**Architecture:** Layered `View -> Composable -> Store -> Service`, one Pinia store and one Axios service per domain (product/purchase/sale/auth), plus a cross-cutting `snackbar` store and `useApiError` composable. Idempotency keys for `/compras` and `/vendas` are generated per user submission inside the owning composable, never by the Axios interceptor.

**Tech Stack:** Vue 3 (Composition API, `<script setup>`) + TypeScript, Vite, Vue Router 4, Pinia 2, Vuetify 3, Axios, Vitest + @vue/test-utils + axios-mock-adapter.

## Global Constraints

- API contract fields stay in Portuguese exactly as the backend defines them: `nome`, `preco_venda`, `custo_medio`, `estoque`, `quantidade`, `preco_unitario`, `fornecedor`, `cliente`, `lucro`, `senha`. Everything else (variables, function names, file names, comments) is English.
- `VITE_API_BASE_URL` defaults to `http://localhost/api` (Laravel Sail default port 80, per `fone-ninja-backend/.env` `APP_PORT` default).
- Seeded login credentials for manual/evaluator testing: `email: test@example.com`, `senha: password` (from the backend's `DatabaseSeeder` + `UserFactory` default).
- No calculation logic (average cost, profit) is duplicated client-side. The only client-side arithmetic allowed is the raw subtotal preview (`quantidade * preco_unitario` summed), never profit.
- Idempotency key: generated once per user submission inside `usePurchaseForm`/`useSaleForm`, passed explicitly to the service call. The Axios interceptor only forwards a key if one is present on the request config — it never generates one.
- Submit buttons bind to a `loading` ref and disable while a request is in flight.
- All money values cross the wire as plain decimal numbers (e.g. `20.5`), matching the backend's `Money::formatted()` boundary — no cents-integer handling needed client-side.
- Path alias `@` resolves to `src/`.

---

### Task 1: Project Scaffold & Tooling

**Files:**
- Create: `package.json`, `vite.config.ts`, `tsconfig.json`, `tsconfig.app.json`, `tsconfig.node.json`, `index.html`
- Create: `src/main.ts`, `src/App.vue`, `src/plugins/vuetify.ts`
- Create: `.env.example`, `.gitignore`
- Create: `vitest.config.ts` (or a `test` block merged into `vite.config.ts`)

**Interfaces:**
- Produces: `@` path alias resolving to `src/`; `npm run dev`, `npm run build`, `npm run test` scripts; a mounted, empty Vuetify app shell renderable at `/`.

- [ ] **Step 1: Scaffold the Vite + Vue 3 + TS project**

```bash
cd /home/pedrophsms/projetos/fone-ninja-frontend
npm create vite@latest . -- --template vue-ts
```

When prompted about the directory not being empty (it has `docs/` and `.git/`), confirm to proceed in the current directory.

- [ ] **Step 2: Install runtime and dev dependencies**

```bash
npm install vue-router@4 pinia axios vuetify@^3 @mdi/font
npm install -D vitest @vue/test-utils jsdom axios-mock-adapter vite-tsconfig-paths
```

- [ ] **Step 3: Configure `vite.config.ts` with the `@` alias and Vitest**

```ts
// vite.config.ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tsconfigPaths from 'vite-tsconfig-paths'

export default defineConfig({
  plugins: [vue(), tsconfigPaths()],
  test: {
    environment: 'jsdom',
    globals: true,
  },
})
```

- [ ] **Step 4: Add the `@` path alias to `tsconfig.app.json`**

Add under `compilerOptions`:

```json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@/*": ["src/*"]
    }
  }
}
```

- [ ] **Step 5: Create the Vuetify plugin**

```ts
// src/plugins/vuetify.ts
import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

export const vuetify = createVuetify({
  components,
  directives,
})
```

- [ ] **Step 6: Wire `main.ts`**

```ts
// src/main.ts
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { vuetify } from './plugins/vuetify'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)

app.mount('#app')
```

`./router` doesn't exist yet — it's created in Task 5. Leave this import as-is; the app won't build until Task 5 lands, which is expected at this stage of an incremental plan.

- [ ] **Step 7: Create `.env.example`**

```
VITE_API_BASE_URL=http://localhost/api
```

- [ ] **Step 8: Add test script to `package.json`**

Add to `"scripts"`:

```json
"test": "vitest run"
```

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "chore: scaffold Vite + Vue 3 + TS project with Vuetify, Pinia, Vitest"
```

---

### Task 2: Type Definitions (API Contract Mirror)

**Files:**
- Create: `src/types/product.ts`
- Create: `src/types/purchase.ts`
- Create: `src/types/sale.ts`
- Create: `src/types/auth.ts`

**Interfaces:**
- Produces: `Product`, `CreateProductPayload`, `PurchaseItemPayload`, `CreatePurchasePayload`, `PurchaseItem`, `Purchase`, `SaleItemPayload`, `CreateSalePayload`, `SaleItem`, `Sale`, `LoginPayload`, `AuthUser`, `LoginResponse` — consumed by every service/store/composable in later tasks.

- [ ] **Step 1: Create `src/types/product.ts`**

```ts
export interface Product {
  id: number
  nome: string
  custo_medio: number
  preco_venda: number
  estoque: number
}

export interface CreateProductPayload {
  nome: string
  preco_venda: number
}
```

- [ ] **Step 2: Create `src/types/purchase.ts`**

```ts
export interface PurchaseItemPayload {
  id: number
  quantidade: number
  preco_unitario: number
}

export interface CreatePurchasePayload {
  fornecedor: string
  produtos: PurchaseItemPayload[]
}

export interface PurchaseItem {
  produto_id: number
  produto_nome: string
  quantidade: number
  preco_unitario: number
  subtotal: number
}

export interface Purchase {
  id: number
  fornecedor: string
  total: number
  produtos: PurchaseItem[]
  created_at: string
}
```

- [ ] **Step 3: Create `src/types/sale.ts`**

```ts
export interface SaleItemPayload {
  id: number
  quantidade: number
  preco_unitario: number
}

export interface CreateSalePayload {
  cliente: string
  produtos: SaleItemPayload[]
}

export interface SaleItem {
  produto_id: number
  produto_nome: string
  quantidade: number
  preco_unitario: number
  subtotal: number
  lucro_item: number
}

export type SaleStatus = 'completed' | 'cancelled'

export interface Sale {
  id: number
  cliente: string
  total: number
  lucro: number
  status: SaleStatus
  produtos: SaleItem[]
  created_at: string
}
```

- [ ] **Step 4: Create `src/types/auth.ts`**

```ts
export interface LoginPayload {
  email: string
  senha: string
}

export interface AuthUser {
  id: number
  nome: string
  email: string
}

export interface LoginResponse {
  usuario: AuthUser
  token: string
}
```

- [ ] **Step 5: Verify the project still type-checks**

Run: `npx vue-tsc --noEmit`
Expected: no errors referencing these new files (errors about the missing `./router` import from Task 1 are expected and fine at this stage).

- [ ] **Step 6: Commit**

```bash
git add src/types
git commit -m "feat: add TypeScript types mirroring the backend API contract"
```

---

### Task 3: Axios HTTP Client with Auth/Idempotency Interceptors

**Files:**
- Create: `src/api/http.ts`
- Test: `src/api/http.spec.ts`

**Interfaces:**
- Consumes: `useAuthStore` (produced in Task 5 — see note below on why this task is written against the store's eventual shape rather than blocked on it).
- Produces: `http` (configured Axios instance), `ApiError` (`{ message: string; fieldErrors?: Record<string, string[]> }`), and the `idempotencyKey` field on Axios request config, consumed by `purchaseService`/`saleService` (Tasks 7–8) and `useApiError` (Task 4).

This task is written assuming `useAuthStore` exposes a `token: string | null` property — that's the store's public contract, fixed here so Task 3 and Task 5 agree on it even though Task 5 implements the store afterward.

- [ ] **Step 1: Write the failing test for error normalization**

```ts
// src/api/http.spec.ts
import { describe, expect, it } from 'vitest'
import MockAdapter from 'axios-mock-adapter'
import { http } from './http'

describe('http error normalization', () => {
  it('extracts fieldErrors from a 422 validation response', async () => {
    const mock = new MockAdapter(http)
    mock.onPost('/produtos').reply(422, {
      message: 'The given data was invalid.',
      errors: { nome: ['O campo nome é obrigatório.'] },
    })

    await expect(http.post('/produtos', {})).rejects.toMatchObject({
      message: 'The given data was invalid.',
      fieldErrors: { nome: ['O campo nome é obrigatório.'] },
    })

    mock.restore()
  })

  it('extracts a plain message from a business-rule error', async () => {
    const mock = new MockAdapter(http)
    mock.onPost('/vendas').reply(422, {
      message: 'Estoque insuficiente para o produto Fone X',
    })

    await expect(http.post('/vendas', {})).rejects.toMatchObject({
      message: 'Estoque insuficiente para o produto Fone X',
    })

    mock.restore()
  })

  it('forwards the idempotencyKey config field as an Idempotency-Key header', async () => {
    const mock = new MockAdapter(http)
    mock.onPost('/compras').reply((config) => {
      expect(config.headers?.['Idempotency-Key']).toBe('test-key-123')
      return [201, {}]
    })

    await http.post('/compras', {}, { idempotencyKey: 'test-key-123' })

    mock.restore()
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run src/api/http.spec.ts`
Expected: FAIL — `src/api/http.ts` does not exist yet.

- [ ] **Step 3: Implement `src/api/http.ts`**

```ts
// src/api/http.ts
import axios, { type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'

export interface ApiError {
  message: string
  fieldErrors?: Record<string, string[]>
}

declare module 'axios' {
  export interface AxiosRequestConfig {
    idempotencyKey?: string
  }
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
})

http.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const authStore = useAuthStore()
  if (authStore.token) {
    config.headers.set('Authorization', `Bearer ${authStore.token}`)
  }
  if (config.idempotencyKey) {
    config.headers.set('Idempotency-Key', config.idempotencyKey)
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: unknown) => Promise.reject(normalizeError(error)),
)

function normalizeError(error: unknown): ApiError {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status
    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined

    if (status === 422 && data?.errors) {
      return { message: data.message ?? 'Dados inválidos', fieldErrors: data.errors }
    }
    if (data?.message) {
      return { message: data.message }
    }
    if (status === 401) {
      return { message: 'Sessão expirada, faça login novamente' }
    }
    return { message: 'Erro de comunicação com o servidor' }
  }
  return { message: 'Erro inesperado' }
}
```

Note: `useAuthStore` doesn't exist until Task 5. This file will fail to build/import in isolation until then — expected for this incremental plan. The test above uses `axios-mock-adapter` directly on the `http` instance and doesn't need a live Pinia store for the interceptor's `authStore.token` branch to be exercised (no `Authorization` header assertions in this task's tests), so the tests pass once Task 5's store file exists, even with `token: null`. If Vitest fails at import-time here because Task 5 isn't done yet, that's fine — proceed to Task 4 and revisit this task's test run after Task 5.

- [ ] **Step 4: Run the test to verify it passes (after Task 5 exists)**

Run: `npx vitest run src/api/http.spec.ts`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/api
git commit -m "feat: add Axios client with auth header and idempotency-key forwarding"
```

---

### Task 4: Snackbar Store & useApiError Composable

**Files:**
- Create: `src/stores/snackbar.ts`
- Create: `src/composables/useApiError.ts`
- Test: `src/composables/useApiError.spec.ts`

**Interfaces:**
- Consumes: `ApiError` (Task 3).
- Produces: `useSnackbarStore()` with state `{ visible, message, color }` and actions `showSuccess(message)` / `showError(message)`; `useApiError()` returning `{ handle(error: unknown): Record<string, string[]> | undefined }` — consumed by every form composable (Tasks 6–8) to route field errors back to the form and everything else to the snackbar.

- [ ] **Step 1: Create the snackbar store**

```ts
// src/stores/snackbar.ts
import { defineStore } from 'pinia'

interface SnackbarState {
  visible: boolean
  message: string
  color: 'success' | 'error'
}

export const useSnackbarStore = defineStore('snackbar', {
  state: (): SnackbarState => ({
    visible: false,
    message: '',
    color: 'success',
  }),
  actions: {
    showSuccess(message: string) {
      this.message = message
      this.color = 'success'
      this.visible = true
    },
    showError(message: string) {
      this.message = message
      this.color = 'error'
      this.visible = true
    },
  },
})
```

- [ ] **Step 2: Write the failing test for `useApiError`**

```ts
// src/composables/useApiError.spec.ts
import { describe, expect, it, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useApiError } from './useApiError'
import { useSnackbarStore } from '@/stores/snackbar'
import type { ApiError } from '@/api/http'

describe('useApiError', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('returns fieldErrors without touching the snackbar when present', () => {
    const snackbar = useSnackbarStore()
    const { handle } = useApiError()
    const error: ApiError = { message: 'invalid', fieldErrors: { nome: ['required'] } }

    const result = handle(error)

    expect(result).toEqual({ nome: ['required'] })
    expect(snackbar.visible).toBe(false)
  })

  it('pushes the message to the snackbar when there are no fieldErrors', () => {
    const snackbar = useSnackbarStore()
    const { handle } = useApiError()
    const error: ApiError = { message: 'Estoque insuficiente para o produto X' }

    const result = handle(error)

    expect(result).toBeUndefined()
    expect(snackbar.visible).toBe(true)
    expect(snackbar.color).toBe('error')
    expect(snackbar.message).toBe('Estoque insuficiente para o produto X')
  })
})
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `npx vitest run src/composables/useApiError.spec.ts`
Expected: FAIL — `src/composables/useApiError.ts` does not exist yet.

- [ ] **Step 4: Implement `useApiError`**

```ts
// src/composables/useApiError.ts
import type { ApiError } from '@/api/http'
import { useSnackbarStore } from '@/stores/snackbar'

export function useApiError() {
  const snackbar = useSnackbarStore()

  function handle(error: unknown): Record<string, string[]> | undefined {
    const apiError = error as ApiError
    if (apiError.fieldErrors) {
      return apiError.fieldErrors
    }
    snackbar.showError(apiError.message ?? 'Erro inesperado')
    return undefined
  }

  return { handle }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `npx vitest run src/composables/useApiError.spec.ts`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add src/stores/snackbar.ts src/composables/useApiError.ts src/composables/useApiError.spec.ts
git commit -m "feat: add snackbar store and useApiError composable"
```

---

### Task 5: Auth Store, Service, Login View & Router

**Files:**
- Create: `src/services/authService.ts`
- Create: `src/stores/auth.ts`
- Create: `src/router/index.ts`
- Create: `src/views/LoginView.vue`
- Modify: `src/App.vue` (nav bar + snackbar + router-view)
- Test: `src/stores/auth.spec.ts`

**Interfaces:**
- Consumes: `http` (Task 3), `LoginPayload`/`AuthUser`/`LoginResponse` (Task 2), `useSnackbarStore` (Task 4).
- Produces: `useAuthStore()` with state `{ token: string | null; user: AuthUser | null }` and actions `login(payload)` / `logout()` — consumed by `http.ts`'s interceptor (Task 3, retroactively satisfied here) and the router guard below. This closes the circular reference noted in Task 3.

- [ ] **Step 1: Create `authService`**

```ts
// src/services/authService.ts
import { http } from '@/api/http'
import type { LoginPayload, LoginResponse } from '@/types/auth'

export const authService = {
  login(payload: LoginPayload) {
    return http.post<LoginResponse>('/login', payload).then((r) => r.data)
  },
  logout() {
    return http.post('/logout').then(() => undefined)
  },
}
```

- [ ] **Step 2: Write the failing test for the auth store**

```ts
// src/stores/auth.spec.ts
import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from './auth'
import { authService } from '@/services/authService'

vi.mock('@/services/authService', () => ({
  authService: { login: vi.fn(), logout: vi.fn() },
}))

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  it('stores the token and user on successful login', async () => {
    vi.mocked(authService.login).mockResolvedValue({
      usuario: { id: 1, nome: 'Test User', email: 'test@example.com' },
      token: 'abc123',
    })
    const store = useAuthStore()

    await store.login({ email: 'test@example.com', senha: 'password' })

    expect(store.token).toBe('abc123')
    expect(store.user?.email).toBe('test@example.com')
    expect(localStorage.getItem('fone-ninja-token')).toBe('abc123')
  })

  it('clears token, user, and localStorage on logout', () => {
    const store = useAuthStore()
    store.token = 'abc123'
    localStorage.setItem('fone-ninja-token', 'abc123')

    store.logout()

    expect(store.token).toBeNull()
    expect(store.user).toBeNull()
    expect(localStorage.getItem('fone-ninja-token')).toBeNull()
  })
})
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `npx vitest run src/stores/auth.spec.ts`
Expected: FAIL — `src/stores/auth.ts` does not exist yet.

- [ ] **Step 4: Implement the auth store**

```ts
// src/stores/auth.ts
import { defineStore } from 'pinia'
import { authService } from '@/services/authService'
import type { AuthUser, LoginPayload } from '@/types/auth'

interface AuthState {
  token: string | null
  user: AuthUser | null
}

const TOKEN_KEY = 'fone-ninja-token'

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem(TOKEN_KEY),
    user: null,
  }),
  actions: {
    async login(payload: LoginPayload) {
      const { usuario, token } = await authService.login(payload)
      this.token = token
      this.user = usuario
      localStorage.setItem(TOKEN_KEY, token)
    },
    logout() {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_KEY)
    },
  },
})
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `npx vitest run src/stores/auth.spec.ts`
Expected: PASS (2 tests)

- [ ] **Step 6: Create the router with the auth guard**

```ts
// src/router/index.ts
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue') },
    { path: '/produtos', name: 'produtos', component: () => import('@/views/ProductsView.vue') },
    { path: '/compras', name: 'compras', component: () => import('@/views/PurchasesView.vue') },
    { path: '/vendas', name: 'vendas', component: () => import('@/views/SalesView.vue') },
    { path: '/', redirect: '/produtos' },
  ],
})

router.beforeEach((to) => {
  const authStore = useAuthStore()
  if (to.name !== 'login' && !authStore.token) {
    return { name: 'login' }
  }
})

export default router
```

`ProductsView.vue`, `PurchasesView.vue`, `SalesView.vue` don't exist yet (Tasks 6–8) — these lazy imports will 404 at runtime until then, which is fine at this stage.

- [ ] **Step 7: Create `LoginView.vue`**

```vue
<!-- src/views/LoginView.vue -->
<template>
  <v-container class="fill-height" max-width="400">
    <v-form @submit.prevent="submit">
      <v-card-title>Login</v-card-title>
      <v-text-field
        v-model="email"
        label="Email"
        type="email"
        :error-messages="errors.email"
      />
      <v-text-field
        v-model="senha"
        label="Senha"
        type="password"
        :error-messages="errors.senha"
      />
      <v-btn type="submit" color="primary" block :loading="loading" :disabled="loading">
        Entrar
      </v-btn>
    </v-form>
  </v-container>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useApiError } from '@/composables/useApiError'

const email = ref('')
const senha = ref('')
const errors = reactive<Record<string, string[]>>({})
const loading = ref(false)
const authStore = useAuthStore()
const { handle } = useApiError()
const router = useRouter()

async function submit() {
  loading.value = true
  try {
    await authStore.login({ email: email.value, senha: senha.value })
    router.push('/produtos')
  } catch (error) {
    const fieldErrors = handle(error)
    if (fieldErrors) Object.assign(errors, fieldErrors)
  } finally {
    loading.value = false
  }
}
</script>
```

- [ ] **Step 8: Wire `App.vue`**

```vue
<!-- src/App.vue -->
<template>
  <v-app>
    <v-app-bar v-if="authStore.token" title="Fone Ninja ERP">
      <v-btn to="/produtos" variant="text">Produtos</v-btn>
      <v-btn to="/compras" variant="text">Compras</v-btn>
      <v-btn to="/vendas" variant="text">Vendas</v-btn>
      <v-spacer />
      <v-btn variant="text" @click="handleLogout">Sair</v-btn>
    </v-app-bar>
    <v-main>
      <v-container>
        <router-view />
      </v-container>
    </v-main>
    <v-snackbar v-model="snackbar.visible" :color="snackbar.color">
      {{ snackbar.message }}
    </v-snackbar>
  </v-app>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSnackbarStore } from '@/stores/snackbar'

const authStore = useAuthStore()
const snackbar = useSnackbarStore()
const router = useRouter()

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>
```

- [ ] **Step 9: Re-run Task 3's `http.spec.ts` now that `useAuthStore` exists**

Run: `npx vitest run src/api/http.spec.ts`
Expected: PASS (3 tests)

- [ ] **Step 10: Commit**

```bash
git add src/services/authService.ts src/stores/auth.ts src/stores/auth.spec.ts src/router src/views/LoginView.vue src/App.vue
git commit -m "feat: add auth store, login view, and router guard"
```

---

### Task 6: Product Domain (Service, Store, Form, View)

**Files:**
- Create: `src/services/productService.ts`
- Create: `src/stores/product.ts`
- Create: `src/composables/useProductForm.ts`
- Create: `src/views/ProductsView.vue`
- Test: `src/composables/useProductForm.spec.ts`

**Interfaces:**
- Consumes: `http` (Task 3), `Product`/`CreateProductPayload` (Task 2), `useApiError` (Task 4), `useSnackbarStore` (Task 4).
- Produces: `useProductStore()` with state `{ items: Product[]; loading: boolean }` and actions `fetchAll()` / `create(payload)` — consumed directly by `usePurchaseForm`/`useSaleForm` (Tasks 7–8) to refetch stock after a purchase/sale.

- [ ] **Step 1: Create `productService`**

```ts
// src/services/productService.ts
import { http } from '@/api/http'
import type { CreateProductPayload, Product } from '@/types/product'

export const productService = {
  list() {
    return http.get<{ data: Product[] }>('/produtos').then((r) => r.data.data)
  },
  create(payload: CreateProductPayload) {
    return http.post<Product>('/produtos', payload).then((r) => r.data)
  },
}
```

- [ ] **Step 2: Create the product store**

```ts
// src/stores/product.ts
import { defineStore } from 'pinia'
import { productService } from '@/services/productService'
import type { CreateProductPayload, Product } from '@/types/product'

interface ProductState {
  items: Product[]
  loading: boolean
}

export const useProductStore = defineStore('product', {
  state: (): ProductState => ({ items: [], loading: false }),
  actions: {
    async fetchAll() {
      this.loading = true
      try {
        this.items = await productService.list()
      } finally {
        this.loading = false
      }
    },
    async create(payload: CreateProductPayload) {
      const created = await productService.create(payload)
      this.items.push(created)
      return created
    },
  },
})
```

- [ ] **Step 3: Write the failing test for `useProductForm`**

```ts
// src/composables/useProductForm.spec.ts
import { describe, expect, it, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useProductForm } from './useProductForm'
import { useProductStore } from '@/stores/product'

describe('useProductForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('rejects a name shorter than 3 characters without calling the store', async () => {
    const { form, errors, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create')
    form.nome = 'ab'
    form.preco_venda = 10

    await submit()

    expect(errors.nome).toEqual(['Nome deve ter no mínimo 3 caracteres'])
    expect(store.create).not.toHaveBeenCalled()
  })

  it('rejects a non-positive preco_venda without calling the store', async () => {
    const { form, errors, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create')
    form.nome = 'Fone Bluetooth'
    form.preco_venda = 0

    await submit()

    expect(errors.preco_venda).toEqual(['Preço de venda deve ser positivo'])
    expect(store.create).not.toHaveBeenCalled()
  })

  it('calls the store and resets the form on valid submit', async () => {
    const { form, submit } = useProductForm()
    const store = useProductStore()
    vi.spyOn(store, 'create').mockResolvedValue({
      id: 1,
      nome: 'Fone Bluetooth',
      custo_medio: 0,
      preco_venda: 50,
      estoque: 0,
    })
    form.nome = 'Fone Bluetooth'
    form.preco_venda = 50

    await submit()

    expect(store.create).toHaveBeenCalledWith({ nome: 'Fone Bluetooth', preco_venda: 50 })
    expect(form.nome).toBe('')
    expect(form.preco_venda).toBeNull()
  })
})
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `npx vitest run src/composables/useProductForm.spec.ts`
Expected: FAIL — `src/composables/useProductForm.ts` does not exist yet.

- [ ] **Step 5: Implement `useProductForm`**

```ts
// src/composables/useProductForm.ts
import { reactive, ref } from 'vue'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'

interface ProductFormState {
  nome: string
  preco_venda: number | null
}

export function useProductForm() {
  const form = reactive<ProductFormState>({ nome: '', preco_venda: null })
  const errors = reactive<Record<string, string[]>>({})
  const loading = ref(false)
  const productStore = useProductStore()
  const { handle } = useApiError()
  const snackbar = useSnackbarStore()

  function validate(): boolean {
    Object.keys(errors).forEach((key) => delete errors[key])
    if (form.nome.trim().length < 3) {
      errors.nome = ['Nome deve ter no mínimo 3 caracteres']
    }
    if (!form.preco_venda || form.preco_venda <= 0) {
      errors.preco_venda = ['Preço de venda deve ser positivo']
    }
    return Object.keys(errors).length === 0
  }

  async function submit() {
    if (!validate()) return
    loading.value = true
    try {
      await productStore.create({ nome: form.nome, preco_venda: form.preco_venda! })
      snackbar.showSuccess('Produto cadastrado com sucesso')
      form.nome = ''
      form.preco_venda = null
    } catch (error) {
      const fieldErrors = handle(error)
      if (fieldErrors) Object.assign(errors, fieldErrors)
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, submit }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `npx vitest run src/composables/useProductForm.spec.ts`
Expected: PASS (3 tests)

- [ ] **Step 7: Create `ProductsView.vue`**

```vue
<!-- src/views/ProductsView.vue -->
<template>
  <div>
    <v-card class="mb-4">
      <v-card-title>Cadastrar produto</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="submit">
          <v-text-field v-model="form.nome" label="Nome" :error-messages="errors.nome" />
          <v-text-field
            v-model.number="form.preco_venda"
            label="Preço de venda"
            type="number"
            step="0.01"
            :error-messages="errors.preco_venda"
          />
          <v-btn type="submit" color="primary" :loading="loading" :disabled="loading">
            Cadastrar
          </v-btn>
        </v-form>
      </v-card-text>
    </v-card>

    <v-data-table :items="productStore.items" :loading="productStore.loading" :headers="headers" />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useProductForm } from '@/composables/useProductForm'
import { useProductStore } from '@/stores/product'

const { form, errors, loading, submit } = useProductForm()
const productStore = useProductStore()

const headers = [
  { title: 'Nome', key: 'nome' },
  { title: 'Custo médio', key: 'custo_medio' },
  { title: 'Preço de venda', key: 'preco_venda' },
  { title: 'Estoque', key: 'estoque' },
]

onMounted(() => {
  productStore.fetchAll()
})
</script>
```

- [ ] **Step 8: Commit**

```bash
git add src/services/productService.ts src/stores/product.ts src/composables/useProductForm.ts src/composables/useProductForm.spec.ts src/views/ProductsView.vue
git commit -m "feat: add product domain (service, store, form, view)"
```

---

### Task 7: Purchase Domain (Service, Store, Form, View)

**Files:**
- Create: `src/services/purchaseService.ts`
- Create: `src/stores/purchase.ts`
- Create: `src/composables/usePurchaseForm.ts`
- Create: `src/views/PurchasesView.vue`
- Test: `src/composables/usePurchaseForm.spec.ts`

**Interfaces:**
- Consumes: `http` (Task 3), `Purchase`/`CreatePurchasePayload`/`PurchaseItemPayload` (Task 2), `useApiError`/`useSnackbarStore` (Task 4), `useProductStore` (Task 6, for the post-purchase refetch).
- Produces: `usePurchaseStore()` with state `{ items: Purchase[]; loading: boolean }` and actions `fetchAll()` / `create(payload, idempotencyKey)`.

- [ ] **Step 1: Create `purchaseService`**

```ts
// src/services/purchaseService.ts
import { http } from '@/api/http'
import type { CreatePurchasePayload, Purchase } from '@/types/purchase'

export const purchaseService = {
  list() {
    return http.get<{ data: Purchase[] }>('/compras').then((r) => r.data.data)
  },
  create(payload: CreatePurchasePayload, idempotencyKey: string) {
    return http.post<Purchase>('/compras', payload, { idempotencyKey }).then((r) => r.data)
  },
}
```

- [ ] **Step 2: Create the purchase store**

```ts
// src/stores/purchase.ts
import { defineStore } from 'pinia'
import { purchaseService } from '@/services/purchaseService'
import type { CreatePurchasePayload, Purchase } from '@/types/purchase'

interface PurchaseState {
  items: Purchase[]
  loading: boolean
}

export const usePurchaseStore = defineStore('purchase', {
  state: (): PurchaseState => ({ items: [], loading: false }),
  actions: {
    async fetchAll() {
      this.loading = true
      try {
        this.items = await purchaseService.list()
      } finally {
        this.loading = false
      }
    },
    async create(payload: CreatePurchasePayload, idempotencyKey: string) {
      const created = await purchaseService.create(payload, idempotencyKey)
      this.items.unshift(created)
      return created
    },
  },
})
```

- [ ] **Step 3: Write the failing test for `usePurchaseForm`**

```ts
// src/composables/usePurchaseForm.spec.ts
import { describe, expect, it, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePurchaseForm } from './usePurchaseForm'
import { usePurchaseStore } from '@/stores/purchase'
import { useProductStore } from '@/stores/product'

describe('usePurchaseForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('rejects duplicate product ids in the same purchase', async () => {
    const { form, errors, submit } = usePurchaseForm()
    const purchaseStore = usePurchaseStore()
    vi.spyOn(purchaseStore, 'create')
    form.fornecedor = 'Fornecedor X'
    form.produtos = [
      { id: 1, quantidade: 5, preco_unitario: 10 },
      { id: 1, quantidade: 2, preco_unitario: 10 },
    ]

    await submit()

    expect(errors.produtos).toEqual(['Não é possível repetir o mesmo produto na mesma compra'])
    expect(purchaseStore.create).not.toHaveBeenCalled()
  })

  it('computes the subtotal preview as a plain sum of quantidade * preco_unitario', () => {
    const { form, subtotalPreview } = usePurchaseForm()
    form.produtos = [
      { id: 1, quantidade: 5, preco_unitario: 10 },
      { id: 2, quantidade: 2, preco_unitario: 3 },
    ]

    expect(subtotalPreview.value).toBe(56)
  })

  it('generates a fresh idempotency key per submit and refetches products on success', async () => {
    const { form, submit } = usePurchaseForm()
    const purchaseStore = usePurchaseStore()
    const productStore = useProductStore()
    vi.spyOn(purchaseStore, 'create').mockResolvedValue({
      id: 1,
      fornecedor: 'Fornecedor X',
      total: 50,
      produtos: [],
      created_at: '2026-08-05T00:00:00Z',
    })
    vi.spyOn(productStore, 'fetchAll').mockResolvedValue()
    form.fornecedor = 'Fornecedor X'
    form.produtos = [{ id: 1, quantidade: 5, preco_unitario: 10 }]

    await submit()

    expect(purchaseStore.create).toHaveBeenCalledTimes(1)
    const [, idempotencyKey] = vi.mocked(purchaseStore.create).mock.calls[0]
    expect(typeof idempotencyKey).toBe('string')
    expect(idempotencyKey.length).toBeGreaterThan(0)
    expect(productStore.fetchAll).toHaveBeenCalledTimes(1)
  })
})
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `npx vitest run src/composables/usePurchaseForm.spec.ts`
Expected: FAIL — `src/composables/usePurchaseForm.ts` does not exist yet.

- [ ] **Step 5: Implement `usePurchaseForm`**

```ts
// src/composables/usePurchaseForm.ts
import { computed, reactive, ref } from 'vue'
import { usePurchaseStore } from '@/stores/purchase'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'
import type { PurchaseItemPayload } from '@/types/purchase'

interface PurchaseFormState {
  fornecedor: string
  produtos: PurchaseItemPayload[]
}

export function usePurchaseForm() {
  const form = reactive<PurchaseFormState>({
    fornecedor: '',
    produtos: [{ id: 0, quantidade: 1, preco_unitario: 0 }],
  })
  const errors = reactive<Record<string, string[]>>({})
  const loading = ref(false)
  const purchaseStore = usePurchaseStore()
  const productStore = useProductStore()
  const { handle } = useApiError()
  const snackbar = useSnackbarStore()

  const subtotalPreview = computed(() =>
    form.produtos.reduce((sum, item) => sum + item.quantidade * item.preco_unitario, 0),
  )

  function addItem() {
    form.produtos.push({ id: 0, quantidade: 1, preco_unitario: 0 })
  }

  function removeItem(index: number) {
    if (form.produtos.length > 1) form.produtos.splice(index, 1)
  }

  function validate(): boolean {
    Object.keys(errors).forEach((key) => delete errors[key])
    if (!form.fornecedor.trim()) {
      errors.fornecedor = ['Fornecedor é obrigatório']
    }
    const ids = form.produtos.map((p) => p.id)
    if (new Set(ids).size !== ids.length) {
      errors.produtos = ['Não é possível repetir o mesmo produto na mesma compra']
    }
    form.produtos.forEach((item, index) => {
      if (!item.id) errors[`produtos.${index}.id`] = ['Selecione um produto']
      if (item.quantidade < 1) errors[`produtos.${index}.quantidade`] = ['Quantidade mínima é 1']
      if (item.preco_unitario < 0.01) {
        errors[`produtos.${index}.preco_unitario`] = ['Preço unitário deve ser no mínimo 0.01']
      }
    })
    return Object.keys(errors).length === 0
  }

  async function submit() {
    if (!validate()) return
    loading.value = true
    const idempotencyKey = crypto.randomUUID()
    try {
      await purchaseStore.create({ fornecedor: form.fornecedor, produtos: form.produtos }, idempotencyKey)
      snackbar.showSuccess('Compra registrada com sucesso')
      form.fornecedor = ''
      form.produtos = [{ id: 0, quantidade: 1, preco_unitario: 0 }]
      await productStore.fetchAll()
    } catch (error) {
      const fieldErrors = handle(error)
      if (fieldErrors) Object.assign(errors, fieldErrors)
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, subtotalPreview, addItem, removeItem, submit }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `npx vitest run src/composables/usePurchaseForm.spec.ts`
Expected: PASS (3 tests)

- [ ] **Step 7: Create `PurchasesView.vue`**

```vue
<!-- src/views/PurchasesView.vue -->
<template>
  <div>
    <v-card class="mb-4">
      <v-card-title>Registrar compra</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="submit">
          <v-text-field v-model="form.fornecedor" label="Fornecedor" :error-messages="errors.fornecedor" />

          <div v-for="(item, index) in form.produtos" :key="index" class="d-flex ga-2 align-center mb-2">
            <v-select
              v-model="item.id"
              :items="productStore.items"
              item-title="nome"
              item-value="id"
              label="Produto"
              :error-messages="errors[`produtos.${index}.id`]"
            />
            <v-text-field
              v-model.number="item.quantidade"
              label="Quantidade"
              type="number"
              :error-messages="errors[`produtos.${index}.quantidade`]"
            />
            <v-text-field
              v-model.number="item.preco_unitario"
              label="Preço unitário"
              type="number"
              step="0.01"
              :error-messages="errors[`produtos.${index}.preco_unitario`]"
            />
            <v-btn icon="mdi-delete" variant="text" @click="removeItem(index)" />
          </div>
          <p v-if="errors.produtos" class="text-error text-caption">{{ errors.produtos[0] }}</p>

          <v-btn variant="text" @click="addItem">Adicionar produto</v-btn>
          <p class="text-subtitle-1">Subtotal estimado: {{ subtotalPreview }}</p>

          <v-btn type="submit" color="primary" :loading="loading" :disabled="loading">
            Registrar compra
          </v-btn>
        </v-form>
      </v-card-text>
    </v-card>

    <v-data-table :items="purchaseStore.items" :loading="purchaseStore.loading" :headers="headers">
      <template #item.produtos="{ item }">
        {{ item.produtos.map((p) => `${p.produto_nome} x${p.quantidade}`).join(', ') }}
      </template>
    </v-data-table>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { usePurchaseForm } from '@/composables/usePurchaseForm'
import { usePurchaseStore } from '@/stores/purchase'
import { useProductStore } from '@/stores/product'

const { form, errors, loading, subtotalPreview, addItem, removeItem, submit } = usePurchaseForm()
const purchaseStore = usePurchaseStore()
const productStore = useProductStore()

const headers = [
  { title: 'Fornecedor', key: 'fornecedor' },
  { title: 'Total', key: 'total' },
  { title: 'Itens', key: 'produtos', sortable: false },
  { title: 'Data', key: 'created_at' },
]

onMounted(() => {
  purchaseStore.fetchAll()
  productStore.fetchAll()
})
</script>
```

- [ ] **Step 8: Commit**

```bash
git add src/services/purchaseService.ts src/stores/purchase.ts src/composables/usePurchaseForm.ts src/composables/usePurchaseForm.spec.ts src/views/PurchasesView.vue
git commit -m "feat: add purchase domain (service, store, form, view)"
```

---

### Task 8: Sale Domain (Service, Store, Form, View, Cancel)

**Files:**
- Create: `src/services/saleService.ts`
- Create: `src/stores/sale.ts`
- Create: `src/composables/useSaleForm.ts`
- Create: `src/views/SalesView.vue`
- Test: `src/composables/useSaleForm.spec.ts`

**Interfaces:**
- Consumes: `http` (Task 3), `Sale`/`CreateSalePayload`/`SaleItemPayload` (Task 2), `useApiError`/`useSnackbarStore` (Task 4), `useProductStore` (Task 6).
- Produces: `useSaleStore()` with state `{ items: Sale[]; loading: boolean }` and actions `fetchAll()` / `create(payload, idempotencyKey)` / `cancel(id)`.

- [ ] **Step 1: Create `saleService`**

```ts
// src/services/saleService.ts
import { http } from '@/api/http'
import type { CreateSalePayload, Sale } from '@/types/sale'

export const saleService = {
  list() {
    return http.get<{ data: Sale[] }>('/vendas').then((r) => r.data.data)
  },
  create(payload: CreateSalePayload, idempotencyKey: string) {
    return http.post<Sale>('/vendas', payload, { idempotencyKey }).then((r) => r.data)
  },
  cancel(id: number) {
    return http.post<Sale>(`/vendas/${id}/cancelar`).then((r) => r.data)
  },
}
```

- [ ] **Step 2: Create the sale store**

```ts
// src/stores/sale.ts
import { defineStore } from 'pinia'
import { saleService } from '@/services/saleService'
import type { CreateSalePayload, Sale } from '@/types/sale'

interface SaleState {
  items: Sale[]
  loading: boolean
}

export const useSaleStore = defineStore('sale', {
  state: (): SaleState => ({ items: [], loading: false }),
  actions: {
    async fetchAll() {
      this.loading = true
      try {
        this.items = await saleService.list()
      } finally {
        this.loading = false
      }
    },
    async create(payload: CreateSalePayload, idempotencyKey: string) {
      const created = await saleService.create(payload, idempotencyKey)
      this.items.unshift(created)
      return created
    },
    async cancel(id: number) {
      const cancelled = await saleService.cancel(id)
      const index = this.items.findIndex((sale) => sale.id === id)
      if (index !== -1) this.items[index] = cancelled
      return cancelled
    },
  },
})
```

- [ ] **Step 3: Write the failing test for `useSaleForm`**

```ts
// src/composables/useSaleForm.spec.ts
import { describe, expect, it, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useSaleForm } from './useSaleForm'
import { useSaleStore } from '@/stores/sale'
import { useProductStore } from '@/stores/product'
import { useSnackbarStore } from '@/stores/snackbar'

describe('useSaleForm', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('rejects duplicate product ids in the same sale', async () => {
    const { form, errors, submit } = useSaleForm()
    const saleStore = useSaleStore()
    vi.spyOn(saleStore, 'create')
    form.cliente = 'Fulano da Silva'
    form.produtos = [
      { id: 1, quantidade: 2, preco_unitario: 50 },
      { id: 1, quantidade: 1, preco_unitario: 50 },
    ]

    await submit()

    expect(errors.produtos).toEqual(['Não é possível repetir o mesmo produto na mesma venda'])
    expect(saleStore.create).not.toHaveBeenCalled()
  })

  it('shows a success message including total and lucro, and refetches products', async () => {
    const { form, submit } = useSaleForm()
    const saleStore = useSaleStore()
    const productStore = useProductStore()
    const snackbar = useSnackbarStore()
    vi.spyOn(saleStore, 'create').mockResolvedValue({
      id: 1,
      cliente: 'Fulano da Silva',
      total: 100,
      lucro: 30,
      status: 'completed',
      produtos: [],
      created_at: '2026-08-05T00:00:00Z',
    })
    vi.spyOn(productStore, 'fetchAll').mockResolvedValue()
    form.cliente = 'Fulano da Silva'
    form.produtos = [{ id: 1, quantidade: 2, preco_unitario: 50 }]

    await submit()

    expect(snackbar.message).toContain('100')
    expect(snackbar.message).toContain('30')
    expect(productStore.fetchAll).toHaveBeenCalledTimes(1)
  })

  it('surfaces an insufficient-stock error via the snackbar, not as a field error', async () => {
    const { form, submit } = useSaleForm()
    const saleStore = useSaleStore()
    const snackbar = useSnackbarStore()
    vi.spyOn(saleStore, 'create').mockRejectedValue({
      message: 'Estoque insuficiente para o produto Fone X',
    })
    form.cliente = 'Fulano da Silva'
    form.produtos = [{ id: 1, quantidade: 999, preco_unitario: 50 }]

    await submit()

    expect(snackbar.visible).toBe(true)
    expect(snackbar.message).toBe('Estoque insuficiente para o produto Fone X')
  })
})
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `npx vitest run src/composables/useSaleForm.spec.ts`
Expected: FAIL — `src/composables/useSaleForm.ts` does not exist yet.

- [ ] **Step 5: Implement `useSaleForm`**

```ts
// src/composables/useSaleForm.ts
import { computed, reactive, ref } from 'vue'
import { useSaleStore } from '@/stores/sale'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'
import type { SaleItemPayload } from '@/types/sale'

interface SaleFormState {
  cliente: string
  produtos: SaleItemPayload[]
}

export function useSaleForm() {
  const form = reactive<SaleFormState>({
    cliente: '',
    produtos: [{ id: 0, quantidade: 1, preco_unitario: 0 }],
  })
  const errors = reactive<Record<string, string[]>>({})
  const loading = ref(false)
  const saleStore = useSaleStore()
  const productStore = useProductStore()
  const { handle } = useApiError()
  const snackbar = useSnackbarStore()

  const subtotalPreview = computed(() =>
    form.produtos.reduce((sum, item) => sum + item.quantidade * item.preco_unitario, 0),
  )

  function addItem() {
    form.produtos.push({ id: 0, quantidade: 1, preco_unitario: 0 })
  }

  function removeItem(index: number) {
    if (form.produtos.length > 1) form.produtos.splice(index, 1)
  }

  function validate(): boolean {
    Object.keys(errors).forEach((key) => delete errors[key])
    if (!form.cliente.trim()) {
      errors.cliente = ['Cliente é obrigatório']
    }
    const ids = form.produtos.map((p) => p.id)
    if (new Set(ids).size !== ids.length) {
      errors.produtos = ['Não é possível repetir o mesmo produto na mesma venda']
    }
    form.produtos.forEach((item, index) => {
      if (!item.id) errors[`produtos.${index}.id`] = ['Selecione um produto']
      if (item.quantidade < 1) errors[`produtos.${index}.quantidade`] = ['Quantidade mínima é 1']
      if (item.preco_unitario < 0.01) {
        errors[`produtos.${index}.preco_unitario`] = ['Preço unitário deve ser no mínimo 0.01']
      }
    })
    return Object.keys(errors).length === 0
  }

  async function submit() {
    if (!validate()) return
    loading.value = true
    const idempotencyKey = crypto.randomUUID()
    try {
      const created = await saleStore.create(
        { cliente: form.cliente, produtos: form.produtos },
        idempotencyKey,
      )
      snackbar.showSuccess(
        `Venda registrada com sucesso — total ${created.total}, lucro ${created.lucro}`,
      )
      form.cliente = ''
      form.produtos = [{ id: 0, quantidade: 1, preco_unitario: 0 }]
      await productStore.fetchAll()
    } catch (error) {
      const fieldErrors = handle(error)
      if (fieldErrors) Object.assign(errors, fieldErrors)
    } finally {
      loading.value = false
    }
  }

  return { form, errors, loading, subtotalPreview, addItem, removeItem, submit }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `npx vitest run src/composables/useSaleForm.spec.ts`
Expected: PASS (3 tests)

- [ ] **Step 7: Create `SalesView.vue`**

```vue
<!-- src/views/SalesView.vue -->
<template>
  <div>
    <v-card class="mb-4">
      <v-card-title>Registrar venda</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="submit">
          <v-text-field v-model="form.cliente" label="Cliente" :error-messages="errors.cliente" />

          <div v-for="(item, index) in form.produtos" :key="index" class="d-flex ga-2 align-center mb-2">
            <v-select
              v-model="item.id"
              :items="productStore.items"
              item-title="nome"
              item-value="id"
              label="Produto"
              :error-messages="errors[`produtos.${index}.id`]"
            />
            <v-text-field
              v-model.number="item.quantidade"
              label="Quantidade"
              type="number"
              :error-messages="errors[`produtos.${index}.quantidade`]"
            />
            <v-text-field
              v-model.number="item.preco_unitario"
              label="Preço unitário"
              type="number"
              step="0.01"
              :error-messages="errors[`produtos.${index}.preco_unitario`]"
            />
            <v-btn icon="mdi-delete" variant="text" @click="removeItem(index)" />
          </div>
          <p v-if="errors.produtos" class="text-error text-caption">{{ errors.produtos[0] }}</p>

          <v-btn variant="text" @click="addItem">Adicionar produto</v-btn>
          <p class="text-subtitle-1">Subtotal estimado: {{ subtotalPreview }}</p>

          <v-btn type="submit" color="primary" :loading="loading" :disabled="loading">
            Registrar venda
          </v-btn>
        </v-form>
      </v-card-text>
    </v-card>

    <v-data-table :items="saleStore.items" :loading="saleStore.loading" :headers="headers">
      <template #item.actions="{ item }">
        <v-btn
          size="small"
          variant="text"
          :disabled="item.status === 'cancelled'"
          @click="cancelSale(item.id)"
        >
          Cancelar
        </v-btn>
      </template>
    </v-data-table>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useSaleForm } from '@/composables/useSaleForm'
import { useSaleStore } from '@/stores/sale'
import { useProductStore } from '@/stores/product'
import { useApiError } from '@/composables/useApiError'
import { useSnackbarStore } from '@/stores/snackbar'

const { form, errors, loading, subtotalPreview, addItem, removeItem, submit } = useSaleForm()
const saleStore = useSaleStore()
const productStore = useProductStore()
const { handle } = useApiError()
const snackbar = useSnackbarStore()

const headers = [
  { title: 'Cliente', key: 'cliente' },
  { title: 'Total', key: 'total' },
  { title: 'Lucro', key: 'lucro' },
  { title: 'Status', key: 'status' },
  { title: 'Ações', key: 'actions', sortable: false },
]

async function cancelSale(id: number) {
  try {
    await saleStore.cancel(id)
    snackbar.showSuccess('Venda cancelada com sucesso')
    await productStore.fetchAll()
  } catch (error) {
    handle(error)
  }
}

onMounted(() => {
  saleStore.fetchAll()
  productStore.fetchAll()
})
</script>
```

- [ ] **Step 8: Commit**

```bash
git add src/services/saleService.ts src/stores/sale.ts src/composables/useSaleForm.ts src/composables/useSaleForm.spec.ts src/views/SalesView.vue
git commit -m "feat: add sale domain (service, store, form, view, cancel)"
```

---

### Task 9: Component Tests — Double-Submit Guard & Cancel-Disabled State

**Files:**
- Test: `src/views/PurchasesView.spec.ts`
- Test: `src/views/SalesView.spec.ts`

**Interfaces:**
- Consumes: `PurchasesView.vue` (Task 7), `SalesView.vue` (Task 8), `http` (Task 3), `axios-mock-adapter`.

These tests exercise the composable+store+Axios wiring together — the level composable-only unit tests (Tasks 6–8) can't reach, since those mock the store directly.

- [ ] **Step 1: Write the failing double-submit test for `PurchasesView`**

```ts
// src/views/PurchasesView.spec.ts
import { describe, expect, it, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import MockAdapter from 'axios-mock-adapter'
import PurchasesView from './PurchasesView.vue'
import { http } from '@/api/http'
import { useProductStore } from '@/stores/product'

const vuetify = createVuetify({ components, directives })

describe('PurchasesView', () => {
  let mockHttp: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    mockHttp = new MockAdapter(http, { delayResponse: 20 })
    mockHttp.onGet('/produtos').reply(200, { data: [] })
    mockHttp.onGet('/compras').reply(200, { data: [] })
  })

  afterEach(() => {
    mockHttp.restore()
  })

  it('disables the submit button while a purchase request is in flight, allowing only one call', async () => {
    let callCount = 0
    mockHttp.onPost('/compras').reply(() => {
      callCount += 1
      return [201, { id: 1, fornecedor: 'Fornecedor X', total: 50, produtos: [], created_at: '' }]
    })
    const productStore = useProductStore()
    productStore.items = [{ id: 1, nome: 'Fone X', custo_medio: 5, preco_venda: 10, estoque: 100 }]

    const wrapper = mount(PurchasesView, { global: { plugins: [vuetify] } })
    await wrapper.vm.$nextTick()

    const form = wrapper.vm as unknown as {
      form: { fornecedor: string; produtos: Array<{ id: number; quantidade: number; preco_unitario: number }> }
      submit: () => Promise<void>
      loading: boolean
    }
    form.form.fornecedor = 'Fornecedor X'
    form.form.produtos = [{ id: 1, quantidade: 5, preco_unitario: 10 }]

    const firstSubmit = form.submit()
    const secondSubmit = form.submit()
    await Promise.all([firstSubmit, secondSubmit])

    expect(callCount).toBe(1)
  })
})
```

Note: this test calls `form.submit()` a second time directly (bypassing the disabled button in the DOM) to prove the guard is the `loading` flag itself, not just the button's `:disabled` binding — the button is a secondary UI affordance for the same underlying guard. For this to work, `submit` must be a no-op re-entry while `loading` is true.

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run src/views/PurchasesView.spec.ts`
Expected: FAIL — `usePurchaseForm`'s `submit()` currently has no re-entry guard, so `callCount` will be `2`.

- [ ] **Step 3: Add the re-entry guard to `usePurchaseForm.submit()`**

In `src/composables/usePurchaseForm.ts`, change the start of `submit`:

```ts
async function submit() {
  if (loading.value) return
  if (!validate()) return
  loading.value = true
  // ...unchanged...
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run src/views/PurchasesView.spec.ts`
Expected: PASS

- [ ] **Step 5: Apply the same re-entry guard to `useSaleForm.submit()`**

In `src/composables/useSaleForm.ts`, change the start of `submit`:

```ts
async function submit() {
  if (loading.value) return
  if (!validate()) return
  loading.value = true
  // ...unchanged...
}
```

- [ ] **Step 6: Write the failing cancel-disabled test for `SalesView`**

```ts
// src/views/SalesView.spec.ts
import { describe, expect, it, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import MockAdapter from 'axios-mock-adapter'
import SalesView from './SalesView.vue'
import { http } from '@/api/http'

const vuetify = createVuetify({ components, directives })

describe('SalesView', () => {
  let mockHttp: MockAdapter

  beforeEach(() => {
    setActivePinia(createPinia())
    mockHttp = new MockAdapter(http)
    mockHttp.onGet('/produtos').reply(200, { data: [] })
    mockHttp.onGet('/vendas').reply(200, {
      data: [
        {
          id: 1,
          cliente: 'Fulano da Silva',
          total: 100,
          lucro: 30,
          status: 'cancelled',
          produtos: [],
          created_at: '',
        },
        {
          id: 2,
          cliente: 'Ciclano',
          total: 200,
          lucro: 60,
          status: 'completed',
          produtos: [],
          created_at: '',
        },
      ],
    })
  })

  afterEach(() => {
    mockHttp.restore()
  })

  it('disables the Cancelar button only for already-cancelled sales', async () => {
    const wrapper = mount(SalesView, { global: { plugins: [vuetify] } })
    await new Promise((resolve) => setTimeout(resolve, 0))
    await wrapper.vm.$nextTick()

    const buttons = wrapper.findAll('button').filter((b) => b.text() === 'Cancelar')
    expect(buttons).toHaveLength(2)
    expect(buttons[0].attributes('disabled')).toBeDefined()
    expect(buttons[1].attributes('disabled')).toBeUndefined()
  })
})
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `npx vitest run src/views/SalesView.spec.ts`
Expected: PASS — `SalesView.vue` (Task 8) already binds `:disabled="item.status === 'cancelled'"`, so no implementation change should be needed here; this step only confirms it.

- [ ] **Step 8: Run the full test suite**

Run: `npx vitest run`
Expected: all test files pass.

- [ ] **Step 9: Commit**

```bash
git add src/views/PurchasesView.spec.ts src/views/SalesView.spec.ts src/composables/usePurchaseForm.ts src/composables/useSaleForm.ts
git commit -m "test: cover double-submit guard and cancel-disabled state at the component level"
```

---

### Task 10: README, Env Docs & Final Smoke Check

**Files:**
- Modify: `README.md` (create if the scaffold in Task 1 didn't produce one worth keeping)
- Verify: `.env.example` (from Task 1)

**Interfaces:**
- None — this task documents and verifies what earlier tasks built; it introduces no new code contracts.

- [ ] **Step 1: Write `README.md`**

```markdown
# Fone Ninja — Frontend

Vue 3 + TypeScript SPA for the Inventory ERP challenge, consuming the `fone-ninja-backend` API.

## Setup

\`\`\`bash
npm install
cp .env.example .env
npm run dev
\`\`\`

`VITE_API_BASE_URL` in `.env` must point at the running backend (defaults to `http://localhost/api`, matching Laravel Sail's default port).

## Login (seeded user)

The backend seeds a test user via `php artisan migrate --seed`:

- email: `test@example.com`
- senha: `password`

## Testing

\`\`\`bash
npm run test
\`\`\`
```

- [ ] **Step 2: Confirm `.env.example` matches the documented default**

Verify `.env.example` contains exactly:

```
VITE_API_BASE_URL=http://localhost/api
```

(Already created in Task 1, Step 7 — this step is just a check, not a new write.)

- [ ] **Step 3: Run the full test suite**

Run: `npx vitest run`
Expected: all tests pass.

- [ ] **Step 4: Run a production build**

Run: `npm run build`
Expected: build succeeds with no TypeScript errors.

- [ ] **Step 5: Commit**

```bash
git add README.md .env.example
git commit -m "docs: add setup instructions and seeded login credentials"
```

---

## Plan Self-Review Notes

- **Spec coverage**: every spec section (§2 layered architecture, §3.1 HTTP client, §3.2 error handling, §3.3 routing, §4 all four screens, §5 testing, §6 folder structure) maps to at least one task above. The seeded-credentials and product-refetch-after-purchase/sale fixes from the design review are implemented in Tasks 7, 8, and 10.
- **Idempotency key correctness**: Task 7/8 generate the key inside the composable's `submit()`, once per call, and pass it explicitly to the store/service — matching the corrected design. Task 9 adds the re-entry guard (`if (loading.value) return`) that the design's "first line of defense" language implied but earlier tasks hadn't yet coded explicitly; this task closes that gap with a real regression test rather than leaving it as an assumption.
- **Type consistency**: `Purchase`/`Sale`/`PurchaseItem`/`SaleItem` (Task 2) are used identically in every later service, store, composable, and test — no renamed fields.
- **Post-review fixes**: an independent review against this plan flagged two real gaps, both fixed inline above: (1) `PurchasesView`'s history table was missing the `produtos` (items) column the spec's §4 explicitly requires — added as a slot rendering `nome xquantidade` per item; (2) `usePurchaseForm`/`useSaleForm` validated `preco_unitario` as merely positive (`< 0.01` was allowed), looser than the backend's `min:0.01` rule — tightened to `< 0.01` in both composables so the client rejects what the backend would reject, instead of round-tripping a 422.
