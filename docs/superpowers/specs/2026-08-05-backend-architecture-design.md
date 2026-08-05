# Backend Architecture Design — Inventory ERP API

Date: 2026-08-05
Status: Approved (pending final written-spec review)
Scope: Backend only (Laravel API). Frontend (Vue) and Docker packaging are separate sub-projects with their own specs.

## 1. Context

Technical challenge: build the API for an inventory ERP. Core capabilities: register products, register purchases (stock in + weighted average cost update), register sales (stock out + revenue/profit calculation, with insufficient-stock validation), and optionally cancel a sale (stock reversal) and list purchase/sale history.

Stack: Laravel 13 + PHP 8.3, Laravel Sail for local dev, MySQL, Pest for testing. All backend identifiers and comments in English.

This challenge is for a **senior backend role at a fintech**. Beyond satisfying the README's functional requirements, the design deliberately demonstrates practices expected when handling real money/inventory at volume: precise monetary arithmetic via a `Money` value object, idempotent write endpoints, an append-only financial record policy, database-level integrity constraints, and request-correlated structured logging (§5).

## 2. Layered Architecture

```
Controller (thin)
  -> FormRequest (validation)
  -> Action (one use case, orchestrates)
      -> Repository interface (persistence, DIP)
      -> Service (pure calculation)
  -> Resource (output formatting)
```

- **Actions**: one per use case with real business logic — `CreateProductAction`, `RegisterPurchaseAction`, `RegisterSaleAction`, `CancelSaleAction`, `RegisterUserAction`, `LoginAction`. Simple listing endpoints (`GET /products`, etc.) go directly from Controller to Repository — no Action, since it would be an empty wrapper (YAGNI).
- **Services**: pure calculation, no I/O, easily unit-tested without a database.
  - `AverageCostService`: weighted average cost calculation.
  - `ProfitCalculatorService`: profit per sale item.
- **Repositories**: interface + Eloquent implementation per aggregate (`ProductRepositoryInterface` / `EloquentProductRepository`, same pattern for Purchase and Sale), bound in `RepositoryServiceProvider`. This is the DIP: Actions and Services depend on contracts, not on Eloquent directly.
- **DTOs**: plain `readonly` PHP 8.2+ classes (no external package) carrying validated data from the FormRequest down to the Action, instead of passing raw arrays or the `Request` object between layers.

### SOLID mapping
- **SRP**: one Action = one use case; one Service = one calculation concern; one Repository = one persistence concern.
- **OCP**: new business rules are added as new classes (e.g. a new Listener), not by editing existing Actions.
- **LSP**: repository implementations are swappable behind their interface without breaking consumers.
- **ISP**: one repository interface per aggregate (Product, Purchase, Sale) instead of one large interface.
- **DIP**: Actions/Services depend on repository interfaces, bound to concrete Eloquent implementations in a Service Provider.

## 3. Data Model

```
products
  id, name, sale_price_cents (integer), average_cost_cents (integer default 0),
  current_stock (unsigned int default 0), timestamps
  CHECK (sale_price_cents > 0), CHECK (current_stock >= 0)

purchases
  id, supplier, total_cents (integer), timestamps

purchase_items
  id, purchase_id, product_id, quantity, unit_price_cents (integer), subtotal_cents (integer)
  CHECK (quantity > 0), CHECK (unit_price_cents > 0)

sales
  id, customer, total_cents (integer), profit_cents (integer),
  status (enum: completed/cancelled), timestamps

sale_items
  id, sale_id, product_id, quantity, unit_price_cents (integer),
  average_cost_snapshot_cents (integer), subtotal_cents, item_profit_cents
  CHECK (quantity > 0), CHECK (unit_price_cents > 0)

stock_movements
  id, product_id, user_id, type (enum: purchase_in/sale_out/sale_cancelled_return),
  quantity, reference_type, reference_id, created_at

idempotency_keys
  id, key (string, unique), route, request_hash, response_status,
  response_body (json), user_id, created_at

users / personal_access_tokens (Sanctum default, via `php artisan install:api`)
```

`average_cost_cents` and `sale_price_cents` live on `products` because they represent the product's current, mutable state. `purchase_items` / `sale_items` store their own `unit_price_cents` (and `average_cost_snapshot_cents` for sale items) because those are immutable historical facts at the time of the transaction — not duplication, two different concepts (current state vs. point-in-time snapshot).

