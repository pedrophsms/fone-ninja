<?php

namespace App\Actions\Sale;

use App\DataTransferObjects\SalePreviewData;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProfitCalculatorService;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PreviewSaleAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProfitCalculatorService $profitCalculator,
    ) {
    }

    /**
     * Projects total + profit for a prospective sale without persisting
     * anything, without locking rows, and without mutating stock. It is a
     * pure read of current average cost; RegisterSaleAction remains the sole
     * writer and still enforces stock on the real submission.
     *
     * @return array{total: string, lucro: string, itens: list<array{id: int, nome: string, quantidade: int, preco_unitario: string, custo_medio: string, subtotal: string, lucro_item: string}>}
     */
    public function execute(SalePreviewData $data): array
    {
        $products = $this->products->findManyByIds(
            array_map(fn ($item) => $item->productId, $data->items),
        );

        if (count($products) !== count($data->items)) {
            // A product disappeared between FormRequest validation and this
            // read. Impossible in normal flow, but fail closed rather than
            // projecting a total against a partially-resolved product set.
            throw (new ModelNotFoundException())->setModel(Product::class);
        }

        $total = Money::zero();
        $lucro = Money::zero();
        $itens = [];

        foreach ($data->items as $item) {
            $product = $products[$item->productId];
            $subtotal = $item->unitPrice->multiply($item->quantity);
            $itemProfit = $this->profitCalculator->calculate(
                unitPrice: $item->unitPrice,
                averageCost: $product->average_cost_cents,
                quantity: $item->quantity,
            );

            $total = $total->add($subtotal);
            $lucro = $lucro->add($itemProfit);

            $itens[] = [
                'id' => $product->id,
                'nome' => $product->name,
                'quantidade' => $item->quantity,
                'preco_unitario' => $item->unitPrice->formatted(),
                'custo_medio' => $product->average_cost_cents->formatted(),
                'subtotal' => $subtotal->formatted(),
                'lucro_item' => $itemProfit->formatted(),
            ];
        }

        return [
            'total' => $total->formatted(),
            'lucro' => $lucro->formatted(),
            'itens' => $itens,
        ];
    }
}
