<?php

namespace App\Repositories\Eloquent;

use App\Models\Purchase;
use App\Repositories\Contracts\PurchaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentPurchaseRepository implements PurchaseRepositoryInterface
{
    public function create(array $attributes): Purchase
    {
        return Purchase::create($attributes);
    }

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator
    {
        return Purchase::query()->with('items.product')->latest()->paginate($perPage);
    }
}
