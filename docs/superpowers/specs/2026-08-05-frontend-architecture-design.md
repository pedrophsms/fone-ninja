# Frontend Architecture Design — Inventory ERP SPA

Date: 2026-08-05
Status: Approved
Scope: Frontend only (Vue SPA). Backend (Laravel API) has its own spec at `fone-ninja-backend/docs/superpowers/specs/2026-08-05-backend-architecture-design.md`. Docker packaging (Dockerfile for this frontend, docker-compose.yml) is a separate sub-project spec, not this one.

## 1. Context

This is the frontend half of a technical challenge for a **senior full-stack role at a fintech**: inventory ERP screens for registering products, purchases, and sales, consuming the Laravel API described by the backend spec. Repo is a standalone sibling of `fone-ninja-backend`, decoupled per the challenge's explicit requirement (`frontend/` and `backend/` as separate Dockerfiles/services, joined only by `docker-compose.yml` later).

**Language boundary** (mirrors the backend spec, §1): the API contract is Portuguese — routes (`/api/produtos`, `/api/compras`, `/api/vendas`, `/api/login`, `/api/registro`), JSON fields (`nome`, `preco_venda`, `custo_medio`, `estoque`, `quantidade`, `preco_unitario`, `fornecedor`, `cliente`, `lucro`), and error messages (`"Estoque insuficiente para o produto <nome>"`). Types in `src/types/` and the `services/` layer speak this Portuguese contract verbatim, since they're the direct mirror of the API. Everything else — component/composable/store names, internal variables, comments — stays in English.

## 2. Layered Architecture

```
View (page component)
  -> Composable (form validation, orchestration)
      -> Store (Pinia: state, actions calling services)
          -> Service (Axios, one per domain, typed to the PT contract)
```

