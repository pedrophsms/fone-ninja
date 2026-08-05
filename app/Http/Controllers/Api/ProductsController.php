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
use OpenApi\Attributes as OA;

class ProductsController extends Controller
{
    #[OA\Get(
        path: '/api/produtos',
        summary: 'Lista produtos com custo médio, preço e estoque atual',
        tags: ['Produtos'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de produtos')],
    )]
    public function index(ProductRepositoryInterface $products): AnonymousResourceCollection
    {
        return ProductResource::collection($products->paginate());
    }

    #[OA\Post(
        path: '/api/produtos',
        summary: 'Cadastra um novo produto',
        tags: ['Produtos'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nome', 'preco_venda'],
                properties: [
                    new OA\Property(property: 'nome', type: 'string', example: 'Fone Bluetooth'),
                    new OA\Property(property: 'preco_venda', type: 'number', format: 'float', example: 99.90),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Produto criado'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ],
    )]
    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        $product = $action->execute(
            name: $request->validated('nome'),
            salePrice: Money::fromDecimalString((string) $request->validated('preco_venda')),
        );

        return (new ProductResource($product))->response()->setStatusCode(201);
    }
}
