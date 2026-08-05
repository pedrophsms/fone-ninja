<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    private int $productId;
    private int $currentStock;
    private int $requestedQuantity;
    private string $productName;

    public static function forProduct(Product $product, int $requested): self
    {
        $exception = new self("Estoque insuficiente para o produto {$product->name}");
        $exception->productId = $product->id;
        $exception->productName = $product->name;
        $exception->currentStock = $product->current_stock;
        $exception->requestedQuantity = $requested;

        return $exception;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }

    public function context(): array
    {
        return [
            'product_id' => $this->productId,
            'current_stock' => $this->currentStock,
            'requested_quantity' => $this->requestedQuantity,
        ];
    }
}
