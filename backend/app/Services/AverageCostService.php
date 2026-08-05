<?php

namespace App\Services;

use App\ValueObjects\Money;

class AverageCostService
{
    public function recalculate(
        int $currentStock,
        Money $currentAverageCost,
        int $incomingQuantity,
        Money $incomingUnitPrice,
    ): Money {
        $totalQuantity = $currentStock + $incomingQuantity;

        if ($totalQuantity === 0) {
            return Money::zero();
        }

        $currentTotalValue = $currentAverageCost->multiply($currentStock);
        $incomingTotalValue = $incomingUnitPrice->multiply($incomingQuantity);
        $combinedValue = $currentTotalValue->add($incomingTotalValue);

        return Money::fromCents((int) round($combinedValue->toCents() / $totalQuantity));
    }
}
