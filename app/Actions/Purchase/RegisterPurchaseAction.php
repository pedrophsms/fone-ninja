<?php

namespace App\Actions\Purchase;

use App\DataTransferObjects\RegisterPurchaseData;
use App\Events\PurchaseRegistered;
use App\Models\Purchase;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Services\AverageCostService;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class RegisterPurchaseAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly PurchaseRepositoryInterface $purchases,
        private readonly AverageCostService $averageCostService,
    ) {
    }

    public function execute(RegisterPurchaseData $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $purchase = $this->purchases->create(['supplier' => $data->supplier, 'total_cents' => Money::zero()]);
            $total = Money::zero();

            foreach ($data->items as $item) {
                $product = $this->products->findForUpdate($item->productId);

                $newAverageCost = $this->averageCostService->recalculate(
                    currentStock: $product->current_stock,
                    currentAverageCost: $product->average_cost_cents,
                    incomingQuantity: $item->quantity,
                    incomingUnitPrice: $item->unitPrice,
                );

                $product->current_stock += $item->quantity;
                $product->average_cost_cents = $newAverageCost;
                $product->save();

                $subtotal = $item->unitPrice->multiply($item->quantity);
                $purchase->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unitPrice,
                    'subtotal_cents' => $subtotal,
                ]);

                $total = $total->add($subtotal);
            }

            $purchase->update(['total_cents' => $total]);
            $purchase->load('items.product');

            event(new PurchaseRegistered($purchase, $userId));

            return $purchase;
        }, attempts: 3);
    }
}
