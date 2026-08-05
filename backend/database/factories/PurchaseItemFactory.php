<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 20),
            'unit_price_cents' => Money::fromDecimalString((string) fake()->randomFloat(2, 5, 100)),
            'subtotal_cents' => Money::zero(),
        ];
    }
}
