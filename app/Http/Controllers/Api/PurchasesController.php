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

class PurchasesController extends Controller
{
    public function index(PurchaseRepositoryInterface $purchases): AnonymousResourceCollection
    {
        return PurchaseResource::collection($purchases->paginateWithItems());
    }

    public function store(StorePurchaseRequest $request, RegisterPurchaseAction $action): JsonResponse
    {
        $purchase = $action->execute(
            RegisterPurchaseData::fromValidated($request->validated()),
            $request->user()->id,
        );

        return response()->json(new PurchaseResource($purchase), 201);
    }
}
