<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->customer,
            'total' => $this->total_cents->formatted(),
            'lucro' => $this->profit_cents->formatted(),
            'status' => $this->status,
            'produtos' => $this->items->map(fn ($item) => [
                'id' => $item->product_id,
                'nome' => $item->product->name,
                'quantidade' => $item->quantity,
                'preco_unitario' => $item->unit_price_cents->formatted(),
                'subtotal' => $item->subtotal_cents->formatted(),
            ]),
        ];
    }
}
