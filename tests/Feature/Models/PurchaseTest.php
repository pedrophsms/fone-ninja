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
})->throws(\Illuminate\Database\QueryException::class)
  ->skip(fn () => DB::getDriverName() !== 'mysql', 'CHECK constraints only enforced on MySQL.');
