# Arquitetura do Backend — Fone Ninja

## Visão Geral

API REST em Laravel 13 (PHP 8.4) para ERP de estoque. Autenticação via Sanctum, padrão Action + Repository, Value Object `Money` para valores monetários (centavos inteiros), idempotência em operações financeiras, e event sourcing para auditoria de movimentações de estoque.

---

## Stack

| Componente | Tecnologia |
|---|---|
| Framework | Laravel 13.8 |
| PHP | ^8.4 |
| Autenticação | Laravel Sanctum (token-based) |
| Testes | Pest 4 + PHPUnit 12 |
| Documentação | darkaonline/l5-swagger (OpenAPI via atributos PHP) |
| Banco | MySQL (implícito — migrations padrão Laravel) |
| Dev | Pint (lint), Sail (Docker) |

---

## Estrutura de Diretórios

```
backend/
├── app/
│   ├── Actions/                # Casos de uso (uma classe por operação)
│   │   ├── Auth/               # LoginAction, RegisterUserAction
│   │   ├── Product/            # CreateProductAction
│   │   ├── Purchase/           # RegisterPurchaseAction
│   │   └── Sale/               # RegisterSaleAction, PreviewSaleAction, CancelSaleAction
│   ├── Casts/
│   │   └── MoneyCast.php       # Cast Eloquent: int cents <-> Money VO
│   ├── DataTransferObjects/    # DTOs imutáveis (final class)
│   │   ├── PurchaseItemData.php
│   │   ├── RegisterPurchaseData.php
│   │   ├── RegisterSaleData.php
│   │   ├── SaleItemData.php
│   │   └── SalePreviewData.php
│   ├── Events/
│   │   ├── PurchaseRegistered.php
│   │   ├── SaleCancelled.php
│   │   └── SaleRegistered.php
│   ├── Exceptions/
│   │   ├── IdempotencyKeyConflictException.php
│   │   ├── InsufficientStockException.php
│   │   └── SaleAlreadyCancelledException.php
│   ├── Http/
│   │   ├── Controllers/Api/    # AuthController, ProductsController, PurchasesController, SalesController
│   │   ├── Middleware/         # AssignRequestId, EnsureIdempotencyKey, AssignAuthenticatedUserToLogContext
│   │   ├── Requests/           # FormRequests (validação)
│   │   └── Resources/          # ProductResource, PurchaseResource, SaleResource
│   ├── Listeners/
│   │   └── RecordStockMovement.php  # Handler unificado para 3 eventos
│   ├── Models/                 # User, Product, Purchase, PurchaseItem, Sale, SaleItem, StockMovement, IdempotencyKey
│   ├── Providers/              # AppServiceProvider, RepositoryServiceProvider
│   ├── Repositories/
│   │   ├── Contracts/          # Interfaces
│   │   └── Eloquent/           # Implementações concretas
│   ├── Services/
│   │   ├── AverageCostService.php
│   │   └── ProfitCalculatorService.php
│   └── ValueObjects/
│       └── Money.php           # Value Object imutável (cents)
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── web.php
└── tests/
    ├── Feature/
    └── Unit/
```

---

## Diagrama de Camadas

```mermaid
graph TB
    subgraph "HTTP Layer"
        MW[Middleware Pipeline]
        REQ[FormRequests - Validação]
        CTRL[Controllers]
    end

    subgraph "Application Layer"
        DTO[DTOs - Data Transfer Objects]
        ACT[Actions - Casos de Uso]
    end

    subgraph "Domain Layer"
        EVT[Events]
        SVC[Services]
        VO[Value Objects - Money]
        EXC[Domain Exceptions]
    end

    subgraph "Infrastructure Layer"
        REPO[Repositories]
        MODEL[Eloquent Models]
        LIS[Listeners]
        DB[(MySQL)]
    end

    REQ --> CTRL
    MW --> REQ
    CTRL --> DTO
    DTO --> ACT
    ACT --> REPO
    ACT --> SVC
    ACT --> EVT
    REPO --> MODEL
    MODEL --> DB
    EVT --> LIS
    LIS --> MODEL
    SVC --> VO
    ACT --> EXC
```

---

## Middleware Pipeline (Ordem de Execução)

