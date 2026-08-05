<?php

namespace App\Actions\Sale;

use App\DataTransferObjects\RegisterSaleData;
use App\Events\SaleRegistered;
use App\Exceptions\InsufficientStockException;
use App\Models\Sale;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Services\ProfitCalculatorService;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

class RegisterSaleAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly SaleRepositoryInterface $sales,
        private readonly ProfitCalculatorService $profitCalculator,
    ) {
    }

    public function execute(RegisterSaleData $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $sale = $this->sales->create([
                'customer' => $data->customer,
                'total_cents' => Money::zero(),
                'profit_cents' => Money::zero(),
                'status' => 'completed',
            ]);

            $total = Money::zero();
            $profit = Money::zero();

            foreach ($data->items as $item) {
                $product = $this->products->findForUpdate($item->productId);

                if ($item->quantity > $product->current_stock) {
                    throw InsufficientStockException::forProduct($product, $item->quantity);
                }

                $itemProfit = $this->profitCalculator->calculate(
                    unitPrice: $item->unitPrice,
                    averageCost: $product->average_cost_cents,
                    quantity: $item->quantity,
                );

                $product->current_stock -= $item->quantity;
                $product->save();

                $subtotal = $item->unitPrice->multiply($item->quantity);
                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unitPrice,
                    'average_cost_snapshot_cents' => $product->average_cost_cents,
                    'subtotal_cents' => $subtotal,
                    'item_profit_cents' => $itemProfit,
                ]);

                $total = $total->add($subtotal);
                $profit = $profit->add($itemProfit);
            }

            $sale->update(['total_cents' => $total, 'profit_cents' => $profit]);
            $sale->refresh();
            $sale->load('items.product');

            event(new SaleRegistered($sale, $userId));

            return $sale;
        }, attempts: 3);
    }
}
