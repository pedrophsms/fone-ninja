# Arquitetura do Frontend — Fone Ninja

## Visão Geral

SPA em Vue 3 (Composition API) + TypeScript strict para ERP de estoque. Pinia para estado global, Vue Router com guard de autenticação, Axios com interceptors, Tailwind CSS v4 com tema dual (claro/escuro), componentes shadcn-vue/reka-ui, e tabelas via TanStack Table.

---

## Stack

| Componente | Tecnologia |
|---|---|
| Framework | Vue 3.5 (Composition API + `<script setup>`) |
| TypeScript | 5.9 (strict mode) |
| Roteamento | Vue Router 4.6 |
| Estado | Pinia 2.3 |
| HTTP | Axios 1.19 |
| Tabelas | @tanstack/vue-table 8.21 |
| UI Primitives | reka-ui 2.10 (sucessor do Radix Vue) |
| Estilização | Tailwind CSS 4.3 + CVA + tailwind-merge + tw-animate-css |
| Ícones | lucide-vue-next |
| Testes | Vitest 4 + @vue/test-utils + jsdom + axios-mock-adapter |
| Build | Vite 7 |
| Deploy | Docker multi-stage (node:22-alpine → nginx:1.27-alpine) |

---

## Estrutura de Diretórios

```
frontend/
├── src/
│   ├── main.ts                  # createApp, Pinia, router, style.css
│   ├── App.vue                  # Layout raiz: sidebar + header + router-view
│   ├── style.css                # Tailwind v4 + CSS variables (light/dark)
│   ├── api/
│   │   ├── http.ts              # Axios instance + interceptors + ApiError
│   │   └── http.spec.ts
│   ├── composables/
│   │   ├── useApiError.ts       # Normalização de erros → fieldErrors ou snackbar
│   │   ├── useProductForm.ts    # Formulário de criação de produto
│   │   ├── usePurchaseForm.ts   # Formulário de compra + idempotência
│   │   ├── useSaleForm.ts       # Formulário de venda + preview ao vivo
│   │   └── useTheme.ts          # Alternância tema claro/escuro
│   ├── lib/
│   │   └── utils.ts             # cn(), formatMoney(), formatQuantity()
│   ├── router/
│   │   └── index.ts             # 6 rotas + beforeEach guard
│   ├── services/
│   │   ├── authService.ts       # login, register, logout
│   │   ├── productService.ts    # list, create
│   │   ├── purchaseService.ts   # list, create (com idempotencyKey)
│   │   └── saleService.ts       # list, create, preview, cancel
│   ├── stores/
│   │   ├── auth.ts              # token + user
│   │   ├── product.ts           # items[], fetchAll, create
│   │   ├── purchase.ts          # items[], fetchAll, create
│   │   ├── sale.ts              # items[], fetchAll, create, cancel, preview
│   │   └── snackbar.ts          # visible, message, color
│   ├── types/
│   │   ├── auth.ts              # LoginPayload, RegisterPayload, AuthUser, LoginResponse
│   │   ├── product.ts           # Product, CreateProductPayload
│   │   ├── purchase.ts          # Purchase, PurchaseItem, CreatePurchasePayload, PurchaseItemPayload
│   │   └── sale.ts              # Sale, SaleItem, SaleStatus, CreateSalePayload, SalePreview, etc.
│   ├── views/
│   │   ├── LoginView.vue        # Login / registro (toggle)
│   │   ├── DashboardView.vue    # KPIs (lucro, receita, ticket medio, etc.)
│   │   ├── ProductsView.vue     # Lista + criação de produtos
│   │   ├── PurchasesView.vue    # Lista + criação de compras
│   │   └── SalesView.vue        # Lista + criação + cancelamento de vendas
│   └── components/
│       ├── AppToast.vue         # Snackbar global (teleported)
│       ├── DataTable.vue        # Tabela genérica (TanStack)
│       └── ui/                  # Primitivos shadcn-vue (reka-ui)
│           ├── badge/Badge.vue
│           ├── button/Button.vue
│           ├── card/            # Card, CardHeader, CardContent, etc.
│           ├── dialog/
│           ├── field/Field.vue
│           ├── input/Input.vue
│           ├── select/Select.vue
│           ├── sheet/           # Sheet, SheetContent, etc.
│           ├── skeleton/Skeleton.vue
│           └── table/
└── docs/
    └── arquitetura-frontend.md  # Este arquivo
```

