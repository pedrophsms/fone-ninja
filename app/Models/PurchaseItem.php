<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory;

    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'unit_price_cents', 'subtotal_cents'];

    protected $casts = [
        'unit_price_cents' => MoneyCast::class,
        'subtotal_cents' => MoneyCast::class,
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
