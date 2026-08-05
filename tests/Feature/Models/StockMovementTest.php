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