---

## Diagrama de Camadas

```mermaid
graph TB
    subgraph "View Layer"
        VIEW[Views .vue]
        COMP[Components]
        DT[DataTable]
        UI[UI Primitives]
    end

    subgraph "Composition Layer"
        FORM[Composables<br/>useProductForm<br/>usePurchaseForm<br/>useSaleForm]
        ERR[useApiError]
        THEME[useTheme]
    end

    subgraph "State Layer"
        AUTH[useAuthStore]
        PROD[useProductStore]
        PUR[usePurchaseStore]
        SALE[useSaleStore]
        SNACK[useSnackbarStore]
    end

    subgraph "Service Layer"
        ASVC[authService]
        PSVC[productService]
        PuSVC[purchaseService]
        SSVC[saleService]
    end

    subgraph "HTTP Layer"
        AXIOS[Axios Instance]
        REQ[Request Interceptor<br/>Bearer token + Idempotency-Key]
        RES[Response Error Interceptor<br/>ApiError normalization]
    end

    subgraph "External"
        API[Laravel API<br/>VITE_API_BASE_URL]
    end

    VIEW --> FORM
    VIEW --> COMP
    COMP --> DT
    COMP --> UI
    FORM --> ERR
    FORM --> PROD
    FORM --> PUR
    FORM --> SALE
    VIEW --> AUTH
    VIEW --> SNACK
    ERR --> SNACK

    AUTH --> ASVC
    PROD --> PSVC
    PUR --> PuSVC
    SALE --> SSVC

    ASVC --> AXIOS
    PSVC --> AXIOS
    PuSVC --> AXIOS
    SSVC --> AXIOS

    AXIOS --> API
```

**Regra de ouro**: `View → Composable → Store → Service → HTTP → API`. Nenhuma View chama Service diretamente. Nenhum Store chama Axios diretamente.

---

## Roteamento

```mermaid
graph LR
    ROOT["/"] -->|redirect| DASH["/dashboard"]
    LOGIN["/login"]
    DASH["/dashboard"]
    PROD["/produtos"]
    COMPR["/compras"]
    VEND["/vendas"]

    subgraph "Auth Guard (beforeEach)"
        GUARD{token presente?}
        GUARD -->|sim| ALLOW[Permite]
        GUARD -->|não| BLOCK{rota === login?}
        BLOCK -->|sim| ALLOW
        BLOCK -->|não| REDIR["Redireciona → /login"]
    end
```

| Path | Nome | View | Lazy Load |
|---|---|---|---|
| `/login` | `login` | `LoginView.vue` | Sim |
| `/dashboard` | `dashboard` | `DashboardView.vue` | Sim |
| `/produtos` | `produtos` | `ProductsView.vue` | Sim |
| `/compras` | `compras` | `PurchasesView.vue` | Sim |
| `/vendas` | `vendas` | `SalesView.vue` | Sim |
| `/` | — | redirect → `/dashboard` | — |

- **Sem rotas aninhadas**. Rotas planas com `createWebHistory()`.
- **Guarda único**: `beforeEach` global — qualquer rota exceto `login` exige token. Sem metadados por rota.

---

## Fluxo de Autenticação

```mermaid
sequenceDiagram
    participant U as Usuário
    participant V as LoginView
    participant AS as useAuthStore
    participant SVC as authService
    participant API as POST /login ou /registro
    participant LS as localStorage
    participant R as Vue Router

    U->>V: Preenche email + senha (+ nome se registro)
    V->>V: Validação client-side
    V->>AS: login(payload) ou register(payload)
    AS->>SVC: authService.login(payload)
    SVC->>API: POST /login { email, senha }
    API-->>SVC: { usuario: AuthUser, token: string }
    SVC-->>AS: response
    AS->>LS: setItem('fone-ninja-token', token)
    AS->>AS: state.token = token, state.user = user
    AS-->>V: sucesso
    V->>R: push('/dashboard')

    Note over AS: Ao inicializar a store, token é lido de localStorage.
    Note over R: beforeEach lê authStore.token. Se ausente e rota ≠ login, redireciona.
```

