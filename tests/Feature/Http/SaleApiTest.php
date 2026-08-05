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