```mermaid
sequenceDiagram
    participant Client
    participant MW1 as AssignRequestId (global)
    participant MW2 as auth:sanctum (group)
    participant MW3 as AssignAuthenticatedUserToLogContext (group)
    participant MW4 as idempotent (route)
    participant MW5 as throttle:financial (route)
    participant App as Controller + Action

    Client->>MW1: Request
    MW1->>MW1: Gera UUID (X-Request-Id)
    MW1->>MW1: Injeta request_id no log context
    MW1->>MW2: next()

    MW2->>MW2: Resolve user via Sanctum token
    MW2->>MW3: next()

    MW3->>MW3: Injeta user_id no log context
    MW3->>MW4: next() [se rota tem idempotent]

    MW4->>MW4: Verifica Idempotency-Key header
    alt Duplicata: mesmo body
        MW4->>Client: 200 (resposta cacheada)
    else Duplicata: body diferente
        MW4->>Client: 422 IdempotencyKeyConflict
    else Em andamento
        MW4->>Client: 409 Conflict
    else Nova key
        MW4->>MW4: Insere placeholder em idempotency_keys
        MW4->>MW5: next()
    end

    MW5->>MW5: Rate limit: 30 req/min
    MW5->>App: next()
    App->>MW4: response
    MW4->>MW4: Atualiza idempotency_keys c/ response
    MW4->>Client: response
```

---

## Modelo de Dados (Entidades e Relacionamentos)

```mermaid
erDiagram
    User ||--o{ StockMovement : "registra"
    User ||--o{ IdempotencyKey : "possui"

    Product ||--o{ PurchaseItem : "referenciado em"
    Product ||--o{ SaleItem : "referenciado em"
    Product ||--o{ StockMovement : "rastreado em"

    Purchase ||--o{ PurchaseItem : "contém"
    Sale ||--o{ SaleItem : "contém"

    Product {
        bigint id PK
        string nome
        bigint sale_price_cents "preco_venda (> 0)"
        bigint average_cost_cents "custo_medio"
        bigint current_stock "estoque (>= 0)"
    }

    Purchase {
        bigint id PK
        string fornecedor
        bigint total_cents "total"
    }

    PurchaseItem {
        bigint id PK
        bigint purchase_id FK
        bigint product_id FK
        int quantidade "> 0"
        bigint unit_price_cents "preco_unitario (> 0)"
        bigint subtotal_cents
    }

    Sale {
        bigint id PK
        string cliente
        bigint total_cents "total"
        bigint profit_cents "lucro (pode ser negativo)"
        enum status "completed | cancelled"
    }

    SaleItem {
        bigint id PK
        bigint sale_id FK
        bigint product_id FK
        int quantidade "> 0"
        bigint unit_price_cents "preco_unitario (> 0)"
        bigint average_cost_snapshot_cents "snapshot do custo medio"
        bigint subtotal_cents
        bigint item_profit_cents "lucro por item (pode ser negativo)"
    }

    StockMovement {
        bigint id PK
        bigint product_id FK
        bigint user_id FK
        enum type "purchase_in | sale_out | sale_cancelled_return"
        int quantidade
        string reference_type "polimórfico"
        bigint reference_id "polimórfico"
        timestamp created_at
    }

    IdempotencyKey {
        bigint id PK
        string key
        string route
        string request_hash "SHA-256"
        int response_status "nullable"
        json response_body "nullable"
        bigint user_id FK
        timestamp created_at
    }

    User {
        bigint id PK
        string nome
        string email UK
        string senha "hashed"
    }
```

---

## Rotas da API

```mermaid
graph LR
    subgraph "Públicas (sem auth)"
        R1["POST /registro"]
        R2["POST /login"]
    end

    subgraph "Autenticadas (auth:sanctum)"
        R3["POST /logout"]
        subgraph "Produtos"
            R4["GET /produtos"]
            R5["POST /produtos"]
        end
        subgraph "Compras"
            R6["GET /compras"]
            R7["POST /compras<br/>+ idempotent + throttle:financial"]
        end
        subgraph "Vendas"
            R8["GET /vendas"]
            R9["POST /vendas<br/>+ idempotent + throttle:financial"]
            R10["POST /vendas/preview"]
            R11["POST /vendas/{id}/cancelar<br/>+ throttle:financial"]
        end
    end
```

