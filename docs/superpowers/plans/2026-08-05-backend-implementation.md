# Backend Implementation Plan — Inventory ERP API

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel 13 backend API for the inventory ERP challenge — products, purchases (stock in + average cost), sales (stock out + profit, insufficient-stock validation), sale cancellation, and purchase/sale listings — with fintech-grade rigor (Money value object, idempotency, append-only records, DB constraints, rate limiting, structured logging).

**Architecture:** Controller → FormRequest → Action → Repository interface (DIP) → Service, with a Resource layer formatting output. FormRequests and Resources are the sole translation boundary between the Portuguese HTTP contract (routes, JSON keys, error messages) and English internal code.

**Tech Stack:** Laravel 13, PHP 8.3, Laravel Sail (MySQL 8), Pest, Sanctum, l5-swagger.

## Global Constraints

- PHP 8.3, Laravel 13, MySQL 8 (Sail), Pest for all tests.
- All internal PHP identifiers (classes, properties, methods, migrations, comments, log messages) in English.
- HTTP contract — routes, FormRequest validated keys, Resource output keys, domain-exception `message` strings — in Portuguese, matching the README verbatim (`/api/produtos`, `/api/compras`, `/api/vendas`, `nome`, `preco_venda`, `custo_medio`, `estoque`, `quantidade`, `preco_unitario`, `fornecedor`, `cliente`, `"Estoque insuficiente para o produto <nome>"`).
- All money stored as integer cents in the database; `App\ValueObjects\Money` is the only type that crosses a method boundary carrying a monetary value. Decimal strings only appear at the HTTP boundary (FormRequest input, Resource output).
- No `DELETE` route/repository method/`SoftDeletes` on `purchases`, `purchase_items`, `sales`, `sale_items`, or `stock_movements`. Foreign keys on these tables use `restrictOnDelete()`.
- Every write to `products`/`purchases`/`purchase_items`/`sales`/`sale_items` that changes stock or money runs inside `DB::transaction(fn () => ..., attempts: 3)` with `lockForUpdate()` on every touched `products` row.
- `POST /api/compras` and `POST /api/vendas` require an `Idempotency-Key` header.
- `Money` arithmetic: `add`, `subtract`, `multiply(float $factor): Money`, `isNegative()`, `equals()`, `toCents(): int`, `formatted(): string`, `Money::fromCents(int)`, `Money::fromDecimalString(string)`, `Money::zero()`.
- CHECK constraints (`sale_price_cents > 0`, `current_stock >= 0`, `quantity > 0`, `unit_price_cents > 0`) are added via `DB::statement()` guarded by `DB::getDriverName() === 'mysql'` — MySQL enforces them, SQLite (used for the fast Pest test run) does not support adding them via `ALTER TABLE`, so guard tests that assert them with `test()->skip(DB::getDriverName() !== 'mysql', ...)` and run those specifically against Sail's MySQL when needed.

---

## Task 1: Scaffold Laravel Project

**Files:**
- Create: entire Laravel 13 skeleton at repo root (`composer.json`, `artisan`, `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`, `phpunit.xml`)
- Modify: `.env`, `.env.example`

**Interfaces:**
- Produces: a runnable Laravel 13 app with Pest installed, Sanctum/API scaffolding installed (`routes/api.php` exists), and `php artisan test` green on the default example tests.

- [ ] **Step 1: Create the Laravel project**

```bash
cd /home/pedrophsms/projetos/fone-ninja-backend
composer create-project laravel/laravel:^13.0 . --prefer-dist
```

- [ ] **Step 2: Install Pest and remove the default PHPUnit test scaffolding**

```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies
php artisan pest:install
```

When prompted to overwrite `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php`, accept — Pest replaces them with `Pest.php`-based equivalents.

- [ ] **Step 3: Install API scaffolding (creates `routes/api.php`, wires Sanctum)**

```bash
php artisan install:api
```

- [ ] **Step 4: Install Sail for local MySQL**

```bash
php artisan sail:install --with=mysql
```

Edit `.env` (created from `.env.example` by `composer create-project`) to match Sail's generated DB values (`DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_PORT=3306`, `DB_DATABASE=laravel`, `DB_USERNAME=sail`, `DB_PASSWORD=password`).

- [ ] **Step 5: Confirm the test database uses SQLite in-memory**

Open `phpunit.xml` and confirm (Laravel 13 ships this by default) it contains:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

If missing, add both `<env>` lines inside the `<php>` block.

- [ ] **Step 6: Verify the app boots and the default test suite passes**

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
```

Expected: all tests pass (the default `ExampleTest`/`Pest.php` smoke tests).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: scaffold Laravel 13 project with Sail, Pest, and API/Sanctum install"
```

---

## Task 2: Money Value Object

**Files:**
- Create: `app/ValueObjects/Money.php`
- Test: `tests/Unit/ValueObjects/MoneyTest.php`

**Interfaces:**
- Produces: `App\ValueObjects\Money` — immutable, with `fromCents(int): self`, `fromDecimalString(string): self`, `zero(): self`, `add(Money): self`, `subtract(Money): self`, `multiply(float): self`, `isNegative(): bool`, `equals(Money): bool`, `toCents(): int`, `formatted(): string`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\ValueObjects\Money;

test('fromCents stores the exact integer amount', function () {
    expect(Money::fromCents(1050)->toCents())->toBe(1050);
});

test('fromDecimalString converts decimal strings to cents', function () {
    expect(Money::fromDecimalString('10.50')->toCents())->toBe(1050);
    expect(Money::fromDecimalString('10')->toCents())->toBe(1000);
    expect(Money::fromDecimalString('0.01')->toCents())->toBe(1);
});

test('fromDecimalString rejects malformed input', function () {
    Money::fromDecimalString('not-a-number');
})->throws(InvalidArgumentException::class);

test('zero is 0 cents', function () {
    expect(Money::zero()->toCents())->toBe(0);
});

test('add sums two amounts', function () {
    $result = Money::fromCents(1000)->add(Money::fromCents(250));
    expect($result->toCents())->toBe(1250);
});

test('subtract can go negative', function () {
    $result = Money::fromCents(100)->subtract(Money::fromCents(300));
    expect($result->toCents())->toBe(-200);
    expect($result->isNegative())->toBeTrue();
});

test('multiply rounds to the nearest cent', function () {
    $result = Money::fromCents(333)->multiply(3);
    expect($result->toCents())->toBe(999);

    $result = Money::fromCents(10)->multiply(0.335);
    expect($result->toCents())->toBe(3);
});

test('equals compares by cent value', function () {
    expect(Money::fromCents(500)->equals(Money::fromCents(500)))->toBeTrue();
    expect(Money::fromCents(500)->equals(Money::fromCents(501)))->toBeFalse();
});

