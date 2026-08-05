# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Vue 3 + TypeScript SPA for the Inventory ERP challenge ("Fone Ninja"), consuming the `fone-ninja-backend` Laravel API. Part of a consolidated repo (`frontend/` + `backend/`, orchestrated by a root `docker-compose.yml`).

## Commands

```bash
npm run dev       # start Vite dev server
npm run build     # type-check (vue-tsc -b) then production build
npm run preview   # preview the production build
npm run test      # run all tests (vitest run)
```

Run a single test file: `npx vitest run src/composables/useProductForm.spec.ts`
Run tests matching a name: `npx vitest run -t "some test name"`

There is no separate lint script; type errors surface via `npm run build` (vue-tsc) or your editor's TS server.

### Local setup

```bash
npm install
cp .env.example .env
npm run dev
```

`VITE_API_BASE_URL` in `.env` must point at the running backend (defaults to `http://localhost/api`, matching Laravel Sail's default port).

### Docker (frontend + backend + mysql)

Run from the repo root, not this folder — see the root README. This folder's `Dockerfile` is two-stage: a Node build (with `VITE_API_BASE_URL=/api`) then nginx serving the static SPA and reverse-proxying `/api/` to the `backend` service (see `nginx.conf`). Same-origin in that setup, so no CORS handling exists in the frontend code.

## Architecture

Layering is strict and consistent across all three resources (products/`produtos`, purchases/`compras`, sales/`vendas`). When adding a new resource, follow this exact chain — every existing resource does:

```
View (.vue) -> composable (useXForm) -> Pinia store (useXStore) -> service (xService) -> http (axios instance)
```

- **`src/api/http.ts`** — single shared axios instance. Request interceptor attaches the bearer token from the auth store and an optional `Idempotency-Key` header (via a custom `idempotencyKey` config field, declared through axios module augmentation). Response interceptor normalizes *all* errors into an `ApiError` (`{ message, fieldErrors? }`) before rejection — services and stores never see raw axios/HTTP errors.
- **`src/services/*Service.ts`** — thin wrappers around `http`, one per resource. Unwrap the `{ data: ... }` envelope the Laravel API returns. No business logic here.
- **`src/stores/*.ts`** (Pinia, options API style) — hold resource state (`items`, `loading`) and call services. `auth.ts` persists the token to `localStorage` (`fone-ninja-token`) and is the source of truth for `router`'s auth guard.
- **`src/composables/use*Form.ts`** — one per create-flow (product, purchase, sale). Own the form's reactive state, client-side `validate()` (mirrors backend validation, e.g. min lengths, positive prices, no duplicate product ids in one purchase), and `submit()`, which calls the store, shows a snackbar (success/error) via `useSnackbarStore`, and resets the form. On failure, `useApiError().handle(error)` returns `fieldErrors` (422 validation) to merge into the form's `errors`, or shows a global snackbar for anything else.
- **`src/composables/useApiError.ts`** — the single place that turns an `ApiError` into either field errors (for the caller to render inline) or a snackbar (for everything else: 401, 429, 5xx, network errors).
- **Idempotency**: mutating flows that must not double-submit (e.g. purchase creation) generate a `crypto.randomUUID()` and pass it as `idempotencyKey` on the request — see `usePurchaseForm.ts`. Follow this pattern for any new "create" flow where duplicate submission is a real risk.
- **Router** (`src/router/index.ts`): a single global `beforeEach` guard redirects to `/login` whenever there's no token in the auth store, except for the login route itself. No per-route meta flags — auth is opt-out only via the `login` route name. Login lands on `/dashboard`.
- **Styling**: Tailwind CSS v4 (`@tailwindcss/vite`) + shadcn-vue-style primitives in `src/components/ui/*` (button, input, label, card, table, dialog, sheet, badge, separator, skeleton, select). All design tokens live as CSS variables in `src/style.css` (see ADR `docs/adr/0001-shadcn-vue-em-vez-de-vuetify.md`): cool paper background, ink-green `primary`, `brass` accent for lucro/positive figures, hairline `border`. Editorial identity: `font-display` (Newsreader, used sparingly), `font-sans` (Schibsted Grotesk), `font-mono` (Spline Sans Mono, for money/quantities, `tabular-nums`). Dark mode via `.dark` class on `<html>` toggled by `src/composables/useTheme.ts` (persisted to localStorage).
- **`src/components/DataTable.vue`** — reusable table wrapper over `@tanstack/vue-table` (v8), client-side sorting, per-column slots (`#cell-<key>`, `#empty`). Use it for every list. Money renders with `formatMoney` (`src/lib/utils.ts`) in mono, right-aligned.
- **Forms**: create-flows open in a Sheet (product, sale, purchase). Views render shadcn `Field` + `Input`/`Select`; `Select` emits string values, so numeric ids are converted via `@update:model-value="item.id = Number($event ?? 0)"`. Number inputs follow the same conversion pattern. Forms close on success by watching the composable's `submitted` ref.
- **Toast**: `src/components/AppToast.vue` renders the snackbar store as a bottom-right toast with auto-hide.
- **Dashboard**: `src/views/DashboardView.vue` computes KPIs client-side from the stores (lucro acumulado, receita, ticket médio, valor em estoque, estoque baixo ≤ 10 unid.) — no backend stats endpoints.
- **Auth**: `authService` also has `register()` (POST `/api/registro`, payload `nome`/`email`/`senha`/`senha_confirmation`), exposed via `useAuthStore.register`. Registration UI is a toggle on the login screen, no separate route.
- Path alias `@/` maps to `src/` (configured in both `tsconfig.app.json` and `vite.config.ts`).

## Testing

- Vitest + `@vue/test-utils`, `jsdom` environment, globals enabled (`describe`/`it`/`expect` available without imports).
- `axios-mock-adapter` mocks the shared `http` instance in service/store tests — mock at the HTTP boundary, not by stubbing services/stores.
- `src/test/setup.ts` stubs `ResizeObserver` and adds `matchMedia`/`PointerEvent` polyfills (Radix/reka components need them under jsdom).
- Tests live next to the code they cover (`*.spec.ts` beside the source file), not in a separate `__tests__` tree.

## Domain notes

- API and domain field names are Portuguese (`produtos`, `preco_venda`, `estoque_inicial`, `fornecedor`, `quantidade`, `preco_unitario`) and user-facing strings (validation messages, snackbar text) are Portuguese — match this convention for any new resource or message, don't translate to English.
- A purchase (`compra`) has multiple line items, each referencing a product by id; duplicate product ids within one purchase are rejected client-side before hitting the API.
