<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sale\CancelSaleAction;
use App\Actions\Sale\RegisterSaleAction;
use App\DataTransferObjects\RegisterSaleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SalesController extends Controller
{
    #[OA\Get(
        path: '/vendas',
        summary: 'Lista vendas registradas com seus itens',
        tags: ['Vendas'],
        responses: [new OA\Response(response: 200, description: 'Lista paginada de vendas')],
    )]
    public function index(SaleRepositoryInterface $sales): AnonymousResourceCollection
    {
        return SaleResource::collection($sales->paginateWithItems());
    }

    #[OA\Post(
        path: '/vendas',
        summary: 'Registra uma venda, valida estoque e calcula lucro',
        tags: ['Vendas'],
        parameters: [
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cliente', 'produtos'],
                properties: [
                    new OA\Property(property: 'cliente', type: 'string', example: 'Fulano da Silva'),
                    new OA\Property(property: 'produtos', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'quantidade', type: 'integer', example: 2),
                            new OA\Property(property: 'preco_unitario', type: 'number', format: 'float', example: 50.00),
                        ],
                    )),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Venda registrada'),
            new OA\Response(response: 422, description: 'Erro de validação ou estoque insuficiente'),
        ],
    )]
    public function store(StoreSaleRequest $request, RegisterSaleAction $action): JsonResponse
    {
        $sale = $action->execute(
            RegisterSaleData::fromValidated($request->validated()),
            $request->user()->id,
        );

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }

    #[OA\Post(
        path: '/vendas/{id}/cancelar',
        summary: 'Cancela uma venda e reverte o estoque (sem alterar o custo médio)',
        tags: ['Vendas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Venda cancelada'),
            new OA\Response(response: 422, description: 'Venda já cancelada'),
        ],
    )]
    public function cancel(int $id, Request $request, CancelSaleAction $action): SaleResource
    {
        return new SaleResource($action->execute($id, $request->user()->id));
    }
}