### Logout

```mermaid
sequenceDiagram
    participant U as Usuário
    participant AS as useAuthStore
    participant SVC as authService
    participant API as POST /logout
    participant LS as localStorage
    participant R as Vue Router

    U->>AS: logout()
    AS->>SVC: authService.logout()
    SVC->>API: POST /logout
    API-->>SVC: 200 OK
    AS->>AS: token = null, user = null
    AS->>LS: removeItem('fone-ninja-token')
    AS->>R: push('/login')
```

---

## Estado Global (Pinia Stores)

```mermaid
classDiagram
    class useAuthStore {
        -token: string | null
        -user: AuthUser | null
        +login(payload) void
        +register(payload) void
        +logout() void
    }

    class useProductStore {
        -items: Product[]
        -loading: boolean
        +fetchAll() void
        +create(payload) Product
    }

    class usePurchaseStore {
        -items: Purchase[]
        -loading: boolean
        +fetchAll() void
        +create(payload, idempotencyKey) Purchase
    }

    class useSaleStore {
        -items: Sale[]
        -loading: boolean
        +fetchAll() void
        +create(payload, idempotencyKey) Sale
        +cancel(id) void
        +preview(payload) SalePreview
    }

    class useSnackbarStore {
        -visible: boolean
        -message: string
        -color: 'success' | 'error'
        +showSuccess(message) void
        +showError(message) void
    }
```

| Store | Persistência | Gatilhos de Recarga |
|---|---|---|
| `auth` | `localStorage('fone-ninja-token')` | Nunca recarregado automaticamente |
| `product` | Apenas memória | `fetchAll()` ao montar views; após criar produto ou após compra/venda com sucesso |
| `purchase` | Apenas memória | `fetchAll()` ao montar PurchasesView |
| `sale` | Apenas memória | `fetchAll()` ao montar SalesView e DashboardView |
| `snackbar` | Apenas memória | Auto-hide após 4s via AppToast.vue |

---

## HTTP Layer (Axios)

```mermaid
sequenceDiagram
    participant SVC as Service
    participant AXIOS as Axios Instance
    participant REQ as Request Interceptor
    participant RES as Response Error Interceptor
    participant API as Backend API

    SVC->>AXIOS: GET /produtos
    AXIOS->>REQ: Intercepta request

    REQ->>REQ: Lê useAuthStore().token
    REQ->>REQ: Adiciona header: Authorization: Bearer <token>
    alt config.idempotencyKey presente
        REQ->>REQ: Adiciona header: Idempotency-Key: <uuid>
    end

    REQ->>API: Request com headers
    API-->>REQ: Response ou erro

    alt Sucesso
        REQ-->>SVC: Response { data }
        SVC->>SVC: Extrai response.data (unwrap)
    else Erro
        REQ->>RES: Intercepta erro
        RES->>RES: Converte em ApiError normalizado
        alt 422 + errors
            RES-->>SVC: ApiError { message, fieldErrors }
            SVC->>SVC: useApiError.handle() → retorna fieldErrors
        else 401
            RES-->>SVC: ApiError { message: 'Sessao expirada...' }
            SVC->>SVC: useApiError.handle() → snackbar.showError()
        else 429
            RES-->>SVC: ApiError { message: 'Muitas tentativas...' }
        else Outro
            RES-->>SVC: ApiError { message: 'Erro de comunicacao...' }
        end
    end
```

### Configuração

```typescript
// src/api/http.ts
const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL, // "http://localhost/api" ou "/api"
})
```

