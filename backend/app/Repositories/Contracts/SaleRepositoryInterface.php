<?php

namespace App\Repositories\Contracts;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SaleRepositoryInterface
{
    public function create(array $attributes): Sale;

    public function findForUpdate(int $id): Sale;

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator;
}