- **Services** (`src/services/`): one per domain — `productService`, `purchaseService`, `saleService`, `authService`. Pure API calls (Axios), typed request/response shapes matching the backend contract exactly. No Vue reactivity, no business logic — same role as the backend's Repository layer, one level up the stack.
- **Stores** (Pinia, one per domain): `useProductStore`, `usePurchaseStore`, `useSaleStore`, `useAuthStore`, plus `useSnackbarStore` for cross-cutting UI feedback. Hold list state, loading/error flags, and call services. Views never call services directly.
- **Composables** (`src/composables/`): `useProductForm`, `usePurchaseForm`, `useSaleForm` — client-side validation (required fields, min values, `distinct` on repeated product ids in an order — mirrors the backend's `distinct` FormRequest rule so the user catches it before submit, not after a 422), formatting, and orchestrating the store call. A single composable owns both validation and submit orchestration deliberately (YAGNI) — these forms are small enough that splitting validation into its own file would be an empty wrapper, unlike the backend's Action/Service split which exists because those layers have real independent logic (DB access, arithmetic). `useApiError` — parses Axios/Laravel error responses (422 validation `errors`, plain `message`, 401) into a single shape `{ message, fieldErrors? }` consumed by both the snackbar (message) and the owning composable (fieldErrors, for inline field display — see §3.2).
- **Views** (`src/views/`): `LoginView`, `ProductsView`, `PurchasesView`, `SalesView`. Thin — Vuetify layout (`v-form`, `v-data-table`) bound to a composable's state/handlers.

No calculation logic (average cost, profit) is duplicated in the frontend — the backend is the sole source of truth for those; composables only validate shape/presence of input before sending it. `usePurchaseForm`/`useSaleForm` do compute and display a client-side **subtotal preview** (sum of `quantidade * preco_unitario` across line items, plain arithmetic with no rounding ambiguity) so the user sees a running total while building the item list — this is a UI convenience, not a source of truth; it's discarded and replaced by the backend's response (`total`, `lucro`) once submitted. Profit is never estimated client-side, since it depends on `custo_medio`, which only the backend knows authoritatively.

## 3. Cross-Cutting Concerns

### 3.1 HTTP client
Single Axios instance (`src/api/http.ts`), `baseURL` from `VITE_API_BASE_URL` env var.

- **Request interceptor**: attaches `Authorization: Bearer <token>` from `useAuthStore` when present; attaches an `Idempotency-Key` header **only if the request config carries one** (see below) — the interceptor never generates a key itself.
- **Idempotency key generation**: the key must represent one user *submission attempt*, not one HTTP request attempt, or the backend's replay protection (§5.2 of the backend spec) is defeated — a network retry needs to reuse the same key to get a replayed response instead of creating a duplicate purchase/sale. So the key is generated once, in `usePurchaseForm`/`useSaleForm`, at the moment the user clicks submit, and passed explicitly down to `purchaseService.create(payload, idempotencyKey)` / `saleService.create(...)`, which sets it on that one Axios call's config. A fresh key is only generated when the user submits a *new* attempt (e.g. changed the payload and resubmitted after a prior error) — never on an automatic retry of the same attempt. The composable also sets a `loading` flag for the duration of the call, and the submit button binds `:disabled="loading"`, so an accidental double-click can't fire two in-flight requests in the first place; the idempotency key is the second line of defense (e.g. a request that times out client-side but succeeded server-side).
- **Response interceptor**: on error, normalizes the Axios error into a common shape (`{ message, fieldErrors? }`) so `useApiError` doesn't need to know Axios internals.

### 3.2 Error handling / UX
`useApiError` composable parses the normalized error into `{ message, fieldErrors? }`. `message` is pushed to `useSnackbarStore` — a single `v-snackbar` lives in `App.vue`, driven by that store, and covers both success toasts (e.g. `"Produto cadastrado com sucesso"`, `"Compra registrada com sucesso"`, `"Venda registrada com sucesso"`, triggered by the composable after a successful store call) and non-field errors (401, business-rule messages like `"Estoque insuficiente para o produto X"`). `fieldErrors` (present on 422 validation responses) is returned to the calling composable instead, which maps each entry to the matching `v-text-field :error-messages` — including per-row fields in the repeatable purchase/sale item lists (e.g. `produtos.1.quantidade`) — so the user sees exactly which line is wrong instead of a generic toast.

### 3.3 Routing & auth guard
Vue Router, routes: `/login`, `/produtos`, `/compras`, `/vendas`. `router.beforeEach` checks `useAuthStore().token`; unauthenticated access to any route but `/login` redirects to `/login`. Token persisted in `localStorage`, hydrated into `useAuthStore` on app boot.

## 4. Screens

- **Login** (`LoginView`): email/senha form (`useAuthForm` folded into `useAuthStore` directly, no dedicated composable needed — it's a single field pair, no repeatable sub-items like purchases/sales have). On success, stores token, redirects to `/produtos`. The backend's `DatabaseSeeder` (§9 of the backend spec) already creates a seeded user for evaluation — this frontend's `.env.example`/README documents those seeded credentials directly, so the evaluator can log in immediately after `docker compose up` without going through a registration flow that's explicitly out of scope (§7).
- **Produtos** (`ProductsView`): form (nome, preço de venda) to create a product + `v-data-table` listing `id, nome, custo_medio, preco_venda, estoque`, refreshed after create.
- **Compras** (`PurchasesView`): form with a repeatable product/quantidade/preço_unitário line-item list (add/remove rows), submits to `POST /compras`; `v-data-table` below listing purchase history (fornecedor, total, items) — the README's "diferencial". A successful purchase also invalidates/refetches `useProductStore`'s list (not just the local purchase history), since `custo_medio`/`estoque` on the affected products just changed — the Produtos screen must reflect that the next time it's viewed, not show stale data.
- **Vendas** (`SalesView`): same repeatable line-item form for `POST /vendas`, shows total + lucro from the response; history table with a "Cancelar" action per row calling `POST /vendas/{id}/cancelar`, disabled for already-cancelled sales. Same as purchases, a successful sale or cancellation refetches `useProductStore` so `estoque` stays current on the Produtos screen.

## 5. Testing Strategy (Vitest)

Unit tests target composables — `useProductForm`, `usePurchaseForm`, `useSaleForm`, `useApiError` — with the underlying store/service mocked, so no real network call is needed. Covers: required-field validation, `distinct` product id rejection in line items, min-quantity/min-price checks, error-shape parsing (422 field errors vs. plain message vs. 401), and the idempotency-key contract — same key reused across a retry of the same submission, a fresh key on a genuinely new submission. Stores and services are thin enough not to need dedicated unit tests beyond what the composable tests already exercise through mocks.

In addition, two component tests (Vue Test Utils + Vitest) cover the integration the composable-level mocks can't reach: `PurchasesView.spec.ts` mounts the real view with a mocked Axios (`msw` or an Axios mock adapter) to verify the submit button disables while the request is in flight and re-enables after, and that a double-click during that window results in exactly one network call. `SalesView.spec.ts` verifies a cancelled sale's "Cancelar" button is disabled from the fetched history data. These close the gap between "composable logic is correct in isolation" and "the composable, store, and Axios interceptor actually wire together correctly."

## 6. Folder Structure

```
fone-ninja-frontend/
  src/
    api/
      http.ts
    services/
      productService.ts
      purchaseService.ts
      saleService.ts
      authService.ts
    stores/
      product.ts
      purchase.ts
      sale.ts
      auth.ts
      snackbar.ts
    composables/
      useProductForm.ts
      usePurchaseForm.ts
      useSaleForm.ts
      useApiError.ts
    views/
      LoginView.vue
      ProductsView.vue
      PurchasesView.vue
      SalesView.vue
    router/
      index.ts
    types/
      product.ts
      purchase.ts
      sale.ts
      auth.ts
    App.vue
    main.ts
  tests/
    composables/
      useProductForm.spec.ts
      usePurchaseForm.spec.ts
      useSaleForm.spec.ts
      useApiError.spec.ts
    views/
      PurchasesView.spec.ts
      SalesView.spec.ts
  vite.config.ts
  vitest.config.ts
  .env.example
```

## 7. Explicitly Out of Scope for This Spec

- Dockerfile for this frontend and `docker-compose.yml` wiring it to the backend — separate sub-project spec (same split the backend spec used).
- Replicating average-cost/profit calculation logic client-side — backend is sole source of truth.
- Registration screen (`/api/registro`) — backend exposes it, but the README only requires login as an entry point for the ERP screens; adding a registration UI is not required by the challenge and is left out to stay focused (can be added trivially later using the same pattern as login if desired).