| Método | Rota | Controller | Middleware Extra |
|---|---|---|---|
| POST | `/registro` | AuthController@register | — |
| POST | `/login` | AuthController@login | — |
| POST | `/logout` | AuthController@logout | — |
| GET | `/produtos` | ProductsController@index | — |
| POST | `/produtos` | ProductsController@store | — |
| GET | `/compras` | PurchasesController@index | — |
| POST | `/compras` | PurchasesController@store | idempotent, throttle:financial |
| GET | `/vendas` | SalesController@index | — |
| POST | `/vendas` | SalesController@store | idempotent, throttle:financial |
| POST | `/vendas/preview` | SalesController@preview | — |
| POST | `/vendas/{id}/cancelar` | SalesController@cancel | throttle:financial |

---

## Fluxo: Compra (RegisterPurchaseAction)

```mermaid
sequenceDiagram
    participant C as Cliente
    participant MW as IdempotencyMiddleware
    participant CTRL as PurchasesController
    participant DTO as RegisterPurchaseData
    participant ACT as RegisterPurchaseAction
    participant DB as Database (transaction)
    participant SVC as AverageCostService
    participant EVT as PurchaseRegistered event
    participant LIS as RecordStockMovement

    C->>MW: POST /compras + Idempotency-Key
    MW->>MW: Verifica/insere idempotency_key
    MW->>CTRL: StorePurchaseRequest validado
    CTRL->>DTO: fromValidated()
    DTO-->>CTRL: RegisterPurchaseData
    CTRL->>ACT: execute(data, userId)

    ACT->>DB: BEGIN transaction (3 retries)

    ACT->>DB: Create Purchase (total=0 placeholder)
    Note over ACT,DB: Ordena itens por productId ASC (prevenção deadlock)

    loop Para cada item
        ACT->>DB: Product::lockForUpdate()
        ACT->>SVC: recalculate(currentStock, avgCost, quantity, unitPrice)
        SVC-->>ACT: newAverageCost (Money)
        ACT->>DB: product.current_stock += quantity
        ACT->>DB: product.average_cost_cents = newAverageCost
        ACT->>DB: Save product
        ACT->>DB: Create PurchaseItem (subtotal = unitPrice × quantity)
    end

    ACT->>DB: Update Purchase.total_cents
    ACT->>DB: COMMIT
    ACT->>EVT: dispatch PurchaseRegistered(purchase, userId)
    EVT-->>LIS: handlePurchaseRegistered()

    loop Para cada item
        LIS->>DB: INSERT stock_movements (type=purchase_in)
    end

    ACT-->>CTRL: Purchase (com items.product)
    CTRL->>MW: PurchaseResource → 201
    MW->>MW: Atualiza idempotency_keys
    MW-->>C: 201 Created
```

---

## Fluxo: Venda (RegisterSaleAction)

```mermaid
sequenceDiagram
    participant C as Cliente
    participant MW as IdempotencyMiddleware
    participant CTRL as SalesController
    participant DTO as RegisterSaleData
    participant ACT as RegisterSaleAction
    participant DB as Database (transaction)
    participant SVC as ProfitCalculatorService
    participant EVT as SaleRegistered event
    participant LIS as RecordStockMovement

    C->>MW: POST /vendas + Idempotency-Key
    MW->>MW: Verifica/insere idempotency_key
    MW->>CTRL: StoreSaleRequest validado
    CTRL->>DTO: fromValidated()
    DTO-->>CTRL: RegisterSaleData
    CTRL->>ACT: execute(data, userId)

    ACT->>DB: BEGIN transaction (3 retries)

    ACT->>DB: Create Sale (total=0, profit=0, status=completed)
    Note over ACT,DB: Ordena itens por productId ASC

    loop Para cada item
        ACT->>DB: Product::lockForUpdate()
        alt quantity > product.current_stock
            ACT->>DB: ROLLBACK
            ACT-->>CTRL: InsufficientStockException (422)
            CTRL-->>C: 422 "Estoque insuficiente para o produto X"
        else estoque suficiente
            ACT->>SVC: calculate(unitPrice, averageCost, quantity)
            SVC-->>ACT: itemProfit (Money) = (unitPrice - averageCost) × quantity
            ACT->>DB: product.current_stock -= quantity
            ACT->>DB: Save product (custo_medio NÃO muda)
            ACT->>DB: Create SaleItem (com average_cost_snapshot, item_profit)
        end
    end

    ACT->>DB: Update Sale.total_cents, Sale.profit_cents
    ACT->>DB: COMMIT
    ACT->>EVT: dispatch SaleRegistered(sale, userId)
    EVT-->>LIS: handleSaleRegistered()

    loop Para cada item
        LIS->>DB: INSERT stock_movements (type=sale_out)
    end

    ACT-->>CTRL: Sale
    CTRL->>MW: SaleResource → 201
    MW->>MW: Atualiza idempotency_keys
    MW-->>C: 201 Created
```

