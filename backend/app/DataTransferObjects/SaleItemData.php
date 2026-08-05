<?php

namespace App\DataTransferObjects;

use App\ValueObjects\Money;

final class SaleItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly Money $unitPrice,
    ) {
    }
}
