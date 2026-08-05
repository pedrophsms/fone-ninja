<?php

namespace App\Events;

use App\Models\Sale;

final class SaleRegistered
{
    public function __construct(
        public readonly Sale $sale,
        public readonly int $userId,
    ) {
    }
}