---

## Fluxo: Preview de Venda (PreviewSaleAction)

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTRL as SalesController
    participant DTO as SalePreviewData
    participant ACT as PreviewSaleAction
    participant REPO as ProductRepository
    participant SVC as ProfitCalculatorService

    C->>CTRL: POST /vendas/preview (sem Idempotency-Key)
    CTRL->>CTRL: PreviewSaleRequest validado
    CTRL->>DTO: fromValidated()
    DTO-->>CTRL: SalePreviewData
    CTRL->>ACT: execute(data)

    Note over ACT: READ-ONLY — sem transaction, sem locks

    ACT->>REPO: findManyByIds(productIds)
    REPO-->>ACT: Map<int, Product>

    loop Para cada item
        ACT->>SVC: calculate(unitPrice, product.averageCost, quantity)
        SVC-->>ACT: itemProfit
        Note over ACT: subtotal = unitPrice × quantity
        Note over ACT: acumula total e lucro
    end

    ACT-->>CTRL: array { total, lucro, itens[] }
    CTRL-->>C: 200 OK (sem escrita no banco, sem eventos)
```

---

## Fluxo: Cancelamento de Venda (CancelSaleAction)

```mermaid
sequenceDiagram
    participant C as Cliente
    participant CTRL as SalesController
    participant ACT as CancelSaleAction
    participant DB as Database (transaction)
    participant EVT as SaleCancelled event
    participant LIS as RecordStockMovement

    C->>CTRL: POST /vendas/{id}/cancelar
    CTRL->>ACT: execute(saleId, userId)

    ACT->>DB: BEGIN transaction (3 retries)
    ACT->>DB: Sale::lockForUpdate()

    alt sale.status === 'cancelled'
        ACT->>DB: ROLLBACK
        ACT-->>CTRL: SaleAlreadyCancelledException (422)
        CTRL-->>C: 422 "Venda já cancelada"
    else sale.status === 'completed'
        loop Para cada SaleItem
            ACT->>DB: Product::lockForUpdate()
            ACT->>DB: product.current_stock += item.quantity
            ACT->>DB: Save product
        end
        ACT->>DB: sale.status = 'cancelled'
        ACT->>DB: Save sale
        ACT->>DB: COMMIT

        ACT->>EVT: dispatch SaleCancelled(sale, userId)
        EVT-->>LIS: handleSaleCancelled()

        loop Para cada item
            LIS->>DB: INSERT stock_movements (type=sale_cancelled_return)
        end

        ACT-->>CTRL: Sale
        CTRL-->>C: 200 OK
    end
```

---

## Cálculo do Custo Médio

```mermaid
graph LR
    subgraph "Fórmula"
        A["Estoque atual: 10 un<br/>Custo médio atual: R$ 5,00<br/>(500 cents)"]
        B["Compra: 5 un<br/>Preço unitário: R$ 8,00<br/>(800 cents)"]
        C["Total unidades: 15"]
        D["Custo total: (10 × 500) + (5 × 800) = 9000 cents"]
        E["Novo custo médio: round(9000 / 15) = 600 cents = R$ 6,00"]
    end

    A --> C
    B --> C
    A --> D
    B --> D
    C --> E
    D --> E
```

Implementação em `AverageCostService::recalculate()`: média ponderada entre estoque existente e novas unidades, arredondada para o centavo mais próximo.

---

## Cálculo do Lucro

```mermaid
graph LR
    subgraph "Por item (ProfitCalculatorService)"
        PV["Preço de venda unitário"] --> DIF["Diferença"]
        CM["Custo médio (snapshot)"] --> DIF
        DIF --> LUCRO["Lucro = (PV - CM) × quantidade"]
    end

    subgraph "Por venda"
        ITEM1["Lucro item 1"] --> TOTAL["Lucro total = Σ lucro por item"]
        ITEM2["Lucro item 2"] --> TOTAL
    end