`average_cost_snapshot_cents` on `sale_items` preserves the cost basis used to compute profit at sale time, so profit stays correct even if `average_cost_cents` changes later (new purchase), and so cancellation can correctly reverse stock without touching `average_cost_cents`.

Model class names: `Product`, `Purchase`, `PurchaseItem`, `Sale`, `SaleItem`, `StockMovement`, `User`.

CHECK constraints are enforced at the database level in the migrations (defense in depth — an application bug should never be able to persist invalid financial state, even if it slips past FormRequest validation). `unsigned` integer columns natively rule out negative stock/quantity; explicit `CHECK` clauses cover strict positivity (`> 0`) where `unsigned` alone isn't enough. MySQL 8.0.16+ (the Sail default image) enforces `CHECK` constraints natively.

Money handling is detailed in §5.

## 4. Business Flows

All three write flows run inside `DB::transaction(fn () => ..., attempts: 3)` — Laravel automatically retries on deadlock up to the given attempts and rolls back on any exception. `POST /purchases` and `POST /sales` additionally go through the idempotency middleware described in §5.2 before reaching the Action.

### Register purchase — `POST /api/purchases`
1. Required `Idempotency-Key` header checked (§5.2); if this key was already processed, the stored response is replayed and the Action never runs.
2. `StorePurchaseRequest` validates: `supplier` required; `products` array, each item's `id` must `exist:products,id`, `quantity` integer min 1, `unit_price` numeric min 0.01.
3. Controller calls `RegisterPurchaseAction::execute(RegisterPurchaseData)`.
4. For each item: `lockForUpdate()` the product row; `AverageCostService` computes the new average cost as `((current_stock * average_cost) + (quantity * unit_price)) / (current_stock + quantity)` using `Money` arithmetic (§5.1); increment `current_stock`; create `PurchaseItem`. Create the `Purchase` header (total = sum of subtotals). Dispatch `PurchaseRegistered`.
5. Return `PurchaseResource`.

### Register sale — `POST /api/sales`
1. Required `Idempotency-Key` header checked (§5.2), same as purchase.
2. `StoreSaleRequest` validates: `customer` required; `products` array, each item's `id` must exist, `quantity` integer min 1, `unit_price` numeric min 0.
3. Controller calls `RegisterSaleAction::execute(RegisterSaleData)`.
4. For each item: `lockForUpdate()` the product row; if `quantity > current_stock`, throw `InsufficientStockException`; `ProfitCalculatorService` computes `item_profit = (unit_price - average_cost) * quantity` via `Money`; decrement `current_stock`; create `SaleItem` with `average_cost_snapshot`. Create the `Sale` header (total + profit summed across items). Dispatch `SaleRegistered`.
5. Return `SaleResource` including `total` and `profit`.

### Cancel sale — `POST /api/sales/{id}/cancel`
1. `CancelSaleAction`: `lockForUpdate()` the sale; if `status === cancelled`, throw `SaleAlreadyCancelledException`; for each `sale_item`, `lockForUpdate()` the product and add `quantity` back to `current_stock`; set `status = cancelled`. Dispatch `SaleCancelled`.
2. `average_cost` is **not** touched on cancellation — cancelling reverses quantity, not cost basis. No row is deleted (§5.3): the sale and its items remain, only `status` changes.

### Stock movement audit trail
`PurchaseRegistered`, `SaleRegistered`, and `SaleCancelled` are dispatched synchronously (not queued) from inside the same transaction. `RecordStockMovement` listener reacts to all three and writes one row to `stock_movements` (`purchase_in` / `sale_out` / `sale_cancelled_return`, `user_id` from the authenticated request), joining the same open transaction. This decouples the Actions from the audit concern (OCP: a new listener can be added later without touching any Action) and gives the ERP a real stock movement history — including *who* triggered each movement, not just what happened.

Async Jobs (queues) are deliberately not used for the core flow: stock and cost updates must be synchronous within the locked transaction for consistency — queuing would break the guarantee the row lock provides. No other requirement in this scope needs background processing.

## 5. Fintech-Grade Practices

