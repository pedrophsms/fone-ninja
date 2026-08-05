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

class SalesController extends Controller
{
    public function index(SaleRepositoryInterface $sales): AnonymousResourceCollection
    {
        return SaleResource::collection($sales->paginateWithItems());
    }

    public function store(StoreSaleRequest $request, RegisterSaleAction $action): JsonResponse
    {
        $sale = $action->execute(
            RegisterSaleData::fromValidated($request->validated()),
            $request->user()->id,
        );

        return response()->json(new SaleResource($sale), 201);
    }

    public function cancel(int $id, Request $request, CancelSaleAction $action): SaleResource
    {
        return new SaleResource($action->execute($id, $request->user()->id));
    }
}