- **Dev**: `VITE_API_BASE_URL=http://localhost/api` (Vite proxy ou CORS)
- **Prod (Docker)**: `VITE_API_BASE_URL=/api` (nginx reverse-proxy `proxy_pass http://backend:80`)

---

## Pipeline de Erro

```mermaid
graph TD
    ERR[Erro HTTP] --> AXIOS[Axios Response Error Interceptor]
    AXIOS --> NORM[Normaliza → ApiError]
    NORM --> COMP{Composable catch}
    COMP -->|fieldErrors presente| FIELD[Merge nos erros do formulário]
    COMP -->|sem fieldErrors| SNACK[snackbar.showError(message)]
    FIELD --> FORM[Exibe erros inline nos campos]
    SNACK --> TOAST[AppToast: exibe snackbar 4s]
```

---

## Estrutura de Tipos

```mermaid
classDiagram
    class AuthUser {
        +id: number
        +nome: string
        +email: string
    }

    class Product {
        +id: number
        +nome: string
        +custo_medio: string
        +preco_venda: string
        +estoque: number
    }

    class Purchase {
        +id: number
        +fornecedor: string
        +total: string
        +produtos: PurchaseItem[]
    }

    class PurchaseItem {
        +id: number
        +nome: string
        +quantidade: number
        +preco_unitario: string
        +subtotal: string
    }

    class Sale {
        +id: number
        +cliente: string
        +total: string
        +lucro: string
        +status: SaleStatus
        +produtos: SaleItem[]
    }

    class SaleItem {
        +id: number
        +nome: string
        +quantidade: number
        +preco_unitario: string
        +subtotal: string
    }

    class SaleStatus {
        <<enum>>
        completed
        cancelled
    }

    class SalePreview {
        +total: string
        +lucro: string
        +itens: SalePreviewItem[]
    }

    Purchase --> PurchaseItem
    Sale --> SaleItem
    Sale --> SaleStatus
```

---

## Componentes Compartilhados

### DataTable (TanStack Table)

```mermaid
graph TB
    subgraph "DataTable.vue"
        PROPS["Props:<br/>columns[]<br/>rows[]<br/>loading<br/>initialSort"]
        TANSTACK["@tanstack/vue-table<br/>getSortedRowModel<br/>(apenas client-side)"]
        SLOTS["Slots:<br/>#cell-{key}<br/>#empty"]
    end

    subgraph "Estados"
        LOADING["Loading: 4 skeleton rows"]
        EMPTY["Empty: slot #empty<br/>(ícone + mensagem)"]
        DATA["Dados: TransitionGroup<br/>com animação de entrada"]
    end

    PROPS --> TANSTACK
    TANSTACK --> LOADING
    TANSTACK --> EMPTY
    TANSTACK --> DATA
```

- **Ordenação**: 3 ciclos (none → asc → desc → none)
- **Colunas**: `{ key, title, align, sortable, className }`
- **Layout responsivo**: via Tailwind breakpoints nos slots de célula

### AppToast (Snackbar Global)

```mermaid
graph LR
    STORE[useSnackbarStore] -->|watch visible| TOAST[AppToast.vue]
    TOAST -->|success| GREEN["Brass border<br/>CheckCircle2 ícone<br/>Mensagem"]
    TOAST -->|error| RED["Destructive border<br/>AlertCircle ícone<br/>Mensagem"]
    TOAST -->|4s timeout| HIDE["visible = false"]
```

- Teleported para `<body>` via `<Teleport to="body">`
- Posição: `fixed bottom-4 right-4`
- Animações: `<Transition>` com Tailwind `animate-in/out`

---

## Padrão de Formulários

