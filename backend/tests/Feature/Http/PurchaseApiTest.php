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
    $response->assertJsonPath('data.fornecedor', 'Fornecedor X');
    $response->assertJsonPath('data.total', '1300.00');

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

test('preco_unitario with more than 2 decimal places is rejected with 422, not 500', function () {
    $product = Product::factory()->create();

    $response = $this->postJson('/api/compras', [
        'fornecedor' => 'Fornecedor Decimal',
        'produtos' => [['id' => $product->id, 'quantidade' => 1, 'preco_unitario' => '10.999']],
    ], ['Idempotency-Key' => 'purchase-decimal']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('produtos.0.preco_unitario');
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
