<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->name,
            'custo_medio' => $this->average_cost_cents->formatted(),
            'preco_venda' => $this->sale_price_cents->formatted(),
            'estoque' => $this->current_stock,
        ];
    }
}
