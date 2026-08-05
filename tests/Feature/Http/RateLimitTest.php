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
