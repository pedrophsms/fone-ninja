<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'supplier' => fake()->company(),
            'total_cents' => Money::zero(),
        ];
    }
}