```mermaid
graph TD
    subgraph "Sheet (direita)"
        TRIGGER["SheetTrigger: botão 'Nova Compra'"]
        CONTENT["SheetContent:<br/>slots: default (form), header, footer"]
    end

    subgraph "Composable (usePurchaseForm)"
        STATE["Estado reativo:<br/>fornecedor, produtos[]"]
        VALID["validate():<br/>regras client-side"]
        COMP["subtotalPreview: computed"]
        SUBMIT["submit():<br/>1. guard: loading reentry<br/>2. crypto.randomUUID()<br/>3. store.create()<br/>4. snackbar + reset + refetch"]
    end

    subgraph "Ciclo de Vida"
        OPEN["Sheet aberto"] --> FILL["Formulário preenchido"]
        FILL --> SUB
        SUB -->|sucesso| CLOSE["Sheet fecha<br/>(watch submitted)"]
        SUB -->|erro| ERRORS["useApiError.handle()<br/>merge fieldErrors"]
        ERRORS --> FILL
    end

    TRIGGER --> OPEN
    CONTENT --> STATE
    STATE --> VALID
    STATE --> COMP
    VALID --> SUBMIT
```

**Padrão consistente** nos 3 formulários de criação (Produto, Compra, Venda):

1. Sheet abre à direita
2. Formulário com validação client-side
3. Submit gera UUID idempotente + chama store action
4. Sucesso → snackbar verde + fecha sheet + refetch
5. Erro → fieldErrors nos campos ou snackbar vermelha

### Preview de Venda (Ao Vivo)

```mermaid
sequenceDiagram
    participant U as Usuário
    participant FORM as useSaleForm
    participant STORE as useSaleStore
    participant API as POST /vendas/preview

    U->>FORM: Altera quantidade, preço ou produto
    FORM->>FORM: watch detecta mudança
    FORM->>FORM: Debounce (evita chamadas excessivas)
    FORM->>STORE: preview({ produtos: [...] })
    STORE->>API: POST /vendas/preview
    API-->>STORE: { total, lucro, itens[] }
    STORE-->>FORM: SalePreview
    FORM->>FORM: Atualiza preview ref
    FORM-->>U: Exibe recibo preview (total + lucro + itens)
```

---

## Dashboard — KPIs

```mermaid
graph TB
    DASH[DashboardView.vue]

    subgraph "Stores consultadas"
        PROD[useProductStore.items]
        SALE[useSaleStore.items]
        PUR[usePurchaseStore.items]
    end

    subgraph "KPIs (computados client-side)"
        LUCRO["Lucro acumulado<br/>Σ sale.profit (status=completed)"]
        RECEITA["Receita total<br/>Σ sale.total (status=completed)"]
        TICKET["Ticket médio<br/>Receita / nº vendas completadas"]
        VALOR["Valor em estoque<br/>Σ (product.custo_medio × product.estoque)"]
    end

    subgraph "Listas"
        ULTIMAS["Últimas 5 vendas<br/>(ordenadas por data)"]
        BAIXO["Estoque baixo<br/>(products com estoque ≤ 10)"]
    end

    subgraph "Estados"
        LOADING["Loading: Skeleton cards"]
        EMPTY["Empty state: CTAs para criar produtos/compras/vendas"]
        DATA["Dados: Cards + listas"]
    end

    DASH --> PROD
    DASH --> SALE
    DASH --> LUCRO
    DASH --> RECEITA
    DASH --> TICKET
    DASH --> VALOR
    DASH --> ULTIMAS
    DASH --> BAIXO
    LUCRO --> LOADING
    LUCRO --> EMPTY
    LUCRO --> DATA
```

---

## Sistema de Temas (Claro/Escuro)

```mermaid
graph TD
    INIT["App.vue onMounted"] --> THEME["useTheme().initTheme()"]

    THEME --> CHECK{"localStorage<br/>('fone-ninja-theme')"}
    CHECK -->|"presente: 'dark'|'light'"| APPLY[apply(dark)]
    CHECK -->|ausente| MEDIA["matchMedia<br/>('prefers-color-scheme: dark')"]
    MEDIA --> APPLY

    TOGGLE["Usuário clica toggle"] --> FLIP["dark = !dark"]
    FLIP --> SAVE["localStorage.setItem"]
    SAVE --> APPLY

    APPLY --> DOM["document.documentElement<br/>.classList.add/remove('dark')"]
    DOM --> CSS["CSS variables alternam<br/>:root ↔ .dark"]
```

