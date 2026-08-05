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
