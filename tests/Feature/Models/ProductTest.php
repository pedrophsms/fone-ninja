<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('products table has the expected columns', function () {
    expect(Schema::hasColumns('products', [
        'id', 'name', 'sale_price_cents', 'average_cost_cents', 'current_stock', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('products current_stock cannot go negative at the database level', function () {
    test()->skip(DB::getDriverName() !== 'mysql', 'CHECK constraints only enforced on MySQL, not the SQLite test driver.');

    DB::table('products')->insert([
        'name' => 'Widget',
        'sale_price_cents' => 100,
        'average_cost_cents' => 0,
        'current_stock' => -1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(\Illuminate\Database\QueryException::class);
