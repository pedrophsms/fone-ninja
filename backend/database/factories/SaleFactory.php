<?php

namespace Database\Factories;

use App\Models\Sale;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'customer' => fake()->name(),
            'total_cents' => Money::zero(),
            'profit_cents' => Money::zero(),
            'status' => 'completed',
        ];
    }
}
