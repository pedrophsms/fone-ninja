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
