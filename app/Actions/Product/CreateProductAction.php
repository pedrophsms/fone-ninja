<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\ValueObjects\Money;

class CreateProductAction
{
    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    public function execute(string $name, Money $salePrice): Product
    {
        return $this->products->create([
            'name' => $name,
            'sale_price_cents' => $salePrice,
            'average_cost_cents' => Money::zero(),
            'current_stock' => 0,
        ]);
    }
}
