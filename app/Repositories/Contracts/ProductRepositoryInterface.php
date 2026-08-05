<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function find(int $id): Product;

    public function findForUpdate(int $id): Product;

    /** @param int[] $ids @return array<int, Product> keyed by product id */
    public function findManyByIds(array $ids): array;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes): Product;
}
