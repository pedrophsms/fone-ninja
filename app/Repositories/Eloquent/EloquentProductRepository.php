<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function find(int $id): Product
    {
        return Product::findOrFail($id);
    }

    public function findForUpdate(int $id): Product
    {
        return Product::query()->lockForUpdate()->findOrFail($id);
    }

    public function findManyByIds(array $ids): array
    {
        return Product::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()->paginate($perPage);
    }

    public function create(array $attributes): Product
    {
        return Product::create($attributes);
    }
}