- Estado singleton em module-level (compartilhado entre instâncias do composable)
- Respeita `prefers-reduced-motion` e `prefers-reduced-transparency`

---

## Tema Visual (Design Tokens)

```mermaid
graph LR
    subgraph "Cores"
        PRIMARY["primary: ink-green #23483c"]
        BRASS["brass: gold #a8762b"]
        BG["background: white ↔ zinc-950"]
        CARD["card: white ↔ zinc-900"]
        MUTED["muted: zinc-100 ↔ zinc-800"]
    end

    subgraph "Tipografia"
        DISPLAY["Newsreader (serif)<br/>títulos e KPIs"]
        SANS["Schibsted Grotesk (sans)<br/>corpo e labels"]
        MONO["Spline Sans Mono (mono)<br/>valores monetários (tabular-nums)"]
    end

    subgraph "Semântica"
        LUCRO["Lucro / positivo → brass"]
        ERRO["Erro / cancelado → destructive (red)"]
        BAIXO["Estoque baixo → brass highlight"]
    end
```

---

## Docker / Deploy

```mermaid
graph LR
    subgraph "Build Stage (node:22-alpine)"
        SRC[Source code] --> VITE["Vite build<br/>VITE_API_BASE_URL=/api"]
        VITE --> DIST[dist/]
    end

    subgraph "Serve Stage (nginx:1.27-alpine)"
        DIST --> NGINX[nginx]
        NGINX --> SPA["SPA: try_files $uri /index.html"]
        NGINX --> PROXY["/api/ → proxy_pass http://backend:80"]
    end

    subgraph "Runtime"
        BROWSER[Browser] --> NGINX
        PROXY --> BACKEND[Laravel Backend]
    end
```

---

## Testes

```mermaid
graph TD
    subgraph "Ferramentas"
        VITEST[Vitest 4]
        VTU["@vue/test-utils"]
        JSDOM[jsdom]
        AXIOS_MOCK[axios-mock-adapter]
    end

    subgraph "Setup (test/setup.ts)"
        RESIZE["ResizeObserver stub"]
        MATCH["matchMedia polyfill"]
        POINTER["PointerEvent polyfill<br/>(exigido pelo reka-ui)"]
    end

    subgraph "Testes (co-localizados)"
        HTTP["api/http.spec.ts"]
        AUTH_S["stores/auth.spec.ts"]
        FORM_S["composables/*.spec.ts"]
        VIEW_S["views/*.spec.ts"]
    end

    VITEST --> VTU
    VITEST --> JSDOM
    VITEST --> AXIOS_MOCK
    RESIZE --> VITEST
    MATCH --> VITEST
    POINTER --> VITEST

    VTU --> HTTP
    VTU --> AUTH_S
    VTU --> FORM_S
    VTU --> VIEW_S
```

- Testes co-localizados com código fonte (`*.spec.ts` ao lado do `.ts`)
- Mock de HTTP via `axios-mock-adapter` na fronteira do serviço
- Componentes testados com `@vue/test-utils` + jsdom

---

## Convenções e Padrões

| Padrão | Descrição |
|---|---|
| **Layering estrito** | View → Composable → Store → Service → HTTP. Sem atalhos. |
| **Composition API** | `<script setup lang="ts">` em todos os componentes |
| **TypeScript strict** | Sem `any` implícito. Tipos em `src/types/` |
| **Idempotência** | `crypto.randomUUID()` por tentativa de submit. Guard `if (loading.value) return` |
| **Money formatting** | `formatMoney()` em `lib/utils.ts`: centavos → `"R$ 1.234,56"` |
| **Sheets para criação** | Formulários de criação em Sheet lateral direita, não em rotas separadas |
| **Client-side sort** | Apenas ordenação client-side via TanStack Table |
| **Snackbar único** | Um AppToast global, não toasts por componente |
| **Lazy loading** | Todas as views com `() => import(...)`, code-splitting automático |
| **Sem meta frameworks** | Sem Nuxt, sem SSR — SPA pura servida por nginx |
