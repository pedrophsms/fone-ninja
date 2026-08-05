<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = ['name', 'sale_price_cents', 'average_cost_cents', 'current_stock'];

    protected $casts = [
        'sale_price_cents' => MoneyCast::class,
        'average_cost_cents' => MoneyCast::class,
        'current_stock' => 'integer',
    ];
}
