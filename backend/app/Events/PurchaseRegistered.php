<?php

namespace App\Events;

use App\Models\Purchase;

final class PurchaseRegistered
{
    public function __construct(
        public readonly Purchase $purchase,
        public readonly int $userId,
    ) {
    }
}
