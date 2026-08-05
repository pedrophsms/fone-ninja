<?php

namespace App\Http\Controllers\Api;

use App\Actions\Product\CreateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\ValueObjects\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductsController extends Controller
{
    public function index(ProductRepositoryInterface $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products->paginate());
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        $product = $action->execute(
            name: $request->validated('nome'),
            salePrice: Money::fromDecimalString((string) $request->validated('preco_venda')),
        );

        return response()->json(new ProductResource($product), 201);
    }
}
