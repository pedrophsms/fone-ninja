<?php

namespace App\Http\Controllers\Api;

use App\Actions\Purchase\RegisterPurchaseAction;
use App\DataTransferObjects\RegisterPurchaseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class PurchasesController extends Controller
{
    #[OA\Get(
        path: '/compras',
        summary: 'Lista compras registradas com seus itens',
        tags: ['Compras'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de compras')],
    )]
    public function index(PurchaseRepositoryInterface $purchases): AnonymousResourceCollection
    {
        return PurchaseResource::collection($purchases->paginateWithItems());
    }

    #[OA\Post(
        path: '/compras',
        summary: 'Registra uma compra e atualiza estoque/custo médio dos produtos',
        tags: ['Compras'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['fornecedor', 'produtos'],
                properties: [
                    new OA\Property(property: 'fornecedor', type: 'string', example: 'Fornecedor X'),
                    new OA\Property(property: 'produtos', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'quantidade', type: 'integer', example: 10),
                            new OA\Property(property: 'preco_unitario', type: 'number', format: 'float', example: 20.00),
                        ],
                    )),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Compra registrada'),
            new OA\Response(response: 422, description: 'Erro de validação'),
        ],
    )]
    public function store(StorePurchaseRequest $request, RegisterPurchaseAction $action): JsonResponse
    {
        $purchase = $action->execute(
            RegisterPurchaseData::fromValidated($request->validated()),
            $request->user()->id,
        );

        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }
}
