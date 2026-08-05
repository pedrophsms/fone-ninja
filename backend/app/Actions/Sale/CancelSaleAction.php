<?php

namespace App\Actions\Sale;

use App\Events\SaleCancelled;
use App\Exceptions\SaleAlreadyCancelledException;
use App\Models\Sale;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CancelSaleAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly SaleRepositoryInterface $sales,
    ) {
    }

    public function execute(int $saleId, int $userId): Sale
    {
        return DB::transaction(function () use ($saleId, $userId) {
            $sale = $this->sales->findForUpdate($saleId);

            if ($sale->status === 'cancelled') {
                throw SaleAlreadyCancelledException::forSale($saleId);
            }

            $sale->load('items');

            foreach ($sale->items as $item) {
                $product = $this->products->findForUpdate($item->product_id);
                $product->current_stock += $item->quantity;
                $product->save();
            }

            $sale->update(['status' => 'cancelled']);
            $sale->refresh();
            $sale->load('items.product');

            event(new SaleCancelled($sale, $userId));

            return $sale;
        }, attempts: 3);
    }
}
