<?php

namespace App\Repositories\Contracts;

use App\Models\Purchase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseRepositoryInterface
{
    public function create(array $attributes): Purchase;

    public function paginateWithItems(int $perPage = 15): LengthAwarePaginator;
}
