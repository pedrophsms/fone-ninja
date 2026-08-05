<?php

namespace App\Listeners;

use App\Events\PurchaseRegistered;
use App\Events\SaleCancelled;
use App\Events\SaleRegistered;
use App\Models\StockMovement;

class RecordStockMovement
{
    public function handlePurchaseRegistered(PurchaseRegistered $event): void
    {
        foreach ($event->purchase->items as $item) {
            StockMovement::create([
                'product_id' => $item->product_id,
                'user_id' => $event->userId,
                'type' => 'purchase_in',
                'quantity' => $item->quantity,
                'reference_type' => 'purchase',
                'reference_id' => $event->purchase->id,
            ]);
        }
    }

    public function handleSaleRegistered(SaleRegistered $event): void
    {
        foreach ($event->sale->items as $item) {
            StockMovement::create([
                'product_id' => $item->product_id,
                'user_id' => $event->userId,
                'type' => 'sale_out',
                'quantity' => $item->quantity,
                'reference_type' => 'sale',
                'reference_id' => $event->sale->id,
            ]);
        }
    }

    public function handleSaleCancelled(SaleCancelled $event): void
    {
        foreach ($event->sale->items as $item) {
            StockMovement::create([
                'product_id' => $item->product_id,
                'user_id' => $event->userId,
                'type' => 'sale_cancelled_return',
                'quantity' => $item->quantity,
                'reference_type' => 'sale',
                'reference_id' => $event->sale->id,
            ]);
        }
    }
}
