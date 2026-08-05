<?php

namespace App\Events;

use App\Models\Sale;

final class SaleCancelled
{
    public function __construct(
        public readonly Sale $sale,
        public readonly int $userId,
    ) {
    }
}
