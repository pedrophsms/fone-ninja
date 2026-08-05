<?php

namespace Database\Factories;

use App\Models\Product;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sale_price_cents' => Money::fromDecimalString((string) fake()->randomFloat(2, 5, 200)),
            'average_cost_cents' => Money::zero(),
            'current_stock' => 0,
        ];
    }
}
