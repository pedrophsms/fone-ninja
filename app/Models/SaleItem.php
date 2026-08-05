<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_id', 'product_id', 'quantity', 'unit_price_cents',
        'average_cost_snapshot_cents', 'subtotal_cents', 'item_profit_cents',
    ];

    protected $casts = [
        'unit_price_cents' => MoneyCast::class,
        'average_cost_snapshot_cents' => MoneyCast::class,
        'subtotal_cents' => MoneyCast::class,
        'item_profit_cents' => MoneyCast::class,
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