### 5.1 Money as a value object
`App\ValueObjects\Money` — immutable, wraps an integer amount in cents. No `float`/`decimal` ever crosses a method boundary as a raw scalar. Operations: `add`, `subtract`, `multiply(float $factor): Money`, `isNegative`, `equals`, `toCents(): int`, `formatted(): string`. `AverageCostService` and `ProfitCalculatorService` accept and return `Money`, not raw numbers — this is what actually guarantees rounding correctness, more than the underlying storage type does. `App\Casts\MoneyCast` (implements `CastsAttributes`) lets Eloquent models expose `sale_price_cents` as a `Money` instance transparently (`$product->salePrice` returns `Money`, the cast converts to/from the integer column). Integer cents in the database sidesteps decimal-column/driver rounding quirks entirely, which is why payment processors (Stripe et al.) use the same representation. The HTTP contract still speaks plain decimal (`"unit_price": 20.50`, matching the README's example payloads) — `Money::fromDecimalString()` converts it to cents at the DTO boundary, and Resources call `Money::formatted()` to serialize back to decimal in responses. Cents never leak into request/response JSON.

### 5.2 Idempotency on financial writes
`POST /api/purchases` and `POST /api/sales` require an `Idempotency-Key` header (client-generated UUID). `EnsureIdempotencyKey` middleware: looks up the key in `idempotency_keys`; if found and the stored `request_hash` matches this request's body hash, the stored `response_body`/`response_status` is replayed immediately without invoking the Action (safe retry after a client-side timeout, no duplicate purchase/sale); if found with a different hash, respond `422 Idempotency-Key reused with a different request body`; if not found, let the request proceed and persist the key + response after a successful commit. This is the standard mechanism (Stripe, most banking/payment APIs) for making POST endpoints that move stock/money safely retryable over an unreliable network.

### 5.3 Append-only financial records
No `DELETE` operation is ever exposed for `purchases`, `purchase_items`, `sales`, `sale_items`, or `stock_movements` — no repository method, no route, no Eloquent `SoftDeletes` even (soft-delete still implies "this stops existing"; the correct model here is that it never stops existing). A sale is cancelled by a `status` transition, not by removing rows. This keeps every financial fact reconstructable and auditable at any point in time, which is the baseline expectation for any ledger-adjacent system.

### 5.4 Rate limiting
A named rate limiter (`RateLimiter::for('financial', ...)` in `AppServiceProvider`) applies `throttle:financial` to the `purchases` and `sales` route groups — e.g. 30 requests/minute per authenticated user. Protects against runaway clients or retry storms hammering endpoints that mutate stock and money.

### 5.5 Observability & error logging
Laravel logs uncaught exceptions to the default channel automatically, but three gaps matter specifically for a financial API and are addressed explicitly:

- **Request correlation**: an `AssignRequestId` middleware reads (or generates) an `X-Request-Id` header, echoes it back on the response, and calls `Log::shareContext(['request_id' => ..., 'user_id' => ...])` so every log line written during that request — including exception logs — carries it. When a customer disputes a sale, the request id (or the `Idempotency-Key`) is enough to pull every log line for that exact operation.
- **Structured audit logging distinct from `stock_movements`**: `RegisterPurchaseAction`, `RegisterSaleAction`, and `CancelSaleAction` emit a structured `Log::info()` on success (e.g. `sale.registered` with sale id, total, profit, item count) in addition to the `PurchaseRegistered`/`SaleRegistered`/`SaleCancelled` events. `stock_movements` is the SQL-queryable business ledger; this log stream is what a log aggregator (CloudWatch, Datadog, ELK) actually searches/alerts on — the two serve different consumers and are both needed.
- **Exception log levels tuned to actual severity**: `InsufficientStockException` and `SaleAlreadyCancelledException` are expected business outcomes, logged at `warning` via the `level()` exception method in `bootstrap/app.php` — not `error`, so they don't page anyone or pollute error-rate dashboards. Unexpected exceptions (DB failures, anything uncaught) keep Laravel's default `error` level. Domain exceptions also implement `context()` (per Laravel's reportable-exception contract) to attach relevant data — product id, requested quantity, current stock — directly to the log entry instead of just a message string.

None of this requires a third-party APM to be wired up now — the design just makes sure the hooks (`report`/`level`/`context`, shared log context) are in place, so plugging in Sentry/Datadog later is a config change, not a refactor.

## 6. Authentication

Laravel Sanctum, scaffolded via `php artisan install:api`.

```
POST /api/register  { name, email, password, password_confirmation } -> 201 { user, token }
POST /api/login      { email, password }                              -> 200 { user, token }
POST /api/logout     (auth:sanctum)                                    -> 204
```

`AuthController` (thin) delegates to `RegisterUserAction` / `LoginAction`. All `products`, `purchases`, `sales` routes are protected by the `auth:sanctum` middleware group.

## 7. Error Handling & Response Format

- `ValidationException` (422) and nested-array validation errors are formatted automatically by Laravel for JSON requests (`{ "message": ..., "errors": { "products.0.id": [...] } }`) — no custom code needed.
- Domain exceptions (`InsufficientStockException`, `SaleAlreadyCancelledException`) implement their own `render(Request $request)` method, returning the appropriate JSON body and 422 status directly on the exception class — no changes to `bootstrap/app.php` needed.
- `ModelNotFoundException` -> 404, `AuthenticationException` -> 401: Laravel's default JSON rendering already covers these for API requests.
- Successful responses always go through an API Resource (`ProductResource`, `PurchaseResource`, `SaleResource`); list endpoints use `paginate()` and Resource collections, which include the standard `data` / `links` / `meta` envelope.

## 8. API Documentation

`darkaonline/l5-swagger` (OpenAPI 3 / zircote/swagger-php). `#[OA\...]` attributes on Controllers and FormRequests describe each endpoint (parameters, request body, 200/201/422/401/404 responses) and Resource schemas. Served at `/api/documentation`; generated via `php artisan l5-swagger:generate` (or `SWAGGER_GENERATE_ALWAYS=true` in local dev).

## 9. Testing Strategy (Pest)

- **Unit** (`tests/Unit`): `MoneyTest` (arithmetic, rounding edge cases), `AverageCostServiceTest` and `ProfitCalculatorServiceTest` as pure-calculation case tables (using `Money`); Action tests with mocked repository interfaces (Mockery) validating orchestration logic without touching the database.
- **Feature** (`tests/Feature`): end-to-end HTTP tests per domain — `ProductApiTest`, `PurchaseApiTest`, `SaleApiTest` (covers insufficient stock -> 422, cancel -> stock reversed, average cost correct across multiple purchases), `AuthApiTest` (register/login/logout, protected route without token -> 401), `StockMovementTest` (each event produces the correct movement row), `IdempotencyTest` (same key + same body replays the original response without creating a second purchase/sale; same key + different body -> 422), `RateLimitTest` (exceeding the financial throttle returns 429).
- `Sanctum::actingAs($user)` used to authenticate in feature tests — no manual token generation needed.
- SQLite in-memory with `RefreshDatabase` for speed; model factories for all entities.

## 10. Folder Structure

```
backend/
  app/
    Actions/
      Auth/RegisterUserAction.php, LoginAction.php
      Product/CreateProductAction.php
      Purchase/RegisterPurchaseAction.php
      Sale/RegisterSaleAction.php, CancelSaleAction.php
    ValueObjects/
      Money.php
    Casts/
      MoneyCast.php
    Services/
      AverageCostService.php
      ProfitCalculatorService.php
    Repositories/
      Contracts/ProductRepositoryInterface.php, PurchaseRepositoryInterface.php, SaleRepositoryInterface.php
      Eloquent/EloquentProductRepository.php, EloquentPurchaseRepository.php, EloquentSaleRepository.php
    DataTransferObjects/
      RegisterPurchaseData.php, PurchaseItemData.php, RegisterSaleData.php, SaleItemData.php
    Events/
      PurchaseRegistered.php, SaleRegistered.php, SaleCancelled.php
    Listeners/
      RecordStockMovement.php
    Exceptions/
      InsufficientStockException.php, SaleAlreadyCancelledException.php, IdempotencyKeyConflictException.php
    Http/
      Controllers/Api/ProductsController.php, PurchasesController.php, SalesController.php, AuthController.php
      Middleware/EnsureIdempotencyKey.php, AssignRequestId.php
      Requests/StoreProductRequest.php, StorePurchaseRequest.php, StoreSaleRequest.php, RegisterUserRequest.php, LoginRequest.php
      Resources/Product/ProductResource.php, Purchase/PurchaseResource.php, Sale/SaleResource.php
    Models/Product.php, Purchase.php, PurchaseItem.php, Sale.php, SaleItem.php, StockMovement.php, IdempotencyKey.php, User.php
    Providers/RepositoryServiceProvider.php
  database/migrations/, factories/, seeders/
  tests/Unit/Services/, Unit/Actions/, Feature/
  config/l5-swagger.php
```

`docker/` (Dockerfile for backend, root `docker-compose.yml` with MySQL + frontend) is addressed by a separate sub-project spec, not this one.

## 11. Explicitly Out of Scope for This Spec

- Frontend (Vue) screens and API integration — separate spec.
- Docker packaging (Dockerfiles, docker-compose.yml) — separate spec.
- Notification emails, PDF/report generation, or any other async job — no requirement in the challenge calls for them.
