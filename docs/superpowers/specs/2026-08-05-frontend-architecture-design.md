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
- **Composables** (`src/composables/`): `useProductForm`, `usePurchaseForm`, `useSaleForm` — client-side validation (required fields, min values, `distinct` on repeated product ids in an order — mirrors the backend's `distinct` FormRequest rule so the user catches it before submit, not after a 422), formatting, and orchestrating the store call. `useApiError` — parses Axios/Laravel error responses (422 validation `errors`, plain `message`, 401) into a single shape consumed by the snackbar.
- **Views** (`src/views/`): `LoginView`, `ProductsView`, `PurchasesView`, `SalesView`. Thin — Vuetify layout (`v-form`, `v-data-table`) bound to a composable's state/handlers.

No calculation logic (average cost, profit) is duplicated in the frontend — the backend is the sole source of truth for those; composables only validate shape/presence of input before sending it.

## 3. Cross-Cutting Concerns

### 3.1 HTTP client
Single Axios instance (`src/api/http.ts`), `baseURL` from `VITE_API_BASE_URL` env var.

- **Request interceptor**: attaches `Authorization: Bearer <token>` from `useAuthStore` when present; attaches `Idempotency-Key: crypto.randomUUID()` only on `POST /compras` and `POST /vendas` (the two endpoints the backend spec requires it for, §5.2 of the backend spec) — generated fresh per request attempt, transparent to composables/stores.
- **Response interceptor**: on error, normalizes the Axios error into a common shape (`{ message, fieldErrors? }`) so `useApiError` doesn't need to know Axios internals.

### 3.2 Error handling / UX
`useApiError` composable parses the normalized error and pushes it to `useSnackbarStore`. A single `v-snackbar` lives in `App.vue`, driven by that store — every view gets consistent success/error toasts (e.g. `"Estoque insuficiente para o produto X"`) without re-implementing alert markup per screen.

### 3.3 Routing & auth guard
Vue Router, routes: `/login`, `/produtos`, `/compras`, `/vendas`. `router.beforeEach` checks `useAuthStore().token`; unauthenticated access to any route but `/login` redirects to `/login`. Token persisted in `localStorage`, hydrated into `useAuthStore` on app boot.

## 4. Screens

- **Login** (`LoginView`): email/senha form (`useAuthForm` folded into `useAuthStore` directly, no dedicated composable needed — it's a single field pair, no repeatable sub-items like purchases/sales have). On success, stores token, redirects to `/produtos`.
- **Produtos** (`ProductsView`): form (nome, preço de venda) to create a product + `v-data-table` listing `id, nome, custo_medio, preco_venda, estoque`, refreshed after create.
- **Compras** (`PurchasesView`): form with a repeatable product/quantidade/preço_unitário line-item list (add/remove rows), submits to `POST /compras`; `v-data-table` below listing purchase history (fornecedor, total, items) — the README's "diferencial".
- **Vendas** (`SalesView`): same repeatable line-item form for `POST /vendas`, shows total + lucro from the response; history table with a "Cancelar" action per row calling `POST /vendas/{id}/cancelar`, disabled for already-cancelled sales.

## 5. Testing Strategy (Vitest)

Unit tests target composables — `useProductForm`, `usePurchaseForm`, `useSaleForm`, `useApiError` — with the underlying store/service mocked, so no real network call is needed. Covers: required-field validation, `distinct` product id rejection in line items, min-quantity/min-price checks, and error-shape parsing (422 field errors vs. plain message vs. 401). Stores and services are thin enough not to need dedicated tests beyond what the composable tests already exercise through mocks.

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
  vite.config.ts
  vitest.config.ts
  .env.example
```

## 7. Explicitly Out of Scope for This Spec

- Dockerfile for this frontend and `docker-compose.yml` wiring it to the backend — separate sub-project spec (same split the backend spec used).
- Replicating average-cost/profit calculation logic client-side — backend is sole source of truth.
- Registration screen (`/api/registro`) — backend exposes it, but the README only requires login as an entry point for the ERP screens; adding a registration UI is not required by the challenge and is left out to stay focused (can be added trivially later using the same pattern as login if desired).
