<?php

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentPurchaseRepository;
use App\Repositories\Eloquent\EloquentSaleRepository;

test('the container resolves each repository interface to its Eloquent implementation', function () {
    expect(app(ProductRepositoryInterface::class))->toBeInstanceOf(EloquentProductRepository::class);
    expect(app(PurchaseRepositoryInterface::class))->toBeInstanceOf(EloquentPurchaseRepository::class);
    expect(app(SaleRepositoryInterface::class))->toBeInstanceOf(EloquentSaleRepository::class);
});

test('EloquentProductRepository finds a product by id', function () {
    $product = Product::factory()->create();

    $found = app(ProductRepositoryInterface::class)->find($product->id);

    expect($found->id)->toBe($product->id);
});

test('EloquentProductRepository throws when a product does not exist', function () {
    app(ProductRepositoryInterface::class)->find(9999);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
