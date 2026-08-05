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
