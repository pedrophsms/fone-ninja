<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price_cents' => Money::fromDecimalString((string) fake()->randomFloat(2, 5, 100)),
            'average_cost_snapshot_cents' => Money::zero(),
            'subtotal_cents' => Money::zero(),
            'item_profit_cents' => Money::zero(),
        ];
    }
}