```

---

## Value Object: Money

```mermaid
classDiagram
    class Money {
        -int cents
        +fromCents(int) Money$
        +fromDecimalString(string) Money$
        +zero() Money$
        +add(Money) Money
        +subtract(Money) Money
        +multiply(float) Money
        +isNegative() bool
        +equals(Money) bool
        +toCents() int
        +formatted() string
    }

    class MoneyCast {
        +get(Model, string, int, array) Money
        +set(Model, string, Money|int, array) int
    }

    MoneyCast ..> Money : converte
```

- **Interno**: centavos inteiros (`int`) — sem arredondamento de ponto flutuante
- **Fronteira HTTP**: strings decimais (`"99.90"`) via `formatted()` e `fromDecimalString()`
- **Banco de dados**: `bigint` (centavos), convertido via `MoneyCast`

---

## Sistema de Idempotência

```mermaid
sequenceDiagram
    participant C as Cliente
    participant MW as EnsureIdempotencyKey
    participant DB as idempotency_keys table

    C->>MW: POST /compras<br/>Header: Idempotency-Key: abc-123<br/>Body: { fornecedor: "ABC", ... }

    MW->>DB: INSERT INTO idempotency_keys<br/>(key, route, request_hash, user_id, response_status=null)

    alt INSERT bem-sucedido (nova key)
        MW->>MW: Prossegue para o controller
        Note over MW: ... ação executada ...
        MW->>DB: UPDATE response_status=201, response_body={...}
        MW-->>C: 201 Created
    else UNIQUE constraint violation
        MW->>DB: SELECT * WHERE user_id=X AND key='abc-123'
        alt response_status IS NULL (em andamento)
            MW-->>C: 409 Conflict
        else request_hash igual
            MW-->>C: Replay da resposta cacheada
        else request_hash diferente
            MW-->>C: 422 IdempotencyKeyConflict
        end
    end
```

**Garantia**: mesmo `Idempotency-Key` com mesmo body → resposta idêntica. Key reutilizada com body diferente → erro. Race condition (requisição ainda em andamento) → 409.

---

## Event Sourcing (StockMovement)

```mermaid
graph TD
    RA[RegisterPurchaseAction] -->|dispatch| PR[PurchaseRegistered]
    RS[RegisterSaleAction] -->|dispatch| SR[SaleRegistered]
    CA[CancelSaleAction] -->|dispatch| SC[SaleCancelled]

    PR --> LIS[RecordStockMovement Listener]
    SR --> LIS
    SC --> LIS

    LIS --> SM1["handlePurchaseRegistered()<br/>→ INSERT stock_movements<br/>(type=purchase_in)"]
    LIS --> SM2["handleSaleRegistered()<br/>→ INSERT stock_movements<br/>(type=sale_out)"]
    LIS --> SM3["handleSaleCancelled()<br/>→ INSERT stock_movements<br/>(type=sale_cancelled_return)"]

    SM1 --> DB[(stock_movements)]
    SM2 --> DB
    SM3 --> DB
```

`stock_movements` é uma tabela append-only de auditoria. Cada registro contém: `product_id`, `user_id`, `type` (enum), `quantity`, e referência polimórfica (`reference_type` + `reference_id`). Nenhum registro é atualizado ou deletado.

---

## Padrão Repository

```mermaid
classDiagram
    class ProductRepositoryInterface {
        <<interface>>
        +find(int) Product
        +findForUpdate(int) Product
        +findManyByIds(int[]) array
        +paginate(int) LengthAwarePaginator
        +create(array) Product
    }

    class PurchaseRepositoryInterface {
        <<interface>>
        +create(array) Purchase
        +paginateWithItems(int) LengthAwarePaginator
    }

    class SaleRepositoryInterface {
        <<interface>>
        +create(array) Sale
        +findForUpdate(int) Sale
        +paginateWithItems(int) LengthAwarePaginator
    }

    class EloquentProductRepository {
        +find() "findOrFail"
        +findForUpdate() "lockForUpdate() + findOrFail"
        +findManyByIds() "whereIn + keyBy('id')"
    }

    class EloquentPurchaseRepository {
        +paginateWithItems() "with('items.product').latest().paginate()"
    }

    class EloquentSaleRepository {
        +findForUpdate() "lockForUpdate() + findOrFail"
        +paginateWithItems() "with('items.product').latest().paginate()"
    }

    ProductRepositoryInterface <|.. EloquentProductRepository
    PurchaseRepositoryInterface <|.. EloquentPurchaseRepository
    SaleRepositoryInterface <|.. EloquentSaleRepository
