<?php

namespace App\Services;

use App\ValueObjects\Money;

class ProfitCalculatorService
{
    public function calculate(Money $unitPrice, Money $averageCost, int $quantity): Money
    {
        return $unitPrice->subtract($averageCost)->multiply($quantity);
    }
}
