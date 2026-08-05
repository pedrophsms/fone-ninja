<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected $fillable = ['supplier', 'total_cents'];

    protected $casts = [
        'total_cents' => MoneyCast::class,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
