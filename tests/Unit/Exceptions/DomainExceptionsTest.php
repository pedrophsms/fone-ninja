<?php

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleAlreadyCancelledException;
use App\Models\Product;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

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
