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
})->throws(\Illuminate\Database\QueryException::class)
  ->skip(fn () => DB::getDriverName() !== 'mysql', 'CHECK constraints only enforced on MySQL.');
