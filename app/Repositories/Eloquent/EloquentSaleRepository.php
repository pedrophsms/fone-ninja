<?php

namespace App\Repositories\Eloquent;

use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentSaleRepository implements SaleRepositoryInterface
{
    public function create(array $attributes): Sale
    {
        return Sale::create($attributes);
    }

    public function findForUpdate(int $id): Sale
    {
        return Sale::query()->lockForUpdate()->findOrFail($id);
    }

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator
    {
        return Sale::query()->with('items.product')->latest()->paginate($perPage);
    }
}
