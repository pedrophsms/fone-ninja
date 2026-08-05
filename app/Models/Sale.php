<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected $fillable = ['customer', 'total_cents', 'profit_cents', 'status'];

    protected $casts = [
        'total_cents' => MoneyCast::class,
        'profit_cents' => MoneyCast::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