```

Binding no `RepositoryServiceProvider`:

```php
$this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
$this->app->bind(PurchaseRepositoryInterface::class, EloquentPurchaseRepository::class);
$this->app->bind(SaleRepositoryInterface::class, EloquentSaleRepository::class);
```

---

## Estrutura de Respostas da API

### ProductResource

```json
{
  "id": 1,
  "nome": "Fone de Ouvido",
  "custo_medio": "15.00",
  "preco_venda": "29.90",
  "estoque": 42
}
```

### PurchaseResource

```json
{
  "id": 1,
  "fornecedor": "Distribuidora ABC",
  "total": "150.00",
  "produtos": [
    {
      "id": 1,
      "nome": "Fone de Ouvido",
      "quantidade": 10,
      "preco_unitario": "15.00",
      "subtotal": "150.00"
    }
  ]
}
```

### SaleResource

```json
{
  "id": 1,
  "cliente": "João Silva",
  "total": "29.90",
  "lucro": "14.90",
  "status": "completed",
  "produtos": [
    {
      "id": 1,
      "nome": "Fone de Ouvido",
      "quantidade": 1,
      "preco_unitario": "29.90",
      "subtotal": "29.90"
    }
  ]
}
```

### Preview de Venda (resposta direta, sem Resource)

```json
{
  "total": "59.80",
  "lucro": "29.80",
  "itens": [
    {
      "id": 1,
      "nome": "Fone de Ouvido",
      "quantidade": 2,
      "preco_unitario": "29.90",
      "subtotal": "59.80",
      "lucro": "29.80"
    }
  ]
}
```

---

## Exceções de Domínio

```mermaid
classDiagram
    class InsufficientStockException {
        +render(Request) JsonResponse
        +context() array
        -LogLevel WARNING
    }
    class SaleAlreadyCancelledException {
        +render(Request) JsonResponse
        +context() array
        -LogLevel WARNING
    }
    class IdempotencyKeyConflictException {
        +render(Request) JsonResponse
        +context() array
    }

    InsufficientStockException --|> Exception
    SaleAlreadyCancelledException --|> Exception
    IdempotencyKeyConflictException --|> Exception
```

| Exceção | Gatilho | HTTP | Mensagem |
|---|---|---|---|
| `InsufficientStockException` | Venda excede `current_stock` | 422 | "Estoque insuficiente para o produto `<nome>`" |
| `SaleAlreadyCancelledException` | Cancelar venda já cancelada | 422 | "Venda já cancelada" |
| `IdempotencyKeyConflictException` | Idempotency-Key reutilizada com body diferente | 422 | "Idempotency-Key já utilizada com um corpo de requisição diferente" |

---

## Fluxo Completo de Requisição

```mermaid
flowchart TD
    A[HTTP Request] --> B[AssignRequestId: gera UUID, log context]
    B --> C{auth:sanctum}
    C -->|token válido| D[AssignAuthenticatedUserToLogContext: user_id no log]
    C -->|sem token / inválido| E[401 Unauthorized]
    D --> F{rota tem<br/>idempotent?}
    F -->|sim| G[EnsureIdempotencyKey: verifica/cria key]
    F -->|não| H[throttle:financial?]
    G --> H
    H -->|sim| I[Rate limiter: 30/min]
    H -->|não| J[FormRequest: validação]
    I --> J
    J -->|válido| K[Controller: delega para Action]
    J -->|inválido| L[422 Validation Error]
    K --> M[Action: lógica de negócio]
    M --> N[Repository: acesso a dados]
    N --> O[(MySQL)]
    M --> P[Event dispatch]
    P --> Q[Listener: efeitos colaterais]
    Q --> O
    M --> R[Resource: transformação]
    R --> S[HTTP Response]
```
