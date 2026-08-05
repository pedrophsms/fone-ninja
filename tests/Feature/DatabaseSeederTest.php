<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the database seeder populates demo data', function () {
    $this->seed();

    expect(User::where('email', 'demo@fone-ninja.test')->exists())->toBeTrue();
    expect(Product::count())->toBeGreaterThanOrEqual(3);
    expect(Purchase::count())->toBeGreaterThanOrEqual(1);
    expect(Sale::count())->toBeGreaterThanOrEqual(1);
});