test('formatted renders a two-decimal string', function () {
    expect(Money::fromCents(1005)->formatted())->toBe('10.05');
    expect(Money::fromCents(5)->formatted())->toBe('0.05');
    expect(Money::fromCents(-1050)->formatted())->toBe('-10.50');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=MoneyTest
```

Expected: FAIL — `Class "App\ValueObjects\Money" not found`.

- [ ] **Step 3: Implement Money**

```php
<?php

namespace App\ValueObjects;

use InvalidArgumentException;

final class Money
{
    private function __construct(private readonly int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromDecimalString(string $amount): self
    {
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException("Invalid decimal amount: {$amount}");
        }

        return new self((int) round(((float) $amount) * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(Money $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->cents * $factor));
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function toCents(): int
    {
        return $this->cents;
    }

    public function formatted(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=MoneyTest
```

Expected: PASS, all 9 assertions.

- [ ] **Step 5: Commit**

```bash
git add app/ValueObjects/Money.php tests/Unit/ValueObjects/MoneyTest.php
git commit -m "feat: add immutable Money value object"
```

---

## Task 3: Products Table, Model, and MoneyCast

**Files:**
- Create: `database/migrations/xxxx_create_products_table.php`
- Create: `app/Casts/MoneyCast.php`
- Create: `app/Models/Product.php`
- Create: `database/factories/ProductFactory.php`
- Test: `tests/Unit/Casts/MoneyCastTest.php`
- Test: `tests/Feature/Models/ProductTest.php`

**Interfaces:**
- Consumes: `App\ValueObjects\Money` (Task 2).
- Produces: `App\Models\Product` with `id`, `name` (string), `sale_price_cents` (`Money`, via cast), `average_cost_cents` (`Money`, via cast), `current_stock` (int). `App\Casts\MoneyCast implements CastsAttributes`.

- [ ] **Step 1: Write the failing migration test**

```php
<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('products table has the expected columns', function () {
    expect(Schema::hasColumns('products', [
        'id', 'name', 'sale_price_cents', 'average_cost_cents', 'current_stock', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('products current_stock cannot go negative at the database level', function () {
    test()->skip(DB::getDriverName() !== 'mysql', 'CHECK constraints only enforced on MySQL, not the SQLite test driver.');

    DB::table('products')->insert([
        'name' => 'Widget',
        'sale_price_cents' => 100,
        'average_cost_cents' => 0,
        'current_stock' => -1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(\Illuminate\Database\QueryException::class);
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=ProductTest
```

Expected: FAIL — table `products` does not exist.

- [ ] **Step 3: Write the migration**

```bash
./vendor/bin/sail artisan make:migration create_products_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sale_price_cents');
            $table->integer('average_cost_cents')->default(0);
            $table->unsignedInteger('current_stock')->default(0);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_sale_price_positive CHECK (sale_price_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

- [ ] **Step 4: Write the MoneyCast**

```php
<?php

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return is_null($value) ? null : Money::fromCents((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if (is_null($value)) {
            return null;
        }

        return $value instanceof Money ? $value->toCents() : (int) $value;
    }
}
```

- [ ] **Step 5: Write the Product model and factory**

```php
<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = ['name', 'sale_price_cents', 'average_cost_cents', 'current_stock'];

    protected $casts = [
        'sale_price_cents' => MoneyCast::class,
        'average_cost_cents' => MoneyCast::class,
        'current_stock' => 'integer',
    ];
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sale_price_cents' => Money::fromDecimalString((string) fake()->randomFloat(2, 5, 200)),
            'average_cost_cents' => Money::zero(),
            'current_stock' => 0,
        ];
    }
}
```

- [ ] **Step 6: Add the cast unit test**

```php
<?php

use App\Casts\MoneyCast;
use App\Models\Product;
use App\ValueObjects\Money;

test('MoneyCast get() converts stored cents to Money', function () {
    $cast = new MoneyCast();
    $product = new Product();

    $result = $cast->get($product, 'sale_price_cents', 1050, []);

    expect($result)->toBeInstanceOf(Money::class);
    expect($result->toCents())->toBe(1050);
});

test('MoneyCast set() converts a Money instance back to cents', function () {
    $cast = new MoneyCast();
    $product = new Product();

    expect($cast->set($product, 'sale_price_cents', Money::fromCents(750), []))->toBe(750);
});

test('Product model exposes sale_price_cents as Money via the cast', function () {
    $product = Product::factory()->make(['sale_price_cents' => Money::fromDecimalString('19.99')]);

    expect($product->sale_price_cents)->toBeInstanceOf(Money::class);
    expect($product->sale_price_cents->formatted())->toBe('19.99');
});
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail artisan test --filter=ProductTest
./vendor/bin/sail artisan test --filter=MoneyCastTest
```

Expected: PASS (the MySQL-only CHECK test will report as skipped when run against SQLite, which is expected).

- [ ] **Step 8: Commit**

```bash
git add database/migrations database/factories/ProductFactory.php app/Casts/MoneyCast.php app/Models/Product.php tests/Unit/Casts/MoneyCastTest.php tests/Feature/Models/ProductTest.php
git commit -m "feat: add products table, Product model, and MoneyCast"
```

---

## Task 4: AverageCostService

**Files:**
- Create: `app/Services/AverageCostService.php`
- Test: `tests/Unit/Services/AverageCostServiceTest.php`

**Interfaces:**
- Consumes: `App\ValueObjects\Money` (Task 2).
- Produces: `App\Services\AverageCostService::recalculate(int $currentStock, Money $currentAverageCost, int $incomingQuantity, Money $incomingUnitPrice): Money`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Services\AverageCostService;
use App\ValueObjects\Money;

test('recalculate computes the weighted average when stock already exists', function () {
    $service = new AverageCostService();

    // 10 units @ 20.00 already in stock, buying 5 more @ 30.00
    // (10 * 2000 + 5 * 3000) / 15 = (20000 + 15000) / 15 = 2333.33 -> 2333 cents
    $result = $service->recalculate(
        currentStock: 10,
        currentAverageCost: Money::fromCents(2000),
        incomingQuantity: 5,
        incomingUnitPrice: Money::fromCents(3000),
    );

    expect($result->toCents())->toBe(2333);
});

test('recalculate returns the incoming unit price when there was no stock', function () {
    $service = new AverageCostService();

    $result = $service->recalculate(
        currentStock: 0,
        currentAverageCost: Money::zero(),
        incomingQuantity: 10,
        incomingUnitPrice: Money::fromCents(1500),
    );

    expect($result->toCents())->toBe(1500);
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=AverageCostServiceTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement AverageCostService**

```php
<?php

namespace App\Services;

use App\ValueObjects\Money;

class AverageCostService
{
    public function recalculate(
        int $currentStock,
        Money $currentAverageCost,
        int $incomingQuantity,
        Money $incomingUnitPrice,
    ): Money {
        $totalQuantity = $currentStock + $incomingQuantity;

        if ($totalQuantity === 0) {
            return Money::zero();
        }

        $currentTotalValue = $currentAverageCost->multiply($currentStock);
        $incomingTotalValue = $incomingUnitPrice->multiply($incomingQuantity);
        $combinedValue = $currentTotalValue->add($incomingTotalValue);

        return Money::fromCents((int) round($combinedValue->toCents() / $totalQuantity));
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=AverageCostServiceTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AverageCostService.php tests/Unit/Services/AverageCostServiceTest.php
git commit -m "feat: add AverageCostService for weighted average cost calculation"
```

---

## Task 5: ProfitCalculatorService

**Files:**
- Create: `app/Services/ProfitCalculatorService.php`
- Test: `tests/Unit/Services/ProfitCalculatorServiceTest.php`

**Interfaces:**
- Consumes: `App\ValueObjects\Money` (Task 2).
- Produces: `App\Services\ProfitCalculatorService::calculate(Money $unitPrice, Money $averageCost, int $quantity): Money`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Services\ProfitCalculatorService;
use App\ValueObjects\Money;

test('calculate returns positive profit when selling above cost', function () {
    $service = new ProfitCalculatorService();

    $result = $service->calculate(
        unitPrice: Money::fromCents(5000),
        averageCost: Money::fromCents(3000),
        quantity: 3,
    );

    expect($result->toCents())->toBe(6000);
});

test('calculate returns negative profit when selling below cost', function () {
    $service = new ProfitCalculatorService();

    $result = $service->calculate(
        unitPrice: Money::fromCents(1000),
        averageCost: Money::fromCents(1500),
        quantity: 2,
    );

    expect($result->toCents())->toBe(-1000);
    expect($result->isNegative())->toBeTrue();
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=ProfitCalculatorServiceTest
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement ProfitCalculatorService**

```php
<?php

namespace App\Services;

use App\ValueObjects\Money;

class ProfitCalculatorService
{
    public function calculate(Money $unitPrice, Money $averageCost, int $quantity): Money
    {
        return $unitPrice->subtract($averageCost)->multiply($quantity);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=ProfitCalculatorServiceTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ProfitCalculatorService.php tests/Unit/Services/ProfitCalculatorServiceTest.php
git commit -m "feat: add ProfitCalculatorService"
```

---

## Task 6: Purchases and Purchase Items

**Files:**
- Create: `database/migrations/xxxx_create_purchases_table.php`
- Create: `database/migrations/xxxx_create_purchase_items_table.php`
- Create: `app/Models/Purchase.php`
- Create: `app/Models/PurchaseItem.php`
- Create: `database/factories/PurchaseFactory.php`
- Create: `database/factories/PurchaseItemFactory.php`
- Test: `tests/Feature/Models/PurchaseTest.php`

**Interfaces:**
- Consumes: `App\Models\Product` (Task 3), `App\Casts\MoneyCast` (Task 3).
- Produces: `App\Models\Purchase` (`supplier`, `total_cents` as Money, `items` hasMany), `App\Models\PurchaseItem` (`purchase_id`, `product_id`, `quantity`, `unit_price_cents` as Money, `subtotal_cents` as Money, `product` belongsTo, `purchase` belongsTo).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('purchases and purchase_items tables have the expected columns', function () {
    expect(Schema::hasColumns('purchases', ['id', 'supplier', 'total_cents', 'created_at', 'updated_at']))->toBeTrue();
    expect(Schema::hasColumns('purchase_items', [
        'id', 'purchase_id', 'product_id', 'quantity', 'unit_price_cents', 'subtotal_cents',
    ]))->toBeTrue();
});

test('a purchase has many purchase items linked to products', function () {
    $product = Product::factory()->create();
    $purchase = Purchase::factory()->create(['supplier' => 'Acme Corp', 'total_cents' => Money::fromCents(5000)]);
    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price_cents' => Money::fromCents(500),
        'subtotal_cents' => Money::fromCents(5000),
    ]);

    expect($purchase->items)->toHaveCount(1);
    expect($purchase->items->first()->product->id)->toBe($product->id);
    expect($purchase->items->first()->unit_price_cents)->toBeInstanceOf(Money::class);
});

test('purchase_items quantity cannot be zero or negative at the database level', function () {
    test()->skip(DB::getDriverName() !== 'mysql', 'CHECK constraints only enforced on MySQL.');

    $product = Product::factory()->create();
    $purchase = Purchase::factory()->create();

    DB::table('purchase_items')->insert([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 0,
        'unit_price_cents' => 100,
        'subtotal_cents' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(\Illuminate\Database\QueryException::class);
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=PurchaseTest
```

Expected: FAIL — tables don't exist.

- [ ] **Step 3: Write the migrations**

```bash
./vendor/bin/sail artisan make:migration create_purchases_table
./vendor/bin/sail artisan make:migration create_purchase_items_table
```

`create_purchases_table`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('supplier');
            $table->integer('total_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
```

`create_purchase_items_table`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->integer('unit_price_cents');
            $table->integer('subtotal_cents');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purchase_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT chk_purchase_items_price_positive CHECK (unit_price_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
```

- [ ] **Step 4: Write the models and factories**

```php
<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected $fillable = ['supplier', 'total_cents'];

    protected $casts = [
        'total_cents' => MoneyCast::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
```

```php
<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory;

    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'unit_price_cents', 'subtotal_cents'];

    protected $casts = [
        'unit_price_cents' => MoneyCast::class,
        'subtotal_cents' => MoneyCast::class,
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'supplier' => fake()->company(),
            'total_cents' => Money::zero(),
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 20),
            'unit_price_cents' => Money::fromDecimalString((string) fake()->randomFloat(2, 5, 100)),
            'subtotal_cents' => Money::zero(),
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail artisan test --filter=PurchaseTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations database/factories/PurchaseFactory.php database/factories/PurchaseItemFactory.php app/Models/Purchase.php app/Models/PurchaseItem.php tests/Feature/Models/PurchaseTest.php
git commit -m "feat: add purchases and purchase_items tables and models"
```

---

## Task 7: Sales and Sale Items

**Files:**
- Create: `database/migrations/xxxx_create_sales_table.php`
- Create: `database/migrations/xxxx_create_sale_items_table.php`
- Create: `app/Models/Sale.php`
- Create: `app/Models/SaleItem.php`
- Create: `database/factories/SaleFactory.php`
- Create: `database/factories/SaleItemFactory.php`
- Test: `tests/Feature/Models/SaleTest.php`

**Interfaces:**
- Consumes: `App\Models\Product` (Task 3).
- Produces: `App\Models\Sale` (`customer`, `total_cents`/`profit_cents` as Money, `status` string `completed`/`cancelled`, `items` hasMany), `App\Models\SaleItem` (`sale_id`, `product_id`, `quantity`, `unit_price_cents`/`average_cost_snapshot_cents`/`subtotal_cents`/`item_profit_cents` as Money, `product`/`sale` belongsTo).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('sales and sale_items tables have the expected columns', function () {
    expect(Schema::hasColumns('sales', [
        'id', 'customer', 'total_cents', 'profit_cents', 'status', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasColumns('sale_items', [
        'id', 'sale_id', 'product_id', 'quantity', 'unit_price_cents',
        'average_cost_snapshot_cents', 'subtotal_cents', 'item_profit_cents',
    ]))->toBeTrue();
});

test('a sale defaults to completed status and has many sale items', function () {
    $product = Product::factory()->create();
    $sale = Sale::factory()->create(['customer' => 'Jane Doe']);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price_cents' => Money::fromCents(1000),
        'average_cost_snapshot_cents' => Money::fromCents(600),
        'subtotal_cents' => Money::fromCents(2000),
        'item_profit_cents' => Money::fromCents(800),
    ]);

    expect($sale->status)->toBe('completed');
    expect($sale->items)->toHaveCount(1);
    expect($sale->items->first()->item_profit_cents->toCents())->toBe(800);
});

test('sale_items quantity cannot be zero or negative at the database level', function () {
    test()->skip(DB::getDriverName() !== 'mysql', 'CHECK constraints only enforced on MySQL.');

    $product = Product::factory()->create();
    $sale = Sale::factory()->create();

    DB::table('sale_items')->insert([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => -1,
        'unit_price_cents' => 100,
        'average_cost_snapshot_cents' => 50,
        'subtotal_cents' => 0,
        'item_profit_cents' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(\Illuminate\Database\QueryException::class);
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=SaleTest
```

Expected: FAIL — tables don't exist.

- [ ] **Step 3: Write the migrations**

```bash
./vendor/bin/sail artisan make:migration create_sales_table
./vendor/bin/sail artisan make:migration create_sale_items_table
```

`create_sales_table`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('customer');
            $table->integer('total_cents');
            $table->integer('profit_cents');
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
```

`create_sale_items_table`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->integer('unit_price_cents');
            $table->integer('average_cost_snapshot_cents');
            $table->integer('subtotal_cents');
            $table->integer('item_profit_cents');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_items_price_positive CHECK (unit_price_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
```

- [ ] **Step 4: Write the models and factories**

```php
<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected $fillable = ['customer', 'total_cents', 'profit_cents', 'status'];

    protected $casts = [
        'total_cents' => MoneyCast::class,
        'profit_cents' => MoneyCast::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
```

```php
<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_id', 'product_id', 'quantity', 'unit_price_cents',
        'average_cost_snapshot_cents', 'subtotal_cents', 'item_profit_cents',
    ];

    protected $casts = [
        'unit_price_cents' => MoneyCast::class,
        'average_cost_snapshot_cents' => MoneyCast::class,
        'subtotal_cents' => MoneyCast::class,
        'item_profit_cents' => MoneyCast::class,
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Sale;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'customer' => fake()->name(),
            'total_cents' => Money::zero(),
            'profit_cents' => Money::zero(),
            'status' => 'completed',
        ];
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price_cents' => Money::fromDecimalString((string) fake()->randomFloat(2, 5, 100)),
            'average_cost_snapshot_cents' => Money::zero(),
            'subtotal_cents' => Money::zero(),
            'item_profit_cents' => Money::zero(),
        ];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail artisan test --filter=SaleTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations database/factories/SaleFactory.php database/factories/SaleItemFactory.php app/Models/Sale.php app/Models/SaleItem.php tests/Feature/Models/SaleTest.php
git commit -m "feat: add sales and sale_items tables and models"
```

---

## Task 8: Stock Movements and Idempotency Keys

**Files:**
- Create: `database/migrations/xxxx_create_stock_movements_table.php`
- Create: `database/migrations/xxxx_create_idempotency_keys_table.php`
- Create: `app/Models/StockMovement.php`
- Create: `app/Models/IdempotencyKey.php`
- Test: `tests/Feature/Models/StockMovementTest.php`

**Interfaces:**
- Consumes: `App\Models\Product` (Task 3), Laravel's default `App\Models\User`.
- Produces: `App\Models\StockMovement` (`product_id`, `user_id`, `type` enum `purchase_in`/`sale_out`/`sale_cancelled_return`, `quantity`, `reference_type`, `reference_id`), `App\Models\IdempotencyKey` (`key`, `route`, `request_hash`, `response_status`, `response_body`, `user_id`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('stock_movements and idempotency_keys tables have the expected columns', function () {
    expect(Schema::hasColumns('stock_movements', [
        'id', 'product_id', 'user_id', 'type', 'quantity', 'reference_type', 'reference_id', 'created_at',
    ]))->toBeTrue();
    expect(Schema::hasColumns('idempotency_keys', [
        'id', 'key', 'route', 'request_hash', 'response_status', 'response_body', 'user_id', 'created_at',
    ]))->toBeTrue();
});

test('a stock movement records who moved which product', function () {
    $product = Product::factory()->create();
    $user = User::factory()->create();

    $movement = StockMovement::create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'type' => 'purchase_in',
        'quantity' => 10,
        'reference_type' => 'purchase',
        'reference_id' => 1,
    ]);

    expect($movement->product->id)->toBe($product->id);
    expect($movement->user->id)->toBe($user->id);
    expect($movement->type)->toBe('purchase_in');
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=StockMovementTest
```

Expected: FAIL — tables don't exist.

- [ ] **Step 3: Write the migrations**

```bash
./vendor/bin/sail artisan make:migration create_stock_movements_table
./vendor/bin/sail artisan make:migration create_idempotency_keys_table
```

`create_stock_movements_table`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['purchase_in', 'sale_out', 'sale_cancelled_return']);
            $table->unsignedInteger('quantity');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
```

`create_idempotency_keys_table`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('route');
            $table->string('request_hash');
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
```

- [ ] **Step 4: Write the models**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'user_id', 'type', 'quantity', 'reference_type', 'reference_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'route', 'request_hash', 'response_status', 'response_body', 'user_id'];

    protected $casts = [
        'response_body' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail artisan test --filter=StockMovementTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/StockMovement.php app/Models/IdempotencyKey.php tests/Feature/Models/StockMovementTest.php
git commit -m "feat: add stock_movements and idempotency_keys tables and models"
```

---

## Task 9: Repository Interfaces and Eloquent Implementations

**Files:**
- Create: `app/Repositories/Contracts/ProductRepositoryInterface.php`
- Create: `app/Repositories/Contracts/PurchaseRepositoryInterface.php`
- Create: `app/Repositories/Contracts/SaleRepositoryInterface.php`
- Create: `app/Repositories/Eloquent/EloquentProductRepository.php`
- Create: `app/Repositories/Eloquent/EloquentPurchaseRepository.php`
- Create: `app/Repositories/Eloquent/EloquentSaleRepository.php`
- Create: `app/Providers/RepositoryServiceProvider.php`
- Modify: `bootstrap/providers.php` (register `RepositoryServiceProvider`)
- Test: `tests/Feature/Repositories/RepositoryBindingTest.php`

**Interfaces:**
- Consumes: `App\Models\Product`, `App\Models\Purchase`, `App\Models\Sale` (Tasks 3, 6, 7).
- Produces:
  - `ProductRepositoryInterface::find(int $id): Product`, `::findForUpdate(int $id): Product`, `::paginate(int $perPage = 15)`.
  - `PurchaseRepositoryInterface::create(array $attributes): Purchase`, `::paginateWithItems(int $perPage = 15)`.
  - `SaleRepositoryInterface::create(array $attributes): Sale`, `::findForUpdate(int $id): Sale`, `::paginateWithItems(int $perPage = 15)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentPurchaseRepository;
use App\Repositories\Eloquent\EloquentSaleRepository;

test('the container resolves each repository interface to its Eloquent implementation', function () {
    expect(app(ProductRepositoryInterface::class))->toBeInstanceOf(EloquentProductRepository::class);
    expect(app(PurchaseRepositoryInterface::class))->toBeInstanceOf(EloquentPurchaseRepository::class);
    expect(app(SaleRepositoryInterface::class))->toBeInstanceOf(EloquentSaleRepository::class);
});

test('EloquentProductRepository finds a product by id', function () {
    $product = Product::factory()->create();

    $found = app(ProductRepositoryInterface::class)->find($product->id);

    expect($found->id)->toBe($product->id);
});

test('EloquentProductRepository throws when a product does not exist', function () {
    app(ProductRepositoryInterface::class)->find(9999);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=RepositoryBindingTest
```

Expected: FAIL — interfaces don't exist.

- [ ] **Step 3: Write the interfaces**

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function find(int $id): Product;

    public function findForUpdate(int $id): Product;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes): Product;
}
```

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Purchase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseRepositoryInterface
{
    public function create(array $attributes): Purchase;

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator;
}
```

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SaleRepositoryInterface
{
    public function create(array $attributes): Sale;

    public function findForUpdate(int $id): Sale;

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator;
}
```

- [ ] **Step 4: Write the Eloquent implementations**

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function find(int $id): Product
    {
        return Product::findOrFail($id);
    }

    public function findForUpdate(int $id): Product
    {
        return Product::query()->lockForUpdate()->findOrFail($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()->paginate($perPage);
    }

    public function create(array $attributes): Product
    {
        return Product::create($attributes);
    }
}
```

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Purchase;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPurchaseRepository implements PurchaseRepositoryInterface
{
    public function create(array $attributes): Purchase
    {
        return Purchase::create($attributes);
    }

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator
    {
        return Purchase::query()->with('items.product')->latest()->paginate($perPage);
    }
}
```

```php
<?php

namespace App\Repositories\Eloquent;

use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentSaleRepository implements SaleRepositoryInterface
{
    public function create(array $attributes): Sale
    {
        return Sale::create($attributes);
    }

    public function findForUpdate(int $id): Sale
    {
        return Sale::query()->lockForUpdate()->findOrFail($id);
    }

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator
    {
        return Sale::query()->with('items.product')->latest()->paginate($perPage);
    }
}
```

- [ ] **Step 5: Write and register the service provider**

```php
<?php

namespace App\Providers;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentPurchaseRepository;
use App\Repositories\Eloquent\EloquentSaleRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(PurchaseRepositoryInterface::class, EloquentPurchaseRepository::class);
        $this->app->bind(SaleRepositoryInterface::class, EloquentSaleRepository::class);
    }
}
```

Add `App\Providers\RepositoryServiceProvider::class` to the array in `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
];
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=RepositoryBindingTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Repositories app/Providers/RepositoryServiceProvider.php bootstrap/providers.php tests/Feature/Repositories/RepositoryBindingTest.php
git commit -m "feat: add repository interfaces, Eloquent implementations, and DIP binding"
```

---

## Task 10: DTOs

**Files:**
- Create: `app/DataTransferObjects/PurchaseItemData.php`
- Create: `app/DataTransferObjects/RegisterPurchaseData.php`
- Create: `app/DataTransferObjects/SaleItemData.php`
- Create: `app/DataTransferObjects/RegisterSaleData.php`
- Test: `tests/Unit/DataTransferObjects/RegisterPurchaseDataTest.php`
- Test: `tests/Unit/DataTransferObjects/RegisterSaleDataTest.php`

**Interfaces:**
- Consumes: `App\ValueObjects\Money` (Task 2).
- Produces: `RegisterPurchaseData::fromValidated(array $validated): self` returning `{supplier: string, items: PurchaseItemData[]}`; `PurchaseItemData{productId: int, quantity: int, unitPrice: Money}`. `RegisterSaleData::fromValidated(array $validated): self` returning `{customer: string, items: SaleItemData[]}`; `SaleItemData{productId: int, quantity: int, unitPrice: Money}`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\DataTransferObjects\RegisterPurchaseData;
use App\ValueObjects\Money;

test('fromValidated maps the Portuguese payload into typed items', function () {
    $data = RegisterPurchaseData::fromValidated([
        'fornecedor' => 'Fornecedor X',
        'produtos' => [
            ['id' => 1, 'quantidade' => 50, 'preco_unitario' => '20.00'],
            ['id' => 2, 'quantidade' => 30, 'preco_unitario' => '10.50'],
        ],
    ]);

    expect($data->supplier)->toBe('Fornecedor X');
    expect($data->items)->toHaveCount(2);
    expect($data->items[0]->productId)->toBe(1);
    expect($data->items[0]->quantity)->toBe(50);
    expect($data->items[0]->unitPrice)->toBeInstanceOf(Money::class);
    expect($data->items[0]->unitPrice->toCents())->toBe(2000);
    expect($data->items[1]->unitPrice->toCents())->toBe(1050);
});
```

```php
<?php

use App\DataTransferObjects\RegisterSaleData;
use App\ValueObjects\Money;

test('fromValidated maps the Portuguese sale payload into typed items', function () {
    $data = RegisterSaleData::fromValidated([
        'cliente' => 'Fulano da Silva',
        'produtos' => [
            ['id' => 1, 'quantidade' => 2, 'preco_unitario' => '50.00'],
        ],
    ]);

    expect($data->customer)->toBe('Fulano da Silva');
    expect($data->items)->toHaveCount(1);
    expect($data->items[0]->unitPrice->toCents())->toBe(5000);
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=RegisterPurchaseDataTest
./vendor/bin/sail artisan test --filter=RegisterSaleDataTest
```

Expected: FAIL — classes don't exist.

- [ ] **Step 3: Implement the DTOs**

```php
<?php

namespace App\DataTransferObjects;

use App\ValueObjects\Money;

final class PurchaseItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly Money $unitPrice,
    ) {
    }
}
```

```php
<?php

namespace App\DataTransferObjects;

final class RegisterPurchaseData
{
    /** @param PurchaseItemData[] $items */
    public function __construct(
        public readonly string $supplier,
        public readonly array $items,
    ) {
    }

    public static function fromValidated(array $validated): self
    {
        return new self(
            supplier: $validated['fornecedor'],
            items: array_map(
                fn (array $item) => new PurchaseItemData(
                    productId: (int) $item['id'],
                    quantity: (int) $item['quantidade'],
                    unitPrice: \App\ValueObjects\Money::fromDecimalString((string) $item['preco_unitario']),
                ),
                $validated['produtos'],
            ),
        );
    }
}
```

```php
<?php

namespace App\DataTransferObjects;

use App\ValueObjects\Money;

final class SaleItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly Money $unitPrice,
    ) {
    }
}
```

```php
<?php

namespace App\DataTransferObjects;

final class RegisterSaleData
{
    /** @param SaleItemData[] $items */
    public function __construct(
        public readonly string $customer,
        public readonly array $items,
    ) {
    }

    public static function fromValidated(array $validated): self
    {
        return new self(
            customer: $validated['cliente'],
            items: array_map(
                fn (array $item) => new SaleItemData(
                    productId: (int) $item['id'],
                    quantity: (int) $item['quantidade'],
                    unitPrice: \App\ValueObjects\Money::fromDecimalString((string) $item['preco_unitario']),
                ),
                $validated['produtos'],
            ),
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=RegisterPurchaseDataTest
./vendor/bin/sail artisan test --filter=RegisterSaleDataTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/DataTransferObjects tests/Unit/DataTransferObjects
git commit -m "feat: add purchase/sale DTOs mapping Portuguese payloads to typed data"
```

---

## Task 11: Domain Exceptions

**Files:**
- Create: `app/Exceptions/InsufficientStockException.php`
- Create: `app/Exceptions/SaleAlreadyCancelledException.php`
- Create: `app/Exceptions/IdempotencyKeyConflictException.php`
- Modify: `bootstrap/app.php` (exception log levels)
- Test: `tests/Unit/Exceptions/DomainExceptionsTest.php`

**Interfaces:**
- Consumes: `App\Models\Product` (Task 3).
- Produces: `InsufficientStockException::forProduct(Product $product, int $requested): self`, `SaleAlreadyCancelledException::forSale(int $saleId): self`, `IdempotencyKeyConflictException::forKey(string $key): self` — all extend `Exception`, all `render(Request): JsonResponse` returning 422 with a Portuguese `message`, all implement `context(): array`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleAlreadyCancelledException;
use App\Models\Product;
use Illuminate\Http\Request;

test('InsufficientStockException renders the README-matching Portuguese message', function () {
    $product = Product::factory()->make(['name' => 'Fone Bluetooth', 'current_stock' => 3]);
    $product->id = 42;

    $exception = InsufficientStockException::forProduct($product, requested: 10);
    $response = $exception->render(Request::create('/api/vendas', 'POST'));

    expect($response->getStatusCode())->toBe(422);
    expect($response->getData(true)['message'])->toBe('Estoque insuficiente para o produto Fone Bluetooth');
    expect($exception->context())->toMatchArray([
        'product_id' => 42,
        'current_stock' => 3,
        'requested_quantity' => 10,
    ]);
});

test('SaleAlreadyCancelledException renders 422', function () {
    $exception = SaleAlreadyCancelledException::forSale(7);
    $response = $exception->render(Request::create('/api/vendas/7/cancelar', 'POST'));

    expect($response->getStatusCode())->toBe(422);
    expect($response->getData(true)['message'])->toBe('Venda já cancelada');
    expect($exception->context())->toMatchArray(['sale_id' => 7]);
});

test('IdempotencyKeyConflictException renders 422', function () {
    $exception = IdempotencyKeyConflictException::forKey('abc-123');
    $response = $exception->render(Request::create('/api/compras', 'POST'));

    expect($response->getStatusCode())->toBe(422);
    expect($response->getData(true)['message'])->toBe('Idempotency-Key já utilizada com um corpo de requisição diferente');
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=DomainExceptionsTest
```

Expected: FAIL — classes don't exist.

- [ ] **Step 3: Implement the exceptions**

```php
<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    private int $productId;
    private int $currentStock;
    private int $requestedQuantity;
    private string $productName;

    public static function forProduct(Product $product, int $requested): self
    {
        $exception = new self("Estoque insuficiente para o produto {$product->name}");
        $exception->productId = $product->id;
        $exception->productName = $product->name;
        $exception->currentStock = $product->current_stock;
        $exception->requestedQuantity = $requested;

        return $exception;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }

    public function context(): array
    {
        return [
            'product_id' => $this->productId,
            'current_stock' => $this->currentStock,
            'requested_quantity' => $this->requestedQuantity,
        ];
    }
}
```

```php
<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleAlreadyCancelledException extends Exception
{
    private int $saleId;

    public static function forSale(int $saleId): self
    {
        $exception = new self('Venda já cancelada');
        $exception->saleId = $saleId;

        return $exception;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }

    public function context(): array
    {
        return ['sale_id' => $this->saleId];
    }
}
```

```php
<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyKeyConflictException extends Exception
{
    private string $key;

    public static function forKey(string $key): self
    {
        $exception = new self('Idempotency-Key já utilizada com um corpo de requisição diferente');
        $exception->key = $key;

        return $exception;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }

    public function context(): array
    {
        return ['idempotency_key' => $this->key];
    }
}
```

- [ ] **Step 4: Wire exception log levels in `bootstrap/app.php`**

Open `bootstrap/app.php` and add to the `withExceptions` closure (create the closure if the skeleton doesn't have one yet):

```php
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleAlreadyCancelledException;
use Illuminate\Foundation\Configuration\Exceptions;
use Psr\Log\LogLevel;

// inside ->withExceptions(function (Exceptions $exceptions): void { ... })
$exceptions->level(InsufficientStockException::class, LogLevel::WARNING);
$exceptions->level(SaleAlreadyCancelledException::class, LogLevel::WARNING);
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=DomainExceptionsTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Exceptions bootstrap/app.php tests/Unit/Exceptions/DomainExceptionsTest.php
git commit -m "feat: add domain exceptions with Portuguese messages and warning-level logging"
```

---

## Task 12: Events and Stock Movement Listener

**Files:**
- Create: `app/Events/PurchaseRegistered.php`
- Create: `app/Events/SaleRegistered.php`
- Create: `app/Events/SaleCancelled.php`
- Create: `app/Listeners/RecordStockMovement.php`
- Modify: `app/Providers/AppServiceProvider.php` (register listeners, since Laravel 13 doesn't ship `EventServiceProvider` by default)
- Test: `tests/Feature/Listeners/RecordStockMovementTest.php`

**Interfaces:**
- Consumes: `App\Models\Purchase`, `App\Models\Sale`, `App\Models\StockMovement` (Tasks 6, 7, 8).
- Produces: `PurchaseRegistered(Purchase $purchase, int $userId)`, `SaleRegistered(Sale $sale, int $userId)`, `SaleCancelled(Sale $sale, int $userId)` — all plain (non-queued) events. `RecordStockMovement` listens to all three and inserts matching `StockMovement` rows.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Events\PurchaseRegistered;
use App\Events\SaleCancelled;
use App\Events\SaleRegistered;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\ValueObjects\Money;

test('PurchaseRegistered creates one purchase_in stock movement per item', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $purchase = Purchase::factory()->create();
    PurchaseItem::factory()->create(['purchase_id' => $purchase->id, 'product_id' => $product->id, 'quantity' => 15]);
    $purchase->load('items');

    event(new PurchaseRegistered($purchase, $user->id));

    $movement = StockMovement::where('reference_type', 'purchase')->where('reference_id', $purchase->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('purchase_in');
    expect($movement->quantity)->toBe(15);
    expect($movement->user_id)->toBe($user->id);
    expect($movement->product_id)->toBe($product->id);
});

test('SaleRegistered creates a sale_out stock movement', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 4]);
    $sale->load('items');

    event(new SaleRegistered($sale, $user->id));

    $movement = StockMovement::where('reference_type', 'sale')->where('reference_id', $sale->id)->first();
    expect($movement->type)->toBe('sale_out');
    expect($movement->quantity)->toBe(4);
});

test('SaleCancelled creates a sale_cancelled_return stock movement', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $sale = Sale::factory()->create(['status' => 'cancelled']);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2]);
    $sale->load('items');

    event(new SaleCancelled($sale, $user->id));

    $movement = StockMovement::where('reference_type', 'sale')->where('reference_id', $sale->id)->first();
    expect($movement->type)->toBe('sale_cancelled_return');
    expect($movement->quantity)->toBe(2);
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=RecordStockMovementTest
```

Expected: FAIL — event classes don't exist.

- [ ] **Step 3: Implement the events**

```php
<?php

namespace App\Events;

use App\Models\Purchase;

final class PurchaseRegistered
{
    public function __construct(
        public readonly Purchase $purchase,
        public readonly int $userId,
    ) {
    }
}
```

```php
<?php

namespace App\Events;

use App\Models\Sale;

final class SaleRegistered
{
    public function __construct(
        public readonly Sale $sale,
        public readonly int $userId,
    ) {
    }
}
```

```php
<?php

namespace App\Events;

use App\Models\Sale;

final class SaleCancelled
{
    public function __construct(
        public readonly Sale $sale,
        public readonly int $userId,
    ) {
    }
}
```

- [ ] **Step 4: Implement the listener**

```php
<?php

namespace App\Listeners;

use App\Events\PurchaseRegistered;
use App\Events\SaleCancelled;
use App\Events\SaleRegistered;
use App\Models\StockMovement;

class RecordStockMovement
{
    public function handlePurchaseRegistered(PurchaseRegistered $event): void
    {
        foreach ($event->purchase->items as $item) {
            StockMovement::create([
                'product_id' => $item->product_id,
                'user_id' => $event->userId,
                'type' => 'purchase_in',
                'quantity' => $item->quantity,
                'reference_type' => 'purchase',
                'reference_id' => $event->purchase->id,
            ]);
        }
    }

    public function handleSaleRegistered(SaleRegistered $event): void
    {
        foreach ($event->sale->items as $item) {
            StockMovement::create([
                'product_id' => $item->product_id,
                'user_id' => $event->userId,
                'type' => 'sale_out',
                'quantity' => $item->quantity,
                'reference_type' => 'sale',
                'reference_id' => $event->sale->id,
            ]);
        }
    }

    public function handleSaleCancelled(SaleCancelled $event): void
    {
        foreach ($event->sale->items as $item) {
            StockMovement::create([
                'product_id' => $item->product_id,
                'user_id' => $event->userId,
                'type' => 'sale_cancelled_return',
                'quantity' => $item->quantity,
                'reference_type' => 'sale',
                'reference_id' => $event->sale->id,
            ]);
        }
    }
}
```

- [ ] **Step 5: Register the listener bindings in `AppServiceProvider::boot()`**

```php
use App\Events\PurchaseRegistered;
use App\Events\SaleCancelled;
use App\Events\SaleRegistered;
use App\Listeners\RecordStockMovement;
use Illuminate\Support\Facades\Event;

// inside boot()
Event::listen(PurchaseRegistered::class, [RecordStockMovement::class, 'handlePurchaseRegistered']);
Event::listen(SaleRegistered::class, [RecordStockMovement::class, 'handleSaleRegistered']);
Event::listen(SaleCancelled::class, [RecordStockMovement::class, 'handleSaleCancelled']);
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=RecordStockMovementTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Events app/Listeners app/Providers/AppServiceProvider.php tests/Feature/Listeners/RecordStockMovementTest.php
git commit -m "feat: add purchase/sale events and RecordStockMovement listener"
```

---

## Task 13: Authentication (Sanctum)

**Files:**
- Create: `app/Actions/Auth/RegisterUserAction.php`
- Create: `app/Actions/Auth/LoginAction.php`
- Create: `app/Http/Requests/RegisterUserRequest.php`
- Create: `app/Http/Requests/LoginRequest.php`
- Create: `app/Http/Controllers/Api/AuthController.php`
- Modify: `app/Models/User.php` (add `HasApiTokens`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Http/AuthApiTest.php`

**Interfaces:**
- Produces: `POST /api/registro`, `POST /api/login`, `POST /api/logout`. `RegisterUserAction::execute(string $name, string $email, string $password): array{user: User, token: string}`. `LoginAction::execute(string $email, string $password): array{user: User, token: string}` (throws `ValidationException` on bad credentials).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\User;

test('a user can register and receives a token', function () {
    $response = $this->postJson('/api/registro', [
        'nome' => 'Ana Souza',
        'email' => 'ana@example.com',
        'senha' => 'password123',
        'senha_confirmation' => 'password123',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['usuario' => ['id', 'nome', 'email'], 'token']);
    expect(User::where('email', 'ana@example.com')->exists())->toBeTrue();
});

test('a user can login with correct credentials', function () {
    User::factory()->create(['email' => 'ana@example.com', 'password' => bcrypt('password123')]);

    $response = $this->postJson('/api/login', ['email' => 'ana@example.com', 'senha' => 'password123']);

    $response->assertOk();
    $response->assertJsonStructure(['usuario', 'token']);
});

test('login fails with wrong password', function () {
    User::factory()->create(['email' => 'ana@example.com', 'password' => bcrypt('password123')]);

    $response = $this->postJson('/api/login', ['email' => 'ana@example.com', 'senha' => 'wrong']);

    $response->assertStatus(422);
});

test('protected routes reject requests without a token', function () {
    $response = $this->getJson('/api/produtos');

    $response->assertStatus(401);
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $response = $this->postJson('/api/logout');

    $response->assertNoContent();
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=AuthApiTest
```

Expected: FAIL — routes return 404.

- [ ] **Step 3: Add `HasApiTokens` to the User model**

In `app/Models/User.php`, add the import and trait:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // ...
}
```

- [ ] **Step 4: Write the FormRequests**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:3'],
            'email' => ['required', 'email', 'unique:users,email'],
            'senha' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ];
    }
}
```

Note: Laravel's `confirmed` rule expects a `senha_confirmation` field alongside `senha` — this matches the FormRequest test payload above.

- [ ] **Step 5: Write the Actions**

```php
<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function execute(string $name, string $email, string $password): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        return ['user' => $user, 'token' => $user->createToken('api')->plainTextToken];
    }
}
```

```php
<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function execute(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Credenciais inválidas.']]);
        }

        return ['user' => $user, 'token' => $user->createToken('api')->plainTextToken];
    }
}
```

- [ ] **Step 6: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterUserRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute($request->validated('nome'), $request->validated('email'), $request->validated('senha'));

        return response()->json([
            'usuario' => ['id' => $result['user']->id, 'nome' => $result['user']->name, 'email' => $result['user']->email],
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute($request->validated('email'), $request->validated('senha'));

        return response()->json([
            'usuario' => ['id' => $result['user']->id, 'nome' => $result['user']->name, 'email' => $result['user']->email],
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
```

- [ ] **Step 7: Wire the routes**

Replace the contents of `routes/api.php` with:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/registro', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

- [ ] **Step 8: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=AuthApiTest
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Actions/Auth app/Http/Requests/RegisterUserRequest.php app/Http/Requests/LoginRequest.php app/Http/Controllers/Api/AuthController.php app/Models/User.php routes/api.php tests/Feature/Http/AuthApiTest.php
git commit -m "feat: add Sanctum-based register/login/logout endpoints"
```

---

## Task 14: Products Endpoints

**Files:**
- Create: `app/Actions/Product/CreateProductAction.php`
- Create: `app/Http/Requests/StoreProductRequest.php`
- Create: `app/Http/Resources/ProductResource.php`
- Create: `app/Http/Controllers/Api/ProductsController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Http/ProductApiTest.php`

**Interfaces:**
- Consumes: `ProductRepositoryInterface` (Task 9), `Money` (Task 2).
- Produces: `POST /api/produtos`, `GET /api/produtos` (both `auth:sanctum`). `CreateProductAction::execute(string $name, Money $salePrice): Product`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\User;

beforeEach(function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
});

test('a product can be created with the README fields', function () {
    $response = $this->postJson('/api/produtos', ['nome' => 'Fone Bluetooth', 'preco_venda' => '99.90']);

    $response->assertCreated();
    $response->assertJson([
        'nome' => 'Fone Bluetooth',
        'preco_venda' => '99.90',
        'custo_medio' => '0.00',
        'estoque' => 0,
    ]);
});

test('nome must be at least 3 characters', function () {
    $response = $this->postJson('/api/produtos', ['nome' => 'Fo', 'preco_venda' => '10.00']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('nome');
});

test('preco_venda must be positive', function () {
    $response = $this->postJson('/api/produtos', ['nome' => 'Produto Teste', 'preco_venda' => '0']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('preco_venda');
});

test('products can be listed with id, nome, custo_medio, preco_venda, estoque', function () {
    \App\Models\Product::factory()->create(['name' => 'Item A']);

    $response = $this->getJson('/api/produtos');

    $response->assertOk();
    $response->assertJsonStructure(['data' => [['id', 'nome', 'custo_medio', 'preco_venda', 'estoque']]]);
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=ProductApiTest
```

Expected: FAIL — route returns 404.

- [ ] **Step 3: Write the Action**

```php
<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\ValueObjects\Money;

class CreateProductAction
{
    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    public function execute(string $name, Money $salePrice): Product
    {
        return $this->products->create([
            'name' => $name,
            'sale_price_cents' => $salePrice,
            'average_cost_cents' => Money::zero(),
            'current_stock' => 0,
        ]);
    }
}
```

- [ ] **Step 4: Write the FormRequest and Resource**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'min:3'],
            'preco_venda' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->name,
            'custo_medio' => $this->average_cost_cents->formatted(),
            'preco_venda' => $this->sale_price_cents->formatted(),
            'estoque' => $this->current_stock,
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Product\CreateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductsController extends Controller
{
    public function index(ProductRepositoryInterface $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products->paginate());
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        $product = $action->execute(
            name: $request->validated('nome'),
            salePrice: Money::fromDecimalString((string) $request->validated('preco_venda')),
        );

        return (new ProductResource($product))->response()->setStatusCode(201);
    }
}
```

- [ ] **Step 6: Wire the routes**

Add inside the `auth:sanctum` group in `routes/api.php`:

```php
use App\Http\Controllers\Api\ProductsController;

Route::get('/produtos', [ProductsController::class, 'index']);
Route::post('/produtos', [ProductsController::class, 'store']);
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=ProductApiTest
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Product app/Http/Requests/StoreProductRequest.php app/Http/Resources/ProductResource.php app/Http/Controllers/Api/ProductsController.php routes/api.php tests/Feature/Http/ProductApiTest.php
git commit -m "feat: add product creation and listing endpoints"
```

---

## Task 15: Idempotency Middleware

**Files:**
- Create: `app/Http/Middleware/EnsureIdempotencyKey.php`
- Modify: `bootstrap/app.php` (register middleware alias `idempotent`)
- Test: `tests/Feature/Http/Middleware/EnsureIdempotencyKeyTest.php`

**Interfaces:**
- Consumes: `App\Models\IdempotencyKey` (Task 8), `App\Exceptions\IdempotencyKeyConflictException` (Task 11).
- Produces: middleware alias `idempotent`, applied to any route; requires header `Idempotency-Key`; replays a stored response on key reuse with a matching body, 422s on key reuse with a different body, 400s if the header is missing.

- [ ] **Step 1: Write the failing test**

Add a throwaway test route to exercise the middleware in isolation, defined inline in the test file via `Route::post()`.

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/_test/idempotent-echo', function (\Illuminate\Http\Request $request) {
        return response()->json(['received' => $request->input('value')], 201);
    })->middleware(['auth:sanctum', 'idempotent']);

    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
});

test('a request without Idempotency-Key is rejected', function () {
    $response = $this->postJson('/_test/idempotent-echo', ['value' => 'a']);

    $response->assertStatus(400);
});

test('replaying the same key and body returns the original response without re-running the route', function () {
    $first = $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'key-1']);
    $first->assertCreated();

    $second = $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'key-1']);

    $second->assertCreated();
    $second->assertJson(['received' => 'a']);
    expect(\App\Models\IdempotencyKey::where('key', 'key-1')->count())->toBe(1);
});

test('reusing the same key with a different body is rejected', function () {
    $this->postJson('/_test/idempotent-echo', ['value' => 'a'], ['Idempotency-Key' => 'key-2'])->assertCreated();

    $response = $this->postJson('/_test/idempotent-echo', ['value' => 'b'], ['Idempotency-Key' => 'key-2']);

    $response->assertStatus(422);
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=EnsureIdempotencyKeyTest
```

Expected: FAIL — middleware alias `idempotent` doesn't exist.

- [ ] **Step 3: Implement the middleware**

```php
<?php

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json(['message' => 'Cabeçalho Idempotency-Key é obrigatório'], 400);
        }

        $requestHash = hash('sha256', $request->getContent());
        $existing = IdempotencyKey::where('key', $key)->first();

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                throw IdempotencyKeyConflictException::forKey($key);
            }

            return response()->json($existing->response_body, $existing->response_status);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            IdempotencyKey::create([
                'key' => $key,
                'route' => $request->path(),
                'request_hash' => $requestHash,
                'response_status' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true),
                'user_id' => $request->user()->id,
            ]);
        }

        return $response;
    }
}
```

- [ ] **Step 4: Register the middleware alias**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware): void { ... })`:

```php
use App\Http\Middleware\EnsureIdempotencyKey;

$middleware->alias(['idempotent' => EnsureIdempotencyKey::class]);
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=EnsureIdempotencyKeyTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/EnsureIdempotencyKey.php bootstrap/app.php tests/Feature/Http/Middleware/EnsureIdempotencyKeyTest.php
git commit -m "feat: add idempotency-key middleware for safe POST retries"
```

---

## Task 16: Purchase Endpoints

**Files:**
- Create: `app/Actions/Purchase/RegisterPurchaseAction.php`
- Create: `app/Http/Requests/StorePurchaseRequest.php`
- Create: `app/Http/Resources/PurchaseResource.php`
- Create: `app/Http/Controllers/Api/PurchasesController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Http/PurchaseApiTest.php`

**Interfaces:**
- Consumes: `ProductRepositoryInterface`, `PurchaseRepositoryInterface` (Task 9), `AverageCostService` (Task 4), `RegisterPurchaseData`/`PurchaseItemData` (Task 10), `PurchaseRegistered` (Task 12), `EnsureIdempotencyKey` (Task 15).
- Produces: `POST /api/compras`, `GET /api/compras`. `RegisterPurchaseAction::execute(RegisterPurchaseData $data, int $userId): Purchase`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
});

test('registering a purchase increases stock and updates the average cost', function () {
    $productA = Product::factory()->create(['current_stock' => 0, 'average_cost_cents' => 0]);
    $productB = Product::factory()->create(['current_stock' => 0, 'average_cost_cents' => 0]);

    $response = $this->postJson('/api/compras', [
        'fornecedor' => 'Fornecedor X',
        'produtos' => [
            ['id' => $productA->id, 'quantidade' => 50, 'preco_unitario' => 20],
            ['id' => $productB->id, 'quantidade' => 30, 'preco_unitario' => 10],
        ],
    ], ['Idempotency-Key' => 'purchase-1']);

    $response->assertCreated();
    $response->assertJsonPath('fornecedor', 'Fornecedor X');
    $response->assertJsonPath('total', '1300.00');

    $productA->refresh();
    expect($productA->current_stock)->toBe(50);
    expect($productA->average_cost_cents->formatted())->toBe('20.00');
});

test('a second purchase recalculates the weighted average cost', function () {
    $product = Product::factory()->create(['current_stock' => 10, 'average_cost_cents' => \App\ValueObjects\Money::fromCents(2000)]);

    $this->postJson('/api/compras', [
        'fornecedor' => 'Fornecedor Y',
        'produtos' => [['id' => $product->id, 'quantidade' => 5, 'preco_unitario' => 30]],
    ], ['Idempotency-Key' => 'purchase-2'])->assertCreated();

    $product->refresh();
    expect($product->current_stock)->toBe(15);
    expect($product->average_cost_cents->formatted())->toBe('23.33');
});

test('duplicate product ids in the same purchase payload are rejected', function () {
    $product = Product::factory()->create();

    $response = $this->postJson('/api/compras', [
        'fornecedor' => 'Fornecedor Z',
        'produtos' => [
            ['id' => $product->id, 'quantidade' => 1, 'preco_unitario' => 10],
            ['id' => $product->id, 'quantidade' => 2, 'preco_unitario' => 10],
        ],
    ], ['Idempotency-Key' => 'purchase-3']);

    $response->assertStatus(422);
});

test('purchases can be listed with items', function () {
    $product = Product::factory()->create();
    $this->postJson('/api/compras', [
        'fornecedor' => 'Fornecedor W',
        'produtos' => [['id' => $product->id, 'quantidade' => 1, 'preco_unitario' => 10]],
    ], ['Idempotency-Key' => 'purchase-4']);

    $response = $this->getJson('/api/compras');

    $response->assertOk();
    $response->assertJsonStructure(['data' => [['id', 'fornecedor', 'total', 'produtos']]]);
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=PurchaseApiTest
```

Expected: FAIL — route returns 404.

- [ ] **Step 3: Write the Action**

```php
<?php

namespace App\Actions\Purchase;

use App\DataTransferObjects\RegisterPurchaseData;
use App\Events\PurchaseRegistered;
use App\Models\Purchase;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Services\AverageCostService;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class RegisterPurchaseAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly PurchaseRepositoryInterface $purchases,
        private readonly AverageCostService $averageCostService,
    ) {
    }

    public function execute(RegisterPurchaseData $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $purchase = $this->purchases->create(['supplier' => $data->supplier, 'total_cents' => Money::zero()]);
            $total = Money::zero();

            foreach ($data->items as $item) {
                $product = $this->products->findForUpdate($item->productId);

                $newAverageCost = $this->averageCostService->recalculate(
                    currentStock: $product->current_stock,
                    currentAverageCost: $product->average_cost_cents,
                    incomingQuantity: $item->quantity,
                    incomingUnitPrice: $item->unitPrice,
                );

                $product->current_stock += $item->quantity;
                $product->average_cost_cents = $newAverageCost;
                $product->save();

                $subtotal = $item->unitPrice->multiply($item->quantity);
                $purchase->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unitPrice,
                    'subtotal_cents' => $subtotal,
                ]);

                $total = $total->add($subtotal);
            }

            $purchase->update(['total_cents' => $total]);
            $purchase->load('items.product');

            event(new PurchaseRegistered($purchase, $userId));

            return $purchase;
        }, attempts: 3);
    }
}
```

- [ ] **Step 4: Write the FormRequest and Resource**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fornecedor' => ['required', 'string'],
            'produtos' => ['required', 'array', 'min:1'],
            'produtos.*.id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'produtos.*.quantidade' => ['required', 'integer', 'min:1'],
            'produtos.*.preco_unitario' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fornecedor' => $this->supplier,
            'total' => $this->total_cents->formatted(),
            'produtos' => $this->items->map(fn ($item) => [
                'id' => $item->product_id,
                'nome' => $item->product->name,
                'quantidade' => $item->quantity,
                'preco_unitario' => $item->unit_price_cents->formatted(),
                'subtotal' => $item->subtotal_cents->formatted(),
            ]),
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Purchase\RegisterPurchaseAction;
use App\DataTransferObjects\RegisterPurchaseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchasesController extends Controller
{
    public function index(PurchaseRepositoryInterface $purchases): AnonymousResourceCollection
    {
        return PurchaseResource::collection($purchases->paginateWithItems());
    }

    public function store(StorePurchaseRequest $request, Request $httpRequest, RegisterPurchaseAction $action): JsonResponse
    {
        $purchase = $action->execute(
            RegisterPurchaseData::fromValidated($request->validated()),
            $httpRequest->user()->id,
        );

        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }
}
```

- [ ] **Step 6: Wire the routes with idempotency and rate limiting**

Add inside the `auth:sanctum` group in `routes/api.php`:

```php
use App\Http\Controllers\Api\PurchasesController;

Route::get('/compras', [PurchasesController::class, 'index']);
Route::post('/compras', [PurchasesController::class, 'store'])->middleware(['idempotent', 'throttle:financial']);
```

(the `financial` rate limiter is defined in Task 18 — until then, `throttle:financial` falls back to Laravel's default `60,1` limiter, which is harmless for these tests.)

- [ ] **Step 7: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=PurchaseApiTest
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Purchase app/Http/Requests/StorePurchaseRequest.php app/Http/Resources/PurchaseResource.php app/Http/Controllers/Api/PurchasesController.php routes/api.php tests/Feature/Http/PurchaseApiTest.php
git commit -m "feat: add purchase registration and listing endpoints"
```

---

## Task 17: Sale Endpoints and Cancellation

**Files:**
- Create: `app/Actions/Sale/RegisterSaleAction.php`
- Create: `app/Actions/Sale/CancelSaleAction.php`
- Create: `app/Http/Requests/StoreSaleRequest.php`
- Create: `app/Http/Resources/SaleResource.php`
- Create: `app/Http/Controllers/Api/SalesController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Http/SaleApiTest.php`

**Interfaces:**
- Consumes: `ProductRepositoryInterface`, `SaleRepositoryInterface` (Task 9), `ProfitCalculatorService` (Task 5), `RegisterSaleData`/`SaleItemData` (Task 10), `InsufficientStockException`, `SaleAlreadyCancelledException` (Task 11), `SaleRegistered`/`SaleCancelled` (Task 12).
- Produces: `POST /api/vendas`, `GET /api/vendas`, `POST /api/vendas/{id}/cancelar`. `RegisterSaleAction::execute(RegisterSaleData $data, int $userId): Sale`. `CancelSaleAction::execute(int $saleId, int $userId): Sale`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\Product;
use App\Models\User;
use App\ValueObjects\Money;

beforeEach(function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
});

test('registering a sale decreases stock and returns total and profit', function () {
    $product = Product::factory()->create([
        'current_stock' => 10,
        'average_cost_cents' => Money::fromCents(3000),
    ]);

    $response = $this->postJson('/api/vendas', [
        'cliente' => 'Fulano da Silva',
        'produtos' => [['id' => $product->id, 'quantidade' => 2, 'preco_unitario' => 50]],
    ], ['Idempotency-Key' => 'sale-1']);

    $response->assertCreated();
    $response->assertJsonPath('total', '100.00');
    $response->assertJsonPath('lucro', '40.00');

    $product->refresh();
    expect($product->current_stock)->toBe(8);
});

test('selling more than the available stock returns the README-matching 422 message', function () {
    $product = Product::factory()->create(['name' => 'Fone Bluetooth', 'current_stock' => 1]);

    $response = $this->postJson('/api/vendas', [
        'cliente' => 'Cliente Teste',
        'produtos' => [['id' => $product->id, 'quantidade' => 5, 'preco_unitario' => 50]],
    ], ['Idempotency-Key' => 'sale-2']);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Estoque insuficiente para o produto Fone Bluetooth');

    $product->refresh();
    expect($product->current_stock)->toBe(1);
});

test('cancelling a sale reverses stock without touching average cost', function () {
    $product = Product::factory()->create(['current_stock' => 10, 'average_cost_cents' => Money::fromCents(3000)]);
    $sale = $this->postJson('/api/vendas', [
        'cliente' => 'Cliente Cancela',
        'produtos' => [['id' => $product->id, 'quantidade' => 3, 'preco_unitario' => 50]],
    ], ['Idempotency-Key' => 'sale-3'])->json();

    $product->refresh();
    expect($product->current_stock)->toBe(7);
    $averageCostBefore = $product->average_cost_cents->formatted();

    $response = $this->postJson("/api/vendas/{$sale['id']}/cancelar");

    $response->assertOk();
    $product->refresh();
    expect($product->current_stock)->toBe(10);
    expect($product->average_cost_cents->formatted())->toBe($averageCostBefore);
});

test('cancelling an already-cancelled sale returns 422', function () {
    $product = Product::factory()->create(['current_stock' => 10]);
    $sale = $this->postJson('/api/vendas', [
        'cliente' => 'Cliente Dupla Cancela',
        'produtos' => [['id' => $product->id, 'quantidade' => 1, 'preco_unitario' => 20]],
    ], ['Idempotency-Key' => 'sale-4'])->json();

    $this->postJson("/api/vendas/{$sale['id']}/cancelar")->assertOk();
    $response = $this->postJson("/api/vendas/{$sale['id']}/cancelar");

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Venda já cancelada');
});

test('sales can be listed with items', function () {
    $product = Product::factory()->create(['current_stock' => 10]);
    $this->postJson('/api/vendas', [
        'cliente' => 'Cliente Lista',
        'produtos' => [['id' => $product->id, 'quantidade' => 1, 'preco_unitario' => 20]],
    ], ['Idempotency-Key' => 'sale-5']);

    $response = $this->getJson('/api/vendas');

    $response->assertOk();
    $response->assertJsonStructure(['data' => [['id', 'cliente', 'total', 'lucro', 'produtos']]]);
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=SaleApiTest
```

Expected: FAIL — route returns 404.

- [ ] **Step 3: Write the Actions**

```php
<?php

namespace App\Actions\Sale;

use App\DataTransferObjects\RegisterSaleData;
use App\Events\SaleRegistered;
use App\Exceptions\InsufficientStockException;
use App\Models\Sale;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Services\ProfitCalculatorService;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class RegisterSaleAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly SaleRepositoryInterface $sales,
        private readonly ProfitCalculatorService $profitCalculator,
    ) {
    }

    public function execute(RegisterSaleData $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $sale = $this->sales->create([
                'customer' => $data->customer,
                'total_cents' => Money::zero(),
                'profit_cents' => Money::zero(),
                'status' => 'completed',
            ]);

            $total = Money::zero();
            $profit = Money::zero();

            foreach ($data->items as $item) {
                $product = $this->products->findForUpdate($item->productId);

                if ($item->quantity > $product->current_stock) {
                    throw InsufficientStockException::forProduct($product, $item->quantity);
                }

                $itemProfit = $this->profitCalculator->calculate(
                    unitPrice: $item->unitPrice,
                    averageCost: $product->average_cost_cents,
                    quantity: $item->quantity,
                );

                $product->current_stock -= $item->quantity;
                $product->save();

                $subtotal = $item->unitPrice->multiply($item->quantity);
                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unitPrice,
                    'average_cost_snapshot_cents' => $product->average_cost_cents,
                    'subtotal_cents' => $subtotal,
                    'item_profit_cents' => $itemProfit,
                ]);

                $total = $total->add($subtotal);
                $profit = $profit->add($itemProfit);
            }

            $sale->update(['total_cents' => $total, 'profit_cents' => $profit]);
            $sale->load('items.product');

            event(new SaleRegistered($sale, $userId));

            return $sale;
        }, attempts: 3);
    }
}
```

```php
<?php

namespace App\Actions\Sale;

use App\Events\SaleCancelled;
use App\Exceptions\SaleAlreadyCancelledException;
use App\Models\Sale;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CancelSaleAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly SaleRepositoryInterface $sales,
    ) {
    }

    public function execute(int $saleId, int $userId): Sale
    {
        return DB::transaction(function () use ($saleId, $userId) {
            $sale = $this->sales->findForUpdate($saleId);

            if ($sale->status === 'cancelled') {
                throw SaleAlreadyCancelledException::forSale($saleId);
            }

            $sale->load('items');

            foreach ($sale->items as $item) {
                $product = $this->products->findForUpdate($item->product_id);
                $product->current_stock += $item->quantity;
                $product->save();
            }

            $sale->update(['status' => 'cancelled']);
            $sale->load('items.product');

            event(new SaleCancelled($sale, $userId));

            return $sale;
        }, attempts: 3);
    }
}
```

- [ ] **Step 4: Write the FormRequest and Resource**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente' => ['required', 'string'],
            'produtos' => ['required', 'array', 'min:1'],
            'produtos.*.id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'produtos.*.quantidade' => ['required', 'integer', 'min:1'],
            'produtos.*.preco_unitario' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->customer,
            'total' => $this->total_cents->formatted(),
            'lucro' => $this->profit_cents->formatted(),
            'status' => $this->status,
            'produtos' => $this->items->map(fn ($item) => [
                'id' => $item->product_id,
                'nome' => $item->product->name,
                'quantidade' => $item->quantity,
                'preco_unitario' => $item->unit_price_cents->formatted(),
                'subtotal' => $item->subtotal_cents->formatted(),
            ]),
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sale\CancelSaleAction;
use App\Actions\Sale\RegisterSaleAction;
use App\DataTransferObjects\RegisterSaleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesController extends Controller
{
    public function index(SaleRepositoryInterface $sales): AnonymousResourceCollection
    {
        return SaleResource::collection($sales->paginateWithItems());
    }

    public function store(StoreSaleRequest $request, Request $httpRequest, RegisterSaleAction $action): JsonResponse
    {
        $sale = $action->execute(
            RegisterSaleData::fromValidated($request->validated()),
            $httpRequest->user()->id,
        );

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }

    public function cancel(int $id, Request $request, CancelSaleAction $action): SaleResource
    {
        return new SaleResource($action->execute($id, $request->user()->id));
    }
}
```

- [ ] **Step 6: Wire the routes**

Add inside the `auth:sanctum` group in `routes/api.php`:

```php
use App\Http\Controllers\Api\SalesController;

Route::get('/vendas', [SalesController::class, 'index']);
Route::post('/vendas', [SalesController::class, 'store'])->middleware(['idempotent', 'throttle:financial']);
Route::post('/vendas/{id}/cancelar', [SalesController::class, 'cancel'])->middleware('throttle:financial');
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=SaleApiTest
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Actions/Sale app/Http/Requests/StoreSaleRequest.php app/Http/Resources/SaleResource.php app/Http/Controllers/Api/SalesController.php routes/api.php tests/Feature/Http/SaleApiTest.php
git commit -m "feat: add sale registration, listing, and cancellation endpoints"
```

---

## Task 18: Request Correlation and Rate Limiting

**Files:**
- Create: `app/Http/Middleware/AssignRequestId.php`
- Modify: `bootstrap/app.php` (register middleware globally, alias not needed since it's global)
- Modify: `app/Providers/AppServiceProvider.php` (define `financial` rate limiter)
- Test: `tests/Feature/Http/Middleware/AssignRequestIdTest.php`
- Test: `tests/Feature/Http/RateLimitTest.php`

**Interfaces:**
- Produces: every response carries an `X-Request-Id` header; `purchases`/`sales` write routes return `429` after 30 requests/minute per authenticated user.

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\User;

test('every response carries an X-Request-Id header', function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/produtos');

    $response->assertHeader('X-Request-Id');
});

test('an incoming X-Request-Id header is echoed back unchanged', function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/produtos', ['X-Request-Id' => 'fixed-id-123']);

    $response->assertHeader('X-Request-Id', 'fixed-id-123');
});
```

```php
<?php

use App\Models\Product;
use App\Models\User;

test('exceeding the financial rate limit on sales returns 429', function () {
    \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create());
    $product = Product::factory()->create(['current_stock' => 1000]);

    for ($i = 0; $i < 30; $i++) {
        $this->postJson('/api/vendas', [
            'cliente' => 'Cliente Loop',
            'produtos' => [['id' => $product->id, 'quantidade' => 1, 'preco_unitario' => 10]],
        ], ['Idempotency-Key' => "rate-{$i}"]);
    }

    $response = $this->postJson('/api/vendas', [
        'cliente' => 'Cliente Estouro',
        'produtos' => [['id' => $product->id, 'quantidade' => 1, 'preco_unitario' => 10]],
    ], ['Idempotency-Key' => 'rate-overflow']);

    $response->assertStatus(429);
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
./vendor/bin/sail artisan test --filter=AssignRequestIdTest
./vendor/bin/sail artisan test --filter=RateLimitTest
```

Expected: FAIL — no `X-Request-Id` header yet; rate limit test fails because `throttle:financial` currently falls back to the default 60/min limiter (30 requests won't trip it).

- [ ] **Step 3: Implement the middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        Log::shareContext([
            'request_id' => $requestId,
            'user_id' => optional($request->user())->id,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
```

- [ ] **Step 4: Register it as a global middleware**

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware): void { ... })`:

```php
use App\Http\Middleware\AssignRequestId;

$middleware->append(AssignRequestId::class);
```

- [ ] **Step 5: Define the `financial` rate limiter**

In `app/Providers/AppServiceProvider.php`, inside `boot()`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('financial', function ($request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=AssignRequestIdTest
./vendor/bin/sail artisan test --filter=RateLimitTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/AssignRequestId.php bootstrap/app.php app/Providers/AppServiceProvider.php tests/Feature/Http/Middleware/AssignRequestIdTest.php tests/Feature/Http/RateLimitTest.php
git commit -m "feat: add request correlation id middleware and financial rate limiter"
```

---

## Task 19: Database Seeder

**Files:**
- Create: `database/seeders/DatabaseSeeder.php` (overwrite the default)
- Test: `tests/Feature/DatabaseSeederTest.php`

**Interfaces:**
- Consumes: `Product`, `Purchase`, `Sale`, `User` factories (Tasks 3, 6, 7, and Laravel's default `UserFactory`).
- Produces: a seeded database with one demo user, a handful of products, one purchase, and one sale — reachable via `php artisan migrate --seed`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the database seeder populates demo data', function () {
    $this->seed();

    expect(User::where('email', 'demo@fone-ninja.test')->exists())->toBeTrue();
    expect(Product::count())->toBeGreaterThanOrEqual(3);
    expect(Purchase::count())->toBeGreaterThanOrEqual(1);
    expect(Sale::count())->toBeGreaterThanOrEqual(1);
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=DatabaseSeederTest
```

Expected: FAIL — no demo user, no purchases/sales.

- [ ] **Step 3: Implement the seeder**

```php
<?php

namespace Database\Seeders;

use App\Actions\Purchase\RegisterPurchaseAction;
use App\Actions\Sale\RegisterSaleAction;
use App\DataTransferObjects\RegisterPurchaseData;
use App\DataTransferObjects\RegisterSaleData;
use App\Models\Product;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@fone-ninja.test'],
            ['name' => 'Usuário Demo', 'password' => bcrypt('password123')],
        );

        $headphones = Product::create(['name' => 'Fone Bluetooth', 'sale_price_cents' => Money::fromDecimalString('99.90'), 'average_cost_cents' => Money::zero(), 'current_stock' => 0]);
        $charger = Product::create(['name' => 'Carregador USB-C', 'sale_price_cents' => Money::fromDecimalString('49.90'), 'average_cost_cents' => Money::zero(), 'current_stock' => 0]);
        $cable = Product::create(['name' => 'Cabo USB-C 1m', 'sale_price_cents' => Money::fromDecimalString('19.90'), 'average_cost_cents' => Money::zero(), 'current_stock' => 0]);

        app(RegisterPurchaseAction::class)->execute(
            RegisterPurchaseData::fromValidated([
                'fornecedor' => 'Fornecedor Demo',
                'produtos' => [
                    ['id' => $headphones->id, 'quantidade' => 50, 'preco_unitario' => '60.00'],
                    ['id' => $charger->id, 'quantidade' => 100, 'preco_unitario' => '25.00'],
                    ['id' => $cable->id, 'quantidade' => 200, 'preco_unitario' => '8.00'],
                ],
            ]),
            $user->id,
        );

        app(RegisterSaleAction::class)->execute(
            RegisterSaleData::fromValidated([
                'cliente' => 'Cliente Demo',
                'produtos' => [
                    ['id' => $headphones->id, 'quantidade' => 5, 'preco_unitario' => '99.90'],
                    ['id' => $cable->id, 'quantidade' => 10, 'preco_unitario' => '19.90'],
                ],
            ]),
            $user->id,
        );
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=DatabaseSeederTest
```

Expected: PASS.

- [ ] **Step 5: Seed the real dev database and commit**

```bash
./vendor/bin/sail artisan migrate:fresh --seed
git add database/seeders/DatabaseSeeder.php tests/Feature/DatabaseSeederTest.php
git commit -m "feat: add DatabaseSeeder with demo user, products, purchase, and sale"
```

---

## Task 20: API Documentation (Swagger / OpenAPI)

**Files:**
- Modify: `composer.json` (add `darkaonline/l5-swagger`)
- Create: `config/l5-swagger.php` (published by the package)
- Create: `app/Http/Controllers/Api/OpenApiController.php` (holds the root `#[OA\Info]` annotation — l5-swagger needs at least one place to anchor the document metadata)
- Modify: `app/Http/Controllers/Api/ProductsController.php`, `PurchasesController.php`, `SalesController.php`, `AuthController.php` (add `#[OA\...]` attributes per action)
- Test: `tests/Feature/Http/SwaggerDocumentationTest.php`

**Interfaces:**
- Produces: `GET /api/documentation` serving a rendered Swagger UI backed by a generated `storage/api-docs/api-docs.json`.

- [ ] **Step 1: Write the failing test**

```php
<?php

test('the generated OpenAPI json describes the produtos endpoints', function () {
    \Illuminate\Support\Facades\Artisan::call('l5-swagger:generate');

    $json = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);

    expect($json['paths'])->toHaveKey('/api/produtos');
    expect($json['paths']['/api/produtos'])->toHaveKeys(['get', 'post']);
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
./vendor/bin/sail artisan test --filter=SwaggerDocumentationTest
```

Expected: FAIL — `l5-swagger:generate` command doesn't exist.

- [ ] **Step 3: Install and publish l5-swagger**

```bash
./vendor/bin/sail composer require darkaonline/l5-swagger
./vendor/bin/sail artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

- [ ] **Step 4: Add the root API info annotation**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'Fone Ninja ERP API', description: 'API de estoque: produtos, compras e vendas.')]
#[OA\Server(url: '/api', description: 'API server')]
class OpenApiController extends Controller
{
}
```

- [ ] **Step 5: Annotate `ProductsController`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Actions\Product\CreateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ProductsController extends Controller
{
    #[OA\Get(
        path: '/produtos',
        summary: 'Lista produtos com custo médio, preço e estoque atual',
        tags: ['Produtos'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de produtos')],
    )]
    public function index(ProductRepositoryInterface $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products->paginate());
    }

    #[OA\Post(
        path: '/produtos',
        summary: 'Cadastra um novo produto',
        tags: ['Produtos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nome', 'preco_venda'],
                properties: [
                    new OA\Property(property: 'nome', type: 'string', example: 'Fone Bluetooth'),
                    new OA\Property(property: 'preco_venda', type: 'number', format: 'float', example: 99.90),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Produto criado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ],
    )]
    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        $product = $action->execute(
            name: $request->validated('nome'),
            salePrice: Money::fromDecimalString((string) $request->validated('preco_venda')),
        );

        return (new ProductResource($product))->response()->setStatusCode(201);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
./vendor/bin/sail artisan test --filter=SwaggerDocumentationTest
```

Expected: PASS.

- [ ] **Step 7: Verify the docs UI serves locally**

```bash
./vendor/bin/sail artisan l5-swagger:generate
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/api/documentation
```

Expected: `200`.

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock config/l5-swagger.php app/Http/Controllers/Api/OpenApiController.php app/Http/Controllers/Api/ProductsController.php tests/Feature/Http/SwaggerDocumentationTest.php
git commit -m "feat: add Swagger/OpenAPI documentation via l5-swagger"
```

---

## Self-Review Notes

- **Spec coverage**: every README requirement maps to a task — product create/list (Task 14), purchase register/list (Tasks 16, 9), sale register/list/cancel (Tasks 17, 9), average cost (Task 4), profit + insufficient stock (Tasks 5, 17), Docker/frontend explicitly out of scope per the spec. Fintech additions each have a task: Money (2), idempotency (15), append-only (enforced via `restrictOnDelete` + no delete routes across Tasks 6-8, 16-17), CHECK constraints (Tasks 3, 6, 7), rate limiting + request correlation (18), Swagger (20), seeder (19).
- **Type consistency checked**: `Money` methods used identically across Tasks 2, 3, 4, 5, 10, 14, 16, 17, 19 (`fromCents`, `fromDecimalString`, `zero`, `add`, `subtract`, `multiply`, `toCents`, `formatted`, `isNegative`). `AverageCostService::recalculate` and `ProfitCalculatorService::calculate` signatures match their Task 4/5 definitions everywhere they're called (Tasks 16, 17). Repository method names (`find`, `findForUpdate`, `paginate`, `create`, `paginateWithItems`) match between Task 9's interfaces and every consumer (Tasks 14, 16, 17, 19).
- **No placeholders**: every step has runnable code or an exact command; no task defers logic to "add validation" or "handle errors" prose.
